<?php

require_once __DIR__ . '/../Models/FoodModel.php';
require_once __DIR__ . '/../Models/CategoriesModel.php';
require_once __DIR__ . '/../Models/RestaurantModel.php';
require_once __DIR__ . '/../Models/EmployeeModel.php';
require_once __DIR__ . '/../Validators/FoodValidator.php';

class FoodController
{
    private Food $foodModel;
    private Category $categoryModel;
    private Restaurant $restaurantModel;
    private Employee $employeeModel;
    private FoodValidator $validator;

    public function __construct(PDO $db)
    {
        $this->foodModel = new Food($db);
        $this->categoryModel = new Category($db);
        $this->restaurantModel = new Restaurant($db);
        $this->employeeModel = new Employee($db);
        $this->validator = new FoodValidator();
    }

    public function index(): void
    {
        $restaurantId = $this->getRestaurantIdFromQuery();
        $foods = $this->foodModel->getAll($restaurantId);

        $this->jsonResponse([
            'success' => true,
            'data' => $this->isEmployeeApiRequest()
                ? $foods
                : array_map([$this, 'sanitizePublicFood'], $foods)
        ]);
    }

    public function show(int $id): void
    {
        $food = $this->foodModel->getById($id);

        if (!$food) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Food not found.'
            ], 404);

            return;
        }

        $this->jsonResponse([
            'success' => true,
            'data' => $this->isEmployeeApiRequest() ? $food : $this->sanitizePublicFood($food)
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

            $this->jsonResponse([
                'success' => true,
                'message' => 'Food created successfully.',
                'data' => $this->foodModel->getById($foodId)
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

        $this->jsonResponse([
            'success' => true,
            'message' => 'Food updated successfully.',
            'data' => $this->foodModel->getById($id)
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

        $this->foodModel->delete($id);

        $this->jsonResponse([
            'success' => true,
            'message' => 'Food deleted successfully.'
        ]);
    }

    private function getJsonInput(): array
    {
        $data = json_decode((string) file_get_contents('php://input'), true);

        return is_array($data) ? $data : [];
    }

    private function getRestaurantIdFromQuery(): ?int
    {
        $restaurantId = filter_input(INPUT_GET, 'restaurant_id', FILTER_VALIDATE_INT);

        return ($restaurantId !== false && $restaurantId !== null && $restaurantId > 0)
            ? $restaurantId
            : null;
    }

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

    private function getHeaderValue(string $name): string
    {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return (string) ($_SERVER[$serverKey] ?? '');
    }

    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode(
            $data,
            JSON_UNESCAPED_UNICODE |
            JSON_PRETTY_PRINT
        );
    }
}
