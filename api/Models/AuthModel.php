<?php

class AuthModel
{
    private const TOKEN_BYTES = 32;

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getEmployeeByUsername(string $username, ?string $restaurantCode = null): ?array
    {
        $this->ensureStaffTable();
        $codeSql = $restaurantCode !== null && trim($restaurantCode) !== ''
            ? "AND (restaurants.main_code = :restaurant_code OR branches.main_code = :restaurant_code)"
            : "";

        $stmt = $this->db->prepare("
            SELECT
                staff.*,
                restaurants.name AS restaurant_name,
                restaurants.main_code,
                branches.main_code AS branch_code
            FROM staff
            INNER JOIN restaurants
                ON restaurants.id = staff.restaurant_id
            LEFT JOIN restaurants branches
                ON branches.id = staff.branch_id
            WHERE staff.username = :username
                $codeSql
            ORDER BY
                CASE WHEN branches.main_code = :sort_restaurant_code THEN 0 ELSE 1 END,
                staff.id ASC
            LIMIT 1
        ");

        $params = [
            ':username' => $username,
            ':sort_restaurant_code' => $restaurantCode ?? '',
        ];
        if ($restaurantCode !== null && trim($restaurantCode) !== '') {
            $params[':restaurant_code'] = trim($restaurantCode);
        }

        $stmt->execute($params);

        $employee = $stmt->fetch(PDO::FETCH_ASSOC);

        return $employee ?: null;
    }

    public function getEmployeeByApiKey(string $apiKey): ?array
    {
        $this->ensureStaffTable();
        $stmt = $this->db->prepare("
            SELECT staff.*, restaurants.name AS restaurant_name
            FROM staff
            INNER JOIN restaurants
                ON restaurants.id = staff.restaurant_id
            WHERE staff.API_KEY = :api_key
                AND (
                    staff.API_KEY_EXPIRY_DATE IS NULL
                    OR staff.API_KEY_EXPIRY_DATE >= NOW()
                )
            LIMIT 1
        ");

        $stmt->execute([':api_key' => $apiKey]);

        $employee = $stmt->fetch(PDO::FETCH_ASSOC);

        return $employee ?: null;
    }

    public function issueApiKey(int $employeeId): array
    {
        $this->ensureStaffTable();
        $apiKey = bin2hex(random_bytes(self::TOKEN_BYTES));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+8 hours'));

        $stmt = $this->db->prepare("
            UPDATE staff
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
        $this->ensureStaffTable();
        $stmt = $this->db->prepare("
            UPDATE staff
            SET
                API_KEY = NULL,
                API_KEY_EXPIRY_DATE = NULL
            WHERE id = :id
        ");

        return $stmt->execute([':id' => $employeeId]);
    }

    public function updatePassword(int $employeeId, string $passwordHash): bool
    {
        $this->ensureStaffTable();
        $stmt = $this->db->prepare("
            UPDATE staff
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

    private function ensureStaffTable(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }

        $checked = true;

        try {
            $stmt = $this->db->query("SHOW TABLES LIKE 'staff'");
            if ($stmt === false || $stmt->fetchColumn() === false) {
                $legacy = $this->db->query("SHOW TABLES LIKE 'employees'");
                if ($legacy !== false && $legacy->fetchColumn() !== false) {
                    $this->db->exec("RENAME TABLE employees TO staff");
                }
            }

            $this->ensureStaffColumns();
        } catch (Throwable $e) {
        }
    }

    private function ensureStaffColumns(): void
    {
        $columns = $this->db
            ->query("SHOW COLUMNS FROM staff")
            ->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('details', $columns, true)) {
            $this->db->exec("ALTER TABLE staff ADD COLUMN details TEXT NULL AFTER pfp");
            if (in_array('description', $columns, true)) {
                $this->db->exec("UPDATE staff SET details = description WHERE details IS NULL");
            }
            $columns[] = 'details';
        }

        if (!in_array('hidden_details', $columns, true)) {
            $this->db->exec("ALTER TABLE staff ADD COLUMN hidden_details TEXT NULL AFTER details");
            $columns[] = 'hidden_details';
        }

        if (!in_array('salary', $columns, true)) {
            $this->db->exec("ALTER TABLE staff ADD COLUMN salary DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER hidden_details");
            $columns[] = 'salary';
        }

        if (!in_array('branch_id', $columns, true)) {
            $this->db->exec("ALTER TABLE staff ADD COLUMN branch_id INT UNSIGNED NULL AFTER salary");
            $columns[] = 'branch_id';
        }

        if (!in_array('is_superadmin', $columns, true)) {
            $this->db->exec("ALTER TABLE staff ADD COLUMN is_superadmin TINYINT(1) NOT NULL DEFAULT 0 AFTER branch_id");
            $columns[] = 'is_superadmin';
        }

        if (!in_array('is_owner', $columns, true)) {
            $this->db->exec("ALTER TABLE staff ADD COLUMN is_owner TINYINT(1) NOT NULL DEFAULT 0 AFTER is_superadmin");
            $columns[] = 'is_owner';
        }

        if (!in_array('is_manager', $columns, true)) {
            $this->db->exec("ALTER TABLE staff ADD COLUMN is_manager TINYINT(1) NOT NULL DEFAULT 0 AFTER is_owner");
            $columns[] = 'is_manager';
        }

        if (!in_array('is_employee', $columns, true)) {
            $this->db->exec("ALTER TABLE staff ADD COLUMN is_employee TINYINT(1) NOT NULL DEFAULT 1 AFTER is_manager");
            $columns[] = 'is_employee';
        }

        if (!in_array('allowed_branches', $columns, true)) {
            $this->db->exec("ALTER TABLE staff ADD COLUMN allowed_branches VARCHAR(500) NULL AFTER is_employee");
            $columns[] = 'allowed_branches';
            if (in_array('managed_branches', $columns, true)) {
                $this->db->exec("UPDATE staff SET allowed_branches = managed_branches WHERE allowed_branches IS NULL AND managed_branches IS NOT NULL");
            }
        }

        if (!in_array('manager_scope', $columns, true)) {
            $this->db->exec("ALTER TABLE staff ADD COLUMN manager_scope ENUM('all','some','none') NULL AFTER allowed_branches");
            $columns[] = 'manager_scope';
        } else {
            try {
                $this->db->exec("ALTER TABLE staff MODIFY COLUMN manager_scope ENUM('all','some','none') NULL AFTER allowed_branches");
            } catch (Throwable $e) {
            }
        }

        if (!in_array('managed_branches', $columns, true)) {
            $this->db->exec("ALTER TABLE staff ADD COLUMN managed_branches VARCHAR(500) NULL AFTER manager_scope");
            $columns[] = 'managed_branches';
        }

        if (!in_array('phone', $columns, true)) {
            $this->db->exec("ALTER TABLE staff ADD COLUMN phone VARCHAR(255) NULL AFTER managed_branches");
            $columns[] = 'phone';
        }

        if (!in_array('email', $columns, true)) {
            $this->db->exec("ALTER TABLE staff ADD COLUMN email VARCHAR(255) NULL AFTER phone");
            $columns[] = 'email';
        }

        if (in_array('role', $columns, true)) {
            $this->db->exec("UPDATE staff SET manager_scope = 'all', branch_id = NULL WHERE role IN ('owner', 'manager') AND manager_scope IS NULL");
            if (in_array('is_owner', $columns, true)) {
                $this->db->exec("UPDATE staff SET is_owner = 1 WHERE role = 'owner'");
            }
            if (in_array('is_manager', $columns, true)) {
                $this->db->exec("UPDATE staff SET is_manager = 1 WHERE role = 'manager'");
            }
            $this->db->exec("ALTER TABLE staff DROP COLUMN role");
        }

        if (in_array('is_superadmin', $columns, true)) {
            $webAdmins = require __DIR__ . '/../Middleware/permissions_config/restaurant_crud_admins.php';
            $webAdminIds = array_values(array_filter(array_map('intval', $webAdmins['employee_ids'] ?? $webAdmins)));
            if ($webAdminIds !== []) {
                $this->db->exec("UPDATE staff SET is_superadmin = 1 WHERE restaurant_id = 1 AND id IN (" . implode(',', $webAdminIds) . ")");
            }
        }

        if (in_array('is_owner', $columns, true)) {
            $this->db->exec("UPDATE staff SET is_owner = 1 WHERE restaurant_id <> 1 AND branch_id IS NULL AND manager_scope = 'all' AND COALESCE(managed_branches, '') = '' AND COALESCE(is_manager, 0) = 0 AND COALESCE(is_employee, 0) = 0");
        }

        if (in_array('is_manager', $columns, true)) {
            $this->db->exec("UPDATE staff SET is_manager = 1, is_employee = 0, allowed_branches = COALESCE(NULLIF(allowed_branches, ''), NULLIF(managed_branches, ''), 'all') WHERE is_owner = 0 AND branch_id IS NULL AND manager_scope IN ('all', 'some', 'none')");
            $this->db->exec("UPDATE staff SET allowed_branches = 'all' WHERE is_manager = 1 AND manager_scope = 'all' AND (allowed_branches IS NULL OR allowed_branches = '')");
            $this->db->exec("UPDATE staff SET allowed_branches = '' WHERE is_manager = 1 AND manager_scope = 'none'");
        }

        if (in_array('is_employee', $columns, true)) {
            $this->db->exec("UPDATE staff SET is_employee = 1 WHERE is_superadmin = 0 AND is_owner = 0 AND is_manager = 0");
        }
    }
}
