<?php

class Table
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }


    // Get all tables
    public function getAll(?int $restaurantId = null): array
    {
        $restaurantSql = $restaurantId !== null
            ? " WHERE restaurant_id = :restaurant_id"
            : "";

        $stmt = $this->db->prepare("
            SELECT *
            FROM tables
            $restaurantSql
            ORDER BY table_floor ASC, table_number ASC
        ");

        $params = [];
        if ($restaurantId !== null) {
            $params[':restaurant_id'] = $restaurantId;
        }

        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    // Get one table by ID
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM tables
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $id
        ]);

        $table = $stmt->fetch(PDO::FETCH_ASSOC);

        return $table ?: null;
    }


    // Get one table by table number
    public function getByNumber(int $tableNumber, ?int $restaurantId = null): ?array
    {
        $restaurantSql = $restaurantId !== null
            ? " AND restaurant_id = :restaurant_id"
            : "";

        $stmt = $this->db->prepare("
            SELECT *
            FROM tables
            WHERE table_number = :table_number
            $restaurantSql
            LIMIT 1
        ");

        $params = [
            ':table_number' => $tableNumber
        ];

        if ($restaurantId !== null) {
            $params[':restaurant_id'] = $restaurantId;
        }

        $stmt->execute($params);

        $table = $stmt->fetch(PDO::FETCH_ASSOC);

        return $table ?: null;
    }


    // Create table
    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO tables (
                table_number,
                table_status,
                table_floor,
                position,
                order_id,
                restaurant_id
            )
            VALUES (
                :table_number,
                :table_status,
                :table_floor,
                :position,
                :order_id,
                :restaurant_id
            )
        ");

        $stmt->execute([
            ':table_number' => $data['table_number'],
            ':table_status' => $data['table_status'] ?? 'free',
            ':table_floor'  => $data['table_floor'] ?? 1,
            ':position'     => isset($data['position'])
                ? json_encode($data['position'])
                : null,
            ':order_id'     => $data['order_id'] ?? null,
            ':restaurant_id' => $data['restaurant_id']
        ]);

        return (int) $this->db->lastInsertId();
    }


    // Update table
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE tables

            SET
                table_number = :table_number,
                table_status = :table_status,
                table_floor = :table_floor,
                position = :position,
                order_id = :order_id,
                restaurant_id = :restaurant_id

            WHERE id = :id
        ");

        return $stmt->execute([
            ':table_number' => $data['table_number'],
            ':table_status' => $data['table_status'],
            ':table_floor'  => $data['table_floor'],
            ':position'     => isset($data['position'])
                ? json_encode($data['position'])
                : null,
            ':order_id'     => $data['order_id'] ?? null,
            ':restaurant_id' => $data['restaurant_id'],
            ':id'           => $id
        ]);
    }


    // Delete table
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM tables
            WHERE id = :id
        ");

        return $stmt->execute([
            ':id' => $id
        ]);
    }


    public function exists(int $id): bool
    {
        return $this->getById($id) !== null;
    }


    // Check if table number already exists
    public function tableNumberExists(
        int $tableNumber,
        ?int $ignoreId = null
    ): bool {

        $sql = "
            SELECT id
            FROM tables
            WHERE table_number = :table_number
        ";

        $params = [
            ':table_number' => $tableNumber
        ];

        if ($ignoreId !== null) {
            $sql .= " AND id != :ignore_id";

            $params[':ignore_id'] = $ignoreId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);

        $stmt->execute($params);

        return (bool) $stmt->fetch();
    }


    // Change only table status
    public function updateStatus(
        int $id,
        string $status
    ): bool {

        $stmt = $this->db->prepare("
            UPDATE tables

            SET table_status = :status

            WHERE id = :id
        ");

        return $stmt->execute([
            ':status' => $status,
            ':id'     => $id
        ]);
    }


    // Assign an order to a table
    public function assignOrder(
        int $tableId,
        int $orderId
    ): bool {

        $stmt = $this->db->prepare("
            UPDATE tables

            SET
                order_id = :order_id,
                table_status = 'waiting_order'

            WHERE id = :table_id
        ");

        return $stmt->execute([
            ':order_id' => $orderId,
            ':table_id' => $tableId
        ]);
    }


    // Remove current order from table
    public function clearOrder(int $tableId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE tables

            SET
                order_id = NULL,
                table_status = 'free'

            WHERE id = :table_id
        ");

        return $stmt->execute([
            ':table_id' => $tableId
        ]);
    }
}
