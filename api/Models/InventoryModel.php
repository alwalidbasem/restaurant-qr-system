<?php

require_once __DIR__ . '/../Controllers/helpers.php';

class Inventory
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getAll(?int $restaurantId = null): array
    {
        $where = $restaurantId !== null ? "WHERE inventory.restaurant_id = :restaurant_id" : "";
        $stmt = $this->db->prepare("
            SELECT inventory.*, restaurants.name AS restaurant_name
            FROM inventory
            INNER JOIN restaurants
                ON restaurants.id = inventory.restaurant_id
            $where
            ORDER BY inventory.id ASC
        ");

        $stmt->execute($restaurantId !== null ? [':restaurant_id' => $restaurantId] : []);

        return array_map(fn (array $item): array => $this->withLinks($item), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT inventory.*, restaurants.name AS restaurant_name
            FROM inventory
            INNER JOIN restaurants
                ON restaurants.id = inventory.restaurant_id
            WHERE inventory.id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ? $this->withLinks($item) : null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO inventory (
                name,
                quantity,
                unit,
                price,
                profit,
                restaurant_id
            )
            VALUES (
                :name,
                :quantity,
                :unit,
                :price,
                :profit,
                :restaurant_id
            )
        ");

        $stmt->execute([
            ':name' => $data['name'],
            ':quantity' => $data['quantity'] ?? 0,
            ':unit' => $data['unit'] ?? 'pcs',
            ':price' => $data['price'] ?? 0,
            ':profit' => $data['profit'] ?? 0,
            ':restaurant_id' => $data['restaurant_id']
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE inventory
            SET
                name = :name,
                quantity = :quantity,
                unit = :unit,
                price = :price,
                profit = :profit,
                restaurant_id = :restaurant_id
            WHERE id = :id
        ");

        return $stmt->execute([
            ':name' => $data['name'],
            ':quantity' => $data['quantity'],
            ':unit' => $data['unit'] ?? 'pcs',
            ':price' => $data['price'],
            ':profit' => $data['profit'],
            ':restaurant_id' => $data['restaurant_id'],
            ':id' => $id
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM inventory
            WHERE id = :id
        ");

        return $stmt->execute([':id' => $id]);
    }

    public function exists(int $id): bool
    {
        return $this->getById($id) !== null;
    }

    public function replaceLinks(int $inventoryId, array $links, int $restaurantId): void
    {
        $this->db->prepare("DELETE FROM inventory_food_links WHERE inventory_id = :inventory_id")
            ->execute([':inventory_id' => $inventoryId]);

        $stmt = $this->db->prepare("
            INSERT INTO inventory_food_links (
                inventory_id,
                food_id,
                addon_id,
                quantity_per_item,
                restaurant_id
            )
            VALUES (
                :inventory_id,
                :food_id,
                :addon_id,
                :quantity_per_item,
                :restaurant_id
            )
        ");

        foreach ($links as $link) {
            if (empty($link['food_id']) || (float) ($link['quantity_per_item'] ?? 0) <= 0) {
                continue;
            }

            $stmt->execute([
                ':inventory_id' => $inventoryId,
                ':food_id' => (int) $link['food_id'],
                ':addon_id' => !empty($link['addon_id']) ? (int) $link['addon_id'] : null,
                ':quantity_per_item' => (float) $link['quantity_per_item'],
                ':restaurant_id' => $restaurantId
            ]);
        }
    }

    public function movement(int $inventoryId, float $quantityChange, string $type, ?string $reason = null, ?int $orderId = null, ?int $orderFoodId = null): bool
    {
        $item = $this->getById($inventoryId);
        if (!$item) {
            return false;
        }

        $this->db->prepare("
            UPDATE inventory
            SET quantity = quantity + :quantity_change
            WHERE id = :id
        ")->execute([
            ':quantity_change' => $quantityChange,
            ':id' => $inventoryId
        ]);

        $stmt = $this->db->prepare("
            INSERT INTO inventory_movements (
                inventory_id,
                order_id,
                order_food_id,
                movement_type,
                quantity_change,
                reason,
                restaurant_id
            )
            VALUES (
                :inventory_id,
                :order_id,
                :order_food_id,
                :movement_type,
                :quantity_change,
                :reason,
                :restaurant_id
            )
        ");

        $saved = $stmt->execute([
            ':inventory_id' => $inventoryId,
            ':order_id' => $orderId,
            ':order_food_id' => $orderFoodId,
            ':movement_type' => $type,
            ':quantity_change' => $quantityChange,
            ':reason' => $reason,
            ':restaurant_id' => (int) $item['restaurant_id']
        ]);

        if ($saved) {
            controllersHelper::logActivity(
                $this->db,
                (int) $item['restaurant_id'],
                'inventory.update',
                $this->movementActionLabel($item, $quantityChange, $type, $reason, $orderId, $orderFoodId),
                'inventory',
                $inventoryId,
                [
                    'entity_name' => $item['name'] ?? null,
                    'movement_type' => $type,
                    'quantity_change' => $quantityChange,
                    'unit' => $item['unit'] ?? null,
                    'order_id' => $orderId,
                    'order_food_id' => $orderFoodId,
                    'reason' => $reason,
                    'changes' => [
                        'quantity' => [
                            'old' => (float) $item['quantity'],
                            'new' => (float) $item['quantity'] + $quantityChange,
                        ],
                    ],
                ]
            );
        }

        return $saved;
    }

    private function movementActionLabel(array $item, float $quantityChange, string $type, ?string $reason, ?int $orderId, ?int $orderFoodId): string
    {
        $quantity = $this->formatQuantity(abs($quantityChange), (string) ($item['unit'] ?? ''));
        $name = (string) ($item['name'] ?? 'stock item');

        if ($type === 'purchase') {
            return "Added {$quantity} to {$name}";
        }

        if ($type === 'consume') {
            return "Decreased {$quantity} from {$name} for food item #{$orderFoodId} in order #{$orderId}";
        }

        if ($type === 'return') {
            return "Returned {$quantity} to {$name} from canceled food item #{$orderFoodId} in order #{$orderId}";
        }

        if ($type === 'waste') {
            return "Marked {$quantity} from {$name} as waste" . ($reason ? ". Reason: {$reason}" : '');
        }

        return ($quantityChange >= 0 ? "Added {$quantity} to {$name}" : "Decreased {$quantity} from {$name}") . ($reason ? ". Reason: {$reason}" : '');
    }

    private function formatQuantity(float $quantity, string $unit): string
    {
        $formatted = rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.');

        return trim($formatted . ' ' . $unit);
    }

    public function getMovements(?int $restaurantId = null): array
    {
        $where = $restaurantId !== null ? "WHERE movements.restaurant_id = :restaurant_id" : "";
        $stmt = $this->db->prepare("
            SELECT
                movements.*,
                inventory.name AS inventory_name,
                inventory.unit
            FROM inventory_movements movements
            INNER JOIN inventory
                ON inventory.id = movements.inventory_id
            $where
            ORDER BY movements.id DESC
            LIMIT 100
        ");
        $stmt->execute($restaurantId !== null ? [':restaurant_id' => $restaurantId] : []);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function applyOrderFoodConsumption(int $orderFoodId, string $movementType = 'consume'): void
    {
        $food = $this->getOrderFoodForInventory($orderFoodId);
        if (!$food) {
            return;
        }

        $links = $this->matchingLinksForOrderFood($food);
        $sign = $movementType === 'return' ? 1 : -1;

        foreach ($links as $link) {
            $this->movement(
                (int) $link['inventory_id'],
                $sign * (float) $link['quantity_per_item'],
                $movementType,
                $movementType === 'return' ? 'Food status canceled' : 'Food ordered',
                (int) $food['order_id'],
                $orderFoodId
            );
        }
    }

    public function applyOrderConsumption(int $orderId): void
    {
        $stmt = $this->db->prepare("SELECT id FROM order_foods WHERE order_id = :order_id");
        $stmt->execute([':order_id' => $orderId]);

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $orderFoodId) {
            $this->applyOrderFoodConsumption((int) $orderFoodId, 'consume');
        }
    }

    private function withLinks(array $item): array
    {
        $stmt = $this->db->prepare("
            SELECT
                links.*,
                foods.name_en AS food_name_en,
                foods.name_ar AS food_name_ar,
                addons.name_en AS addon_name_en,
                addons.name_ar AS addon_name_ar
            FROM inventory_food_links links
            INNER JOIN menu_foods foods
                ON foods.id = links.food_id
            LEFT JOIN food_addons addons
                ON addons.id = links.addon_id
            WHERE links.inventory_id = :inventory_id
            ORDER BY foods.name_en ASC, addons.name_en ASC
        ");
        $stmt->execute([':inventory_id' => $item['id']]);
        $item['links'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $item;
    }

    private function getOrderFoodForInventory(int $orderFoodId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM order_foods
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $orderFoodId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function matchingLinksForOrderFood(array $food): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM inventory_food_links
            WHERE restaurant_id = :restaurant_id
                AND food_id = :food_id
        ");
        $stmt->execute([
            ':restaurant_id' => $food['restaurant_id'],
            ':food_id' => $food['food_id']
        ]);

        $addonIds = $this->decodeAddonIds($food['addon_id'] ?? null);

        return array_values(array_filter($stmt->fetchAll(PDO::FETCH_ASSOC), function (array $link) use ($addonIds): bool {
            return empty($link['addon_id']) || in_array((int) $link['addon_id'], $addonIds, true);
        }));
    }

    private function decodeAddonIds(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $decoded = is_string($value) ? json_decode($value, true) : $value;
        $values = is_array($decoded) ? $decoded : [$decoded];

        return array_values(array_unique(array_filter(
            array_map('intval', $values),
            static fn (int $id): bool => $id > 0
        )));
    }
}
