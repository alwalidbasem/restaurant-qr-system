<?php

require_once __DIR__ . '/../Models/RestaurantModel.php';
require_once __DIR__ . '/../Validators/RestaurantValidator.php';

class RestaurantController
{
    private Restaurant $restaurantModel;
    private RestaurantValidator $validator;

    public function __construct(PDO $db)
    {
        $this->restaurantModel = new Restaurant($db);
        $this->validator = new RestaurantValidator();
    }

    public function index(): void
    {
        $this->jsonResponse([
            'success' => true,
            'data' => $this->restaurantModel->getAll()
        ]);
    }

    public function show(int $id): void
    {
        $restaurant = $this->restaurantModel->getById($id);

        if (!$restaurant) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Restaurant not found.'
            ], 404);

            return;
        }

        $this->jsonResponse([
            'success' => true,
            'data' => $restaurant
        ]);
    }

    public function store(): void
    {
        $data = $this->getJsonInput();
        $errors = $this->validator->validateCreate($data);

        if (!empty($errors)) {
            $this->jsonResponse([
                'success' => false,
                'errors' => $errors
            ], 422);

            return;
        }

        try {
            $restaurantId = $this->restaurantModel->create($data);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Restaurant created successfully.',
                'data' => $this->restaurantModel->getById($restaurantId)
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
        $errors = $this->validator->validateUpdate($data);

        if (!empty($errors)) {
            $this->jsonResponse([
                'success' => false,
                'errors' => $errors
            ], 422);

            return;
        }

        $this->restaurantModel->update($id, $data);

        $this->jsonResponse([
            'success' => true,
            'message' => 'Restaurant updated successfully.',
            'data' => $this->restaurantModel->getById($id)
        ]);
    }

    public function destroy(int $id): void
    {
        if (!$this->restaurantModel->exists($id)) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Restaurant not found.'
            ], 404);

            return;
        }

        $this->restaurantModel->delete($id);

        $this->jsonResponse([
            'success' => true,
            'message' => 'Restaurant deleted successfully.'
        ]);
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
