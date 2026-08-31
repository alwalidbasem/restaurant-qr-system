<?php

class ActivityLog
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(array $data): int
    {
        $this->ensureBranchColumn();

        $stmt = $this->db->prepare("
            INSERT INTO activity_logs (
                restaurant_id,
                branch_id,
                employee_id,
                employee_name,
                permission_key,
                entity_type,
                entity_id,
                action_label,
                message,
                metadata
            )
            VALUES (
                :restaurant_id,
                :branch_id,
                :employee_id,
                :employee_name,
                :permission_key,
                :entity_type,
                :entity_id,
                :action_label,
                :message,
                :metadata
            )
        ");

        $stmt->execute([
            ':restaurant_id' => $data['restaurant_id'],
            ':branch_id' => $data['branch_id'] ?? 0,
            ':employee_id' => $data['employee_id'] ?? null,
            ':employee_name' => $data['employee_name'] ?? null,
            ':permission_key' => $data['permission_key'],
            ':entity_type' => $data['entity_type'],
            ':entity_id' => $data['entity_id'] ?? null,
            ':action_label' => $data['action_label'],
            ':message' => $data['message'],
            ':metadata' => isset($data['metadata']) ? json_encode($data['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function getAll(int $restaurantId, array $filters = []): array
    {
        $this->ensureBranchColumn();

        $where = ['restaurant_id = :restaurant_id'];
        $params = [':restaurant_id' => $restaurantId];

        $restaurantIds = array_values(array_unique(array_filter(
            array_map('intval', (array) ($filters['restaurant_ids'] ?? [])),
            static fn (int $id): bool => $id > 0
        )));
        if ($restaurantIds !== []) {
            $where = [];
            $placeholders = [];
            foreach ($restaurantIds as $index => $id) {
                $key = ':restaurant_id_' . $index;
                $placeholders[] = $key;
                $params[$key] = $id;
            }
            unset($params[':restaurant_id']);
            $where[] = 'restaurant_id IN (' . implode(',', $placeholders) . ')';
        }

        if (!empty($filters['before_id'])) {
            $where[] = 'id < :before_id';
            $params[':before_id'] = (int) $filters['before_id'];
        }

        if (!empty($filters['after_id'])) {
            $where[] = 'id > :after_id';
            $params[':after_id'] = (int) $filters['after_id'];
        }

        if (!empty($filters['employee_ids'])) {
            $ids = array_values(array_filter(array_map('intval', (array) $filters['employee_ids'])));
            if ($ids !== []) {
                $placeholders = [];
                foreach ($ids as $index => $id) {
                    $key = ':employee_id_' . $index;
                    $placeholders[] = $key;
                    $params[$key] = $id;
                }
                $where[] = 'employee_id IN (' . implode(',', $placeholders) . ')';
            }
        }

        if (array_key_exists('branch_id', $filters) && $filters['branch_id'] !== null) {
            $where[] = 'branch_id = :branch_id';
            $params[':branch_id'] = (int) $filters['branch_id'];
        }

        if (!empty($filters['permissions'])) {
            $permissions = array_values(array_filter(array_map('trim', (array) $filters['permissions'])));
            if ($permissions !== []) {
                $placeholders = [];
                foreach ($permissions as $index => $permission) {
                    $key = ':permission_' . $index;
                    $placeholders[] = $key;
                    $params[$key] = $permission;
                }
                $where[] = 'permission_key IN (' . implode(',', $placeholders) . ')';
            }
        }

        $range = (string) ($filters['range'] ?? '24h');
        $intervals = [
            '1h' => '1 HOUR',
            '24h' => '24 HOUR',
            'week' => '7 DAY',
            'month' => '1 MONTH',
            '3months' => '3 MONTH',
        ];
        if (isset($intervals[$range])) {
            $where[] = 'created_at >= DATE_SUB(NOW(), INTERVAL ' . $intervals[$range] . ')';
        }

        $limit = max(1, min(100, (int) ($filters['limit'] ?? 25)));
        $sql = "
            SELECT *
            FROM activity_logs
            WHERE " . implode(' AND ', $where) . "
            ORDER BY id DESC
            LIMIT {$limit}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $metadata = json_decode((string) ($row['metadata'] ?? ''), true);
            $row['metadata'] = is_array($metadata) ? $metadata : [];
        }

        return array_reverse($rows);
    }

    private function ensureBranchColumn(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }

        $checked = true;
        $columns = $this->db
            ->query("SHOW COLUMNS FROM activity_logs")
            ->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('branch_id', $columns, true)) {
            $this->db->exec("ALTER TABLE activity_logs ADD COLUMN branch_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER restaurant_id");
        }
    }
}
