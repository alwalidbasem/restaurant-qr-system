<?php

require_once __DIR__ . '/../Models/CategoriesModel.php';
require_once __DIR__ . '/../Models/RestaurantModel.php';
require_once __DIR__ . '/../Validators/CategoriesValidator.php';

class CategoriesController
{
    private Category $categoryModel;
    private Restaurant $restaurantModel;
    private CategoryValidator $validator;

    public function __construct(PDO $db)
    {
        $this->categoryModel = new Category($db);
        $this->restaurantModel = new Restaurant($db);
        $this->validator = new CategoryValidator();
    }

    public function index(): void
    {
        $restaurantId = $this->getRestaurantIdFromQuery();

        $this->jsonResponse([
            'success' => true,
            'data' => $this->categoryModel->getAll($restaurantId)
        ]);
    }

    public function show(int $id): void
    {
        $category = $this->categoryModel->getById($id);

        if (!$category) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Category not found.'
            ], 404);

            return;
        }

        $this->jsonResponse([
            'success' => true,
            'data' => $category
        ]);
    }

    public function store(): void
    {
        $data = $this->getJsonInput();
        $errors = $this->validator->validateCreate($data);

        if (!isset($errors['restaurant_id']) && !$this->restaurantModel->exists((int) $data['restaurant_id'])) {
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
            $categoryId = $this->categoryModel->create($data);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Category created successfully.',
                'data' => $this->categoryModel->getById($categoryId)
            ], 201);
        } catch (PDOException $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to create category.'
            ], 500);
        }
    }

    public function update(int $id): void
    {
        $category = $this->categoryModel->getById($id);

        if (!$category) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Category not found.'
            ], 404);

            return;
        }

        $data = array_merge($category, $this->getJsonInput());
        $errors = $this->validator->validateUpdate($data);

        if (!isset($errors['restaurant_id']) && !$this->restaurantModel->exists((int) $data['restaurant_id'])) {
            $errors['restaurant_id'] = 'Restaurant does not exist.';
        }

        if (!empty($errors)) {
            $this->jsonResponse([
                'success' => false,
                'errors' => $errors
            ], 422);

            return;
        }

        $this->categoryModel->update($id, $data);

        $this->jsonResponse([
            'success' => true,
            'message' => 'Category updated successfully.',
            'data' => $this->categoryModel->getById($id)
        ]);
    }

    public function destroy(int $id): void
    {
        if (!$this->categoryModel->exists($id)) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Category not found.'
            ], 404);

            return;
        }

        $this->categoryModel->delete($id);

        $this->jsonResponse([
            'success' => true,
            'message' => 'Category deleted successfully.'
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
