<?php

require_once __DIR__ . '/../../Middleware/PermissionsMiddleware.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../../../config/security/hash.php';
require_once __DIR__ . '/../../Models/StaffModel.php';
require_once __DIR__ . '/../../Models/RestaurantModel.php';
require_once __DIR__ . '/../../Validators/StaffValidator.php';
require_once __DIR__ . '/../Auth/AuthController.php';

class StaffController
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
    private Staff $employeeModel;
    private Restaurant $restaurantModel;
    private StaffValidator $validator;

    private function isManagerScopedStaff(array $data): bool
    {
        if (!empty($data['is_owner']) || !empty($data['is_superadmin'])) {
            return false;
        }

        return !empty($data['is_manager'])
            || in_array((string) ($data['manager_scope'] ?? ''), ['all', 'some', 'none'], true)
            || (array_key_exists('allowed_branches', $data) && $data['allowed_branches'] !== null)
            || (array_key_exists('managed_branches', $data) && $data['managed_branches'] !== null);
    }

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

        $isBrandManager = $this->isManagerScopedStaff($data) || !empty($data['is_owner']) || !empty($data['is_superadmin']);
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

    private function validateUsernameScope(array $data, array &$errors, ?int $employeeId = null): void
    {
        if (isset($errors['username']) || isset($errors['restaurant_id'])) {
            return;
        }

        $username = trim((string) ($data['username'] ?? ''));
        $restaurantId = (int) ($data['restaurant_id'] ?? 0);
        if ($username === '' || $restaurantId <= 0) {
            return;
        }

        if ($this->employeeModel->usernameExistsInRestaurant($username, $restaurantId, $employeeId)) {
            $errors['username'] = 'Username already exists in this restaurant or brand.';
        }
    }

    private function sanitizeEmployee(?array $employee): ?array
    {
        if ($employee === null) {
            return null;
        }

        if (!controllersHelper::isSuperAdminEmployee($this->authenticatedEmployee() ?? [])) {
            unset($employee['hidden_details']);
        }

        unset(
            $employee['password'],
            $employee['API_KEY'],
            $employee['API_KEY_EXPIRY_DATE']
        );

        return $employee;
    }

    private function normalizeEmployeePermissions(?string $permissions, ?int $employeeId = null, ?array $employeeData = null): string
    {
        $values = array_map('trim', explode(',', (string) $permissions));
        $permissionCount = count(controllersHelper::permissionKeys());

        for ($index = 0; $index < $permissionCount; $index++) {
            $values[$index] = ($values[$index] ?? '0') === '1' ? '1' : '0';
        }

        $role = controllersHelper::employeeRoleKey($employeeData);
        $allowed = controllersHelper::roleAllowedPermissions($employeeData);
        if ($role === 'is_owner') {
            return '';
        }

        if ($allowed !== null) {
            $keys = controllersHelper::permissionKeys();
            foreach ($keys as $index => $permission) {
                if (!isset($allowed[$permission])) {
                    $values[$index] = '0';
                }
            }
        } elseif (!$this->canHoldRestaurantCrudPermissions($employeeId)) {
            foreach (array_keys(controllersHelper::permissionRoleDefinitions()['is_superadmin'] ?? []) as $permission) {
                $index = array_search($permission, controllersHelper::permissionKeys(), true);
                if ($index !== false) {
                    $values[$index] = '0';
                }
            }
        }

        $this->lockWritePermissionsBehindReadPermission($values);

        return implode(',', array_slice($values, 0, $permissionCount));
    }

    private function permissionChanges(?string $oldPermissions, ?string $newPermissions): array
    {
        $keys = controllersHelper::permissionKeys();
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
        $keys = controllersHelper::permissionKeys();
        $groups = ['restaurants', 'branches', 'staff', 'inventory', 'orders', 'foods', 'categories', 'tables', 'discounts'];

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

    private function roleRank(array $employee): int
    {
        if (!empty($employee['is_superadmin'])) {
            return 4;
        }
        if (!empty($employee['is_owner'])) {
            return 3;
        }
        if (!empty($employee['is_manager'])) {
            return 2;
        }

        return 1;
    }

    private function roleLabel(array $employee): string
    {
        if (!empty($employee['is_superadmin'])) {
            return 'super admin';
        }
        if (!empty($employee['is_owner'])) {
            return 'owner';
        }
        if (!empty($employee['is_manager'])) {
            return 'manager';
        }

        return 'employee';
    }

    private function normalizeRoleFlags(array &$data, array $currentEmployee, ?array $existingEmployee = null): void
    {
        $isSuperAdmin = controllersHelper::isSuperAdminEmployee($currentEmployee);
        $requested = [
            'is_superadmin' => !empty($data['is_superadmin']),
            'is_owner' => !empty($data['is_owner']),
            'is_manager' => !empty($data['is_manager']) || $this->isManagerScopedStaff($data),
            'is_employee' => !empty($data['is_employee']),
        ];

        if (!$requested['is_superadmin'] && !$requested['is_owner'] && !$requested['is_manager'] && !$requested['is_employee']) {
            $requested['is_employee'] = true;
        }

        if (!$isSuperAdmin && $existingEmployee !== null) {
            $requested['is_superadmin'] = !empty($existingEmployee['is_superadmin']);
            $requested['is_owner'] = !empty($existingEmployee['is_owner']);
        } elseif (!$isSuperAdmin) {
            $requested['is_superadmin'] = false;
            $requested['is_owner'] = false;
        }

        if ($requested['is_superadmin']) {
            $requested = ['is_superadmin' => true, 'is_owner' => false, 'is_manager' => false, 'is_employee' => false];
        } elseif ($requested['is_owner']) {
            $requested = ['is_superadmin' => false, 'is_owner' => true, 'is_manager' => false, 'is_employee' => false];
        } elseif ($requested['is_manager']) {
            $requested = ['is_superadmin' => false, 'is_owner' => false, 'is_manager' => true, 'is_employee' => false];
        } else {
            $requested = ['is_superadmin' => false, 'is_owner' => false, 'is_manager' => false, 'is_employee' => true];
        }

        foreach ($requested as $field => $enabled) {
            $data[$field] = $enabled ? 1 : 0;
        }
    }

    private function enforceStaffHierarchy(array $currentEmployee, array $targetEmployee, string $action): ?array
    {
        if ($currentEmployee === []) {
            return ['message' => 'API-KEY is required.', 'status' => 401];
        }

        if (controllersHelper::isSuperAdminEmployee($currentEmployee)) {
            return null;
        }

        if (!empty($targetEmployee['is_superadmin'])) {
            return ['message' => 'Only a super admin can manage a super admin account.', 'status' => 403];
        }

        if (!empty($targetEmployee['is_owner'])) {
            return ['message' => 'Only a super admin can manage an owner account.', 'status' => 403];
        }

        if (!empty($targetEmployee['is_manager']) && empty($currentEmployee['is_owner'])) {
            return ['message' => 'Only an owner or super admin can manage a manager account.', 'status' => 403];
        }

        if ($action === 'create' && !empty($targetEmployee['is_employee']) && empty($currentEmployee['is_owner']) && empty($currentEmployee['is_manager'])) {
            $staffPermissions = $this->staffPermissionChanges(null, $targetEmployee['permissions'] ?? null);
            if ($staffPermissions !== []) {
                return ['message' => 'Employees cannot create another employee with staff permissions.', 'status' => 403];
            }
        }

        return null;
    }

    private function staffPermissionChanges(?string $oldPermissions, ?string $newPermissions): array
    {
        $keys = controllersHelper::permissionKeys();
        $oldValues = array_map('trim', explode(',', (string) $oldPermissions));
        $newValues = array_map('trim', explode(',', (string) $newPermissions));
        $changes = [];

        foreach (['staff.create', 'staff.get', 'staff.update', 'staff.delete'] as $permission) {
            $index = array_search($permission, $keys, true);
            if ($index === false) {
                continue;
            }

            $oldEnabled = ($oldValues[$index] ?? '0') === '1';
            $newEnabled = ($newValues[$index] ?? '0') === '1';
            if ($oldEnabled !== $newEnabled || $newEnabled) {
                $changes[$permission] = $newEnabled;
            }
        }

        return $changes;
    }

    private function enabledPermissions(?string $permissions, array $permissionNames): array
    {
        $keys = controllersHelper::permissionKeys();
        $values = array_map('trim', explode(',', (string) $permissions));
        $enabled = [];

        foreach ($permissionNames as $permission) {
            $index = array_search($permission, $keys, true);
            if ($index !== false && ($values[$index] ?? '0') === '1') {
                $enabled[] = $permission;
            }
        }

        return $enabled;
    }

    private function managerRestrictedEmployeePermissions(): array
    {
        return [
            'branches.create',
            'branches.get',
            'branches.update',
            'branches.delete',
            'branches_dashboard.get',
            'branches_logs.get',
            'managers_log.get',
            'managers.create',
            'managers.get',
            'managers.update',
            'managers.delete',
        ];
    }

    private function enforceEmployeeStaffPermissionRules(array $currentEmployee, ?array $existingEmployee, array $data, ?string $submittedPermissions = null): ?array
    {
        if (controllersHelper::isSuperAdminEmployee($currentEmployee) || !empty($currentEmployee['is_owner'])) {
            return null;
        }

        if (!empty($currentEmployee['is_manager'])) {
            $restricted = $this->enabledPermissions(
                $submittedPermissions ?? ($data['permissions'] ?? null),
                $this->managerRestrictedEmployeePermissions()
            );

            if ($restricted !== []) {
                return ['message' => 'Managers cannot grant branch management or branch log permissions to employees.', 'status' => 403];
            }

            return null;
        }

        if ($existingEmployee !== null && (int) ($existingEmployee['id'] ?? 0) === (int) ($currentEmployee['id'] ?? -1)) {
            return null;
        }

        if (!empty($existingEmployee['is_employee']) && $this->staffPermissionChanges(null, $existingEmployee['permissions'] ?? null) !== []) {
            return ['message' => 'Employees cannot edit an employee that has staff permissions.', 'status' => 403];
        }

        if ($this->staffPermissionChanges($existingEmployee['permissions'] ?? null, $data['permissions'] ?? null) !== []) {
            return ['message' => 'Employees cannot grant or edit staff permissions.', 'status' => 403];
        }

        return null;
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
        $this->employeeModel = new Staff($db);
        $this->restaurantModel = new Restaurant($db);
        $this->validator = new StaffValidator();
    }

    public function index(): array
    {
        $employee = $this->authenticatedEmployee();
        $restaurantId = null;

        if ($employee !== null && !controllersHelper::isSuperAdminEmployee($employee)) {
            $requestedRestaurantId = $this->getRestaurantIdFromQuery();
            $restaurantId = $requestedRestaurantId !== null && controllersHelper::employeeCanAccessRestaurant($this->db, $employee, $requestedRestaurantId)
                ? $requestedRestaurantId
                : (!empty($employee['is_owner']) || !empty($employee['is_manager'])
                    ? (int) $employee['restaurant_id']
                    : controllersHelper::effectiveRestaurantId($employee));
        } elseif ($employee !== null) {
            $restaurantId = $this->getRestaurantIdFromQuery();
        }

        return $this->apiResponse([
            'success' => true,
            'data' => $this->permissionData(array_map([$this, 'sanitizeEmployee'], $this->employeeModel->getAll($restaurantId)), 'staff.get')
        ]);
    }

    public function show(int $id): array
    {
        $employee = $this->employeeModel->getById($id);

        if (!$employee) {
            return $this->apiResponse([
                'success' => false,
                'message' => 'Staff member not found.'
            ], 404);
        }

        return $this->apiResponse([
            'success' => true,
            'data' => $this->permissionData($this->sanitizeEmployee($employee), 'staff.get')
        ]);
    }

    private function normalizeManagerScope(array &$data, array &$errors): void
    {
        $isManagerRole = $this->isManagerScopedStaff($data);

        if (!$isManagerRole) {
            $data['manager_scope'] = null;
            $data['managed_branches'] = null;
            $data['allowed_branches'] = null;
            return;
        }

        $allowedBranches = $data['allowed_branches'] ?? $data['managed_branches'] ?? null;
        $scope = (string) ($data['manager_scope'] ?? '');
        if (is_string($allowedBranches) && strtolower(trim($allowedBranches)) === 'all') {
            $scope = 'all';
        } elseif ($allowedBranches !== null && $allowedBranches !== '' && $allowedBranches !== []) {
            $scope = 'some';
        }

        if ($scope === 'some') {
            $managed = $allowedBranches ?? [];
            if (is_string($managed)) {
                $managed = array_values(array_filter(array_map('trim', explode(',', (string) $managed))));
            }
            $managed = array_values(array_filter(array_map('intval', $managed), static fn ($v) => $v > 0));

            if ($managed === []) {
                $errors['managed_branches'] = 'Select at least one branch for this manager.';
                return;
            }

            $restaurantId = (int) ($data['restaurant_id'] ?? 0);
            $valid = [];
            foreach ($managed as $branchId) {
                $branch = $this->restaurantModel->getById($branchId);
                if (!$branch || (int) ($branch['parent_restaurant_id'] ?? 0) !== $restaurantId) {
                    $errors['managed_branches'] = 'Each selected branch must belong to this restaurant.';
                    return;
                }
                $valid[] = $branchId;
            }

            $data['manager_scope'] = 'some';
            $data['allowed_branches'] = implode(',', array_unique($valid));
            $data['managed_branches'] = $data['allowed_branches'];
            $data['branch_id'] = null;
        } elseif ($scope === 'none') {
            $data['manager_scope'] = 'none';
            $data['allowed_branches'] = '';
            $data['managed_branches'] = '';
            $data['branch_id'] = null;
        } else {
            $data['manager_scope'] = 'all';
            $data['allowed_branches'] = 'all';
            $data['managed_branches'] = '';
            $data['branch_id'] = null;
        }
    }

    public function store(): void
    {
        $data = $this->getJsonInput();
        $currentEmployee = $this->authenticatedEmployee() ?? [];
        $this->normalizeRoleFlags($data, $currentEmployee);
        $errors = $this->validator->validateCreate($data);
        $this->validateRestaurant($data, $errors);
        $this->validateUsernameScope($data, $errors);
        $this->normalizeManagerScope($data, $errors);
        $this->normalizeRoleFlags($data, $currentEmployee);
        $submittedPermissions = $data['permissions'] ?? null;
        $data['permissions'] = $this->normalizeEmployeePermissions($data['permissions'] ?? null, null, $data);

        $hierarchyError = $this->enforceStaffHierarchy($currentEmployee, $data, 'create')
            ?? $this->enforceEmployeeStaffPermissionRules($currentEmployee, null, $data, $submittedPermissions);
        if ($hierarchyError !== null) {
            $this->jsonResponse([
                'success' => false,
                'message' => $hierarchyError['message']
            ], $hierarchyError['status']);

            return;
        }

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
            if (!controllersHelper::isSuperAdminEmployee($currentEmployee)) {
                $data['hidden_details'] = null;
            }
            $data['password'] = $this->hashEmployeePassword((string) $data['password']);
            $employeeId = $this->employeeModel->create($data);
            $createdEmployee = $this->sanitizeEmployee($this->employeeModel->getById($employeeId));
            controllersHelper::logActivity($this->db, (int) $data['restaurant_id'], 'staff.create', 'Added new staff member', 'staff', $employeeId, [
                'entity_name' => $createdEmployee['name'] ?? null,
                'snapshot' => $createdEmployee,
            ]);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Staff member created successfully.',
                'data' => $this->permissionData($createdEmployee, 'staff.get')
            ], 201);
        } catch (Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to create staff member.',
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
                'message' => 'Staff member not found.'
            ], 404);

            return;
        }

        $input = $this->getJsonInput();
        $data = array_merge($employee, $input);
        $currentEmployee = $this->authenticatedEmployee();
        $currentEmployeeData = $currentEmployee ?? [];
        if ($currentEmployee === null || !controllersHelper::isSuperAdminEmployee($currentEmployee)) {
            $data['restaurant_id'] = $employee['restaurant_id'];
            $data['hidden_details'] = $employee['hidden_details'] ?? null;
            $data['is_superadmin'] = $employee['is_superadmin'] ?? 0;
            $data['is_owner'] = $employee['is_owner'] ?? 0;
        }
        $this->normalizeRoleFlags($data, $currentEmployeeData, $employee);

        if (!isset($data['password']) || trim((string) $data['password']) === (string) $employee['password']) {
            unset($data['password']);
        }
        $errors = $this->validator->validateUpdate($data);
        $this->validateRestaurant($data, $errors);
        $this->validateUsernameScope($data, $errors, $id);
        $this->normalizeManagerScope($data, $errors);
        $this->normalizeRoleFlags($data, $currentEmployeeData, $employee);

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
        $submittedPermissions = $data['permissions'] ?? null;
        $data['permissions'] = $this->normalizeEmployeePermissions($data['permissions'] ?? null, $id, $data);
        $hierarchyError = $this->enforceStaffHierarchy($currentEmployeeData, $employee, 'update')
            ?? $this->enforceStaffHierarchy($currentEmployeeData, $data, 'update')
            ?? $this->enforceEmployeeStaffPermissionRules($currentEmployeeData, $employee, $data, $submittedPermissions);
        if ($hierarchyError !== null) {
            $this->jsonResponse([
                'success' => false,
                'message' => $hierarchyError['message']
            ], $hierarchyError['status']);

            return;
        }
        $isSelfUpdate = $currentEmployee !== null && (int) ($currentEmployee['id'] ?? 0) === $id;
        $data['permissions'] = $isSelfUpdate
            ? (string) ($employee['permissions'] ?? '')
            : $data['permissions'];
        $this->employeeModel->update($id, $data);
        $updatedEmployee = $this->sanitizeEmployee($this->employeeModel->getById($id));
        $changes = controllersHelper::changedFields(
            $this->sanitizeEmployee($employee) ?? [],
            $updatedEmployee ?? [],
            ['password', 'API_KEY', 'API_KEY_EXPIRY_DATE', 'permissions']
        );
        $changes = array_merge($changes, $this->permissionChanges($employee['permissions'] ?? null, $data['permissions'] ?? null));
        controllersHelper::logActivity($this->db, (int) $data['restaurant_id'], 'staff.update', 'Updated staff member', 'staff', $id, [
            'entity_name' => $updatedEmployee['name'] ?? $employee['name'] ?? null,
            'changes' => $changes,
        ]);

        $this->jsonResponse([
            'success' => true,
            'message' => 'Staff member updated successfully.',
            'data' => $this->permissionData($updatedEmployee, 'staff.get')
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
                'message' => 'Staff member not found.'
            ], 404);

            return;
        }

        if (!empty($employee['is_owner']) && !controllersHelper::isSuperAdminEmployee($currentEmployee ?? [])) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Only a super admin can delete an owner account.'
            ], 403);

            return;
        }

        $hierarchyError = $this->enforceStaffHierarchy($currentEmployee ?? [], $employee, 'delete')
            ?? $this->enforceEmployeeStaffPermissionRules($currentEmployee ?? [], $employee, $employee);
        if ($hierarchyError !== null) {
            $this->jsonResponse([
                'success' => false,
                'message' => $hierarchyError['message']
            ], $hierarchyError['status']);

            return;
        }

        $this->employeeModel->delete($id);
        controllersHelper::logActivity($this->db, (int) ($employee['restaurant_id'] ?? 0), 'staff.delete', 'Deleted staff member', 'staff', $id, [
            'entity_name' => $employee['name'] ?? null,
            'snapshot' => $this->sanitizeEmployee($employee),
        ]);

        $this->jsonResponse([
            'success' => true,
            'message' => 'Staff member deleted successfully.'
        ]);
    }
}
