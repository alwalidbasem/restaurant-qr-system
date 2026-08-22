<?php

require_once __DIR__ . '/../Models/TablesModel.php';
require_once __DIR__ . '/../Models/RestaurantModel.php';
require_once __DIR__ . '/../Validators/TablesValidator.php';


class TableController
{
    private Table $tableModel;
    private Restaurant $restaurantModel;
    private TableValidator $validator;

    public function __construct(PDO $db)
    {
        $this->tableModel = new Table($db);
        $this->restaurantModel = new Restaurant($db);
        $this->validator = new TableValidator();
    }


    // GET /tables
    public function index(): void
    {
        $restaurantId = $this->getRestaurantIdFromQuery();
        $tables = $this->tableModel->getAll($restaurantId);

        $this->jsonResponse([
            'success' => true,
            'data' => $tables
        ]);
    }


    // GET /tables/{id}
    public function show(int $id, bool $isJson = true): array
    {
        $table = $this->tableModel->getById($id);

        if (!$table) {

            $response = [
                'success' => false,
                'message' => 'Table not found.'
            ];


            if ($isJson) {
                $this->jsonResponse($response,404);
            }
            return $response;
        }


        // Decode JSON position
        if ($table['position']) {
            $table['position'] =
                json_decode(
                    $table['position'],
                    true
                );
        }

        $response = [
            'success' => true,
            'data' => $table
        ];

        if ($isJson) {
            $this->jsonResponse($response);
        }

        return $response;
    }


    public function showByNumber(int $tableNumber, bool $isJson = true, ?int $restaurantId = null): array
    {
        $table = $this->tableModel->getByNumber($tableNumber, $restaurantId);

        if (!$table) {

            $response = [
                'success' => false,
                'message' => 'Table not found.'
            ];

            if ($isJson) {
                $this->jsonResponse($response, 404);
            }

            return $response;
        }

        if ($table['position']) {
            $table['position'] = json_decode($table['position'], true);
        }

        $response = [
            'success' => true,
            'data' => $table
        ];

        if ($isJson) {
            $this->jsonResponse($response);
        }

        return $response;
    }


    // POST /tables
    public function store(): void
    {
        $data = $this->getJsonInput();

        $errors =
            $this->validator->validateCreate($data);

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


        if (
            $this->tableModel->tableNumberExists(
                (int) $data['table_number']
            )
        ) {

            $this->jsonResponse([
                'success' => false,
                'errors' => [
                    'table_number' =>
                        'Table number already exists.'
                ]
            ], 409);

            return;
        }


        try {

            $tableId =
                $this->tableModel->create($data);


            $table =
                $this->tableModel->getById($tableId);


            $this->jsonResponse([
                'success' => true,
                'message' => 'Table created successfully.',
                'data' => $table
            ], 201);

        } catch (PDOException $e) {

            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to create table.'
            ], 500);
        }
    }


    // PUT /tables/{id}
    public function update(int $id): void
    {
        $table =
            $this->tableModel->getById($id);


        if (!$table) {

            $this->jsonResponse([
                'success' => false,
                'message' => 'Table not found.'
            ], 404);

            return;
        }


        $data = $this->getJsonInput();


        /*
         * Fill missing values with current values.
         * This allows partial updates.
         */

        $data['table_number'] =
            $data['table_number']
            ?? $table['table_number'];

        $data['table_status'] =
            $data['table_status']
            ?? $table['table_status'];

        $data['table_floor'] =
            $data['table_floor']
            ?? $table['table_floor'];

        $data['order_id'] =
            array_key_exists('order_id', $data)
            ? $data['order_id']
            : $table['order_id'];

        $data['restaurant_id'] =
            $data['restaurant_id']
            ?? $table['restaurant_id'];


        if (!array_key_exists('position', $data)) {

            $data['position'] =
                $table['position']
                ? json_decode(
                    $table['position'],
                    true
                )
                : null;
        }


        $errors =
            $this->validator->validateUpdate($data);

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


        if (
            $this->tableModel->tableNumberExists(
                (int) $data['table_number'],
                $id
            )
        ) {

            $this->jsonResponse([
                'success' => false,
                'errors' => [
                    'table_number' =>
                        'Table number already exists.'
                ]
            ], 409);

            return;
        }


        $this->tableModel->update(
            $id,
            $data
        );


        $updatedTable =
            $this->tableModel->getById($id);


        $this->jsonResponse([
            'success' => true,
            'message' => 'Table updated successfully.',
            'data' => $updatedTable
        ]);
    }


    // DELETE /tables/{id}
    public function destroy(int $id): void
    {
        $table =
            $this->tableModel->getById($id);


        if (!$table) {

            $this->jsonResponse([
                'success' => false,
                'message' => 'Table not found.'
            ], 404);

            return;
        }


        $this->tableModel->delete($id);


        $this->jsonResponse([
            'success' => true,
            'message' => 'Table deleted successfully.'
        ]);
    }


    // PATCH /tables/{id}/status
    public function updateStatus(int $id): void
    {
        $table =
            $this->tableModel->getById($id);


        if (!$table) {

            $this->jsonResponse([
                'success' => false,
                'message' => 'Table not found.'
            ], 404);

            return;
        }


        $data = $this->getJsonInput();


        if (!isset($data['table_status'])) {

            $this->jsonResponse([
                'success' => false,
                'message' => 'Table status is required.'
            ], 422);

            return;
        }


        $errors =
            $this->validator->validateStatus(
                $data['table_status']
            );


        if (!empty($errors)) {

            $this->jsonResponse([
                'success' => false,
                'errors' => $errors
            ], 422);

            return;
        }


        $this->tableModel->updateStatus(
            $id,
            $data['table_status']
        );


        $this->jsonResponse([
            'success' => true,
            'message' => 'Table status updated successfully.'
        ]);
    }


    // Read JSON body
    private function getJsonInput(): array
    {
        $input =
            file_get_contents('php://input');

        $data =
            json_decode($input, true);

        return is_array($data)
            ? $data
            : [];
    }


    private function getRestaurantIdFromQuery(): ?int
    {
        $restaurantId = filter_input(INPUT_GET, 'restaurant_id', FILTER_VALIDATE_INT);

        return ($restaurantId !== false && $restaurantId !== null && $restaurantId > 0)
            ? $restaurantId
            : null;
    }


    // JSON Response
    private function jsonResponse(
        array $data,
        int $statusCode = 200
    ): void {

        http_response_code($statusCode);

        header('Content-Type: application/json');

        echo json_encode(
            $data,
            JSON_UNESCAPED_UNICODE |
            JSON_PRETTY_PRINT
        );
    }
}
