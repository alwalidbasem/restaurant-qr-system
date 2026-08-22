<?php

class Inventory
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getAll(): array
    {
        $stmt = $this->db->prepare("
            SELECT inventory.*, restaurants.name AS restaurant_name
            FROM inventory
            INNER JOIN restaurants
                ON restaurants.id = inventory.restaurant_id
            ORDER BY inventory.id ASC
        ");

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT inventory.*, restaurants.name AS restaurant_name
            FROM inventory
            INNER JOIN restaurants
                ON restaurants.id = inventory.restaurant_id
            WHERE inventory.id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO inventory (
                name,
                quantity,
                price,
                profit,
                restaurant_id
            )
            VALUES (
                :name,
                :quantity,
                :price,
                :profit,
                :restaurant_id
            )
        ");

        $stmt->execute([
            ':name' => $data['name'],
            ':quantity' => $data['quantity'] ?? 0,
            ':price' => $data['price'] ?? 0,
            ':profit' => $data['profit'] ?? 0,
            ':restaurant_id' => $data['restaurant_id']
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE inventory
            SET
                name = :name,
                quantity = :quantity,
                price = :price,
                profit = :profit,
                restaurant_id = :restaurant_id
            WHERE id = :id
        ");

        return $stmt->execute([
            ':name' => $data['name'],
            ':quantity' => $data['quantity'],
            ':price' => $data['price'],
            ':profit' => $data['profit'],
            ':restaurant_id' => $data['restaurant_id'],
            ':id' => $id
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM inventory
            WHERE id = :id
        ");

        return $stmt->execute([':id' => $id]);
    }

    public function exists(int $id): bool
    {
        return $this->getById($id) !== null;
    }
}
