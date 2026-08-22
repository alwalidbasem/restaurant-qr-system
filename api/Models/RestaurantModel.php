<?php

class Restaurant
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getAll(): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM restaurants
            ORDER BY id ASC
        ");

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM restaurants
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);

        $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);

        return $restaurant ?: null;
    }

    public function getByCode(string $code): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM restaurants
            WHERE main_code = :main_code
            LIMIT 1
        ");

        $stmt->execute([':main_code' => $code]);

        $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);

        return $restaurant ?: null;
    }

    public function create(array $data): int
    {
        if (isset($data['id'])) {
            $stmt = $this->db->prepare("
                INSERT INTO restaurants (
                    id,
                    name,
                    location,
                    active_unitl,
                    manager_number,
                    txt_details,
                    main_code
                )
                VALUES (
                    :id,
                    :name,
                    :location,
                    :active_unitl,
                    :manager_number,
                    :txt_details,
                    :main_code
                )
            ");

            $stmt->execute([
                ':id' => $data['id'],
                ':name' => $data['name'],
                ':location' => $data['location'],
                ':active_unitl' => $data['active_unitl'],
                ':manager_number' => $data['manager_number'],
                ':txt_details' => $data['txt_details'],
                ':main_code' => $data['main_code']
            ]);

            return (int) $data['id'];
        }

        $stmt = $this->db->prepare("
            INSERT INTO restaurants (
                name,
                location,
                active_unitl,
                manager_number,
                txt_details,
                main_code
            )
            VALUES (
                :name,
                :location,
                :active_unitl,
                :manager_number,
                :txt_details,
                :main_code
            )
        ");

        $stmt->execute([
            ':name' => $data['name'],
            ':location' => $data['location'],
            ':active_unitl' => $data['active_unitl'],
            ':manager_number' => $data['manager_number'],
            ':txt_details' => $data['txt_details'],
            ':main_code' => $data['main_code']
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE restaurants
            SET
                name = :name,
                location = :location,
                active_unitl = :active_unitl,
                manager_number = :manager_number,
                txt_details = :txt_details,
                main_code = :main_code
            WHERE id = :id
        ");

        return $stmt->execute([
            ':name' => $data['name'],
            ':location' => $data['location'],
            ':active_unitl' => $data['active_unitl'],
            ':manager_number' => $data['manager_number'],
            ':txt_details' => $data['txt_details'],
            ':main_code' => $data['main_code'],
            ':id' => $id
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM restaurants
            WHERE id = :id
        ");

        return $stmt->execute([':id' => $id]);
    }

    public function exists(int $id): bool
    {
        return $this->getById($id) !== null;
    }
}
