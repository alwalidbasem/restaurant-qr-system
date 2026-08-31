<?php

require_once __DIR__ . '/../../Middleware/PermissionsMiddleware.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../../Models/CategoriesModel.php';
require_once __DIR__ . '/../../Models/DiscountsModel.php';
require_once __DIR__ . '/../../Models/FoodAddonsModel.php';
require_once __DIR__ . '/../../Models/FoodModel.php';
require_once __DIR__ . '/../../Models/RestaurantModel.php';
require_once __DIR__ . '/../../Validators/DiscountsValidator.php';

class DiscountsController
{

    private function getJsonInput(): array
    {
        $data = json_decode((string) file_get_contents('php://input'), true);

        return is_array($data) ? $data : [];
    }

    private function getHeaderValue(string $name): string
    {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return (string) ($_SERVER[$serverKey] ?? '');
    }

    private function getRestaurantIdFromQuery(): ?int
    {
        $restaurantId = filter_input(INPUT_GET, 'restaurant_id', FILTER_VALIDATE_INT);

        return ($restaurantId !== false && $restaurantId !== null && $restaurantId > 0)
            ? $restaurantId
            : null;
    }

    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function apiResponse(array $body, int $statusCode = 200): array
    {
        return [
            'body' => $body,
            'status' => $statusCode
        ];
    }

    private function permissionData(array $data, string $crudName): array
    {
        require __DIR__ . '/../../../config/database.php';

        $middleware = new PermissionsMiddleware($conn);

        if ($this->isListData($data)) {
            return array_map(
                fn (array $row): array => $this->filterPermissionRow($row, $crudName, $middleware),
                $data
            );
        }

        return $this->filterPermissionRow($data, $crudName, $middleware);
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

    private Discount $discountModel;
    private Food $foodModel;
    private Category $categoryModel;
    private FoodAddon $addonModel;
    private Restaurant $restaurantModel;
    private DiscountsValidator $validator;
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->discountModel = new Discount($db);
        $this->foodModel = new Food($db);
        $this->categoryModel = new Category($db);
        $this->addonModel = new FoodAddon($db);
        $this->restaurantModel = new Restaurant($db);
        $this->validator = new DiscountsValidator();
    }

    public function index(): array
    {
        $restaurantId = $this->getRestaurantIdFromQuery();

        return $this->apiResponse([
            'success' => true,
            'data' => $this->permissionData($this->discountModel->getAll($restaurantId), 'discounts.get')
        ]);
    }

    public function show(int $id): array
    {
        $discount = $this->discountModel->getById($id);

        if (!$discount) {
            return $this->apiResponse([
                'success' => false,
                'message' => 'Discount not found.'
            ], 404);
        }

        return $this->apiResponse([
            'success' => true,
            'data' => $this->permissionData($discount, 'discounts.get')
        ]);
    }

    public function store(): void
    {
        $data = $this->normalizedPayload($this->getJsonInput());
        $errors = $this->validator->validateCreate($data);
        $this->validateReferences($data, $errors);

        if (!empty($errors)) {
            $this->jsonResponse([
                'success' => false,
                'errors' => $errors
            ], 422);

            return;
        }

        try {
            $discountId = $this->discountModel->create($data);
            $createdDiscount = $this->discountModel->getById($discountId);
            controllersHelper::logActivity($this->db, (int) $data['restaurant_id'], 'discounts.create', 'Added discount', 'discount', $discountId, [
                'entity_name' => $createdDiscount['name'] ?? null,
                'snapshot' => $createdDiscount,
            ]);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Discount created successfully.',
                'data' => $this->permissionData($createdDiscount, 'discounts.get')
            ], 201);
        } catch (PDOException $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to create discount.'
            ], 500);
        }
    }

    public function update(int $id): void
    {
        $discount = $this->discountModel->getById($id);

        if (!$discount) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Discount not found.'
            ], 404);

            return;
        }

        $data = $this->normalizedPayload(array_merge($discount, $this->getJsonInput()));
        $errors = $this->validator->validateUpdate($data);
        $this->validateReferences($data, $errors);

        if (!empty($errors)) {
            $this->jsonResponse([
                'success' => false,
                'errors' => $errors
            ], 422);

            return;
        }

        $this->discountModel->update($id, $data);
        $updatedDiscount = $this->discountModel->getById($id);
        controllersHelper::logActivity($this->db, (int) $data['restaurant_id'], 'discounts.update', 'Updated discount', 'discount', $id, [
            'entity_name' => $updatedDiscount['name'] ?? $discount['name'] ?? null,
            'changes' => controllersHelper::changedFields($discount, $updatedDiscount ?: $data),
        ]);

        $this->jsonResponse([
            'success' => true,
            'message' => 'Discount updated successfully.',
            'data' => $this->permissionData($updatedDiscount, 'discounts.get')
        ]);
    }

    public function destroy(int $id): void
    {
        $discount = $this->discountModel->getById($id);

        if (!$discount) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Discount not found.'
            ], 404);

            return;
        }

        $this->discountModel->delete($id);
        controllersHelper::logActivity($this->db, (int) ($discount['restaurant_id'] ?? 0), 'discounts.delete', 'Deleted discount', 'discount', $id, [
            'entity_name' => $discount['name'] ?? null,
            'snapshot' => $discount,
        ]);

        $this->jsonResponse([
            'success' => true,
            'message' => 'Discount deleted successfully.'
        ]);
    }

    private function normalizedPayload(array $data): array
    {
        $targetType = (string) ($data['target_type'] ?? '');
        if (in_array($targetType, ['full_menu_with_addons', 'full_menu_without_addons'], true)) {
            $data['target_id'] = null;
        }

        $data['is_active'] = !empty($data['is_active']) ? 1 : 0;

        return $data;
    }

    private function validateReferences(array $data, array &$errors): void
    {
        $restaurantId = isset($data['restaurant_id']) ? (int) $data['restaurant_id'] : null;

        if (!isset($errors['restaurant_id']) && !$this->restaurantModel->exists((int) $data['restaurant_id'])) {
            $errors['restaurant_id'] = 'Restaurant does not exist.';
        }

        if (!empty($errors['target_id']) || !empty($errors['target_type']) || $restaurantId === null) {
            return;
        }

        $targetType = (string) ($data['target_type'] ?? '');
        $targetId = isset($data['target_id']) ? (int) $data['target_id'] : 0;

        if ($targetType === 'food') {
            $food = $this->foodModel->getById($targetId);
            if (!$food) {
                $errors['target_id'] = 'Food does not exist.';
            } elseif ((int) $food['restaurant_id'] !== $restaurantId) {
                $errors['target_id'] = 'Food does not belong to this restaurant.';
            }
        }

        if ($targetType === 'category') {
            $category = $this->categoryModel->getById($targetId);
            if (!$category) {
                $errors['target_id'] = 'Category does not exist.';
            } elseif ((int) $category['restaurant_id'] !== $restaurantId) {
                $errors['target_id'] = 'Category does not belong to this restaurant.';
            }
        }

        if ($targetType === 'addon') {
            $addon = $this->addonModel->getById($targetId);
            if (!$addon) {
                $errors['target_id'] = 'Food addon does not exist.';
            } elseif ((int) $addon['restaurant_id'] !== $restaurantId) {
                $errors['target_id'] = 'Food addon does not belong to this restaurant.';
            }
        }
    }

}
