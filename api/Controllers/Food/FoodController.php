<?php

require_once __DIR__ . '/../../Middleware/PermissionsMiddleware.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../../Models/CategoriesModel.php';
require_once __DIR__ . '/../../Models/EmployeeModel.php';
require_once __DIR__ . '/../../Models/FoodModel.php';
require_once __DIR__ . '/../../Models/RestaurantModel.php';
require_once __DIR__ . '/../../Services/DiscountService.php';
require_once __DIR__ . '/../../Validators/FoodValidator.php';

class FoodController
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

    private Food $foodModel;
    private Category $categoryModel;
    private Restaurant $restaurantModel;
    private Employee $employeeModel;
    private DiscountService $discountService;
    private FoodValidator $validator;
    private PDO $db;

    private function isEmployeeApiRequest(): bool
    {
        $apiKey = $this->getHeaderValue('API-KEY')
            ?: $this->getHeaderValue('X-API-KEY')
            ?: (string) ($_GET['api_key'] ?? '');
        $apiKey = trim($apiKey);

        return $apiKey !== '' && $this->employeeModel->getByApiKey($apiKey) !== null;
    }

    private function sanitizePublicFood(array $food): array
    {
        unset($food['profit']);

        if (isset($food['addons']) && is_array($food['addons'])) {
            foreach ($food['addons'] as &$addon) {
                unset($addon['extra_profit'], $addon['profit']);
            }
        }

        return $food;
    }

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->foodModel = new Food($db);
        $this->categoryModel = new Category($db);
        $this->restaurantModel = new Restaurant($db);
        $this->employeeModel = new Employee($db);
        $this->discountService = new DiscountService($db);
        $this->validator = new FoodValidator();
    }

    public function index(): array
    {
        $restaurantId = $this->getRestaurantIdFromQuery();
        $foods = $this->discountService->annotateFoods($this->foodModel->getAll($restaurantId), $restaurantId);

        return $this->apiResponse([
            'success' => true,
            'data' => $this->permissionData($foods, 'foods.get')
        ]);
    }

    public function show(int $id): array
    {
        $food = $this->foodModel->getById($id);

        if (!$food) {
            return $this->apiResponse([
                'success' => false,
                'message' => 'Food not found.'
            ], 404);
        }

        $food = $this->discountService->annotateFoods([$food], (int) ($food['restaurant_id'] ?? 0))[0] ?? $food;

        return $this->apiResponse([
            'success' => true,
            'data' => $this->permissionData($food, 'foods.get')
        ]);
    }

    public function store(): void
    {
        $data = $this->getJsonInput();
        $errors = $this->validator->validateCreate($data);

        if (empty($errors) && !$this->categoryModel->exists((int) $data['category_id'])) {
            $errors['category_id'] = 'Category does not exist.';
        }

        if (empty($errors['restaurant_id']) && !$this->restaurantModel->exists((int) $data['restaurant_id'])) {
            $errors['restaurant_id'] = 'Restaurant does not exist.';
        }

        if (!empty($errors)) {
            $this->jsonResponse([
                'success' => false,
                'errors' => $errors
            ], 422);

            return;
        }

        try {
            $foodId = $this->foodModel->create($data);
            $createdFood = $this->foodModel->getById($foodId);
            $foodName = $createdFood['name_en'] ?? $createdFood['name_ar'] ?? ('Food #' . $foodId);
            controllersHelper::logActivity($this->db, (int) $data['restaurant_id'], 'foods.create', 'Added food "' . $foodName . '" with price ' . number_format((float) ($createdFood['price'] ?? 0), 2), 'food', $foodId, [
                'entity_name' => $createdFood['name_en'] ?? $createdFood['name_ar'] ?? null,
                'snapshot' => $createdFood,
            ]);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Food created successfully.',
                'data' => $this->permissionData($this->foodModel->getById($foodId), 'foods.get')
            ], 201);
        } catch (PDOException $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to create food.'
            ], 500);
        }
    }

    public function update(int $id): void
    {
        $food = $this->foodModel->getById($id);

        if (!$food) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Food not found.'
            ], 404);

            return;
        }

        $data = array_merge($food, $this->getJsonInput());
        $errors = $this->validator->validateUpdate($data);

        if (empty($errors) && !$this->categoryModel->exists((int) $data['category_id'])) {
            $errors['category_id'] = 'Category does not exist.';
        }

        if (empty($errors['restaurant_id']) && !$this->restaurantModel->exists((int) $data['restaurant_id'])) {
            $errors['restaurant_id'] = 'Restaurant does not exist.';
        }

        if (!empty($errors)) {
            $this->jsonResponse([
                'success' => false,
                'errors' => $errors
            ], 422);

            return;
        }

        $this->foodModel->update($id, $data);
        $updatedFood = $this->foodModel->getById($id);
        $foodName = $updatedFood['name_en'] ?? $updatedFood['name_ar'] ?? $food['name_en'] ?? $food['name_ar'] ?? ('Food #' . $id);
        controllersHelper::logActivity($this->db, (int) $data['restaurant_id'], 'foods.update', 'Updated food "' . $foodName . '"', 'food', $id, [
            'entity_name' => $updatedFood['name_en'] ?? $updatedFood['name_ar'] ?? $food['name_en'] ?? $food['name_ar'] ?? null,
            'changes' => controllersHelper::changedFields($food, $updatedFood ?: $data, ['addons']),
        ]);

        $this->jsonResponse([
            'success' => true,
            'message' => 'Food updated successfully.',
            'data' => $this->permissionData($updatedFood, 'foods.get')
        ]);
    }

    public function destroy(int $id): void
    {
        if (!$this->foodModel->getById($id)) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Food not found.'
            ], 404);

            return;
        }

        $food = $this->foodModel->getById($id);
        $this->foodModel->delete($id);
        $foodName = $food['name_en'] ?? $food['name_ar'] ?? ('Food #' . $id);
        controllersHelper::logActivity($this->db, (int) ($food['restaurant_id'] ?? 0), 'foods.delete', 'Deleted food "' . $foodName . '"', 'food', $id, [
            'entity_name' => $food['name_en'] ?? $food['name_ar'] ?? null,
            'snapshot' => $food,
        ]);

        $this->jsonResponse([
            'success' => true,
            'message' => 'Food deleted successfully.'
        ]);
    }
}
