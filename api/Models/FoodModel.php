<?php

class Food
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->ensureNoteColumn();
    }

    public function getAll(?int $restaurantId = null): array
    {
        $restaurantSql = $restaurantId !== null
            ? " WHERE foods.restaurant_id = :restaurant_id"
            : "";

        $stmt = $this->db->prepare("
            SELECT
                foods.*,
                categories.name_ar AS category_name_ar,
                categories.name_en AS category_name_en
            FROM menu_foods foods
            INNER JOIN menu_categories categories
                ON categories.id = foods.category_id
            $restaurantSql
            ORDER BY categories.id ASC, foods.id ASC
        ");

        $params = [];
        if ($restaurantId !== null) {
            $params[':restaurant_id'] = $restaurantId;
        }

        $stmt->execute($params);
        $foods = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->attachAddons($foods);
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                foods.*,
                categories.name_ar AS category_name_ar,
                categories.name_en AS category_name_en
            FROM menu_foods foods
            INNER JOIN menu_categories categories
                ON categories.id = foods.category_id
            WHERE foods.id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $id
        ]);

        $food = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$food) {
            return null;
        }

        $withAddons = $this->attachAddons([$food]);

        return $withAddons[0] ?? null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO menu_foods (
                name_en,
                name_ar,
                description_en,
                description_ar,
                image_url,
                price,
                profit,
                tax_category,
                tax_rate,
                special_tax_amount,
                tax_exempt,
                note_enabled,
                restaurant_id,
                category_id
            )
            VALUES (
                :name_en,
                :name_ar,
                :description_en,
                :description_ar,
                :image_url,
                :price,
                :profit,
                :tax_category,
                :tax_rate,
                :special_tax_amount,
                :tax_exempt,
                :note_enabled,
                :restaurant_id,
                :category_id
            )
        ");

        $stmt->execute([
            ':name_en' => $data['name_en'],
            ':name_ar' => $data['name_ar'],
            ':description_en' => $data['description_en'] ?? null,
            ':description_ar' => $data['description_ar'] ?? null,
            ':image_url' => $data['image_url'],
            ':price' => $data['price'],
            ':profit' => $data['profit'] ?? 0,
            ':tax_category' => $data['tax_category'] ?? 'default',
            ':tax_rate' => ($data['tax_rate'] ?? '') === '' ? null : $data['tax_rate'],
            ':special_tax_amount' => $data['special_tax_amount'] ?? 0,
            ':tax_exempt' => !empty($data['tax_exempt']) ? 1 : 0,
            ':note_enabled' => !empty($data['note_enabled']) ? 1 : 0,
            ':restaurant_id' => $data['restaurant_id'],
            ':category_id' => $data['category_id']
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE menu_foods

            SET
                name_en = :name_en,
                name_ar = :name_ar,
                description_en = :description_en,
                description_ar = :description_ar,
                image_url = :image_url,
                price = :price,
                profit = :profit,
                tax_category = :tax_category,
                tax_rate = :tax_rate,
                special_tax_amount = :special_tax_amount,
                tax_exempt = :tax_exempt,
                note_enabled = :note_enabled,
                restaurant_id = :restaurant_id,
                category_id = :category_id

            WHERE id = :id
        ");

        return $stmt->execute([
            ':name_en' => $data['name_en'],
            ':name_ar' => $data['name_ar'],
            ':description_en' => $data['description_en'] ?? null,
            ':description_ar' => $data['description_ar'] ?? null,
            ':image_url' => $data['image_url'],
            ':price' => $data['price'],
            ':profit' => $data['profit'],
            ':tax_category' => $data['tax_category'] ?? 'default',
            ':tax_rate' => ($data['tax_rate'] ?? '') === '' ? null : $data['tax_rate'],
            ':special_tax_amount' => $data['special_tax_amount'] ?? 0,
            ':tax_exempt' => !empty($data['tax_exempt']) ? 1 : 0,
            ':note_enabled' => !empty($data['note_enabled']) ? 1 : 0,
            ':restaurant_id' => $data['restaurant_id'],
            ':category_id' => $data['category_id'],
            ':id' => $id
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM menu_foods
            WHERE id = :id
        ");

        return $stmt->execute([
            ':id' => $id
        ]);
    }

    public function exists(int $id): bool
    {
        return $this->getById($id) !== null;
    }

    private function attachAddons(array $foods): array
    {
        if ($foods === []) {
            return [];
        }

        $foodIds = array_map(static fn (array $food): int => (int) $food['id'], $foods);
        $categoryIds = array_values(array_unique(array_filter(
            array_map(static fn (array $food): ?int => isset($food['category_id']) ? (int) $food['category_id'] : null, $foods),
            static fn (?int $id): bool => $id !== null
        )));
        $foodPlaceholders = implode(',', array_fill(0, count($foodIds), '?'));
        $categoryPlaceholders = implode(',', array_fill(0, count($categoryIds), '?'));

        $restaurantIds = array_values(array_unique(array_filter(
            array_map(static fn (array $food): ?int => isset($food['restaurant_id']) ? (int) $food['restaurant_id'] : null, $foods),
            static fn (?int $id): bool => $id !== null
        )));
        $restaurantSql = $restaurantIds !== []
            ? " AND restaurant_id IN (" . implode(',', array_fill(0, count($restaurantIds), '?')) . ")"
            : "";

        $stmt = $this->db->prepare("
            SELECT *
            FROM food_addons
            WHERE (food_id IN ($foodPlaceholders)" . ($categoryIds !== [] ? " OR category_id IN ($categoryPlaceholders)" : "") . ")
            $restaurantSql
            ORDER BY id ASC
        ");

        $stmt->execute(array_merge($foodIds, $categoryIds, $restaurantIds));
        $addonsByFood = [];
        $addonsByCategory = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $addon) {
            if (!empty($addon['food_id'])) {
                $foodId = (int) $addon['food_id'];
                $addonsByFood[$foodId][] = $addon;
            }

            if (!empty($addon['category_id'])) {
                $categoryId = (int) $addon['category_id'];
                $addonsByCategory[$categoryId][] = $addon;
            }
        }

        foreach ($foods as &$food) {
            $food['addons'] = array_values(array_merge(
                $addonsByCategory[(int) $food['category_id']] ?? [],
                $addonsByFood[(int) $food['id']] ?? []
            ));
        }

        return $foods;
    }

    private function ensureNoteColumn(): void
    {
        try {
            $this->db->exec("
                ALTER TABLE menu_foods
                ADD COLUMN note_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER tax_exempt
            ");
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) !== 1060) {
                throw $e;
            }
        }
    }
}
