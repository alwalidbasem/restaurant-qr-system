<?php

require_once __DIR__ . '/../../Middleware/PermissionsMiddleware.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../Auth/AuthController.php';
require_once __DIR__ . '/../../Models/FoodModel.php';
require_once __DIR__ . '/../../Models/InventoryModel.php';
require_once __DIR__ . '/../../Models/OrdersModel.php';
require_once __DIR__ . '/../../Models/RestaurantModel.php';
require_once __DIR__ . '/../../Models/TablesModel.php';
require_once __DIR__ . '/../../Services/DiscountService.php';
require_once __DIR__ . '/../../Services/InvoiceService.php';
require_once __DIR__ . '/../../Validators/OrdersValidator.php';

class OrdersController
{

    private function getJsonInput(): array
    {
        return controllersHelper::getJsonInput();
    }

    private function getHeaderValue(string $name): string
    {
        return controllersHelper::getHeaderValue($name);
    }

    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        controllersHelper::jsonResponse($data, $statusCode);
    }

    private function apiResponse(array $body, int $statusCode = 200): array
    {
        return controllersHelper::apiResponse($body, $statusCode);
    }

    private function permissionData(array $data, string $crudName): array
    {
        return controllersHelper::permissionData($this->db, $data, $crudName);
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

    private PDO $db;
    private Order $orderModel;
    private Food $foodModel;
    private Table $tableModel;
    private Restaurant $restaurantModel;
    private Inventory $inventoryModel;
    private DiscountService $discountService;
    private InvoiceService $invoiceService;
    private OrdersValidator $validator;

    private function getOrderIdsFromQuery(): array
    {
        $raw = (string) ($_GET['order_ids'] ?? $_GET['order_id'] ?? '');

        return array_values(array_unique(array_filter(
            array_map('intval', explode(',', $raw)),
            static fn (int $id): bool => $id > 0
        )));
    }

    private function getSessionOrderKeyFromRequest(): string
    {
        return trim(
            $this->getHeaderValue('SESSION-ORDER-KEY')
            ?: (string) ($_GET['session_order_key'] ?? '')
        );
    }

    private function getRestaurantIdFromQuery(): ?int
    {
        $restaurantId = controllersHelper::getRestaurantIdFromQuery();

        if ($restaurantId !== null) {
            return $restaurantId;
        }

        $restaurantCode = $_GET['restaurant_code'] ?? $_GET['r_code'] ?? $_GET['main_code'] ?? null;
        $restaurantCode = is_string($restaurantCode) ? trim($restaurantCode) : '';
        if ($restaurantCode === '') {
            return null;
        }

        $restaurant = $this->restaurantModel->getByCode($restaurantCode);

        return $restaurant ? (int) $restaurant['id'] : null;
    }

    private function getOrderReadAuth(?int $restaurantId, array $orderIds = [], ?string $sessionOrderKey = null, bool $forceCustomerSession = false): array
    {
        $sessionOrderKey = trim((string) $sessionOrderKey);
        $employee = $this->getAuthenticatedEmployee();

        if (!$forceCustomerSession && $employee !== null) {
            return [
                'allowed' => true,
                'is_employee' => true,
                'employee' => $employee,
                'can_access_all_restaurants' => $this->employeeCanAccessAllRestaurants($employee)
            ];
        }

        if ($sessionOrderKey === '') {
            return [
                'allowed' => false,
                'is_employee' => false,
                'status' => 401,
                'message' => $forceCustomerSession
                    ? 'SESSION-ORDER-KEY is required.'
                    : 'SESSION-ORDER-KEY or API-KEY is required.'
            ];
        }

        if ($restaurantId === null) {
            return [
                'allowed' => false,
                'is_employee' => false,
                'status' => 422,
                'message' => 'Restaurant code or restaurant ID is required.'
            ];
        }

        if ($orderIds === []) {
            return [
                'allowed' => false,
                'is_employee' => false,
                'status' => 422,
                'message' => 'Order ID is required.'
            ];
        }

        return [
            'allowed' => true,
            'is_employee' => false,
            'session_order_key' => $sessionOrderKey,
            'order_ids' => $orderIds
        ];
    }

    private function getAuthenticatedEmployee(): ?array
    {
        $auth = new AuthController($this->db, true);
        $response = $auth->isAuth();
        $employee = $response['data']['employee'] ?? null;

        return is_array($employee) ? $employee : null;
    }

    private function getEmployeeRestaurantId(array $employee): ?int
    {
        if (!array_key_exists('restaurant_id', $employee) || $employee['restaurant_id'] === null || $employee['restaurant_id'] === '') {
            return null;
        }

        $restaurantId = filter_var($employee['restaurant_id'], FILTER_VALIDATE_INT);

        return ($restaurantId !== false && $restaurantId > 0)
            ? (int) $restaurantId
            : null;
    }

    private function employeeCanAccessAllRestaurants(array $employee): bool
    {
        $webAdmins = require __DIR__ . '/../../Middleware/permissions_config/restaurant_crud_admins.php';
        $webAdminIds = array_map('intval', $webAdmins['employee_ids'] ?? $webAdmins);

        return $this->getEmployeeRestaurantId($employee) === 1
            && in_array((int) ($employee['id'] ?? 0), $webAdminIds, true);
    }

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->orderModel = new Order($db);
        $this->foodModel = new Food($db);
        $this->tableModel = new Table($db);
        $this->restaurantModel = new Restaurant($db);
        $this->inventoryModel = new Inventory($db);
        $this->discountService = new DiscountService($db);
        $this->invoiceService = new InvoiceService($db);
        $this->validator = new OrdersValidator();
    }

    public function index(): array
    {
        $PermissionsMiddleware = new PermissionsMiddleware($this->db);
        $restaurantId = $this->getRestaurantIdFromQuery();
        $orderIds = $this->getOrderIdsFromQuery();
        $sessionOrderKey = $this->getSessionOrderKeyFromRequest();
        $forceCustomerSession = trim($this->getHeaderValue('SESSION-ORDER-KEY')) !== '';
        $requestedOrderType = strtolower(trim((string) ($_GET['order_type'] ?? '')));
        $isTakeawayList = $requestedOrderType === 'takeaway';
        $auth = $this->getOrderReadAuth($restaurantId, $orderIds, $sessionOrderKey, $forceCustomerSession);

        if (!$auth['allowed']) {
            return $this->apiResponse([
                'success' => false,
                'message' => $auth['message']
            ], $auth['status']);
        }

        $hasOrdersGet = $auth['is_employee']
            ? $PermissionsMiddleware->isQualifiedEmployee('orders.get', true)
            : false;
        $hasTakeawayTableAccess = $auth['is_employee']
            ? (
                $PermissionsMiddleware->isQualifiedEmployee('tables.get', true)
                || $PermissionsMiddleware->isQualifiedEmployee('tables.update', true)
            )
            : false;

        if ($auth['is_employee'] && !$auth['can_access_all_restaurants'] && !$hasOrdersGet && !($isTakeawayList && $hasTakeawayTableAccess)) {
            return $this->apiResponse([
                'success' => false,
                'message' => 'Permission denied: orders.get'
            ], 403);
        }

        if ($auth['is_employee'] && !$auth['can_access_all_restaurants']) {
            $employeeRestaurantId = controllersHelper::effectiveRestaurantId($auth['employee']);
            if ($restaurantId !== null && !controllersHelper::employeeCanAccessRestaurant($this->db, $auth['employee'], $restaurantId)) {
                return $this->apiResponse([
                    'success' => false,
                    'message' => 'Permission denied: cannot view another restaurant orders.'
                ], 403);
            }
            $restaurantId = $employeeRestaurantId;
        }

        if ($auth['is_employee']) {
            $sessionOrderKey = null;
        } else {
            $sessionOrderKey = $auth['session_order_key'];
        }

        $orders = $this->orderModel->getAll($restaurantId, $sessionOrderKey, $orderIds);
        if ($isTakeawayList) {
            $orders = array_values(array_filter($orders, static fn (array $row): bool => ($row['order_type'] ?? 'table') === 'takeaway'));
        }
        $data = $auth['is_employee'] && !$auth['can_access_all_restaurants'] && !$hasOrdersGet && $isTakeawayList
            ? array_map(static fn (array $row): array => [
                'order_id' => $row['order_id'] ?? null,
                'order_type' => $row['order_type'] ?? 'takeaway',
                'status' => $row['status'] ?? 'waiting',
                'created_at' => $row['created_at'] ?? null,
                'order_price' => $row['order_price'] ?? $row['price'] ?? 0,
                'price' => $row['price'] ?? 0,
                'restaurant_id' => $row['restaurant_id'] ?? null,
            ], $orders)
            : $this->permissionData($orders, 'orders.get');

        return $this->apiResponse([
            'success' => true,
            'data' => $data
        ]);
    }

    public function show(int $id): array
    {
        $order = $this->orderModel->getParentById($id);

        if (!$order) {
            return $this->apiResponse([
                'success' => false,
                'message' => 'Order not found.'
            ], 404);
        }

        $auth = $this->getOrderReadAuth(
            (int) $order['restaurant_id'],
            [$id],
            $this->getSessionOrderKeyFromRequest(),
            trim($this->getHeaderValue('SESSION-ORDER-KEY')) !== ''
        );

        if (!$auth['allowed']) {
            return $this->apiResponse([
                'success' => false,
                'message' => $auth['message']
            ], $auth['status']);
        }

        if (!$auth['is_employee'] && !hash_equals((string) $order['session_order_key'], (string) $auth['session_order_key'])) {
            return $this->apiResponse([
                'success' => false,
                'message' => 'Order not found.'
            ], 404);
        }

        $data = $this->orderModel->getFoodsByOrderId($id);

        return $this->apiResponse([
            'success' => true,
            'data' => $this->permissionData($data, 'orders.get')
        ]);
    }

    public function store(): void
    {
        $data = $this->getJsonInput();

        if (isset($data['items']) && is_array($data['items'])) {
            $this->storeBulk($data);
            return;
        }

        $selectedAddons = $this->normalizeSelectedAddons($data['addons'] ?? []);
        $data['order_type'] = $this->normalizeOrderType($data);
        if ($data['order_type'] === 'takeaway') {
            $data['table_id'] = null;
        }
        $data['qty'] = max(1, (int) ($data['qty'] ?? 1));
        $data['addon_id'] = $selectedAddons !== []
            ? array_column($selectedAddons, 'id')
            : $this->normalizeAddonIds($data['addon_id'] ?? null);
        $data['details'] = $this->normalizeChefNote($data['details'] ?? null);
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
            $this->orderModel->syncOrderAutoIncrements();
            $this->orderModel->beginTransaction();
            $table = $data['order_type'] === 'takeaway'
                ? null
                : $this->tableModel->getById((int) $data['table_id']);
            $food = $this->foodModel->getById((int) $data['food_id']);
            $addonRows = $this->orderModel->getAddonsByIds(
                $data['addon_id'],
                (int) $data['food_id'],
                (int) $data['restaurant_id']
            );
            $orderFoodGroups = $this->buildOrderFoodGroups($food, (int) $data['qty'], $selectedAddons, $addonRows);
            $data['extra_price'] = array_sum(array_map(static fn (array $group): float => (float) $group['extra_price'], $orderFoodGroups));
            $data['price'] = array_sum(array_map(static fn (array $group): float => (float) $group['price'], $orderFoodGroups));
            $data['profit'] = array_sum(array_map(static fn (array $group): float => (float) $group['profit'], $orderFoodGroups));
            $createdAtTimestamp = isset($data['created_at'])
                ? strtotime((string) $data['created_at'])
                : time();
            $data['session_order_key'] = $this->generateSessionOrderKey(
                (int) $data['restaurant_id'],
                $this->sessionOrderNumber($data, $table),
                (int) ($createdAtTimestamp ?: time())
            );
            $orderId = $this->orderModel->create($data);
            $data['order_id'] = $orderId;
            foreach ($orderFoodGroups as $group) {
                $this->orderModel->createFood(array_merge($data, $group));
            }
            $this->inventoryModel->applyOrderConsumption($orderId);
            if ($data['order_type'] !== 'takeaway') {
                $this->tableModel->assignOrder((int) $data['table_id'], $orderId);
            }
            $this->orderModel->commit();
            $orderLabel = $data['order_type'] === 'takeaway'
                ? 'takeaway order #' . $orderId
                : 'order #' . $orderId . ' for table #' . (int) ($table['table_number'] ?? $data['table_id']);
            controllersHelper::logActivity($this->db, (int) $data['restaurant_id'], 'orders.create', 'Created ' . $orderLabel . ' with ' . count($orderFoodGroups) . ' food item(s). Total: ' . number_format((float) $data['price'], 2), 'order', $orderId, [
                'table_id' => $data['table_id'] !== null ? (int) $data['table_id'] : null,
                'table_number' => (int) ($table['table_number'] ?? 0),
                'order_type' => $data['order_type'],
                'items_count' => count($orderFoodGroups),
                'total' => (float) $data['price'],
                'snapshot' => $this->orderModel->getParentById($orderId),
            ]);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Order created successfully.',
                'data' => [
                    'session_order_key' => $data['session_order_key'],
                    'order' => $this->permissionData($this->orderModel->getById($orderId), 'orders.get')
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
        $orderType = $this->normalizeOrderType($data);
        $tableId = $orderType === 'takeaway' ? null : (int) $data['table_id'];
        $createdAt = $data['created_at'] ?? date('Y-m-d H:i:s');
        $orderFoods = [];
        $total = 0.0;
        $totalExtraPrice = 0.0;
        $totalProfit = 0.0;

        try {
            $this->orderModel->syncOrderAutoIncrements();
            $this->orderModel->beginTransaction();
            $table = $orderType === 'takeaway'
                ? null
                : $this->tableModel->getById((int) $tableId);
            $sessionOrderKey = $this->generateSessionOrderKey(
                $restaurantId,
                $orderType === 'takeaway' ? 0 : (int) ($table['table_number'] ?? $tableId),
                (int) strtotime((string) $createdAt)
            );

            foreach ($data['items'] as $index => $itemInput) {
                $food = $this->foodModel->getById((int) $itemInput['food_id']);

                if (!$food || (int) $food['restaurant_id'] !== $restaurantId) {
                    throw new RuntimeException("Invalid food at item {$index}.");
                }

                $qty = max(1, (int) ($itemInput['qty'] ?? 1));
                $selectedAddons = $this->normalizeSelectedAddons($itemInput['addons'] ?? [], false);
                $addonRows = $this->orderModel->getAddonsByIds(
                    array_column($selectedAddons, 'id'),
                    (int) $food['id'],
                    $restaurantId
                );
                $orderFoodGroups = $this->buildOrderFoodGroups($food, $qty, $selectedAddons, $addonRows);

                foreach ($orderFoodGroups as $group) {
                    $orderFoods[] = array_merge($group, [
                        'food_id' => (int) $food['id'],
                        'table_id' => $tableId,
                        'order_type' => $orderType,
                        'details' => $this->normalizeChefNote($itemInput['details'] ?? null),
                        'session_order_key' => $sessionOrderKey,
                        'created_at' => $createdAt,
                        'restaurant_id' => $restaurantId
                    ]);

                    $total += (float) $group['price'];
                    $totalExtraPrice += (float) $group['extra_price'];
                    $totalProfit += (float) $group['profit'];
                }
            }

            $orderId = $this->orderModel->create([
                'table_id' => $tableId,
                'order_type' => $orderType,
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
            $this->inventoryModel->applyOrderConsumption($orderId);
            if ($orderType !== 'takeaway') {
                $this->tableModel->assignOrder((int) $tableId, $orderId);
            }
            $this->orderModel->commit();
            $orderLabel = $orderType === 'takeaway'
                ? 'takeaway order #' . $orderId
                : 'order #' . $orderId . ' for table #' . (int) ($table['table_number'] ?? $tableId);
            controllersHelper::logActivity($this->db, $restaurantId, 'orders.create', 'Created ' . $orderLabel . ' with ' . count($orderFoods) . ' food item(s). Total: ' . number_format($total, 2), 'order', $orderId, [
                'table_id' => $tableId,
                'table_number' => (int) ($table['table_number'] ?? 0),
                'order_type' => $orderType,
                'items_count' => count($orderFoods),
                'total' => $total,
                'snapshot' => $this->orderModel->getParentById($orderId),
            ]);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Order created successfully.',
                'data' => [
                    'session_order_key' => $sessionOrderKey,
                    'order_id' => $orderId,
                    'orders' => $this->permissionData($createdOrderFoods, 'orders.get'),
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

    private function validateBulk(array $data): array
    {
        $errors = [];
        $orderType = $this->normalizeOrderType($data);

        foreach (['restaurant_id'] as $field) {
            if (!isset($data[$field])) {
                $errors[$field] = str_replace('_', ' ', ucfirst($field)) . ' is required.';
            } elseif (
                filter_var($data[$field], FILTER_VALIDATE_INT) === false ||
                (int) $data[$field] <= 0
            ) {
                $errors[$field] = str_replace('_', ' ', ucfirst($field)) . ' must be a valid positive integer.';
            }
        }

        if (!in_array($orderType, ['table', 'takeaway'], true)) {
            $errors['order_type'] = 'Invalid order type.';
        }

        if ($orderType !== 'takeaway') {
            if (!isset($data['table_id'])) {
                $errors['table_id'] = 'Table ID is required.';
            } elseif (
                filter_var($data['table_id'], FILTER_VALIDATE_INT) === false ||
                (int) $data['table_id'] <= 0
            ) {
                $errors['table_id'] = 'Table ID must be a valid positive integer.';
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

        if ($orderType === 'takeaway' && !isset($errors['restaurant_id'])) {
            $restaurant = $this->restaurantModel->getById((int) $data['restaurant_id']);
            if (!$restaurant || (int) ($restaurant['takeaway_enabled'] ?? 0) !== 1) {
                $errors['order_type'] = 'Takeaway ordering is disabled for this restaurant.';
            }
        }

        if ($orderType !== 'takeaway' && !isset($errors['table_id'])) {
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

                if (isset($item['details']) && $item['details'] !== null && !is_string($item['details'])) {
                    $errors["items.{$index}.details"] = 'Chef note must be text.';
                } elseif (isset($item['details']) && strlen((string) $item['details']) > 500) {
                    $errors["items.{$index}.details"] = 'Chef note must be 500 characters or less.';
                }
            }
        }

        return $errors;
    }

    private function normalizeChefNote(mixed $value): ?string
    {
        $note = trim((string) ($value ?? ''));

        return $note !== '' ? substr($note, 0, 500) : null;
    }

    private function buildOrderFoodGroups(array $food, int $qty, array $selectedAddons, array $addonRows): array
    {
        $foodPrice = (float) ($food['price'] ?? 0);
        $foodProfit = (float) ($food['profit'] ?? 0);
        $discounts = $this->discountService->activeDiscounts((int) ($food['restaurant_id'] ?? 0));
        $foodDiscount = $this->discountService->foodDiscountAmount($food, $discounts);
        $discountedFoodPrice = max(0, round($foodPrice - $foodDiscount, 3));
        $addonIds = array_values(array_unique(array_map(
            static fn (array $addon): int => (int) $addon['id'],
            $selectedAddons
        )));

        if ($addonIds === []) {
            return array_fill(0, $qty, [
                'qty' => 1,
                'addon_id' => [],
                'extra_price' => 0,
                'price' => $discountedFoodPrice,
                'profit' => max(0, round($foodProfit - $foodDiscount, 3))
            ]);
        }

        $extraPricePerItem = 0.0;
        $extraProfitPerItem = 0.0;
        $addonDiscountPerItem = 0.0;

        foreach ($addonIds as $addonId) {
            if (!isset($addonRows[$addonId])) {
                throw new RuntimeException('Invalid addon for selected food.');
            }

            $addonPrice = (float) ($addonRows[$addonId]['extra_price'] ?? 0);
            $addonDiscount = $this->discountService->addonDiscountAmount($addonRows[$addonId], $food, $discounts);
            $extraPricePerItem += max(0, round($addonPrice - $addonDiscount, 3));
            $extraProfitPerItem += (float) $addonRows[$addonId]['extra_profit'];
            $addonDiscountPerItem += $addonDiscount;
        }

        return array_fill(0, $qty, [
            'qty' => 1,
            'addon_id' => $addonIds,
            'extra_price' => $extraPricePerItem,
            'price' => $discountedFoodPrice + $extraPricePerItem,
            'profit' => max(0, round($foodProfit + $extraProfitPerItem - $foodDiscount - $addonDiscountPerItem, 3))
        ]);
    }

    private function normalizeSelectedAddons(array $addons, bool $unique = true): array
    {
        $normalized = [];
        $seen = [];

        foreach ($addons as $addon) {
            if (!is_array($addon) || !isset($addon['id']) || (int) $addon['id'] <= 0) {
                continue;
            }

            $addonId = (int) $addon['id'];
            if ($unique && isset($seen[$addonId])) {
                continue;
            }
            $seen[$addonId] = true;

            $normalized[] = [
                'id' => $addonId,
                'type' => $addon['type'] ?? 'checkbox',
                'value' => $addon['value'] ?? null
            ];
        }

        return $normalized;
    }

    private function normalizeAddonIds(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        }

        $values = is_array($value) ? $value : [$value];

        return array_values(array_unique(array_filter(
            array_map('intval', $values),
            static fn (int $id): bool => $id > 0
        )));
    }

    private function generateSessionOrderKey(int $restaurantId, int $tableNumber, int $timestamp): string
    {
        $nextOrderNumber = $this->orderModel->getMaxOrderId() + 1;
        $hourMinute = date('Hi', $timestamp > 0 ? $timestamp : time());

        return (string) $restaurantId
            . ($tableNumber > 0 ? (string) $tableNumber : 'TA')
            . (string) $nextOrderNumber
            . $hourMinute;
    }

    private function normalizeOrderType(array $data): string
    {
        $orderType = strtolower(trim((string) ($data['order_type'] ?? 'table')));
        if ($orderType === '') {
            $orderType = 'table';
        }

        return $orderType;
    }

    private function sessionOrderNumber(array $data, ?array $table): int
    {
        if (($data['order_type'] ?? 'table') === 'takeaway') {
            return 0;
        }

        return (int) ($table['table_number'] ?? $data['table_id'] ?? 0);
    }

    public function update(int $id): void
    {
        $order = $this->orderModel->getParentById($id);

        if (!$order) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Order not found.'
            ], 404);

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
        $updatedOrder = $this->orderModel->getParentById($id);
        $statusText = ucfirst(str_replace('_', ' ', (string) ($data['status'] ?? $updatedOrder['status'] ?? 'updated')));
        controllersHelper::logActivity($this->db, (int) $data['restaurant_id'], 'orders.update', 'Set order #' . $id . ' status to ' . $statusText, 'order', $id, [
            'changes' => controllersHelper::changedFields($order, $updatedOrder ?: $data),
        ]);

        $this->jsonResponse([
            'success' => true,
            'message' => 'Order updated successfully.',
            'data' => $this->permissionData($updatedOrder, 'orders.get')
        ]);
    }

    public function updateTakeawayStatus(int $id): void
    {
        $order = $this->orderModel->getParentById($id);

        if (!$order || ($order['order_type'] ?? 'table') !== 'takeaway') {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Takeaway order not found.'
            ], 404);

            return;
        }

        $data = $this->getJsonInput();
        $status = (string) ($data['status'] ?? '');
        $errors = $this->validator->validateStatus($status);

        if (!empty($errors)) {
            $this->jsonResponse([
                'success' => false,
                'errors' => $errors
            ], 422);

            return;
        }

        $this->orderModel->beginTransaction();
        try {
            $this->orderModel->updateStatus($id, $status);
            if (in_array($status, ['finished', 'canceled'], true)) {
                $this->orderModel->updateFoodsStatusByOrder($id, $status);
                $this->orderModel->recalculateOrderTotalsFromFoods($id);
            }
            $this->orderModel->commit();
        } catch (Throwable $e) {
            $this->orderModel->rollBack();
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to update takeaway order.',
                'error' => $e->getMessage()
            ], 500);

            return;
        }

        $updatedOrder = $this->orderModel->getParentById($id);
        controllersHelper::logActivity($this->db, (int) $order['restaurant_id'], 'orders.update', 'Set takeaway order #' . $id . ' status to ' . ucfirst(str_replace('_', ' ', $status)), 'order', $id, [
            'entity_name' => 'Takeaway order ' . $id,
            'changes' => ['status' => ['old' => $order['status'] ?? 'waiting', 'new' => $status]],
        ]);

        $this->jsonResponse([
            'success' => true,
            'message' => 'Takeaway order updated successfully.',
            'data' => $this->permissionData($updatedOrder ?: [], 'orders.get')
        ]);
    }

    public function updateTakeawayPayment(int $id): void
    {
        $order = $this->orderModel->getParentById($id);

        if (!$order || ($order['order_type'] ?? 'table') !== 'takeaway') {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Takeaway order not found.'
            ], 404);

            return;
        }

        if (($order['status'] ?? 'waiting') !== 'finished') {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Only finished takeaway orders can be paid.'
            ], 422);

            return;
        }

        if (($order['payment_status'] ?? 'unpaid') === 'paid') {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Takeaway order is already paid.'
            ], 422);

            return;
        }

        $payment = $this->normalizePaymentData($this->getJsonInput(), (float) ($order['price'] ?? 0));
        if ($payment['errors'] !== []) {
            $this->jsonResponse([
                'success' => false,
                'errors' => $payment['errors']
            ], 422);

            return;
        }

        $this->orderModel->updatePayment((int) $order['order_id'], $payment['data']);
        $invoiceResult = $this->invoiceService->finalizeOrderInvoice((int) $order['order_id']);
        $updatedOrder = $this->orderModel->getParentById($id);

        controllersHelper::logActivity($this->db, (int) $order['restaurant_id'], 'orders.update', 'Collected ' . ucfirst(str_replace('_', ' and ', $payment['data']['payment_method'])) . ' payment for takeaway order #' . $id, 'order', $id, [
            'entity_name' => 'Takeaway order ' . $id,
            'payment_method' => $payment['data']['payment_method'],
            'changes' => ['payment_status' => ['old' => $order['payment_status'] ?? 'unpaid', 'new' => 'paid']],
        ]);

        $this->jsonResponse([
            'success' => true,
            'message' => 'Takeaway payment saved.',
            'data' => [
                'order' => $this->permissionData($updatedOrder ?: [], 'orders.get'),
                'extra_paid' => $payment['extra_paid'],
                'invoice' => $invoiceResult['invoice'] ?? null,
                'invoice_warning' => !($invoiceResult['success'] ?? false) ? ($invoiceResult['message'] ?? 'Invoice was not finalized.') : null
            ]
        ]);
    }

    private function normalizePaymentData(array $data, float $orderTotal): array
    {
        $method = (string) ($data['payment_method'] ?? '');
        $cash = 0.0;
        $credit = 0.0;
        $errors = [];

        if (!in_array($method, ['cash', 'credit', 'cash_credit'], true)) {
            $errors['payment_method'] = 'Payment method must be cash, credit, or cash_credit.';
        }

        if ($method === 'cash') {
            $cash = $orderTotal;
        } elseif ($method === 'credit') {
            $credit = $orderTotal;
        } elseif ($method === 'cash_credit') {
            $cash = isset($data['total_paid_cash']) && is_numeric($data['total_paid_cash'])
                ? (float) $data['total_paid_cash']
                : -1;
            $credit = isset($data['total_paid_credit']) && is_numeric($data['total_paid_credit'])
                ? (float) $data['total_paid_credit']
                : -1;

            if ($cash < 0) {
                $errors['total_paid_cash'] = 'Cash amount must be a valid non-negative number.';
            }

            if ($credit < 0) {
                $errors['total_paid_credit'] = 'Credit amount must be a valid non-negative number.';
            }

            if ($cash >= 0 && $credit >= 0 && ($cash + $credit) < $orderTotal) {
                $errors['payment_total'] = 'Paid total must be higher than or equal to the order total.';
            }
        }

        return [
            'errors' => $errors,
            'data' => [
                'payment_status' => 'paid',
                'payment_method' => $method,
                'total_paid_cash' => $cash,
                'total_paid_credit' => $credit
            ],
            'extra_paid' => max(0, ($cash + $credit) - $orderTotal)
        ];
    }

    public function updateFoodStatus(int $id): void
    {
        $orderFood = $this->orderModel->getOrderFoodById($id);

        if (!$orderFood) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Order food not found.'
            ], 404);

            return;
        }

        $data = $this->getJsonInput();
        $status = (string) ($data['status'] ?? '');
        $rowIds = isset($data['row_ids']) && is_array($data['row_ids'])
            ? array_values(array_unique(array_filter(array_map('intval', $data['row_ids']))))
            : [$id];
        $cancelQty = isset($data['cancel_qty']) ? max(0, (int) $data['cancel_qty']) : 0;
        $errors = $this->validator->validateStatus($status);

        if (!empty($errors)) {
            $this->jsonResponse([
                'success' => false,
                'errors' => $errors
            ], 422);

            return;
        }

        if (!in_array($id, $rowIds, true)) {
            $rowIds[] = $id;
        }

        if ($cancelQty > 0) {
            $rowIds = array_slice($rowIds, 0, $cancelQty);
        }

        $rows = $this->orderModel->getOrderFoodsByIds($rowIds);
        $rowsById = [];
        foreach ($rows as $row) {
            $rowsById[(int) $row['id']] = $row;
        }
        $rowIds = array_values(array_filter($rowIds, static fn (int $rowId): bool => isset($rowsById[$rowId])));

        foreach ($rows as $row) {
            if ((int) $row['order_id'] !== (int) $orderFood['order_id'] || (int) $row['restaurant_id'] !== (int) $orderFood['restaurant_id']) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Food rows must belong to the same order.'
                ], 422);

                return;
            }
        }

        $this->orderModel->beginTransaction();

        try {
            $this->orderModel->updateOrderFoodStatuses($rowIds, $status);
            foreach ($rows as $row) {
                $wasCanceled = ($row['status'] ?? 'waiting') === 'canceled';
                $willBeCanceled = $status === 'canceled';

                if (!$wasCanceled && $willBeCanceled) {
                    $this->inventoryModel->applyOrderFoodConsumption((int) $row['id'], 'return');
                } elseif ($wasCanceled && !$willBeCanceled) {
                    $this->inventoryModel->applyOrderFoodConsumption((int) $row['id'], 'consume');
                }
            }
            $this->orderModel->recalculateOrderTotalsFromFoods((int) $orderFood['order_id']);
            $this->orderModel->commit();
            controllersHelper::logActivity(
                $this->db,
                (int) $orderFood['restaurant_id'],
                'orders.update',
                'Set order #' . (int) $orderFood['order_id'] . ' food item #' . $id . ' status to ' . ucfirst(str_replace('_', ' ', $status)),
                'order_food',
                $id,
                [
                    'order_id' => (int) $orderFood['order_id'],
                    'entity_name' => $orderFood['food_name_en'] ?? $orderFood['food_name_ar'] ?? null,
                    'row_ids' => $rowIds,
                    'changes' => [
                        'status' => [
                            'old' => $orderFood['status'] ?? 'waiting',
                            'new' => $status,
                        ],
                    ],
                ]
            );
        } catch (Throwable $e) {
            $this->orderModel->rollBack();

            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to update food status.',
                'error' => $e->getMessage()
            ], 500);

            return;
        }

        $this->jsonResponse([
            'success' => true,
            'message' => 'Food status updated successfully.',
            'data' => $this->permissionData(
                $this->orderModel->getFoodsByOrderId((int) $orderFood['order_id']),
                'orders.get'
            )
        ]);
    }

    private function validateReferences(array $data, array &$errors): void
    {
        $restaurantId = isset($data['restaurant_id']) ? (int) $data['restaurant_id'] : null;
        $orderType = $this->normalizeOrderType($data);

        if (isset($data['food_id']) && !isset($errors['food_id'])) {
            $food = $this->foodModel->getById((int) $data['food_id']);
            if (!$food) {
                $errors['food_id'] = 'Food does not exist.';
            } elseif ($restaurantId !== null && (int) $food['restaurant_id'] !== $restaurantId) {
                $errors['food_id'] = 'Food does not belong to this restaurant.';
            }
        }

        if (isset($data['addon_id']) && $data['addon_id'] !== null && $data['addon_id'] !== '' && !isset($errors['addon_id']) && isset($data['food_id']) && $restaurantId !== null) {
            $addonIds = $this->normalizeAddonIds($data['addon_id']);
            $addons = $this->orderModel->getAddonsByIds($addonIds, (int) $data['food_id'], $restaurantId);
            if (count($addons) !== count($addonIds)) {
                $errors['addon_id'] = 'Addon does not belong to this food and restaurant.';
            }
        }

        if ($orderType === 'takeaway' && $restaurantId !== null) {
            $restaurant = $this->restaurantModel->getById($restaurantId);
            if (!$restaurant || (int) ($restaurant['takeaway_enabled'] ?? 0) !== 1) {
                $errors['order_type'] = 'Takeaway ordering is disabled for this restaurant.';
            }
        }

        if ($orderType !== 'takeaway' && !isset($errors['table_id'])) {
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

    public function destroy(int $id): void
    {
        $order = $this->orderModel->getParentById($id);

        if (!$order) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Order not found.'
            ], 404);

            return;
        }

        $this->orderModel->delete($id);
        controllersHelper::logActivity($this->db, (int) $order['restaurant_id'], 'orders.delete', 'Deleted order', 'order', $id, [
            'snapshot' => $order,
        ]);

        $this->jsonResponse([
            'success' => true,
            'message' => 'Order deleted successfully.'
        ]);
    }
}
