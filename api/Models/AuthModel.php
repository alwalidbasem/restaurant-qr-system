<?php

class AuthModel
{
    private const TOKEN_BYTES = 32;

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getEmployeeByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare("
            SELECT employees.*, restaurants.name AS restaurant_name, restaurants.main_code
            FROM employees
            INNER JOIN restaurants
                ON restaurants.id = employees.restaurant_id
            WHERE employees.username = :username
            LIMIT 1
        ");

        $stmt->execute([':username' => $username]);

        $employee = $stmt->fetch(PDO::FETCH_ASSOC);

        return $employee ?: null;
    }

    public function getEmployeeByApiKey(string $apiKey): ?array
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

    public function issueApiKey(int $employeeId, bool $remember = false): array
    {
        $apiKey = bin2hex(random_bytes(self::TOKEN_BYTES));
        $expiresAt = date('Y-m-d H:i:s', strtotime($remember ? '+30 days' : '+1 day'));

        $stmt = $this->db->prepare("
            UPDATE employees
            SET
                API_KEY = :api_key,
                API_KEY_EXPIRY_DATE = :expires_at
            WHERE id = :id
        ");

        $stmt->execute([
            ':api_key' => $apiKey,
            ':expires_at' => $expiresAt,
            ':id' => $employeeId
        ]);

        return [
            'api_key' => $apiKey,
            'expires_at' => $expiresAt
        ];
    }

    public function clearApiKey(int $employeeId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE employees
            SET
                API_KEY = NULL,
                API_KEY_EXPIRY_DATE = NULL
            WHERE id = :id
        ");

        return $stmt->execute([':id' => $employeeId]);
    }

    public function updatePassword(int $employeeId, string $passwordHash): bool
    {
        $stmt = $this->db->prepare("
            UPDATE employees
            SET
                password = :password,
                API_KEY = NULL,
                API_KEY_EXPIRY_DATE = NULL
            WHERE id = :id
        ");

        return $stmt->execute([
            ':password' => $passwordHash,
            ':id' => $employeeId
        ]);
    }
}
