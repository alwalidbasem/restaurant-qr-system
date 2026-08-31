<?php

require_once __DIR__ . '/../../Middleware/PermissionsMiddleware.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../../../config/security/hash.php';
require_once __DIR__ . '/../../Models/AuthModel.php';
require_once __DIR__ . '/../../Models/ActivityLogModel.php';
require_once __DIR__ . '/../../Validators/AuthValidator.php';

class AuthController
{

    private function getJsonInput(): array
    {
        $data = json_decode((string) file_get_contents('php://input'), true);

        return is_array($data) ? $data : [];
    }

    private function filterPermissionRow(array $row, string $crudName, PermissionsMiddleware $middleware): array
    {
        $hadRestaurantId = array_key_exists('restaurant_id', $row);
        $filtered = $middleware->final_data($this->filterNestedPublicData($row, $middleware), $crudName);

        if (!$hadRestaurantId && array_key_exists('restaurant_id', $filtered)) {
            unset($filtered['restaurant_id']);
        }

        return $filtered;
    }

    private function filterNestedPublicData(array $row, PermissionsMiddleware $middleware): array
    {
        if (isset($row['addons']) && is_array($row['addons'])) {
            $row['addons'] = $middleware->final_data($row['addons'], 'food_addons.get');
        }

        if (isset($row['id']) && !isset($row['restaurant_id'])) {
            $row['restaurant_id'] = $row['id'];
        }

        return $row;
    }

    private const AUTH_COOKIE_NAME = 'API_KEY';
    private const AUTH_COOKIE_SECONDS = 28800;

    private AuthModel $authModel;
    private AuthValidator $validator;
    private bool $returnArray;
    private PDO $db;

    private function getApiKeyFromCookie(): string
    {
        return trim(
            (string) ($_COOKIE[self::AUTH_COOKIE_NAME] ?? '')
            ?: (string) ($_SERVER['HTTP_API_KEY'] ?? '')
            ?: (string) ($_SERVER['HTTP_X_API_KEY'] ?? '')
            ?: (string) ($_GET['api_key'] ?? '')
        );
    }

    private function setAuthCookie(string $apiKey): void
    {
        setcookie(
            self::AUTH_COOKIE_NAME,
            $apiKey,
            [
                'expires' => time() + self::AUTH_COOKIE_SECONDS,
                'path' => '/',
                'secure' => $this->isHttpsRequest(),
                'httponly' => true,
                'samesite' => 'Lax'
            ]
        );

        $_COOKIE[self::AUTH_COOKIE_NAME] = $apiKey;
    }

    private function clearAuthCookie(): void
    {
        setcookie(
            self::AUTH_COOKIE_NAME,
            '',
            [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => $this->isHttpsRequest(),
                'httponly' => true,
                'samesite' => 'Lax'
            ]
        );

        unset($_COOKIE[self::AUTH_COOKIE_NAME]);
    }

    private function isHttpsRequest(): bool
    {
        return (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            ||
            (string) ($_SERVER['SERVER_PORT'] ?? '') === '443'
        );
    }

    private function sanitizeEmployee(array $employee): array
    {
        unset(
            $employee['password'],
            $employee['API_KEY'],
            $employee['API_KEY_EXPIRY_DATE'],
            $employee['main_code']
        );

        return $employee;
    }

    private function invalidCredentials(): ?array
    {
        return $this->response([
            'success' => false,
            'message' => 'Invalid username, password, or restaurant code.'
        ], 401);
    }

    private function response(
        array $data,
        int $statusCode = 200
    ): ?array {
        if ($this->returnArray) {
            return $data;
        }

        http_response_code($statusCode);

        header(
            'Content-Type: application/json; charset=utf-8'
        );

        echo json_encode(
            $data,
            JSON_UNESCAPED_UNICODE |
            JSON_PRETTY_PRINT
        );

        return null;
    }

    public function __construct(PDO $db, bool $returnArray = false)
    {
        $this->db = $db;
        $this->authModel = new AuthModel($db);
        $this->validator = new AuthValidator();
        $this->returnArray = $returnArray;
    }

    public function isAuth(): ?array
    {
        $apiKey = $this->getApiKeyFromCookie();

        if ($apiKey === '') {
            return $this->response([
                'success' => true,
                'data' => [
                    'authenticated' => false,
                    'employee' => null
                ]
            ]);
        }

        $employee = $this->authModel->getEmployeeByApiKey(
            $apiKey
        );

        if (!$employee) {
            $this->clearAuthCookie();

            return $this->response([
                'success' => true,
                'data' => [
                    'authenticated' => false,
                    'employee' => null
                ]
            ]);
        }

        return $this->response([
            'success' => true,
            'data' => [
                'authenticated' => true,
                'employee' => $this->sanitizeEmployee($employee)
            ]
        ]);
    }

    public function login(): ?array
    {
        $data = $this->getJsonInput();
        $errors = $this->validator->validateLogin($data);

        if (!empty($errors)) {
            return $this->response([
                'success' => false,
                'errors' => $errors
            ], 422);
        }

        $employee = $this->authModel->getEmployeeByUsername(
            trim((string) $data['username'])
        );

        if (!$employee) {
            return $this->invalidCredentials();
        }

        if (strcasecmp((string) ($employee['main_code'] ?? ''), trim((string) $data['restaurant_code'])) !== 0) {
            return $this->invalidCredentials();
        }

        $verification = AdminPasswordHasher::verifyAndRehash(
            (string) $data['password'],
            (string) $employee['password']
        );

        if (!$verification['valid']) {
            return $this->invalidCredentials();
        }

        if ($verification['new_hash'] !== null) {
            $this->authModel->updatePassword(
                (int) $employee['id'],
                $verification['new_hash']
            );
        }

        $token = $this->authModel->issueApiKey(
            (int) $employee['id']
        );

        $this->setAuthCookie(
            $token['api_key']
        );

        $this->writeAuthLog($employee, 'auth.login', 'Logged in');

        return $this->response([
            'success' => true,
            'message' => 'Logged in successfully.',
            'data' => [
                'employee' => $this->sanitizeEmployee($employee),
                'expires_at' => $token['expires_at']
            ]
        ]);
    }

    public function logout(): ?array
    {
        $apiKey = $this->getApiKeyFromCookie();

        if ($apiKey !== '') {
            $employee = $this->authModel->getEmployeeByApiKey(
                $apiKey
            );

            if ($employee) {
                $this->authModel->clearApiKey(
                    (int) $employee['id']
                );
                $this->writeAuthLog($employee, 'auth.logout', 'Logged out');
            }
        }

        $this->clearAuthCookie();

        return $this->response([
            'success' => true,
            'message' => 'Logged out successfully.'
        ]);
    }

    private function writeAuthLog(array $employee, string $permissionKey, string $actionLabel): void
    {
        try {
            $employeeName = (string) ($employee['name'] ?? 'System');
            $restaurantId = !empty($employee['branch_id'])
                ? (int) $employee['branch_id']
                : (int) ($employee['restaurant_id'] ?? 0);
            if ($restaurantId <= 0) {
                return;
            }
            $scope = controllersHelper::activityLogScope($this->db, $restaurantId);

            (new ActivityLog($this->db))->create([
                'restaurant_id' => $scope['restaurant_id'],
                'branch_id' => $scope['branch_id'],
                'employee_id' => isset($employee['id']) ? (int) $employee['id'] : null,
                'employee_name' => $employeeName,
                'permission_key' => $permissionKey,
                'entity_type' => 'employee',
                'entity_id' => isset($employee['id']) ? (string) $employee['id'] : null,
                'action_label' => $actionLabel,
                'message' => $employeeName . ' - ' . $actionLabel,
                'metadata' => [
                    'entity_name' => $employeeName,
                    'username' => $employee['username'] ?? null,
                    'branch_id' => $employee['branch_id'] ?? null,
                ],
            ]);
        } catch (Throwable $e) {
            // Authentication must not fail because logging is unavailable.
        }
    }
}
