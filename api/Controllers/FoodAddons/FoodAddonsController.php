<?php

require_once __DIR__ . '/../../Middleware/PermissionsMiddleware.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../../Models/FoodAddonsModel.php';
require_once __DIR__ . '/../../Models/FoodModel.php';
require_once __DIR__ . '/../../Models/CategoriesModel.php';
require_once __DIR__ . '/../../Models/RestaurantModel.php';
require_once __DIR__ . '/../../Services/DiscountService.php';
require_once __DIR__ . '/../../Validators/FoodAddonsValidator.php';

class FoodAddonsController
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

    private FoodAddon $addonModel;
    private Food $foodModel;
    private Category $categoryModel;
    private Restaurant $restaurantModel;
    private DiscountService $discountService;
    private FoodAddonsValidator $validator;
    private PDO $db;

    private function validateReferences(array $data, array &$errors): void
    {
        $restaurantId = isset($data['restaurant_id']) ? (int) $data['restaurant_id'] : null;

        if (!isset($errors['food_id']) && !empty($data['food_id'])) {
            $food = $this->foodModel->getById((int) $data['food_id']);
            if (!$food) {
                $errors['food_id'] = 'Food does not exist.';
            } elseif ($restaurantId !== null && (int) $food['restaurant_id'] !== $restaurantId) {
                $errors['food_id'] = 'Food does not belong to this restaurant.';
            }
        }

        if (!isset($errors['category_id']) && !empty($data['category_id'])) {
            $category = $this->categoryModel->getById((int) $data['category_id']);
            if (!$category) {
                $errors['category_id'] = 'Category does not exist.';
            } elseif ($restaurantId !== null && (int) $category['restaurant_id'] !== $restaurantId) {
                $errors['category_id'] = 'Category does not belong to this restaurant.';
            }
        }

        if (!isset($errors['restaurant_id']) && !$this->restaurantModel->exists((int) $data['restaurant_id'])) {
            $errors['restaurant_id'] = 'Restaurant does not exist.';
        }
    }

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->addonModel = new FoodAddon($db);
        $this->foodModel = new Food($db);
        $this->categoryModel = new Category($db);
        $this->restaurantModel = new Restaurant($db);
        $this->discountService = new DiscountService($db);
        $this->validator = new FoodAddonsValidator();
    }

    public function index(): array
    {
        $restaurantId = $this->getRestaurantIdFromQuery();

        return $this->apiResponse([
            'success' => true,
            'data' => $this->permissionData($this->discountService->annotateAddons($this->addonModel->getAll($restaurantId), $restaurantId), 'food_addons.get')
        ]);
    }

    public function show(int $id): array
    {
        $addon = $this->addonModel->getById($id);

        if (!$addon) {
            return $this->apiResponse([
                'success' => false,
                'message' => 'Food addon not found.'
            ], 404);
        }

        return $this->apiResponse([
            'success' => true,
            'data' => $this->permissionData(($this->discountService->annotateAddons([$addon], (int) ($addon['restaurant_id'] ?? 0))[0] ?? $addon), 'food_addons.get')
        ]);
    }

    public function store(): void
    {
        $data = $this->getJsonInput();
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
            $addonId = $this->addonModel->create($data);
            $createdAddon = $this->addonModel->getById($addonId);
            controllersHelper::logActivity($this->db, (int) $data['restaurant_id'], 'foods.create', 'Added new food addon', 'food_addon', $addonId, [
                'food_id' => !empty($data['food_id']) ? (int) $data['food_id'] : null,
                'category_id' => !empty($data['category_id']) ? (int) $data['category_id'] : null,
                'entity_name' => $createdAddon['name_en'] ?? $createdAddon['name_ar'] ?? null,
                'snapshot' => $createdAddon,
            ]);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Food addon created successfully.',
                'data' => $this->permissionData($this->addonModel->getById($addonId), 'food_addons.get')
            ], 201);
        } catch (PDOException $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to create food addon.'
            ], 500);
        }
    }

    public function update(int $id): void
    {
        $addon = $this->addonModel->getById($id);

        if (!$addon) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Food addon not found.'
            ], 404);

            return;
        }

        $data = array_merge($addon, $this->getJsonInput());
        $errors = $this->validator->validateUpdate($data);
        $this->validateReferences($data, $errors);

        if (!empty($errors)) {
            $this->jsonResponse([
                'success' => false,
                'errors' => $errors
            ], 422);

            return;
        }

        $this->addonModel->update($id, $data);
        $updatedAddon = $this->addonModel->getById($id);
        controllersHelper::logActivity($this->db, (int) $data['restaurant_id'], 'foods.update', 'Updated food addon', 'food_addon', $id, [
            'food_id' => !empty($data['food_id']) ? (int) $data['food_id'] : null,
            'category_id' => !empty($data['category_id']) ? (int) $data['category_id'] : null,
            'entity_name' => $updatedAddon['name_en'] ?? $updatedAddon['name_ar'] ?? $addon['name_en'] ?? $addon['name_ar'] ?? null,
            'changes' => controllersHelper::changedFields($addon, $updatedAddon ?: $data),
        ]);

        $this->jsonResponse([
            'success' => true,
            'message' => 'Food addon updated successfully.',
            'data' => $this->permissionData($updatedAddon, 'food_addons.get')
        ]);
    }

    public function destroy(int $id): void
    {
        $addon = $this->addonModel->getById($id);
        if (!$addon) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Food addon not found.'
            ], 404);

            return;
        }

        $this->addonModel->delete($id);
        controllersHelper::logActivity($this->db, (int) ($addon['restaurant_id'] ?? 0), 'foods.delete', 'Deleted food addon', 'food_addon', $id, [
            'food_id' => !empty($addon['food_id']) ? (int) $addon['food_id'] : null,
            'category_id' => !empty($addon['category_id']) ? (int) $addon['category_id'] : null,
            'entity_name' => $addon['name_en'] ?? $addon['name_ar'] ?? null,
            'snapshot' => $addon,
        ]);

        $this->jsonResponse([
            'success' => true,
            'message' => 'Food addon deleted successfully.'
        ]);
    }
}
