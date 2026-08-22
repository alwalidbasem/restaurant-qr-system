<?php

require_once __DIR__ . '/../Models/InventoryModel.php';
require_once __DIR__ . '/../Models/RestaurantModel.php';
require_once __DIR__ . '/../Validators/InventoryValidator.php';

class InventoryController
{
    private Inventory $inventoryModel;
    private Restaurant $restaurantModel;
    private InventoryValidator $validator;

    public function __construct(PDO $db)
    {
        $this->inventoryModel = new Inventory($db);
        $this->restaurantModel = new Restaurant($db);
        $this->validator = new InventoryValidator();
    }

    public function index(): void
    {
        $this->jsonResponse([
            'success' => true,
            'data' => $this->inventoryModel->getAll()
        ]);
    }

    public function show(int $id): void
    {
        $item = $this->inventoryModel->getById($id);

        if (!$item) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Inventory item not found.'
            ], 404);

            return;
        }

        $this->jsonResponse([
            'success' => true,
            'data' => $item
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
            $itemId = $this->inventoryModel->create($data);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Inventory item created successfully.',
                'data' => $this->inventoryModel->getById($itemId)
            ], 201);
        } catch (PDOException $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to create inventory item.'
            ], 500);
        }
    }

    public function update(int $id): void
    {
        $item = $this->inventoryModel->getById($id);

        if (!$item) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Inventory item not found.'
            ], 404);

            return;
        }

        $data = array_merge($item, $this->getJsonInput());
        $errors = $this->validator->validateUpdate($data);
        $this->validateRestaurant($data, $errors);

        if (!empty($errors)) {
            $this->jsonResponse([
                'success' => false,
                'errors' => $errors
            ], 422);

            return;
        }

        $this->inventoryModel->update($id, $data);

        $this->jsonResponse([
            'success' => true,
            'message' => 'Inventory item updated successfully.',
            'data' => $this->inventoryModel->getById($id)
        ]);
    }

    public function destroy(int $id): void
    {
        if (!$this->inventoryModel->exists($id)) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Inventory item not found.'
            ], 404);

            return;
        }

        $this->inventoryModel->delete($id);

        $this->jsonResponse([
            'success' => true,
            'message' => 'Inventory item deleted successfully.'
        ]);
    }

    private function validateRestaurant(array $data, array &$errors): void
    {
        if (!isset($errors['restaurant_id']) && !$this->restaurantModel->exists((int) $data['restaurant_id'])) {
            $errors['restaurant_id'] = 'Restaurant does not exist.';
        }
    }

    private function getJsonInput(): array
    {
        $data = json_decode((string) file_get_contents('php://input'), true);

        return is_array($data) ? $data : [];
    }

    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
