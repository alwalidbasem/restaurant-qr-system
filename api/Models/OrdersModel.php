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
            $where[] = "orders.restaurant_id = :restaurant_id";
            $params[':restaurant_id'] = $restaurantId;
        }

        if ($sessionOrderKey !== null) {
            $where[] = "orders.session_order_key = :session_order_key";
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
            $where[] = "orders.order_id IN (" . implode(',', $placeholders) . ")";
        }

        $whereSql = $where !== []
            ? " WHERE " . implode(' AND ', $where)
            : "";

        $stmt = $this->db->prepare("
            SELECT
                orders.order_id,
                orders.table_id,
                orders.status,
                orders.session_order_key,
                orders.created_at,
                orders.restaurant_id,
                orders.extra_price AS order_extra_price,
                orders.price AS order_price,
                orders.profit AS order_profit,
                orders.details AS order_details,
                order_foods.food_id,
                order_foods.extra_price,
                order_foods.price,
                order_foods.profit,
                order_foods.details,
                order_foods.created_at AS food_created_at,
                foods.name_en AS food_name_en,
                foods.name_ar AS food_name_ar,
                tables.table_number,
                restaurants.name AS restaurant_name
            FROM orders
            INNER JOIN order_foods
                ON order_foods.order_id = orders.order_id
            INNER JOIN menu_foods foods
                ON foods.id = order_foods.food_id
            INNER JOIN tables
                ON tables.id = orders.table_id
            INNER JOIN restaurants
                ON restaurants.id = orders.restaurant_id
            $whereSql
            ORDER BY orders.order_id DESC, order_foods.created_at ASC, order_foods.food_id ASC
        ");

        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
            INNER JOIN tables
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

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO orders (
                table_id,
                status,
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
                :status,
                :extra_price,
                :price,
                :profit,
                :details,
                :session_order_key,
                :created_at,
                :restaurant_id
            )
        ");

        $stmt->execute([
            ':table_id' => $data['table_id'],
            ':status' => $data['status'] ?? 'waiting',
            ':extra_price' => $data['extra_price'] ?? 0,
            ':price' => $data['price'] ?? 0,
            ':profit' => $data['profit'] ?? 0,
            ':details' => $data['details'] ?? null,
            ':session_order_key' => $data['session_order_key'],
            ':created_at' => $data['created_at'] ?? date('Y-m-d H:i:s'),
            ':restaurant_id' => $data['restaurant_id']
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function createFood(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO order_foods (
                order_id,
                food_id,
                table_id,
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
                :table_id,
                :price,
                :extra_price,
                :profit,
                :details,
                :session_order_key,
                :created_at,
                :restaurant_id
            )
        ");

        $stmt->execute([
            ':order_id' => $data['order_id'],
            ':food_id' => $data['food_id'],
            ':table_id' => $data['table_id'],
            ':price' => $data['price'] ?? 0,
            ':extra_price' => $data['extra_price'] ?? 0,
            ':profit' => $data['profit'] ?? 0,
            ':details' => $data['details'] ?? null,
            ':session_order_key' => $data['session_order_key'],
            ':created_at' => $data['created_at'] ?? date('Y-m-d H:i:s'),
            ':restaurant_id' => $data['restaurant_id']
        ]);

        return (int) $this->db->lastInsertId();
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
            SELECT *
            FROM food_addons
            WHERE id IN ($placeholders)
                AND food_id = ?
                AND restaurant_id = ?
        ");

        $stmt->execute([...$ids, $foodId, $restaurantId]);

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

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE orders
            SET
                table_id = :table_id,
                status = :status,
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
            ':table_id' => $data['table_id'],
            ':status' => $data['status'],
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
}
