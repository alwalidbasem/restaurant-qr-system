<?php

require_once __DIR__ . '/../Models/FoodAddonsModel.php';
require_once __DIR__ . '/../Models/FoodModel.php';
require_once __DIR__ . '/../Models/RestaurantModel.php';
require_once __DIR__ . '/../Validators/FoodAddonsValidator.php';

class FoodAddonsController
{
    private FoodAddon $addonModel;
    private Food $foodModel;
    private Restaurant $restaurantModel;
    private FoodAddonsValidator $validator;

    public function __construct(PDO $db)
    {
        $this->addonModel = new FoodAddon($db);
        $this->foodModel = new Food($db);
        $this->restaurantModel = new Restaurant($db);
        $this->validator = new FoodAddonsValidator();
    }

    public function index(): void
    {
        $restaurantId = $this->getRestaurantIdFromQuery();

        $this->jsonResponse([
            'success' => true,
            'data' => $this->addonModel->getAll($restaurantId)
        ]);
    }

    public function show(int $id): void
    {
        $addon = $this->addonModel->getById($id);

        if (!$addon) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Food addon not found.'
            ], 404);

            return;
        }

        $this->jsonResponse([
            'success' => true,
            'data' => $addon
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

            $this->jsonResponse([
                'success' => true,
                'message' => 'Food addon created successfully.',
                'data' => $this->addonModel->getById($addonId)
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

        $this->jsonResponse([
            'success' => true,
            'message' => 'Food addon updated successfully.',
            'data' => $this->addonModel->getById($id)
        ]);
    }

    public function destroy(int $id): void
    {
        if (!$this->addonModel->exists($id)) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Food addon not found.'
            ], 404);

            return;
        }

        $this->addonModel->delete($id);

        $this->jsonResponse([
            'success' => true,
            'message' => 'Food addon deleted successfully.'
        ]);
    }

    private function validateReferences(array $data, array &$errors): void
    {
        if (!isset($errors['food_id']) && !$this->foodModel->exists((int) $data['food_id'])) {
            $errors['food_id'] = 'Food does not exist.';
        }

        if (!isset($errors['restaurant_id']) && !$this->restaurantModel->exists((int) $data['restaurant_id'])) {
            $errors['restaurant_id'] = 'Restaurant does not exist.';
        }
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

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
