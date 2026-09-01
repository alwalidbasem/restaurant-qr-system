<?php

class Staff
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
            ? " WHERE staff.restaurant_id = :restaurant_id OR staff.branch_id = :restaurant_id"
            : "";

        $stmt = $this->db->prepare("
            SELECT staff.*, restaurants.name AS restaurant_name, branches.name AS branch_name
            FROM staff
            LEFT JOIN restaurants
                ON restaurants.id = staff.restaurant_id
            LEFT JOIN restaurants branches
                ON branches.id = staff.branch_id
            $restaurantSql
            ORDER BY staff.id ASC
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
            SELECT staff.*, restaurants.name AS restaurant_name, branches.name AS branch_name
            FROM staff
            LEFT JOIN restaurants
                ON restaurants.id = staff.restaurant_id
            LEFT JOIN restaurants branches
                ON branches.id = staff.branch_id
            WHERE staff.id = :id
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
            SELECT staff.*, restaurants.name AS restaurant_name, branches.name AS branch_name
            FROM staff
            LEFT JOIN restaurants
                ON restaurants.id = staff.restaurant_id
            LEFT JOIN restaurants branches
                ON branches.id = staff.branch_id
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

    public function create(array $data): int
    {
        $this->ensureBranchAndSalaryColumns();

        $stmt = $this->db->prepare("
            INSERT INTO staff (
                name,
                username,
                password,
                pfp,
                details,
                hidden_details,
                salary,
                branch_id,
                is_superadmin,
                is_owner,
                is_manager,
                is_employee,
                allowed_branches,
                permissions,
                restaurant_id,
                manager_scope,
                managed_branches,
                phone,
                email
            )
            VALUES (
                :name,
                :username,
                :password,
                :pfp,
                :details,
                :hidden_details,
                :salary,
                :branch_id,
                :is_superadmin,
                :is_owner,
                :is_manager,
                :is_employee,
                :allowed_branches,
                :permissions,
                :restaurant_id,
                :manager_scope,
                :managed_branches,
                :phone,
                :email
            )
        ");

        $stmt->execute([
            ':name' => $data['name'],
            ':username' => $data['username'],
            ':password' => $data['password'],
            ':pfp' => $data['pfp'] ?? null,
            ':details' => $data['details'] ?? null,
            ':hidden_details' => $data['hidden_details'] ?? null,
            ':salary' => $data['salary'] ?? 0,
            ':branch_id' => $data['branch_id'] ?? null,
            ':is_superadmin' => !empty($data['is_superadmin']) ? 1 : 0,
            ':is_owner' => !empty($data['is_owner']) ? 1 : 0,
            ':is_manager' => !empty($data['is_manager']) ? 1 : 0,
            ':is_employee' => !empty($data['is_employee']) ? 1 : 0,
            ':allowed_branches' => $data['allowed_branches'] ?? $data['managed_branches'] ?? null,
            ':permissions' => $data['permissions'] ?? '',
            ':restaurant_id' => $data['restaurant_id'],
            ':manager_scope' => $data['manager_scope'] ?? null,
            ':managed_branches' => $data['managed_branches'] ?? null,
            ':phone' => $data['phone'] ?? null,
            ':email' => $data['email'] ?? null
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function usernameExistsInRestaurant(string $username, int $restaurantId, ?int $excludeEmployeeId = null): bool
    {
        $this->ensureBranchAndSalaryColumns();

        $sql = "
            SELECT COUNT(*)
            FROM staff
            WHERE username = :username
                AND restaurant_id = :restaurant_id
        ";
        $params = [
            ':username' => $username,
            ':restaurant_id' => $restaurantId,
        ];

        if ($excludeEmployeeId !== null) {
            $sql .= " AND id <> :id";
            $params[':id'] = $excludeEmployeeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function update(int $id, array $data): bool
    {
        $this->ensureBranchAndSalaryColumns();

        $passwordSql = isset($data['password']) && trim((string) $data['password']) !== ''
            ? "password = :password,"
            : "";

        $stmt = $this->db->prepare("
            UPDATE staff
            SET
                name = :name,
                username = :username,
                $passwordSql
                pfp = :pfp,
                details = :details,
                hidden_details = :hidden_details,
                salary = :salary,
                branch_id = :branch_id,
                is_superadmin = :is_superadmin,
                is_owner = :is_owner,
                is_manager = :is_manager,
                is_employee = :is_employee,
                allowed_branches = :allowed_branches,
                permissions = :permissions,
                restaurant_id = :restaurant_id,
                manager_scope = :manager_scope,
                managed_branches = :managed_branches,
                phone = :phone,
                email = :email
            WHERE id = :id
        ");

        $params = [
            ':name' => $data['name'],
            ':username' => $data['username'],
            ':pfp' => $data['pfp'] ?? null,
            ':details' => $data['details'] ?? null,
            ':hidden_details' => $data['hidden_details'] ?? null,
            ':salary' => $data['salary'] ?? 0,
            ':branch_id' => $data['branch_id'] ?? null,
            ':is_superadmin' => !empty($data['is_superadmin']) ? 1 : 0,
            ':is_owner' => !empty($data['is_owner']) ? 1 : 0,
            ':is_manager' => !empty($data['is_manager']) ? 1 : 0,
            ':is_employee' => !empty($data['is_employee']) ? 1 : 0,
            ':allowed_branches' => $data['allowed_branches'] ?? $data['managed_branches'] ?? null,
            ':permissions' => $data['permissions'] ?? '',
            ':restaurant_id' => $data['restaurant_id'],
            ':manager_scope' => $data['manager_scope'] ?? null,
            ':managed_branches' => $data['managed_branches'] ?? null,
            ':phone' => $data['phone'] ?? null,
            ':email' => $data['email'] ?? null,
            ':id' => $id
        ];

        if (isset($data['password']) && trim((string) $data['password']) !== '') {
            $params[':password'] = $data['password'];
        }

        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $this->ensureStaffTable();
        $stmt = $this->db->prepare("
            DELETE FROM staff
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
            FROM staff
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
        $this->ensureStaffTable();
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
        } else {
            try {
                $type = $this->db->query("SHOW COLUMNS FROM staff LIKE 'manager_scope'")->fetchColumn(1);
                if (is_string($type) && stripos($type, "'none'") === false) {
                    $this->db->exec("ALTER TABLE staff MODIFY COLUMN manager_scope ENUM('all','some','none') NULL AFTER allowed_branches");
                } elseif (in_array('allowed_branches', $columns, true)) {
                    $this->db->exec("ALTER TABLE staff MODIFY COLUMN manager_scope ENUM('all','some','none') NULL AFTER allowed_branches");
                }
            } catch (Throwable $e) {
            }
        }

        if (!in_array('managed_branches', $columns, true)) {
            $this->db->exec("ALTER TABLE staff ADD COLUMN managed_branches VARCHAR(500) NULL AFTER manager_scope");
        }

        if (!in_array('phone', $columns, true)) {
            $this->db->exec("ALTER TABLE staff ADD COLUMN phone VARCHAR(255) NULL AFTER managed_branches");
        }

        if (!in_array('email', $columns, true)) {
            $this->db->exec("ALTER TABLE staff ADD COLUMN email VARCHAR(255) NULL AFTER phone");
        }

        if (in_array('role', $columns, true)) {
            if (in_array('manager_scope', $columns, true)) {
                $this->db->exec("UPDATE staff SET manager_scope = 'all', branch_id = NULL WHERE role IN ('owner', 'manager') AND manager_scope IS NULL");
            }
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

        $this->ensureRestaurantScopedUsernameIndex();
    }

    private function ensureStaffTable(): void
    {
        try {
            $stmt = $this->db->query("SHOW TABLES LIKE 'staff'");
            if ($stmt !== false && $stmt->fetchColumn() !== false) {
                return;
            }

            $legacy = $this->db->query("SHOW TABLES LIKE 'employees'");
            if ($legacy !== false && $legacy->fetchColumn() !== false) {
                $this->db->exec("RENAME TABLE employees TO staff");
            }
        } catch (Throwable $e) {
        }
    }

    private function ensureRestaurantScopedUsernameIndex(): void
    {
        try {
            $this->ensureStaffTable();
            $indexes = $this->db->query("SHOW INDEX FROM staff")->fetchAll(PDO::FETCH_ASSOC);
            $hasScopedUnique = false;
            $scopedColumns = [];

            foreach ($indexes as $index) {
                if (
                    in_array(($index['Key_name'] ?? ''), ['unique_staff_username_restaurant', 'unique_employee_username_restaurant'], true)
                    && (int) ($index['Non_unique'] ?? 1) === 0
                ) {
                    $scopedColumns[(int) ($index['Seq_in_index'] ?? 0)] = (string) ($index['Column_name'] ?? '');
                }
            }
            ksort($scopedColumns);
            $hasScopedUnique = array_values($scopedColumns) === ['restaurant_id', 'username'];

            foreach ($indexes as $index) {
                $keyName = (string) ($index['Key_name'] ?? '');
                if (
                    $keyName !== ''
                    && $keyName !== 'PRIMARY'
                    && !in_array($keyName, ['unique_staff_username_restaurant', 'unique_employee_username_restaurant'], true)
                    && (int) ($index['Non_unique'] ?? 1) === 0
                    && (string) ($index['Column_name'] ?? '') === 'username'
                    && (int) ($index['Seq_in_index'] ?? 0) === 1
                ) {
                    $this->db->exec("ALTER TABLE staff DROP INDEX `$keyName`");
                }
            }

            if (!$hasScopedUnique && $scopedColumns !== []) {
                $this->db->exec("ALTER TABLE staff DROP INDEX unique_employee_username_restaurant");
            }

            if (!$hasScopedUnique) {
                $this->db->exec("ALTER TABLE staff ADD UNIQUE KEY unique_staff_username_restaurant (restaurant_id, username)");
            }
        } catch (Throwable $e) {
        }
    }
}
