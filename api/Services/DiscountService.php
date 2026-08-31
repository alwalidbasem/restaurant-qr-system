<?php

require_once __DIR__ . '/../Models/DiscountsModel.php';

class DiscountService
{
    private Discount $discountModel;

    public function __construct(PDO $db)
    {
        $this->discountModel = new Discount($db);
    }

    public function annotateFoods(array $foods, ?int $restaurantId = null): array
    {
        if ($foods === []) {
            return [];
        }

        $discountsByRestaurant = [];

        foreach ($foods as &$food) {
            $foodRestaurantId = (int) ($food['restaurant_id'] ?? $restaurantId ?? 0);
            if ($foodRestaurantId <= 0) {
                continue;
            }

            if (!isset($discountsByRestaurant[$foodRestaurantId])) {
                $discountsByRestaurant[$foodRestaurantId] = $this->activeDiscounts($foodRestaurantId);
            }

            $discounts = $discountsByRestaurant[$foodRestaurantId];
            $foodDiscount = $this->foodDiscountAmount($food, $discounts);
            $food = $this->applyPriceFields($food, 'price', 'original_price', 'discounted_price', $foodDiscount);

            if (isset($food['addons']) && is_array($food['addons'])) {
                foreach ($food['addons'] as &$addon) {
                    $addonDiscount = $this->addonDiscountAmount($addon, $food, $discounts);
                    $addon = $this->applyPriceFields($addon, 'extra_price', 'original_extra_price', 'discounted_extra_price', $addonDiscount);
                }
                unset($addon);
            }
        }
        unset($food);

        return $foods;
    }

    public function activeDiscounts(int $restaurantId): array
    {
        return array_values(array_filter(
            $this->discountModel->getAll($restaurantId),
            static fn (array $discount): bool => (int) ($discount['is_active'] ?? 0) === 1
        ));
    }

    public function annotateAddons(array $addons, ?int $restaurantId = null): array
    {
        if ($addons === []) {
            return [];
        }

        $discountsByRestaurant = [];

        foreach ($addons as &$addon) {
            $addonRestaurantId = (int) ($addon['restaurant_id'] ?? $restaurantId ?? 0);
            if ($addonRestaurantId <= 0) {
                continue;
            }

            if (!isset($discountsByRestaurant[$addonRestaurantId])) {
                $discountsByRestaurant[$addonRestaurantId] = $this->activeDiscounts($addonRestaurantId);
            }

            $addonDiscount = $this->addonDiscountAmount($addon, [], $discountsByRestaurant[$addonRestaurantId]);
            $addon = $this->applyPriceFields($addon, 'extra_price', 'original_extra_price', 'discounted_extra_price', $addonDiscount);
        }
        unset($addon);

        return $addons;
    }

    public function foodDiscountAmount(array $food, array $discounts): float
    {
        return $this->totalDiscount((float) ($food['price'] ?? 0), array_filter(
            $discounts,
            static function (array $discount) use ($food): bool {
                $targetType = (string) ($discount['target_type'] ?? '');
                $targetId = (int) ($discount['target_id'] ?? 0);

                return $targetType === 'full_menu_with_addons'
                    || $targetType === 'full_menu_without_addons'
                    || ($targetType === 'food' && $targetId === (int) ($food['id'] ?? 0))
                    || ($targetType === 'category' && $targetId === (int) ($food['category_id'] ?? 0));
            }
        ));
    }

    public function addonDiscountAmount(array $addon, array $food, array $discounts): float
    {
        return $this->totalDiscount((float) ($addon['extra_price'] ?? 0), array_filter(
            $discounts,
            static function (array $discount) use ($addon): bool {
                $targetType = (string) ($discount['target_type'] ?? '');
                $targetId = (int) ($discount['target_id'] ?? 0);

                return $targetType === 'full_menu_with_addons'
                    || ($targetType === 'addon' && $targetId === (int) ($addon['id'] ?? 0));
            }
        ));
    }

    private function totalDiscount(float $amount, iterable $discounts): float
    {
        $discountTotal = 0.0;

        foreach ($discounts as $discount) {
            $remaining = max(0.0, $amount - $discountTotal);
            if ($remaining <= 0.0) {
                break;
            }

            $value = max(0.0, (float) ($discount['discount_value'] ?? 0));
            $discountAmount = ($discount['discount_type'] ?? '') === 'percentage'
                ? $remaining * min(100.0, $value) / 100
                : $value;

            $discountTotal += min($remaining, $discountAmount);
        }

        return round(min($amount, $discountTotal), 3);
    }

    private function applyPriceFields(array $row, string $priceField, string $originalField, string $discountedField, float $discount): array
    {
        $original = (float) ($row[$priceField] ?? 0);

        $row[$originalField] = $original;
        $row['discount_amount'] = $discount;
        $row['has_discount'] = $discount > 0;
        $row[$discountedField] = max(0, round($original - $discount, 3));

        return $row;
    }
}
