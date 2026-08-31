<?php

require_once __DIR__ . '/../../Middleware/PermissionsMiddleware.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../../Models/RestaurantModel.php';
require_once __DIR__ . '/../../Validators/RestaurantValidator.php';

class RestaurantController
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
    private Restaurant $restaurantModel;
    private RestaurantValidator $validator;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->restaurantModel = new Restaurant($db);
        $this->validator = new RestaurantValidator();
    }

    public function index(): array
    {
        return $this->apiResponse([
            'success' => true,
            'data' => $this->permissionData($this->restaurantModel->getAll(), 'restaurants.get')
        ]);
    }

    public function show(int $id): array
    {
        $restaurant = $this->restaurantModel->getById($id);

        if (!$restaurant) {
            return $this->apiResponse([
                'success' => false,
                'message' => 'Restaurant not found.'
            ], 404);
        }

        return $this->apiResponse([
            'success' => true,
            'data' => $this->permissionData($restaurant, 'restaurants.get')
        ]);
    }

    public function branchesDashboard(int $id): array
    {
        $restaurant = $this->restaurantModel->getById($id);

        if (!$restaurant) {
            return $this->apiResponse([
                'success' => false,
                'message' => 'Restaurant not found.'
            ], 404);
        }

        return $this->apiResponse([
            'success' => true,
            'data' => $this->restaurantModel->branchDashboard($id)
        ]);
    }

    public function store(): void
    {
        $data = $this->getJsonInput();
        $employee = controllersHelper::currentEmployee($this->db);
        if ($employee !== null && !controllersHelper::isSuperAdminEmployee($employee)) {
            $data['parent_restaurant_id'] = (int) ($employee['restaurant_id'] ?? 0);
            $data['branch_management_enabled'] = 0;
            $data['branch_limit'] = 0;
        }
        $errors = $this->validator->validateCreate($data);

        if (!empty($errors)) {
            $this->jsonResponse([
                'success' => false,
                'errors' => $errors
            ], 422);

            return;
        }

        try {
            $this->enforceBranchLimit($data, null);
            $restaurantId = $this->restaurantModel->create($data);
            $createdRestaurant = $this->restaurantModel->getById($restaurantId);
            $isBranch = !empty($createdRestaurant['parent_restaurant_id']);
            $logRestaurantId = $isBranch ? (int) $createdRestaurant['parent_restaurant_id'] : $restaurantId;
            $logMetadata = [
                'entity_name' => $createdRestaurant['name'] ?? null,
                'snapshot' => $createdRestaurant,
            ];
            if ($isBranch) {
                $logMetadata['_log_branch_id'] = $restaurantId;
            }
            controllersHelper::logActivity(
                $this->db,
                $logRestaurantId,
                $isBranch ? 'branches.create' : 'restaurants.create',
                $isBranch ? 'Added new branch' : 'Added new restaurant',
                'restaurant',
                $restaurantId,
                $logMetadata
            );

            $this->jsonResponse([
                'success' => true,
                'message' => 'Restaurant created successfully.',
                'data' => $this->permissionData($this->restaurantModel->getById($restaurantId), 'restaurants.get')
            ], 201);
        } catch (PDOException $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to create restaurant.'
            ], 500);
        }
    }

    public function update(int $id): void
    {
        $restaurant = $this->restaurantModel->getById($id);

        if (!$restaurant) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Restaurant not found.'
            ], 404);

            return;
        }

        $data = array_merge($restaurant, $this->getJsonInput());
        $employee = controllersHelper::currentEmployee($this->db);
        if ($employee === null || !controllersHelper::isSuperAdminEmployee($employee)) {
            $isBranchUpdate = !empty($restaurant['parent_restaurant_id']);
            $data['id'] = $restaurant['id'];
            if (!$isBranchUpdate) {
                $data['main_code'] = $restaurant['main_code'];
            }
            $data['parent_restaurant_id'] = $restaurant['parent_restaurant_id'] ?? null;
            $data['branch_management_enabled'] = $restaurant['branch_management_enabled'] ?? 0;
            $data['branch_limit'] = $restaurant['branch_limit'] ?? 0;
        }

        $errors = $this->validator->validateUpdate($data);

        if (!empty($errors)) {
            $this->jsonResponse([
                'success' => false,
                'errors' => $errors
            ], 422);

            return;
        }

        $this->enforceBranchLimit($data, $id);
        $this->restaurantModel->update($id, $data);
        $this->restaurantModel->updateWebsiteSettings($id, $data);
        $updatedRestaurant = $this->restaurantModel->getById($id);
        $isBranch = !empty($updatedRestaurant['parent_restaurant_id'] ?? $restaurant['parent_restaurant_id'] ?? null);
        $logRestaurantId = $isBranch
            ? (int) (($updatedRestaurant['parent_restaurant_id'] ?? $restaurant['parent_restaurant_id']))
            : $id;
        $logMetadata = [
            'entity_name' => $updatedRestaurant['name'] ?? $restaurant['name'] ?? null,
            'changes' => controllersHelper::changedFields($restaurant, $updatedRestaurant ?: $data, ['id']),
        ];
        if ($isBranch) {
            $logMetadata['_log_branch_id'] = $id;
        }
        controllersHelper::logActivity(
            $this->db,
            $logRestaurantId,
            $isBranch ? 'branches.update' : ($employee !== null && controllersHelper::isSuperAdminEmployee($employee) ? 'restaurants.update' : 'restaurant.update'),
            $isBranch ? 'Updated branch settings' : 'Updated restaurant settings',
            'restaurant',
            $id,
            $logMetadata
        );

        $this->jsonResponse([
            'success' => true,
            'message' => 'Restaurant updated successfully.',
            'data' => $this->permissionData($updatedRestaurant, 'restaurants.get')
        ]);
    }

    private function enforceBranchLimit(array $data, ?int $currentRestaurantId): void
    {
        $parentId = isset($data['parent_restaurant_id']) && $data['parent_restaurant_id'] !== ''
            ? (int) $data['parent_restaurant_id']
            : null;

        if ($parentId === null) {
            return;
        }

        $parent = $this->restaurantModel->getById($parentId);
        if (!$parent) {
            $this->jsonResponse([
                'success' => false,
                'errors' => ['parent_restaurant_id' => 'Parent restaurant does not exist.']
            ], 422);
            exit;
        }

        if ((int) ($parent['branch_management_enabled'] ?? 0) !== 1) {
            $this->jsonResponse([
                'success' => false,
                'errors' => ['parent_restaurant_id' => 'Parent restaurant does not have branch management enabled.']
            ], 422);
            exit;
        }

        $limit = (int) ($parent['branch_limit'] ?? 0);
        if ($limit <= 0) {
            $this->jsonResponse([
                'success' => false,
                'errors' => ['branch_limit' => 'Super admin must allow at least one branch first.']
            ], 422);
            exit;
        }

        if ($this->restaurantModel->branchCount($parentId, $currentRestaurantId) >= $limit) {
            $this->jsonResponse([
                'success' => false,
                'errors' => ['branch_limit' => 'Branch limit reached for this restaurant.']
            ], 422);
            exit;
        }
    }

    public function destroy(int $id): void
    {
        $restaurant = $this->restaurantModel->getById($id);
        if (!$restaurant) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Restaurant not found.'
            ], 404);

            return;
        }

        $logRestaurantId = !empty($restaurant['parent_restaurant_id'])
            ? (int) $restaurant['parent_restaurant_id']
            : $id;
        $logMetadata = [
            'entity_name' => $restaurant['name'] ?? null,
            'snapshot' => $restaurant,
        ];
        if (!empty($restaurant['parent_restaurant_id'])) {
            $logMetadata['_log_branch_id'] = $id;
        }

        $this->restaurantModel->delete($id);
        controllersHelper::logActivity(
            $this->db,
            $logRestaurantId,
            !empty($restaurant['parent_restaurant_id']) ? 'branches.delete' : 'restaurants.delete',
            !empty($restaurant['parent_restaurant_id']) ? 'Deleted branch' : 'Deleted restaurant',
            'restaurant',
            $id,
            $logMetadata
        );

        $this->jsonResponse([
            'success' => true,
            'message' => 'Restaurant deleted successfully.'
        ]);
    }
}
