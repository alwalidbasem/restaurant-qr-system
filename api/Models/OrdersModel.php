<?php

class Order
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getAll(?int $restaurantId = null, ?string $sessionOrderKey = null, array $orderIds = []): array
    {
        $where = [];
        $params = [];

        if ($restaurantId !== null) {
            $where[] = "order_foods.restaurant_id = :restaurant_id";
            $params[':restaurant_id'] = $restaurantId;
        }

        if ($sessionOrderKey !== null) {
            $where[] = "order_foods.session_order_key = :session_order_key";
            $params[':session_order_key'] = $sessionOrderKey;
        }

        $orderIds = array_values(array_unique(array_filter(
            array_map('intval', $orderIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($orderIds !== []) {
            $placeholders = [];
            foreach ($orderIds as $index => $orderId) {
                $placeholder = ':order_id_' . $index;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $orderId;
            }
            $where[] = "order_foods.order_id IN (" . implode(',', $placeholders) . ")";
        }

        $whereSql = $where !== []
            ? " WHERE " . implode(' AND ', $where)
            : "";

        $stmt = $this->db->prepare("
            SELECT
                orders.order_id,
                orders.table_id,
                orders.order_type,
                orders.status,
                orders.payment_status,
                orders.payment_method,
                orders.total_paid_cash,
                orders.total_paid_credit,
                orders.session_order_key,
                orders.created_at,
                orders.restaurant_id,
                orders.extra_price AS order_extra_price,
                orders.price AS order_price,
                orders.profit AS order_profit,
                orders.details AS order_details,
                order_foods.id AS order_food_id,
                order_foods.id AS order_food_row_id,
                order_foods.food_id,
                order_foods.qty,
                order_foods.addon_id,
                order_foods.status AS food_status,
                order_foods.extra_price,
                order_foods.price,
                order_foods.profit,
                order_foods.details,
                order_foods.created_at AS food_created_at,
                foods.name_en AS food_name_en,
                foods.name_ar AS food_name_ar,
                foods.description_en AS food_description_en,
                foods.description_ar AS food_description_ar,
                foods.image_url,
                foods.price AS food_price,
                foods.tax_category,
                foods.tax_rate,
                foods.special_tax_amount,
                foods.tax_exempt,
                foods.category_id,
                categories.name_en AS category_name_en,
                categories.name_ar AS category_name_ar,
                tables.table_number,
                restaurants.name AS restaurant_name
            FROM orders
            INNER JOIN order_foods
                ON order_foods.order_id = orders.order_id
            INNER JOIN menu_foods foods
                ON foods.id = order_foods.food_id
            INNER JOIN menu_categories categories
                ON categories.id = foods.category_id
            LEFT JOIN tables
                ON tables.id = orders.table_id
            INNER JOIN restaurants
                ON restaurants.id = orders.restaurant_id
            $whereSql
            ORDER BY orders.order_id DESC, order_foods.created_at ASC, order_foods.food_id ASC
        ");

        $stmt->execute($params);

        return $this->attachOrderAddons($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getById(int $id): ?array
    {
        $rows = $this->getAll(null, null, [$id]);

        return $rows[0] ?? null;
    }

    public function getParentById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                orders.*,
                tables.table_number,
                restaurants.name AS restaurant_name
            FROM orders
            LEFT JOIN tables
                ON tables.id = orders.table_id
            INNER JOIN restaurants
                ON restaurants.id = orders.restaurant_id
            WHERE orders.order_id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);

        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        return $order ?: null;
    }

    public function getFoodsByOrderId(int $orderId): array
    {
        return $this->getAll(null, null, [$orderId]);
    }

    private function expandOrderFoodRows(array $rows): array
    {
        $expanded = [];

        foreach ($rows as $row) {
            $qty = max(1, (int) ($row['qty'] ?? 1));
            $unitRow = $row;
            $unitRow['qty'] = 1;

            foreach (['extra_price', 'price', 'profit'] as $field) {
                if (array_key_exists($field, $unitRow)) {
                    $unitRow[$field] = number_format((float) $unitRow[$field] / $qty, 2, '.', '');
                }
            }

            for ($index = 0; $index < $qty; $index++) {
                $unitRow['order_food_id'] = (string) ($row['order_food_id'] ?? '') . '-' . ($index + 1);
                $expanded[] = $unitRow;
            }
        }

        return $expanded;
    }

    private function attachOrderAddons(array $rows): array
    {
        $addonIds = [];

        foreach ($rows as &$row) {
            $row['addon_id'] = $this->decodeAddonIds($row['addon_id'] ?? null);
            foreach ($row['addon_id'] as $addonId) {
                $addonIds[$addonId] = true;
            }
        }
        unset($row);

        $addons = $this->getAddonsMap(array_keys($addonIds));

        foreach ($rows as &$row) {
            $row['addons'] = [];
            foreach ($row['addon_id'] as $addonId) {
                if (!isset($addons[$addonId])) {
                    continue;
                }

                $addon = $addons[$addonId];
                $row['addons'][] = [
                    'id' => (int) $addon['id'],
                    'name_ar' => $addon['name_ar'],
                    'name_en' => $addon['name_en'],
                    'food_id' => (int) $addon['food_id'],
                    'extra_price' => $addon['extra_price'],
                    'extra_profit' => $addon['extra_profit'],
                    'restaurant_id' => (int) $addon['restaurant_id']
                ];
            }

            $firstAddon = $row['addons'][0] ?? null;
            $row['addon_name_en'] = $firstAddon['name_en'] ?? null;
            $row['addon_name_ar'] = $firstAddon['name_ar'] ?? null;
            $row['addon_extra_price'] = $firstAddon['extra_price'] ?? null;
            $row['addon_extra_profit'] = $firstAddon['extra_profit'] ?? null;
        }
        unset($row);

        return $rows;
    }

    private function decodeAddonIds(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $decoded = is_string($value) ? json_decode($value, true) : $value;
        if (is_int($decoded)) {
            $decoded = [$decoded];
        }

        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map('intval', $decoded),
            static fn (int $id): bool => $id > 0
        )));
    }

    private function getAddonsMap(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn (int $id): bool => $id > 0
        )));

        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("
            SELECT *
            FROM food_addons
            WHERE id IN ($placeholders)
        ");
        $stmt->execute($ids);

        $addons = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $addon) {
            $addons[(int) $addon['id']] = $addon;
        }

        return $addons;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO orders (
                table_id,
                order_type,
                status,
                payment_status,
                payment_method,
                total_paid_cash,
                total_paid_credit,
                extra_price,
                price,
                profit,
                details,
                session_order_key,
                created_at,
                restaurant_id
            )
            VALUES (
                :table_id,
                :order_type,
                :status,
                :payment_status,
                :payment_method,
                :total_paid_cash,
                :total_paid_credit,
                :extra_price,
                :price,
                :profit,
                :details,
                :session_order_key,
                :created_at,
                :restaurant_id
            )
        ");

        $params = [
            ':table_id' => $data['table_id'] ?? null,
            ':order_type' => $data['order_type'] ?? 'table',
            ':status' => $data['status'] ?? 'waiting',
            ':payment_status' => $data['payment_status'] ?? 'unpaid',
            ':payment_method' => $data['payment_method'] ?? null,
            ':total_paid_cash' => $data['total_paid_cash'] ?? 0,
            ':total_paid_credit' => $data['total_paid_credit'] ?? 0,
            ':extra_price' => $data['extra_price'] ?? 0,
            ':price' => $data['price'] ?? 0,
            ':profit' => $data['profit'] ?? 0,
            ':details' => $data['details'] ?? null,
            ':session_order_key' => $data['session_order_key'],
            ':created_at' => $data['created_at'] ?? date('Y-m-d H:i:s'),
            ':restaurant_id' => $data['restaurant_id']
        ];

        try {
            $stmt->execute($params);
        } catch (PDOException $e) {
            if (!$this->isDuplicatePrimaryKey($e)) {
                throw $e;
            }

            if (!$this->syncAutoIncrementIfAvailable('orders')) {
                throw $e;
            }
            $stmt->execute($params);
        }

        return (int) $this->db->lastInsertId();
    }

    public function createFood(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO order_foods (
                order_id,
                food_id,
                qty,
                addon_id,
                status,
                table_id,
                order_type,
                price,
                extra_price,
                profit,
                details,
                session_order_key,
                created_at,
                restaurant_id
            )
            VALUES (
                :order_id,
                :food_id,
                :qty,
                :addon_id,
                :status,
                :table_id,
                :order_type,
                :price,
                :extra_price,
                :profit,
                :details,
                :session_order_key,
                :created_at,
                :restaurant_id
            )
        ");

        $params = [
            ':order_id' => $data['order_id'],
            ':food_id' => $data['food_id'],
            ':qty' => $data['qty'] ?? 1,
            ':addon_id' => $this->encodeAddonIds($data['addon_id'] ?? null),
            ':status' => $data['food_status'] ?? 'waiting',
            ':table_id' => $data['table_id'] ?? null,
            ':order_type' => $data['order_type'] ?? 'table',
            ':price' => $data['price'] ?? 0,
            ':extra_price' => $data['extra_price'] ?? 0,
            ':profit' => $data['profit'] ?? 0,
            ':details' => $data['details'] ?? null,
            ':session_order_key' => $data['session_order_key'],
            ':created_at' => $data['created_at'] ?? date('Y-m-d H:i:s'),
            ':restaurant_id' => $data['restaurant_id']
        ];

        try {
            $stmt->execute($params);
        } catch (PDOException $e) {
            if (!$this->isDuplicatePrimaryKey($e)) {
                throw $e;
            }

            if (!$this->syncAutoIncrementIfAvailable('order_foods')) {
                throw $e;
            }
            $stmt->execute($params);
        }

        return (int) $this->db->lastInsertId();
    }

    private function encodeAddonIds(mixed $value): ?string
    {
        $ids = $this->decodeAddonIds($value);

        return $ids !== [] ? json_encode($ids) : null;
    }

    public function getAddonsByIds(array $ids, int $foodId, int $restaurantId): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn (int $id): bool => $id > 0
        )));

        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("
            SELECT addons.*
            FROM food_addons addons
            INNER JOIN menu_foods foods
                ON foods.id = ?
            WHERE addons.id IN ($placeholders)
                AND addons.restaurant_id = ?
                AND (
                    addons.food_id = foods.id
                    OR addons.category_id = foods.category_id
                )
        ");

        $stmt->execute(array_merge([$foodId], $ids, [$restaurantId]));

        $addons = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $addon) {
            $addons[(int) $addon['id']] = $addon;
        }

        return $addons;
    }

    public function getMaxOrderId(): int
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(MAX(order_id), 0)
            FROM orders
        ");

        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function syncOrderAutoIncrements(): void
    {
        $this->syncAutoIncrementIfAvailable('orders');
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE orders
            SET
                table_id = :table_id,
                order_type = :order_type,
                status = :status,
                payment_status = :payment_status,
                payment_method = :payment_method,
                total_paid_cash = :total_paid_cash,
                total_paid_credit = :total_paid_credit,
                extra_price = :extra_price,
                price = :price,
                profit = :profit,
                details = :details,
                session_order_key = :session_order_key,
                created_at = :created_at,
                restaurant_id = :restaurant_id
            WHERE order_id = :id
        ");

        return $stmt->execute([
            ':table_id' => $data['table_id'] ?? null,
            ':order_type' => $data['order_type'] ?? 'table',
            ':status' => $data['status'],
            ':payment_status' => $data['payment_status'] ?? 'unpaid',
            ':payment_method' => $data['payment_method'] ?? null,
            ':total_paid_cash' => $data['total_paid_cash'] ?? 0,
            ':total_paid_credit' => $data['total_paid_credit'] ?? 0,
            ':extra_price' => $data['extra_price'],
            ':price' => $data['price'],
            ':profit' => $data['profit'],
            ':details' => $data['details'] ?? null,
            ':session_order_key' => $data['session_order_key'],
            ':created_at' => $data['created_at'],
            ':restaurant_id' => $data['restaurant_id'],
            ':id' => $id
        ]);
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare("
            UPDATE orders
            SET status = :status
            WHERE order_id = :id
        ");

        return $stmt->execute([
            ':status' => $status,
            ':id' => $id
        ]);
    }

    public function updateFoodsStatusByOrder(int $orderId, string $status): bool
    {
        $stmt = $this->db->prepare("
            UPDATE order_foods
            SET status = :status
            WHERE order_id = :order_id
        ");

        return $stmt->execute([
            ':status' => $status,
            ':order_id' => $orderId
        ]);
    }

    public function updatePayment(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE orders
            SET
                payment_status = :payment_status,
                payment_method = :payment_method,
                total_paid_cash = :total_paid_cash,
                total_paid_credit = :total_paid_credit
            WHERE order_id = :id
        ");

        return $stmt->execute([
            ':payment_status' => $data['payment_status'],
            ':payment_method' => $data['payment_method'],
            ':total_paid_cash' => $data['total_paid_cash'],
            ':total_paid_credit' => $data['total_paid_credit'],
            ':id' => $id
        ]);
    }

    public function getOrderFoodById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                order_foods.*,
                orders.status AS order_status,
                foods.name_en AS food_name_en,
                foods.name_ar AS food_name_ar
            FROM order_foods
            INNER JOIN orders
                ON orders.order_id = order_foods.order_id
            INNER JOIN menu_foods foods
                ON foods.id = order_foods.food_id
            WHERE order_foods.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function getOrderFoodsByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn (int $id): bool => $id > 0
        )));

        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("
            SELECT *
            FROM order_foods
            WHERE id IN ($placeholders)
        ");
        $stmt->execute($ids);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateOrderFoodStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare("
            UPDATE order_foods
            SET status = :status
            WHERE id = :id
        ");

        return $stmt->execute([
            ':status' => $status,
            ':id' => $id
        ]);
    }

    public function updateOrderFoodStatuses(array $ids, string $status): bool
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn (int $id): bool => $id > 0
        )));

        if ($ids === []) {
            return true;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("
            UPDATE order_foods
            SET status = ?
            WHERE id IN ($placeholders)
        ");

        return $stmt->execute(array_merge([$status], $ids));
    }

    public function recalculateOrderTotalsFromFoods(int $orderId): bool
    {
        $stmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(price), 0) AS price,
                COALESCE(SUM(extra_price), 0) AS extra_price,
                COALESCE(SUM(profit), 0) AS profit
            FROM order_foods
            WHERE order_id = :order_id
                AND status <> 'canceled'
        ");
        $stmt->execute([':order_id' => $orderId]);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $update = $this->db->prepare("
            UPDATE orders
            SET
                price = :price,
                extra_price = :extra_price,
                profit = :profit
            WHERE order_id = :order_id
        ");

        return $update->execute([
            ':price' => $totals['price'] ?? 0,
            ':extra_price' => $totals['extra_price'] ?? 0,
            ':profit' => $totals['profit'] ?? 0,
            ':order_id' => $orderId
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM orders
            WHERE order_id = :id
        ");

        return $stmt->execute([':id' => $id]);
    }

    public function exists(int $id): bool
    {
        return $this->getParentById($id) !== null;
    }

    public function beginTransaction(): bool
    {
        return !$this->db->inTransaction()
            ? $this->db->beginTransaction()
            : true;
    }

    public function commit(): bool
    {
        return $this->db->inTransaction()
            ? $this->db->commit()
            : true;
    }

    public function rollBack(): bool
    {
        return $this->db->inTransaction()
            ? $this->db->rollBack()
            : true;
    }

    private function isDuplicatePrimaryKey(PDOException $e): bool
    {
        return (string) ($e->errorInfo[0] ?? '') === '23000'
            && (int) ($e->errorInfo[1] ?? 0) === 1062
            && stripos($e->getMessage(), "key 'PRIMARY'") !== false;
    }

    private function syncAutoIncrementIfAvailable(string $table): bool
    {
        $allowed = ['orders', 'order_foods'];
        if (!in_array($table, $allowed, true)) {
            throw new InvalidArgumentException('Invalid auto-increment target.');
        }

        $primaryKey = $this->getAutoIncrementColumn($table);
        if ($primaryKey === null) {
            return false;
        }

        $nextId = (int) $this->db
            ->query("SELECT COALESCE(MAX(`{$primaryKey}`), 0) + 1 FROM `{$table}`")
            ->fetchColumn();

        $this->db->exec("ALTER TABLE `{$table}` AUTO_INCREMENT = {$nextId}");

        return true;
    }

    private function getAutoIncrementColumn(string $table): ?string
    {
        $columns = $this->db
            ->query("SHOW COLUMNS FROM `{$table}`")
            ->fetchAll(PDO::FETCH_ASSOC);

        foreach ($columns as $column) {
            if (stripos((string) ($column['Extra'] ?? ''), 'auto_increment') !== false) {
                return (string) $column['Field'];
            }
        }

        return null;
    }
}
