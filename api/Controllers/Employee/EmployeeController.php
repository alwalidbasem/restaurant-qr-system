<?php

require_once __DIR__ . '/../../Middleware/PermissionsMiddleware.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../../../config/security/hash.php';
require_once __DIR__ . '/../../Models/EmployeeModel.php';
require_once __DIR__ . '/../../Models/RestaurantModel.php';
require_once __DIR__ . '/../../Validators/EmployeeValidator.php';
require_once __DIR__ . '/../Auth/AuthController.php';

class EmployeeController
{

    private function getJsonInput(): array
    {
        return controllersHelper::getJsonInput();
    }

    private function getHeaderValue(string $name): string
    {
        return controllersHelper::getHeaderValue($name);
    }

    private function getRestaurantIdFromQuery(): ?int
    {
        return controllersHelper::getRestaurantIdFromQuery();
    }

    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        controllersHelper::jsonResponse($data, $statusCode);
    }

    private function apiResponse(array $body, int $statusCode = 200): array
    {
        return controllersHelper::apiResponse($body, $statusCode);
    }

    private function permissionData(array $data, string $crudName): array
    {
        return controllersHelper::permissionData($this->db, $data, $crudName);
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

    private function isListData(array $data): bool
    {
        return $data !== [] && array_keys($data) === range(0, count($data) - 1);
    }

    private PDO $db;
    private Employee $employeeModel;
    private Restaurant $restaurantModel;
    private EmployeeValidator $validator;
    private function validateRestaurant(array $data, array &$errors): void
    {
        if (!isset($errors['restaurant_id']) && !$this->restaurantModel->exists((int) $data['restaurant_id'])) {
            $errors['restaurant_id'] = 'Restaurant does not exist.';
        }

        $restaurant = !isset($errors['restaurant_id'])
            ? $this->restaurantModel->getById((int) $data['restaurant_id'])
            : null;
        $branchId = isset($data['branch_id']) && $data['branch_id'] !== ''
            ? (int) $data['branch_id']
            : null;

        $isBrandManager = in_array((string) ($data['role'] ?? ''), ['owner', 'manager'], true);
        if ($restaurant && (int) ($restaurant['branch_management_enabled'] ?? 0) === 1 && !$isBrandManager) {
            if ($branchId === null) {
                $errors['branch_id'] = 'Branch is required when branch management is enabled.';
                return;
            }

            $branch = $this->restaurantModel->getById($branchId);
            if (!$branch || (int) ($branch['parent_restaurant_id'] ?? 0) !== (int) $data['restaurant_id']) {
                $errors['branch_id'] = 'Branch must belong to this restaurant.';
            }
        } elseif ($branchId !== null) {
            $branch = $this->restaurantModel->getById($branchId);
            if (!$branch || ((int) ($restaurant['branch_management_enabled'] ?? 0) === 1 && (int) ($branch['parent_restaurant_id'] ?? 0) !== (int) $data['restaurant_id'])) {
                $errors['branch_id'] = 'Branch does not exist or does not belong to this restaurant.';
            }
        }
    }

    private function sanitizeEmployee(?array $employee): ?array
    {
        if ($employee === null) {
            return null;
        }

        unset(
            $employee['password'],
            $employee['API_KEY'],
            $employee['API_KEY_EXPIRY_DATE']
        );

        return $employee;
    }

    private function normalizeEmployeePermissions(?string $permissions, ?int $employeeId = null): string
    {
        $values = array_map('trim', explode(',', (string) $permissions));
        $permissionCount = count(require __DIR__ . '/../../Middleware/permissions_config/definitions.php');

        for ($index = 0; $index < $permissionCount; $index++) {
            $values[$index] = ($values[$index] ?? '0') === '1' ? '1' : '0';
        }

        if (!$this->canHoldRestaurantCrudPermissions($employeeId)) {
            for ($index = 0; $index < 4; $index++) {
                $values[$index] = '0';
            }
        }

        $this->lockWritePermissionsBehindReadPermission($values);

        return implode(',', array_slice($values, 0, $permissionCount));
    }

    private function permissionChanges(?string $oldPermissions, ?string $newPermissions): array
    {
        $keys = array_keys(require __DIR__ . '/../../Middleware/permissions_config/definitions.php');
        $oldValues = array_map('trim', explode(',', (string) $oldPermissions));
        $newValues = array_map('trim', explode(',', (string) $newPermissions));
        $changes = [];

        foreach ($keys as $index => $permission) {
            $oldEnabled = ($oldValues[$index] ?? '0') === '1';
            $newEnabled = ($newValues[$index] ?? '0') === '1';

            if ($oldEnabled === $newEnabled) {
                continue;
            }

            $changes[$permission] = [
                'old' => $oldEnabled ? 'Enabled' : 'Disabled',
                'new' => $newEnabled ? 'Enabled' : 'Disabled',
            ];
        }

        return $changes;
    }

    private function lockWritePermissionsBehindReadPermission(array &$values): void
    {
        $keys = array_keys(require __DIR__ . '/../../Middleware/permissions_config/definitions.php');
        $groups = ['restaurants', 'employees', 'inventory', 'orders', 'foods', 'categories', 'tables'];

        foreach ($groups as $group) {
            $readIndex = array_search($group . '.get', $keys, true);
            if ($readIndex === false || ($values[$readIndex] ?? '0') === '1') {
                continue;
            }

            foreach (['create', 'update', 'delete'] as $action) {
                $index = array_search($group . '.' . $action, $keys, true);
                if ($index !== false) {
                    $values[$index] = '0';
                }
            }
        }
    }

    private function canHoldRestaurantCrudPermissions(?int $employeeId): bool
    {
        $currentEmployee = $this->authenticatedEmployee();
        if ($currentEmployee !== null && controllersHelper::isSuperAdminEmployee($currentEmployee)) {
            return true;
        }

        if ($employeeId === null || $employeeId <= 0) {
            return false;
        }

        $config = require __DIR__ . '/../../Middleware/permissions_config/restaurant_crud_admins.php';
        $allowedIds = array_map('intval', $config['employee_ids'] ?? []);

        return in_array($employeeId, $allowedIds, true);
    }

    private function authenticatedEmployee(): ?array
    {
        $auth = new AuthController($this->db, true);
        $response = $auth->isAuth();
        $employee = $response['data']['employee'] ?? null;

        return is_array($employee) ? $employee : null;
    }

    private function hashEmployeePassword(string $password): string
    {
        return AdminPasswordHasher::hash(trim($password));
    }

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->employeeModel = new Employee($db);
        $this->restaurantModel = new Restaurant($db);
        $this->validator = new EmployeeValidator();
    }

    public function index(): array
    {
        $employee = $this->authenticatedEmployee();
        $restaurantId = null;

        if ($employee !== null && !controllersHelper::isSuperAdminEmployee($employee)) {
            $restaurantId = (int) $employee['restaurant_id'];
        } elseif ($employee !== null) {
            $restaurantId = $this->getRestaurantIdFromQuery();
        }

        return $this->apiResponse([
            'success' => true,
            'data' => $this->permissionData(array_map([$this, 'sanitizeEmployee'], $this->employeeModel->getAll($restaurantId)), 'employees.get')
        ]);
    }

    public function show(int $id): array
    {
        $employee = $this->employeeModel->getById($id);

        if (!$employee) {
            return $this->apiResponse([
                'success' => false,
                'message' => 'Employee not found.'
            ], 404);
        }

        return $this->apiResponse([
            'success' => true,
            'data' => $this->permissionData($this->sanitizeEmployee($employee), 'employees.get')
        ]);
    }

    public function store(): void
    {
        $data = $this->getJsonInput();
        $errors = $this->validator->validateCreate($data);
        $this->validateRestaurant($data, $errors);

        if (!empty($errors)) {
            $this->jsonResponse([
                'success' => false,
                'errors' => $errors
            ], 422);

            return;
        }

        try {
            $data['salary'] = max(0, (float) ($data['salary'] ?? 0));
            $data['branch_id'] = !empty($data['branch_id']) ? (int) $data['branch_id'] : null;
            $data['password'] = $this->hashEmployeePassword((string) $data['password']);
            $data['permissions'] = $this->normalizeEmployeePermissions($data['permissions'] ?? null);
            $employeeId = $this->employeeModel->create($data);
            $createdEmployee = $this->sanitizeEmployee($this->employeeModel->getById($employeeId));
            controllersHelper::logActivity($this->db, (int) $data['restaurant_id'], 'employees.create', 'Added new employee', 'employee', $employeeId, [
                'entity_name' => $createdEmployee['name'] ?? null,
                'snapshot' => $createdEmployee,
            ]);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Employee created successfully.',
                'data' => $this->permissionData($createdEmployee, 'employees.get')
            ], 201);
        } catch (Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to create employee.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(int $id): void
    {
        $employee = $this->employeeModel->getById($id);

        if (!$employee) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Employee not found.'
            ], 404);

            return;
        }

        $data = array_merge($employee, $this->getJsonInput());
        $currentEmployee = $this->authenticatedEmployee();
        if ($currentEmployee === null || !controllersHelper::isSuperAdminEmployee($currentEmployee)) {
            $data['restaurant_id'] = $employee['restaurant_id'];
        }

        if (!isset($data['password']) || trim((string) $data['password']) === (string) $employee['password']) {
            unset($data['password']);
        }
        $errors = $this->validator->validateUpdate($data);
        $this->validateRestaurant($data, $errors);

        if (!empty($errors)) {
            $this->jsonResponse([
                'success' => false,
                'errors' => $errors
            ], 422);

            return;
        }

        if (isset($data['password']) && trim((string) $data['password']) !== '') {
            $data['password'] = $this->hashEmployeePassword((string) $data['password']);
        }

        $data['salary'] = max(0, (float) ($data['salary'] ?? 0));
        $data['branch_id'] = !empty($data['branch_id']) ? (int) $data['branch_id'] : null;
        $isSelfUpdate = $currentEmployee !== null && (int) ($currentEmployee['id'] ?? 0) === $id;
        $data['permissions'] = $isSelfUpdate
            ? (string) ($employee['permissions'] ?? '')
            : $this->normalizeEmployeePermissions($data['permissions'] ?? null, $id);
        $this->employeeModel->update($id, $data);
        $updatedEmployee = $this->sanitizeEmployee($this->employeeModel->getById($id));
        $changes = controllersHelper::changedFields(
            $this->sanitizeEmployee($employee) ?? [],
            $updatedEmployee ?? [],
            ['password', 'API_KEY', 'API_KEY_EXPIRY_DATE', 'permissions']
        );
        $changes = array_merge($changes, $this->permissionChanges($employee['permissions'] ?? null, $data['permissions'] ?? null));
        controllersHelper::logActivity($this->db, (int) $data['restaurant_id'], 'employees.update', 'Updated employee', 'employee', $id, [
            'entity_name' => $updatedEmployee['name'] ?? $employee['name'] ?? null,
            'changes' => $changes,
        ]);

        $this->jsonResponse([
            'success' => true,
            'message' => 'Employee updated successfully.',
            'data' => $this->permissionData($updatedEmployee, 'employees.get')
        ]);
    }

    public function destroy(int $id): void
    {
        $currentEmployee = $this->authenticatedEmployee();

        if ($currentEmployee && (int) ($currentEmployee['id'] ?? 0) === $id) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'You cannot delete your own admin account.'
            ], 403);

            return;
        }

        $employee = $this->employeeModel->getById($id);
        if (!$employee) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Employee not found.'
            ], 404);

            return;
        }

        $this->employeeModel->delete($id);
        controllersHelper::logActivity($this->db, (int) ($employee['restaurant_id'] ?? 0), 'employees.delete', 'Deleted employee', 'employee', $id, [
            'entity_name' => $employee['name'] ?? null,
            'snapshot' => $this->sanitizeEmployee($employee),
        ]);

        $this->jsonResponse([
            'success' => true,
            'message' => 'Employee deleted successfully.'
        ]);
    }
}
