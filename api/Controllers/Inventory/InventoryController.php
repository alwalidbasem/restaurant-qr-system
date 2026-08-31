<?php

require_once __DIR__ . '/../../Middleware/PermissionsMiddleware.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../../Models/InventoryModel.php';
require_once __DIR__ . '/../../Models/RestaurantModel.php';
require_once __DIR__ . '/../../Validators/InventoryValidator.php';

class InventoryController
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

    private Inventory $inventoryModel;
    private Restaurant $restaurantModel;
    private InventoryValidator $validator;
    private PDO $db;

    private function validateRestaurant(array $data, array &$errors): void
    {
        if (!isset($errors['restaurant_id']) && !$this->restaurantModel->exists((int) $data['restaurant_id'])) {
            $errors['restaurant_id'] = 'Restaurant does not exist.';
        }
    }

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->inventoryModel = new Inventory($db);
        $this->restaurantModel = new Restaurant($db);
        $this->validator = new InventoryValidator();
    }

    public function index(): array
    {
        $restaurantId = $this->getRestaurantIdFromQuery();

        return $this->apiResponse([
            'success' => true,
            'data' => $this->permissionData($this->inventoryModel->getAll($restaurantId), 'inventory.get')
        ]);
    }

    public function show(int $id): array
    {
        $item = $this->inventoryModel->getById($id);

        if (!$item) {
            return $this->apiResponse([
                'success' => false,
                'message' => 'Inventory item not found.'
            ], 404);
        }

        return $this->apiResponse([
            'success' => true,
            'data' => $this->permissionData($item, 'inventory.get')
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
            $this->db->beginTransaction();
            $itemId = $this->inventoryModel->create($data);
            $this->inventoryModel->replaceLinks($itemId, $data['links'] ?? [], (int) $data['restaurant_id']);
            $this->db->commit();
            $createdItem = $this->inventoryModel->getById($itemId);
            controllersHelper::logActivity($this->db, (int) $data['restaurant_id'], 'inventory.create', 'Added inventory item "' . ($createdItem['name'] ?? ('Item #' . $itemId)) . '" with ' . rtrim(rtrim(number_format((float) ($createdItem['quantity'] ?? 0), 3, '.', ''), '0'), '.') . ' ' . ($createdItem['unit'] ?? ''), 'inventory', $itemId, [
                'entity_name' => $createdItem['name'] ?? null,
                'snapshot' => $createdItem,
            ]);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Inventory item created successfully.',
                'data' => $this->permissionData($createdItem, 'inventory.get')
            ], 201);
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
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

        try {
            $this->db->beginTransaction();
            $this->inventoryModel->update($id, $data);
            $this->inventoryModel->replaceLinks($id, $data['links'] ?? [], (int) $data['restaurant_id']);
            $this->db->commit();
            $updatedItem = $this->inventoryModel->getById($id);
            controllersHelper::logActivity($this->db, (int) $data['restaurant_id'], 'inventory.update', 'Updated inventory item "' . ($updatedItem['name'] ?? $item['name'] ?? ('Item #' . $id)) . '"', 'inventory', $id, [
                'entity_name' => $updatedItem['name'] ?? $item['name'] ?? null,
                'changes' => controllersHelper::changedFields($item, $updatedItem ?: $data, ['links']),
            ]);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Inventory item updated successfully.',
                'data' => $this->permissionData($updatedItem, 'inventory.get')
            ]);
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to update inventory item.'
            ], 500);
        }
    }

    public function destroy(int $id): void
    {
        $item = $this->inventoryModel->getById($id);
        if (!$item) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Inventory item not found.'
            ], 404);

            return;
        }

        $this->inventoryModel->delete($id);
        controllersHelper::logActivity($this->db, (int) ($item['restaurant_id'] ?? 0), 'inventory.delete', 'Deleted inventory item "' . ($item['name'] ?? ('Item #' . $id)) . '"', 'inventory', $id, [
            'entity_name' => $item['name'] ?? null,
            'snapshot' => $item,
        ]);

        $this->jsonResponse([
            'success' => true,
            'message' => 'Inventory item deleted successfully.'
        ]);
    }

    public function movement(int $id): void
    {
        $item = $this->inventoryModel->getById($id);

        if (!$item) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Inventory item not found.'
            ], 404);

            return;
        }

        $data = $this->getJsonInput();
        $type = (string) ($data['movement_type'] ?? 'adjustment');
        $quantity = (float) ($data['quantity'] ?? 0);
        $reason = trim((string) ($data['reason'] ?? ''));
        $allowed = ['purchase', 'waste', 'adjustment'];

        if (!in_array($type, $allowed, true) || $quantity <= 0) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Movement type and positive quantity are required.'
            ], 422);

            return;
        }

        if ($type === 'waste' && $reason === '') {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Waste reason is required.'
            ], 422);

            return;
        }

        $change = $type === 'purchase' ? $quantity : -$quantity;
        $this->inventoryModel->movement($id, $change, $type, $reason ?: null);

        $this->jsonResponse([
            'success' => true,
            'message' => 'Inventory movement saved.',
            'data' => $this->permissionData($this->inventoryModel->getById($id), 'inventory.get')
        ]);
    }

    public function movements(): array
    {
        return $this->apiResponse([
            'success' => true,
            'data' => $this->permissionData(
                $this->inventoryModel->getMovements($this->getRestaurantIdFromQuery()),
                'inventory.get'
            )
        ]);
    }
}
