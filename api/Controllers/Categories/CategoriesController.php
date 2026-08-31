<?php

require_once __DIR__ . '/../../Middleware/PermissionsMiddleware.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../../Models/CategoriesModel.php';
require_once __DIR__ . '/../../Models/RestaurantModel.php';
require_once __DIR__ . '/../../Validators/CategoriesValidator.php';

class CategoriesController
{

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

    private Category $categoryModel;
    private Restaurant $restaurantModel;
    private CategoryValidator $validator;
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->categoryModel = new Category($db);
        $this->restaurantModel = new Restaurant($db);
        $this->validator = new CategoryValidator();
    }

    public function index(): array
    {
        $restaurantId = $this->getRestaurantIdFromQuery();
        
        $data = $this->categoryModel->getAll($restaurantId);
        
        return $this->apiResponse([
            'success' => true,
            'data' => $this->permissionData($data, 'categories.get')
        ]);
    }

    public function show(int $id): array
    {
        $category = $this->categoryModel->getById($id);

        if (!$category) {
            return $this->apiResponse([
                'success' => false,
                'message' => 'Category not found.'
            ], 404);
        }

        return $this->apiResponse([
            'success' => true,
            'data' => $this->permissionData($category, 'all')
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
            $createdCategory = $this->categoryModel->getById($categoryId);
            controllersHelper::logActivity($this->db, (int) $data['restaurant_id'], 'categories.create', 'Added new category', 'category', $categoryId, [
                'entity_name' => $createdCategory['name_en'] ?? $createdCategory['name_ar'] ?? null,
                'snapshot' => $createdCategory,
            ]);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Category created successfully.',
                'data' => $this->permissionData($this->categoryModel->getById($categoryId), 'categories.get')
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
        $updatedCategory = $this->categoryModel->getById($id);
        controllersHelper::logActivity($this->db, (int) $data['restaurant_id'], 'categories.update', 'Updated category', 'category', $id, [
            'entity_name' => $updatedCategory['name_en'] ?? $updatedCategory['name_ar'] ?? $category['name_en'] ?? $category['name_ar'] ?? null,
            'changes' => controllersHelper::changedFields($category, $updatedCategory ?: $data),
        ]);

        $this->jsonResponse([
            'success' => true,
            'message' => 'Category updated successfully.',
            'data' => $this->permissionData($updatedCategory, 'categories.get')
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

        $category = $this->categoryModel->getById($id);
        $this->categoryModel->delete($id);
        controllersHelper::logActivity($this->db, (int) ($category['restaurant_id'] ?? 0), 'categories.delete', 'Deleted category', 'category', $id, [
            'entity_name' => $category['name_en'] ?? $category['name_ar'] ?? null,
            'snapshot' => $category,
        ]);

        $this->jsonResponse([
            'success' => true,
            'message' => 'Category deleted successfully.'
        ]);
    }
}
