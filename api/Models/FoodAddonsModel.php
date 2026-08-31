<?php

class FoodAddon
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getAll(?int $restaurantId = null): array
    {
        $restaurantSql = $restaurantId !== null
            ? " WHERE addons.restaurant_id = :restaurant_id"
            : "";

        $stmt = $this->db->prepare("
            SELECT
                addons.*,
                foods.name_en AS food_name_en,
                foods.name_ar AS food_name_ar,
                categories.name_en AS category_name_en,
                categories.name_ar AS category_name_ar,
                restaurants.name AS restaurant_name
            FROM food_addons addons
            LEFT JOIN menu_foods foods
                ON foods.id = addons.food_id
            LEFT JOIN menu_categories categories
                ON categories.id = addons.category_id
            INNER JOIN restaurants
                ON restaurants.id = addons.restaurant_id
            $restaurantSql
            ORDER BY addons.id ASC
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
                addons.*,
                foods.name_en AS food_name_en,
                foods.name_ar AS food_name_ar,
                categories.name_en AS category_name_en,
                categories.name_ar AS category_name_ar,
                restaurants.name AS restaurant_name
            FROM food_addons addons
            LEFT JOIN menu_foods foods
                ON foods.id = addons.food_id
            LEFT JOIN menu_categories categories
                ON categories.id = addons.category_id
            INNER JOIN restaurants
                ON restaurants.id = addons.restaurant_id
            WHERE addons.id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);

        $addon = $stmt->fetch(PDO::FETCH_ASSOC);

        return $addon ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO food_addons (
                name_ar,
                name_en,
                food_id,
                category_id,
                extra_price,
                extra_profit,
                restaurant_id
            )
            VALUES (
                :name_ar,
                :name_en,
                :food_id,
                :category_id,
                :extra_price,
                :extra_profit,
                :restaurant_id
            )
        ");

        $stmt->execute([
            ':name_ar' => $data['name_ar'],
            ':name_en' => $data['name_en'],
            ':food_id' => ($data['food_id'] ?? null) !== null && ($data['food_id'] ?? '') !== '' ? (int) $data['food_id'] : null,
            ':category_id' => ($data['category_id'] ?? null) !== null && ($data['category_id'] ?? '') !== '' ? (int) $data['category_id'] : null,
            ':extra_price' => $data['extra_price'] ?? 0,
            ':extra_profit' => $data['extra_profit'] ?? 0,
            ':restaurant_id' => $data['restaurant_id']
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE food_addons
            SET
                name_ar = :name_ar,
                name_en = :name_en,
                food_id = :food_id,
                category_id = :category_id,
                extra_price = :extra_price,
                extra_profit = :extra_profit,
                restaurant_id = :restaurant_id
            WHERE id = :id
        ");

        return $stmt->execute([
            ':name_ar' => $data['name_ar'],
            ':name_en' => $data['name_en'],
            ':food_id' => ($data['food_id'] ?? null) !== null && ($data['food_id'] ?? '') !== '' ? (int) $data['food_id'] : null,
            ':category_id' => ($data['category_id'] ?? null) !== null && ($data['category_id'] ?? '') !== '' ? (int) $data['category_id'] : null,
            ':extra_price' => $data['extra_price'],
            ':extra_profit' => $data['extra_profit'],
            ':restaurant_id' => $data['restaurant_id'],
            ':id' => $id
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM food_addons
            WHERE id = :id
        ");

        return $stmt->execute([':id' => $id]);
    }

    public function exists(int $id): bool
    {
        return $this->getById($id) !== null;
    }
}
