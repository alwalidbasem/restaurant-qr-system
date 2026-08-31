<?php

class Employee
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getAll(?int $restaurantId = null): array
    {
        $this->ensureBranchAndSalaryColumns();

        $restaurantSql = $restaurantId !== null
            ? " WHERE employees.restaurant_id = :restaurant_id OR employees.branch_id = :restaurant_id"
            : "";

        $stmt = $this->db->prepare("
            SELECT employees.*, restaurants.name AS restaurant_name, branches.name AS branch_name
            FROM employees
            LEFT JOIN restaurants
                ON restaurants.id = employees.restaurant_id
            LEFT JOIN restaurants branches
                ON branches.id = employees.branch_id
            $restaurantSql
            ORDER BY employees.id ASC
        ");

        $params = [];
        if ($restaurantId !== null) {
            $params[':restaurant_id'] = $restaurantId;
        }

        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id): ?array
    {
        $this->ensureBranchAndSalaryColumns();

        $stmt = $this->db->prepare("
            SELECT employees.*, restaurants.name AS restaurant_name, branches.name AS branch_name
            FROM employees
            LEFT JOIN restaurants
                ON restaurants.id = employees.restaurant_id
            LEFT JOIN restaurants branches
                ON branches.id = employees.branch_id
            WHERE employees.id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);

        $employee = $stmt->fetch(PDO::FETCH_ASSOC);

        return $employee ?: null;
    }

    public function getByApiKey(string $apiKey): ?array
    {
        $this->ensureBranchAndSalaryColumns();

        $stmt = $this->db->prepare("
            SELECT employees.*, restaurants.name AS restaurant_name, branches.name AS branch_name
            FROM employees
            LEFT JOIN restaurants
                ON restaurants.id = employees.restaurant_id
            LEFT JOIN restaurants branches
                ON branches.id = employees.branch_id
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
        $this->ensureBranchAndSalaryColumns();

        $stmt = $this->db->prepare("
            INSERT INTO employees (
                name,
                username,
                password,
                pfp,
                description,
                role,
                salary,
                branch_id,
                permissions,
                restaurant_id
            )
            VALUES (
                :name,
                :username,
                :password,
                :pfp,
                :description,
                :role,
                :salary,
                :branch_id,
                :permissions,
                :restaurant_id
            )
        ");

        $stmt->execute([
            ':name' => $data['name'],
            ':username' => $data['username'],
            ':password' => $data['password'],
            ':pfp' => $data['pfp'] ?? null,
            ':description' => $data['description'] ?? null,
            ':role' => $data['role'] ?? 'delivery_manager',
            ':salary' => $data['salary'] ?? 0,
            ':branch_id' => $data['branch_id'] ?? null,
            ':permissions' => $data['permissions'] ?? '',
            ':restaurant_id' => $data['restaurant_id']
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $this->ensureBranchAndSalaryColumns();

        $passwordSql = isset($data['password']) && trim((string) $data['password']) !== ''
            ? "password = :password,"
            : "";

        $stmt = $this->db->prepare("
            UPDATE employees
            SET
                name = :name,
                username = :username,
                $passwordSql
                pfp = :pfp,
                description = :description,
                role = :role,
                salary = :salary,
                branch_id = :branch_id,
                permissions = :permissions,
                restaurant_id = :restaurant_id
            WHERE id = :id
        ");

        $params = [
            ':name' => $data['name'],
            ':username' => $data['username'],
            ':pfp' => $data['pfp'] ?? null,
            ':description' => $data['description'] ?? null,
            ':role' => $data['role'],
            ':salary' => $data['salary'] ?? 0,
            ':branch_id' => $data['branch_id'] ?? null,
            ':permissions' => $data['permissions'] ?? '',
            ':restaurant_id' => $data['restaurant_id'],
            ':id' => $id
        ];

        if (isset($data['password']) && trim((string) $data['password']) !== '') {
            $params[':password'] = $data['password'];
        }

        return $stmt->execute($params);
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

    public function salaryTotal(?int $restaurantId = null): float
    {
        $this->ensureBranchAndSalaryColumns();

        $where = $restaurantId !== null
            ? "WHERE restaurant_id = :restaurant_id OR branch_id = :restaurant_id"
            : "";
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(salary), 0)
            FROM employees
            $where
        ");
        $stmt->execute($restaurantId !== null ? [':restaurant_id' => $restaurantId] : []);

        return (float) $stmt->fetchColumn();
    }

    private function ensureBranchAndSalaryColumns(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }

        $checked = true;
        $columns = $this->db
            ->query("SHOW COLUMNS FROM employees")
            ->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('salary', $columns, true)) {
            $this->db->exec("ALTER TABLE employees ADD COLUMN salary DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER role");
        }

        if (!in_array('branch_id', $columns, true)) {
            $this->db->exec("ALTER TABLE employees ADD COLUMN branch_id INT UNSIGNED NULL AFTER salary");
        }
    }
}
