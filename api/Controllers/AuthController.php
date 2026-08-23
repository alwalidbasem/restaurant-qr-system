<?php

require_once __DIR__ . '/../Models/AuthModel.php';
require_once __DIR__ . '/../Validators/AuthValidator.php';
require_once __DIR__ . '/../../config/security/hash.php';

class AuthController
{
    private const AUTH_COOKIE_NAME = 'API_KEY';
    private const AUTH_COOKIE_SECONDS = 28800;

    private AuthModel $authModel;
    private AuthValidator $validator;

    public function __construct(PDO $db)
    {
        $this->authModel = new AuthModel($db);
        $this->validator = new AuthValidator();
    }

    public function login(): void
    {
        $data = $this->getJsonInput();

        $errors = $this->validator->validateLogin($data);

        if (!empty($errors)) {
            $this->jsonResponse([
                'success' => false,
                'errors' => $errors
            ], 422);

            return;
        }

        $employee = $this->authModel->getEmployeeByUsername(
            trim((string) $data['username'])
        );

        if (!$employee) {
            $this->invalidCredentials();
            return;
        }

        $verification = AdminPasswordHasher::verifyAndRehash(
            (string) $data['password'],
            (string) $employee['password']
        );

        if (!$verification['valid']) {
            $this->invalidCredentials();
            return;
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

        $this->jsonResponse([
            'success' => true,
            'message' => 'Logged in successfully.',
            'data' => [
                'employee' => $this->sanitizeEmployee($employee),
                'expires_at' => $token['expires_at']
            ]
        ]);
    }

    public function isAuth(): void
    {
        $apiKey = $this->getApiKeyFromCookie();

        if ($apiKey === '') {
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'authenticated' => false,
                    'employee' => null
                ]
            ]);

            return;
        }

        $employee = $this->authModel->getEmployeeByApiKey(
            $apiKey
        );

        if (!$employee) {
            $this->clearAuthCookie();

            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'authenticated' => false,
                    'employee' => null
                ]
            ]);

            return;
        }

        $this->jsonResponse([
            'success' => true,
            'data' => [
                'authenticated' => true,
                'employee' => $this->sanitizeEmployee($employee)
            ]
        ]);
    }

    public function logout(): void
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
            }
        }

        $this->clearAuthCookie();

        $this->jsonResponse([
            'success' => true,
            'message' => 'Logged out successfully.'
        ]);
    }

    private function getApiKeyFromCookie(): string
    {
        return trim(
            (string) ($_COOKIE[self::AUTH_COOKIE_NAME] ?? '')
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

    private function invalidCredentials(): void
    {
        $this->jsonResponse([
            'success' => false,
            'message' => 'Invalid username or password.'
        ], 401);
    }

    private function getJsonInput(): array
    {
        $data = json_decode(
            (string) file_get_contents('php://input'),
            true
        );

        return is_array($data)
            ? $data
            : [];
    }

    private function jsonResponse(
        array $data,
        int $statusCode = 200
    ): void {
        http_response_code($statusCode);

        header(
            'Content-Type: application/json; charset=utf-8'
        );

        echo json_encode(
            $data,
            JSON_UNESCAPED_UNICODE |
            JSON_PRETTY_PRINT
        );
    }
}