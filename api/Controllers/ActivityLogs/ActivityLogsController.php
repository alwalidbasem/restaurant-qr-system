<?php

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../../Models/ActivityLogModel.php';

class ActivityLogsController
{
    private PDO $db;
    private ActivityLog $logModel;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->logModel = new ActivityLog($db);
    }

    public function index(): array
    {
        $restaurantId = controllersHelper::getRestaurantIdFromQuery();
        $employee = controllersHelper::currentEmployee($this->db);
        $selectedRestaurantId = $restaurantId;

        if ($employee === null) {
            return controllersHelper::apiResponse([
                'success' => false,
                'message' => 'API-KEY is required.'
            ], 401);
        }

        if (!controllersHelper::isSuperAdminEmployee($employee)) {
            $restaurantId = $selectedRestaurantId !== null && controllersHelper::employeeCanAccessRestaurant($this->db, $employee, $selectedRestaurantId)
                ? $selectedRestaurantId
                : (int) ($employee['restaurant_id'] ?? 0);
        }

        if (!$restaurantId) {
            return controllersHelper::apiResponse([
                'success' => false,
                'message' => 'Restaurant ID is required.'
            ], 422);
        }

        $filters = [
            'limit' => filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT) ?: 25,
            'before_id' => filter_input(INPUT_GET, 'before_id', FILTER_VALIDATE_INT) ?: null,
            'after_id' => filter_input(INPUT_GET, 'after_id', FILTER_VALIDATE_INT) ?: null,
            'range' => $_GET['range'] ?? '24h',
            'permissions' => $this->csv('permissions'),
            'employee_ids' => $this->csv('employee_ids'),
        ];

        $scope = controllersHelper::activityLogScope($this->db, $restaurantId);
        $queryRestaurantId = $scope['restaurant_id'] ?: $restaurantId;
        if ($scope['branch_id'] > 0) {
            $filters['branch_id'] = $scope['branch_id'];
        }

        if ($scope['branch_id'] === 0 && $this->isBranchBrand($queryRestaurantId)) {
            $filters['restaurant_ids'] = $this->brandRestaurantIds($queryRestaurantId);
            if ($filters['permissions'] === []) {
                $filters['permissions'] = [
                    'auth.login',
                    'auth.logout',
                    'branches.create',
                    'branches.update',
                    'branches.delete',
                    'employees.create',
                    'employees.update',
                    'employees.delete',
                ];
            }
        }

        return controllersHelper::apiResponse([
            'success' => true,
            'data' => controllersHelper::permissionData(
                $this->db,
                $this->logModel->getAll($queryRestaurantId, $filters),
                'logs.get'
            )
        ]);
    }

    private function csv(string $key): array
    {
        $value = $_GET[$key] ?? '';
        if (is_array($value)) {
            return $value;
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $value))));
    }

    private function isBranchBrand(int $restaurantId): bool
    {
        try {
            $columns = $this->db->query("SHOW COLUMNS FROM restaurants")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('branch_management_enabled', $columns, true)) {
                return false;
            }

            $stmt = $this->db->prepare("
                SELECT parent_restaurant_id, branch_management_enabled
                FROM restaurants
                WHERE id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $restaurantId]);
            $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);

            return $restaurant
                && empty($restaurant['parent_restaurant_id'])
                && (int) ($restaurant['branch_management_enabled'] ?? 0) === 1;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function brandRestaurantIds(int $restaurantId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT id
                FROM restaurants
                WHERE id = :id OR parent_restaurant_id = :id
            ");
            $stmt->execute([':id' => $restaurantId]);

            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        } catch (Throwable $e) {
            return [$restaurantId];
        }
    }
}
