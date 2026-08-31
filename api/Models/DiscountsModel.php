<?php

class Discount
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->ensureTable();
    }

    public function getAll(?int $restaurantId = null): array
    {
        $restaurantSql = $restaurantId !== null
            ? " WHERE discounts.restaurant_id = :restaurant_id"
            : "";

        $stmt = $this->db->prepare("
            SELECT
                discounts.*,
                restaurants.name AS restaurant_name,
                CASE
                    WHEN discounts.target_type = 'food' THEN foods.name_en
                    WHEN discounts.target_type = 'category' THEN categories.name_en
                    WHEN discounts.target_type = 'addon' THEN addons.name_en
                    WHEN discounts.target_type = 'full_menu_with_addons' THEN 'Full menu (addons included)'
                    WHEN discounts.target_type = 'full_menu_without_addons' THEN 'Full menu (without addons)'
                    ELSE NULL
                END AS target_label
            FROM discounts
            INNER JOIN restaurants
                ON restaurants.id = discounts.restaurant_id
            LEFT JOIN menu_foods foods
                ON foods.id = discounts.target_id AND discounts.target_type = 'food'
            LEFT JOIN menu_categories categories
                ON categories.id = discounts.target_id AND discounts.target_type = 'category'
            LEFT JOIN food_addons addons
                ON addons.id = discounts.target_id AND discounts.target_type = 'addon'
            $restaurantSql
            ORDER BY discounts.id DESC
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
        $stmt = $this->db->prepare("
            SELECT
                discounts.*,
                restaurants.name AS restaurant_name,
                CASE
                    WHEN discounts.target_type = 'food' THEN foods.name_en
                    WHEN discounts.target_type = 'category' THEN categories.name_en
                    WHEN discounts.target_type = 'addon' THEN addons.name_en
                    WHEN discounts.target_type = 'full_menu_with_addons' THEN 'Full menu (addons included)'
                    WHEN discounts.target_type = 'full_menu_without_addons' THEN 'Full menu (without addons)'
                    ELSE NULL
                END AS target_label
            FROM discounts
            INNER JOIN restaurants
                ON restaurants.id = discounts.restaurant_id
            LEFT JOIN menu_foods foods
                ON foods.id = discounts.target_id AND discounts.target_type = 'food'
            LEFT JOIN menu_categories categories
                ON categories.id = discounts.target_id AND discounts.target_type = 'category'
            LEFT JOIN food_addons addons
                ON addons.id = discounts.target_id AND discounts.target_type = 'addon'
            WHERE discounts.id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);
        $discount = $stmt->fetch(PDO::FETCH_ASSOC);

        return $discount ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO discounts (
                name,
                discount_type,
                discount_value,
                target_type,
                target_id,
                is_active,
                restaurant_id
            )
            VALUES (
                :name,
                :discount_type,
                :discount_value,
                :target_type,
                :target_id,
                :is_active,
                :restaurant_id
            )
        ");

        $stmt->execute($this->params($data));

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE discounts
            SET
                name = :name,
                discount_type = :discount_type,
                discount_value = :discount_value,
                target_type = :target_type,
                target_id = :target_id,
                is_active = :is_active,
                restaurant_id = :restaurant_id
            WHERE id = :id
        ");

        return $stmt->execute($this->params($data) + [':id' => $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM discounts
            WHERE id = :id
        ");

        return $stmt->execute([':id' => $id]);
    }

    private function params(array $data): array
    {
        $targetId = $data['target_id'] ?? null;
        $fullMenu = in_array($data['target_type'] ?? '', ['full_menu_with_addons', 'full_menu_without_addons'], true);

        return [
            ':name' => $data['name'],
            ':discount_type' => $data['discount_type'],
            ':discount_value' => $data['discount_value'],
            ':target_type' => $data['target_type'],
            ':target_id' => $fullMenu || $targetId === null || $targetId === '' ? null : (int) $targetId,
            ':is_active' => !empty($data['is_active']) ? 1 : 0,
            ':restaurant_id' => $data['restaurant_id'],
        ];
    }

    private function ensureTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS discounts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                discount_type ENUM('percentage', 'fixed') NOT NULL DEFAULT 'percentage',
                discount_value DECIMAL(10, 3) NOT NULL DEFAULT 0.000,
                target_type ENUM('food', 'category', 'addon', 'full_menu_with_addons', 'full_menu_without_addons') NOT NULL,
                target_id INT UNSIGNED NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                restaurant_id INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_discounts_restaurant (restaurant_id),
                INDEX idx_discounts_target (target_type, target_id),
                FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE ON UPDATE CASCADE
            )
        ");
    }
}
