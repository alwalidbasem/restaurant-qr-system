<?php

require_once __DIR__ . '/../../Middleware/PermissionsMiddleware.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../../Models/OrdersModel.php';
require_once __DIR__ . '/../../Models/RestaurantModel.php';
require_once __DIR__ . '/../../Models/TablesModel.php';
require_once __DIR__ . '/../../Services/InvoiceService.php';
require_once __DIR__ . '/../../Validators/TablesValidator.php';

class TableController
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

    private Table $tableModel;
    private Order $orderModel;
    private Restaurant $restaurantModel;
    private InvoiceService $invoiceService;
    private TableValidator $validator;
    private PDO $db;

    


    // GET /tables
    


    // GET /tables/{id}
    

    


    // POST /tables
    


    // PUT /tables/{id}
    


    // DELETE /tables/{id}
    


    // PATCH /tables/{id}/status
    private function freeStaleWaitingOrder(array &$table): void
    {
        if (($table['table_status'] ?? '') !== 'waiting_order') {
            return;
        }

        $orderId = filter_var($table['order_id'] ?? null, FILTER_VALIDATE_INT);
        if ($orderId !== false && $orderId !== null && $orderId > 0 && $this->orderModel->exists((int) $orderId)) {
            return;
        }

        $this->tableModel->clearOrder((int) $table['id']);
        $table['table_status'] = 'free';
        $table['order_id'] = null;
    }

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->tableModel = new Table($db);
        $this->orderModel = new Order($db);
        $this->restaurantModel = new Restaurant($db);
        $this->invoiceService = new InvoiceService($db);
        $this->validator = new TableValidator();
    }

    public function index(): array
    {
        $restaurantId = $this->getRestaurantIdFromQuery();
        $tables = $this->tableModel->getAll($restaurantId);
        
        return $this->apiResponse([
            'success' => true,
            'data' => $this->permissionData($tables, 'tables.get')
        ]);
    }

    public function show(int $id): array
    {
        $table = $this->tableModel->getById($id);

        if (!$table) {
            return $this->apiResponse([
                'success' => false,
                'message' => 'Table not found.'
            ], 404);
        }

        $this->freeStaleWaitingOrder($table);

        if ($table['position']) {
            $table['position'] = json_decode($table['position'], true);
        }

        return $this->apiResponse([
            'success' => true,
            'data' => $this->permissionData($table, 'tables.get')
        ]);
    }

    public function showByNumber(int $tableNumber, bool $isJson = true, ?int $restaurantId = null): array
    {
        $table = $this->tableModel->getByNumber($tableNumber, $restaurantId);

        if (!$table) {
            return [
                'success' => false,
                'message' => 'Table not found.'
            ];
        }

        $this->freeStaleWaitingOrder($table);

        if ($table['position']) {
            $table['position'] = json_decode($table['position'], true);
        }

        return [
            'success' => true,
            'data' => $this->permissionData($table, 'tables.get')
        ];
    }

    public function store(): void
    {
        $data = $this->getJsonInput();

        $errors =
            $this->validator->validateCreate($data);

        if (($data['table_status'] ?? 'free') === 'waiting_order') {
            $errors['table_status'] = 'Table status cannot be manually created as waiting order.';
        }

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
                null,
                (int) $data['restaurant_id']
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
            controllersHelper::logActivity($this->db, (int) $data['restaurant_id'], 'tables.create', 'Added new table', 'table', $tableId, [
                'entity_name' => 'Table ' . ($table['table_number'] ?? $tableId),
                'snapshot' => $table,
            ]);


            $this->jsonResponse([
                'success' => true,
                'message' => 'Table created successfully.',
                'data' => $this->permissionData($table, 'tables.get')
            ], 201);

        } catch (PDOException $e) {

            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to create table.'
            ], 500);
        }
    }

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

        $data['table_status'] = $table['table_status'];

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
                $id,
                (int) $data['restaurant_id']
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
        controllersHelper::logActivity($this->db, (int) $data['restaurant_id'], 'tables.update', 'Updated the table layout/details', 'table', $id, [
            'entity_name' => 'Table ' . ($updatedTable['table_number'] ?? $table['table_number'] ?? $id),
            'changes' => controllersHelper::changedFields($table, $updatedTable ?: $data),
        ]);


        $this->jsonResponse([
            'success' => true,
            'message' => 'Table updated successfully.',
            'data' => $this->permissionData($updatedTable, 'tables.get')
        ]);
    }

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


        $targetStatus = (string) $data['table_status'];
        $errors = $this->validator->validateStatus($targetStatus);


        if (!empty($errors)) {

            $this->jsonResponse([
                'success' => false,
                'errors' => $errors
            ], 422);

            return;
        }


        $currentStatus = (string) ($table['table_status'] ?? 'free');
        $orderId = filter_var($table['order_id'] ?? null, FILTER_VALIDATE_INT);
        $order = ($orderId !== false && $orderId !== null && $orderId > 0)
            ? $this->orderModel->getParentById((int) $orderId)
            : null;

        if ($targetStatus === 'waiting_order') {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Table status cannot be changed manually to waiting order.'
            ], 422);

            return;
        }

        if ($currentStatus === 'waiting_order') {
            if (!$order) {
                $this->tableModel->clearOrder($id);
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Stale table order cleared.'
                ]);

                return;
            }

            if ($targetStatus === 'order_done') {
                $this->orderModel->updateStatus((int) $order['order_id'], 'finished');
                $this->tableModel->updateStatus($id, 'order_done');
                controllersHelper::logActivity($this->db, (int) $table['restaurant_id'], 'tables.update', 'Set table #' . ($table['table_number'] ?? $id) . ' status to Order done and set order #' . $order['order_id'] . ' status to Finished', 'table', $id, [
                    'entity_name' => 'Table ' . ($table['table_number'] ?? $id),
                    'order_id' => $order['order_id'],
                    'changes' => ['table_status' => ['old' => $currentStatus, 'new' => 'order_done']],
                ]);

                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Order finished and table marked order done.'
                ]);

                return;
            }

            if ($targetStatus === 'free') {
                $this->orderModel->updateStatus((int) $order['order_id'], 'canceled');
                $this->tableModel->clearOrder($id);
                controllersHelper::logActivity($this->db, (int) $table['restaurant_id'], 'tables.update', 'Canceled order #' . $order['order_id'] . ' and set table #' . ($table['table_number'] ?? $id) . ' status to Free', 'table', $id, [
                    'entity_name' => 'Table ' . ($table['table_number'] ?? $id),
                    'order_id' => $order['order_id'],
                    'changes' => ['table_status' => ['old' => $currentStatus, 'new' => 'free']],
                ]);

                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Order canceled and table freed.'
                ]);

                return;
            }
        }

        if ($currentStatus === 'order_done') {
            if ($targetStatus !== 'free') {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Order done tables can only be changed to free after payment.'
                ], 422);

                return;
            }

            if (!$order) {
                $this->tableModel->clearOrder($id);
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Table freed.'
                ]);

                return;
            }

            $payment = $this->normalizePaymentData($data, (float) ($order['price'] ?? 0));
            if ($payment['errors'] !== []) {
                $this->jsonResponse([
                    'success' => false,
                    'errors' => $payment['errors']
                ], 422);

                return;
            }

            $this->orderModel->updatePayment((int) $order['order_id'], $payment['data']);
            $this->tableModel->clearOrder($id);
            $invoiceResult = $this->invoiceService->finalizeOrderInvoice((int) $order['order_id']);
            controllersHelper::logActivity($this->db, (int) $table['restaurant_id'], 'tables.update', 'Collected ' . ucfirst(str_replace('_', ' and ', $payment['data']['payment_method'])) . ' payment for order #' . $order['order_id'] . ' and set table #' . ($table['table_number'] ?? $id) . ' status to Free', 'table', $id, [
                'entity_name' => 'Table ' . ($table['table_number'] ?? $id),
                'order_id' => $order['order_id'],
                'payment_method' => $payment['data']['payment_method'],
                'changes' => ['table_status' => ['old' => $currentStatus, 'new' => 'free']],
            ]);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Payment saved and table freed.',
                'data' => [
                    'extra_paid' => $payment['extra_paid'],
                    'invoice' => $invoiceResult['invoice'] ?? null,
                    'invoice_warning' => !($invoiceResult['success'] ?? false) ? ($invoiceResult['message'] ?? 'Invoice was not finalized.') : null
                ]
            ]);

            return;
        }

        if ($currentStatus === 'free' && $targetStatus !== 'free') {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Free tables cannot be manually changed to order statuses.'
            ], 422);

            return;
        }

        $this->tableModel->updateStatus($id, 'free');
        controllersHelper::logActivity($this->db, (int) $table['restaurant_id'], 'tables.update', 'Set table #' . ($table['table_number'] ?? $id) . ' status from ' . ucfirst(str_replace('_', ' ', $currentStatus)) . ' to Free', 'table', $id, [
            'entity_name' => 'Table ' . ($table['table_number'] ?? $id),
            'changes' => ['table_status' => ['old' => $currentStatus, 'new' => 'free']],
        ]);


        $this->jsonResponse([
            'success' => true,
            'message' => 'Table status updated successfully.'
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
        controllersHelper::logActivity($this->db, (int) $table['restaurant_id'], 'tables.delete', 'Deleted table', 'table', $id, [
            'entity_name' => 'Table ' . ($table['table_number'] ?? $id),
            'snapshot' => $table,
        ]);


        $this->jsonResponse([
            'success' => true,
            'message' => 'Table deleted successfully.'
        ]);
    }
}
