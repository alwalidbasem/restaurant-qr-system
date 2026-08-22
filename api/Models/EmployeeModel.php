<?php

class Employee
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getAll(): array
    {
        $stmt = $this->db->prepare("
            SELECT employees.*, restaurants.name AS restaurant_name
            FROM employees
            INNER JOIN restaurants
                ON restaurants.id = employees.restaurant_id
            ORDER BY employees.id ASC
        ");

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT employees.*, restaurants.name AS restaurant_name
            FROM employees
            INNER JOIN restaurants
                ON restaurants.id = employees.restaurant_id
            WHERE employees.id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);

        $employee = $stmt->fetch(PDO::FETCH_ASSOC);

        return $employee ?: null;
    }

    public function getByApiKey(string $apiKey): ?array
    {
        $stmt = $this->db->prepare("
            SELECT employees.*, restaurants.name AS restaurant_name
            FROM employees
            INNER JOIN restaurants
                ON restaurants.id = employees.restaurant_id
            WHERE employees.API_KEY = :api_key
                AND (
                    employees.API_KEY_EXPIRY_DATE IS NULL
                    OR employees.API_KEY_EXPIRY_DATE >= NOW()
                )
            LIMIT 1
        ");

        $stmt->execute([':api_key' => $apiKey]);

        $employee = $stmt->fetch(PDO::FETCH_ASSOC);

        return $employee ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO employees (
                name,
                pfp,
                description,
                role,
                restaurant_id
            )
            VALUES (
                :name,
                :pfp,
                :description,
                :role,
                :restaurant_id
            )
        ");

        $stmt->execute([
            ':name' => $data['name'],
            ':pfp' => $data['pfp'],
            ':description' => $data['description'],
            ':role' => $data['role'] ?? 'delivery_manager',
            ':restaurant_id' => $data['restaurant_id']
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE employees
            SET
                name = :name,
                pfp = :pfp,
                description = :description,
                role = :role,
                restaurant_id = :restaurant_id
            WHERE id = :id
        ");

        return $stmt->execute([
            ':name' => $data['name'],
            ':pfp' => $data['pfp'],
            ':description' => $data['description'],
            ':role' => $data['role'],
            ':restaurant_id' => $data['restaurant_id'],
            ':id' => $id
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM employees
            WHERE id = :id
        ");

        return $stmt->execute([':id' => $id]);
    }

    public function exists(int $id): bool
    {
        return $this->getById($id) !== null;
    }
}
