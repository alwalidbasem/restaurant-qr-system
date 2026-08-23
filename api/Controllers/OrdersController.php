<?php

require_once __DIR__ . '/../Models/OrdersModel.php';
require_once __DIR__ . '/../Models/FoodModel.php';
require_once __DIR__ . '/../Models/TablesModel.php';
require_once __DIR__ . '/../Models/RestaurantModel.php';
require_once __DIR__ . '/../Models/EmployeeModel.php';
require_once __DIR__ . '/../Validators/OrdersValidator.php';

class OrdersController
{
    private Order $orderModel;
    private Food $foodModel;
    private Table $tableModel;
    private Restaurant $restaurantModel;
    private Employee $employeeModel;
    private OrdersValidator $validator;

    public function __construct(PDO $db)
    {
        $this->orderModel = new Order($db);
        $this->foodModel = new Food($db);
        $this->tableModel = new Table($db);
        $this->restaurantModel = new Restaurant($db);
        $this->employeeModel = new Employee($db);
        $this->validator = new OrdersValidator();
    }

    public function index(): void
    {
        $restaurantId = $this->getRestaurantIdFromQuery();
        $auth = $this->getOrderReadAuth($restaurantId);

        if (!$auth['allowed']) {
            $this->jsonResponse([
                'success' => false,
                'message' => $auth['message']
            ], $auth['status']);

            return;
        }

        if ($auth['is_employee'] && $restaurantId === null) {
            $restaurantId = (int) $auth['employee']['restaurant_id'];
        }

        $orderIds = $auth['is_employee']
            ? $this->getOrderIdsFromQuery()
            : $auth['order_ids'];
        $sessionOrderKey = $auth['is_employee']
            ? null
            : $auth['session_order_key'];
        $orders = $this->orderModel->getAll($restaurantId, $sessionOrderKey, $orderIds);

        $this->jsonResponse([
            'success' => true,
            'data' => $auth['is_employee'] ? $orders : array_map([$this, 'sanitizeCustomerOrder'], $orders)
        ]);
    }

    public function show(int $id): void
    {
        $order = $this->orderModel->getParentById($id);

        if (!$order) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Order not found.'
            ], 404);

            return;
        }

        $auth = $this->getOrderReadAuth((int) $order['restaurant_id'], [$id]);

        if (!$auth['allowed']) {
            $this->jsonResponse([
                'success' => false,
                'message' => $auth['message']
            ], $auth['status']);

            return;
        }

        if (!$auth['is_employee'] && !hash_equals((string) $order['session_order_key'], (string) $auth['session_order_key'])) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Order not found.'
            ], 404);

            return;
        }

        $this->jsonResponse([
            'success' => true,
            'data' => $auth['is_employee']
                ? $this->orderModel->getFoodsByOrderId($id)
                : array_map([$this, 'sanitizeCustomerOrder'], $this->orderModel->getFoodsByOrderId($id))
        ]);
    }

    public function store(): void
    {
        $data = $this->getJsonInput();

        if (isset($data['items']) && is_array($data['items'])) {
            $this->storeBulk($data);
            return;
        }

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
            $this->orderModel->beginTransaction();
            $table = $this->tableModel->getById((int) $data['table_id']);
            $createdAtTimestamp = isset($data['created_at'])
                ? strtotime((string) $data['created_at'])
                : time();
            $data['session_order_key'] = $this->generateSessionOrderKey(
                (int) $data['restaurant_id'],
                (int) ($table['table_number'] ?? $data['table_id']),
                (int) ($createdAtTimestamp ?: time())
            );
            $orderId = $this->orderModel->create($data);
            $data['order_id'] = $orderId;
            $this->orderModel->createFood($data);
            $this->tableModel->assignOrder((int) $data['table_id'], $orderId);
            $this->orderModel->commit();

            $this->jsonResponse([
                'success' => true,
                'message' => 'Order created successfully.',
                'data' => [
                    'session_order_key' => $data['session_order_key'],
                    'order' => $this->sanitizeCustomerOrder($this->orderModel->getById($orderId))
                ]
            ], 201);
        } catch (PDOException $e) {
            $this->orderModel->rollBack();

            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to create order.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function storeBulk(array $data): void
    {
        $errors = $this->validateBulk($data);

        if (!empty($errors)) {
            $this->jsonResponse([
                'success' => false,
                'errors' => $errors
            ], 422);

            return;
        }

        $restaurantId = (int) $data['restaurant_id'];
        $tableId = (int) $data['table_id'];
        $createdAt = $data['created_at'] ?? date('Y-m-d H:i:s');
        $orderFoods = [];
        $total = 0.0;
        $totalExtraPrice = 0.0;
        $totalProfit = 0.0;

        try {
            $this->orderModel->beginTransaction();
            $table = $this->tableModel->getById($tableId);
            $sessionOrderKey = $this->generateSessionOrderKey(
                $restaurantId,
                (int) ($table['table_number'] ?? $tableId),
                (int) strtotime((string) $createdAt)
            );

            foreach ($data['items'] as $index => $itemInput) {
                $food = $this->foodModel->getById((int) $itemInput['food_id']);

                if (!$food || (int) $food['restaurant_id'] !== $restaurantId) {
                    throw new RuntimeException("Invalid food at item {$index}.");
                }

                $qty = max(1, (int) ($itemInput['qty'] ?? 1));
                $selectedAddons = $this->normalizeSelectedAddons($itemInput['addons'] ?? []);
                $addonRows = $this->orderModel->getAddonsByIds(
                    array_column($selectedAddons, 'id'),
                    (int) $food['id'],
                    $restaurantId
                );

                $extraPrice = 0.0;
                $extraProfit = 0.0;
                $detailsAddons = [];

                foreach ($selectedAddons as $selectedAddon) {
                    $addonId = (int) $selectedAddon['id'];
                    if (!isset($addonRows[$addonId])) {
                        throw new RuntimeException("Invalid addon at item {$index}.");
                    }

                    $addon = $addonRows[$addonId];
                    $extraPrice += (float) $addon['extra_price'];
                    $extraProfit += (float) $addon['extra_profit'];
                    $detailsAddons[] = [
                        'id' => $addonId,
                        'name_ar' => $addon['name_ar'],
                        'name_en' => $addon['name_en'],
                        'type' => $selectedAddon['type'] ?? 'checkbox',
                        'value' => $selectedAddon['value'] ?? null,
                        'price' => (float) $addon['extra_price'],
                        'profit' => (float) $addon['extra_profit']
                    ];
                }

                $price = ((float) ($food['price'] ?? 0) * $qty) + $extraPrice;
                $profit = ((float) ($food['profit'] ?? 0) * $qty) + $extraProfit;

                $orderFoods[] = [
                    'food_id' => (int) $food['id'],
                    'table_id' => $tableId,
                    'extra_price' => $extraPrice,
                    'price' => $price,
                    'profit' => $profit,
                    'details' => json_encode([
                        'qty' => $qty,
                        'addons' => $detailsAddons
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'session_order_key' => $sessionOrderKey,
                    'created_at' => $createdAt,
                    'restaurant_id' => $restaurantId
                ];

                $total += $price;
                $totalExtraPrice += $extraPrice;
                $totalProfit += $profit;
            }

            $orderId = $this->orderModel->create([
                'table_id' => $tableId,
                'status' => 'waiting',
                'extra_price' => $totalExtraPrice,
                'price' => $total,
                'profit' => $totalProfit,
                'details' => null,
                'session_order_key' => $sessionOrderKey,
                'created_at' => $createdAt,
                'restaurant_id' => $restaurantId
            ]);

            foreach ($orderFoods as $orderFood) {
                $orderFood['order_id'] = $orderId;
                $this->orderModel->createFood($orderFood);
            }

            $createdOrderFoods = $this->orderModel->getFoodsByOrderId($orderId);
            $this->tableModel->assignOrder($tableId, $orderId);
            $this->orderModel->commit();

            $this->jsonResponse([
                'success' => true,
                'message' => 'Order created successfully.',
                'data' => [
                    'session_order_key' => $sessionOrderKey,
                    'order_id' => $orderId,
                    'orders' => array_map([$this, 'sanitizeCustomerOrder'], $createdOrderFoods),
                    'total' => $total
                ]
            ], 201);
        } catch (Throwable $e) {
            $this->orderModel->rollBack();

            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to create order.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(int $id): void
    {
        $employee = $this->requireEmployeePermission('orders.write');
        if (!$employee) {
            return;
        }

        $order = $this->orderModel->getParentById($id);

        if (!$order) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Order not found.'
            ], 404);

            return;
        }

        if ((int) $employee['restaurant_id'] !== (int) $order['restaurant_id']) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'API key does not belong to this restaurant.'
            ], 403);

            return;
        }

        $data = array_merge($order, $this->getJsonInput());
        $errors = $this->validator->validateUpdate($data);
        $this->validateReferences($data, $errors);

        if (!empty($errors)) {
            $this->jsonResponse([
                'success' => false,
                'errors' => $errors
            ], 422);

            return;
        }

        $this->orderModel->update($id, $data);

        $this->jsonResponse([
            'success' => true,
            'message' => 'Order updated successfully.',
            'data' => $this->orderModel->getParentById($id)
        ]);
    }

    public function destroy(int $id): void
    {
        $employee = $this->requireEmployeePermission('orders.delete');
        if (!$employee) {
            return;
        }

        $order = $this->orderModel->getParentById($id);

        if (!$order) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Order not found.'
            ], 404);

            return;
        }

        if ((int) $employee['restaurant_id'] !== (int) $order['restaurant_id']) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'API key does not belong to this restaurant.'
            ], 403);

            return;
        }

        $this->orderModel->delete($id);

        $this->jsonResponse([
            'success' => true,
            'message' => 'Order deleted successfully.'
        ]);
    }

    private function validateReferences(array $data, array &$errors): void
    {
        $restaurantId = isset($data['restaurant_id']) ? (int) $data['restaurant_id'] : null;

        if (isset($data['food_id']) && !isset($errors['food_id'])) {
            $food = $this->foodModel->getById((int) $data['food_id']);
            if (!$food) {
                $errors['food_id'] = 'Food does not exist.';
            } elseif ($restaurantId !== null && (int) $food['restaurant_id'] !== $restaurantId) {
                $errors['food_id'] = 'Food does not belong to this restaurant.';
            }
        }

        if (!isset($errors['table_id'])) {
            $table = $this->tableModel->getById((int) $data['table_id']);
            if (!$table) {
                $errors['table_id'] = 'Table does not exist.';
            } elseif ($restaurantId !== null && (int) $table['restaurant_id'] !== $restaurantId) {
                $errors['table_id'] = 'Table does not belong to this restaurant.';
            }
        }

        if (!isset($errors['restaurant_id']) && !$this->restaurantModel->exists((int) $data['restaurant_id'])) {
            $errors['restaurant_id'] = 'Restaurant does not exist.';
        }
    }

    private function validateBulk(array $data): array
    {
        $errors = [];

        foreach (['table_id', 'restaurant_id'] as $field) {
            if (!isset($data[$field])) {
                $errors[$field] = str_replace('_', ' ', ucfirst($field)) . ' is required.';
            } elseif (
                filter_var($data[$field], FILTER_VALIDATE_INT) === false ||
                (int) $data[$field] <= 0
            ) {
                $errors[$field] = str_replace('_', ' ', ucfirst($field)) . ' must be a valid positive integer.';
            }
        }

        if (!isset($data['items']) || !is_array($data['items']) || $data['items'] === []) {
            $errors['items'] = 'At least one order item is required.';
        }

        if (isset($data['created_at']) && strtotime((string) $data['created_at']) === false) {
            $errors['created_at'] = 'Created at must be a valid date/time.';
        }

        if (!isset($errors['restaurant_id']) && !$this->restaurantModel->exists((int) $data['restaurant_id'])) {
            $errors['restaurant_id'] = 'Restaurant does not exist.';
        }

        if (!isset($errors['table_id'])) {
            $table = $this->tableModel->getById((int) $data['table_id']);
            if (!$table) {
                $errors['table_id'] = 'Table does not exist.';
            } elseif (!isset($errors['restaurant_id']) && (int) $table['restaurant_id'] !== (int) $data['restaurant_id']) {
                $errors['table_id'] = 'Table does not belong to this restaurant.';
            }
        }

        if (!isset($errors['items'])) {
            foreach ($data['items'] as $index => $item) {
                if (!is_array($item)) {
                    $errors["items.{$index}"] = 'Order item must be an object.';
                    continue;
                }

                if (!isset($item['food_id'])) {
                    $errors["items.{$index}.food_id"] = 'Food ID is required.';
                } elseif (
                    filter_var($item['food_id'], FILTER_VALIDATE_INT) === false ||
                    (int) $item['food_id'] <= 0
                ) {
                    $errors["items.{$index}.food_id"] = 'Food ID must be a valid positive integer.';
                }

                if (isset($item['qty']) && (
                    filter_var($item['qty'], FILTER_VALIDATE_INT) === false ||
                    (int) $item['qty'] <= 0
                )) {
                    $errors["items.{$index}.qty"] = 'Quantity must be a valid positive integer.';
                }

                if (isset($item['addons']) && !is_array($item['addons'])) {
                    $errors["items.{$index}.addons"] = 'Addons must be an array.';
                }
            }
        }

        return $errors;
    }

    private function normalizeSelectedAddons(array $addons): array
    {
        $normalized = [];

        foreach ($addons as $addon) {
            if (!is_array($addon) || !isset($addon['id']) || (int) $addon['id'] <= 0) {
                continue;
            }

            $normalized[] = [
                'id' => (int) $addon['id'],
                'type' => $addon['type'] ?? 'checkbox',
                'value' => $addon['value'] ?? null
            ];
        }

        return $normalized;
    }

    private function getOrderReadAuth(?int $restaurantId, array $specificOrderIds = []): array
    {
        $employee = $this->getEmployeeFromApiKey();
        if ($employee) {
            if ($restaurantId !== null && (int) $employee['restaurant_id'] !== $restaurantId) {
                return [
                    'allowed' => false,
                    'is_employee' => true,
                    'status' => 403,
                    'message' => 'API key does not belong to this restaurant.'
                ];
            }

            if (!$this->employeeCan($employee, 'orders.read')) {
                return [
                    'allowed' => false,
                    'is_employee' => true,
                    'status' => 403,
                    'message' => 'Employee does not have permission to read orders.'
                ];
            }

            return [
                'allowed' => true,
                'is_employee' => true,
                'employee' => $employee
            ];
        }

        $sessionOrderKey = $this->getHeaderValue('SESSION-ORDER-KEY')
            ?: (string) ($_GET['session_order_key'] ?? '');
        $sessionOrderKey = trim($sessionOrderKey);

        if ($sessionOrderKey === '') {
            return [
                'allowed' => false,
                'is_employee' => false,
                'status' => 401,
                'message' => 'SESSION-ORDER-KEY or API-KEY is required.'
            ];
        }

        $orderIds = $specificOrderIds !== []
            ? $specificOrderIds
            : $this->getOrderIdsFromQuery();

        return [
            'allowed' => true,
            'is_employee' => false,
            'session_order_key' => $sessionOrderKey,
            'order_ids' => $orderIds
        ];
    }

    private function getEmployeeFromApiKey(): ?array
    {
        $apiKey = $this->getHeaderValue('API-KEY')
            ?: $this->getHeaderValue('X-API-KEY')
            ?: (string) ($_GET['api_key'] ?? '');
        $apiKey = trim($apiKey);

        if ($apiKey === '') {
            return null;
        }

        return $this->employeeModel->getByApiKey($apiKey);
    }

    private function requireEmployeePermission(string $permission): ?array
    {
        $employee = $this->getEmployeeFromApiKey();

        if (!$employee) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'API-KEY is required.'
            ], 401);

            return null;
        }

        if (!$this->employeeCan($employee, $permission)) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Employee does not have permission.'
            ], 403);

            return null;
        }

        return $employee;
    }

    private function employeeCan(array $employee, string $permission): bool
    {
        if (($employee['role'] ?? '') === 'owner') {
            return true;
        }

        $permissionIndex = [
            'orders.read' => 0,
            'orders.write' => 1,
            'orders.delete' => 2
        ][$permission] ?? null;

        if ($permissionIndex === null) {
            return false;
        }

        $permissions = array_map('trim', explode(',', (string) ($employee['permissions'] ?? '')));

        return ($permissions[$permissionIndex] ?? '0') === '1';
    }

    private function sanitizeCustomerOrder(?array $order): ?array
    {
        if ($order === null) {
            return null;
        }

        unset(
            $order['profit'],
            $order['order_profit'],
            $order['session_order_key'],
            $order['restaurant_id']
        );

        if (isset($order['details'])) {
            $details = json_decode((string) $order['details'], true);
            if (is_array($details) && isset($details['addons']) && is_array($details['addons'])) {
                foreach ($details['addons'] as &$addon) {
                    unset($addon['profit']);
                }
                $order['details'] = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        return $order;
    }

    private function getOrderIdsFromQuery(): array
    {
        $raw = (string) ($_GET['order_ids'] ?? '');

        return array_values(array_unique(array_filter(
            array_map('intval', explode(',', $raw)),
            static fn (int $id): bool => $id > 0
        )));
    }

    private function generateSessionOrderKey(int $restaurantId, int $tableNumber, int $timestamp): string
    {
        $nextOrderNumber = $this->orderModel->getMaxOrderId() + 1;
        $hourMinute = date('Hi', $timestamp > 0 ? $timestamp : time());

        return (string) $restaurantId
            . (string) $tableNumber
            . (string) $nextOrderNumber
            . $hourMinute;
    }

    private function getHeaderValue(string $name): string
    {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return (string) ($_SERVER[$serverKey] ?? '');
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
