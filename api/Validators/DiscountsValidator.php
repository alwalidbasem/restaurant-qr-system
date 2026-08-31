<?php

class DiscountsValidator
{
    private array $targetTypes = [
        'food',
        'category',
        'addon',
        'full_menu_with_addons',
        'full_menu_without_addons',
    ];

    public function validateCreate(array $data): array
    {
        $errors = [];

        if (!isset($data['name']) || trim((string) $data['name']) === '') {
            $errors['name'] = 'Discount name is required.';
        } elseif (strlen((string) $data['name']) > 255) {
            $errors['name'] = 'Discount name must be 255 characters or less.';
        }

        if (!isset($data['discount_type']) || !in_array($data['discount_type'], ['percentage', 'fixed'], true)) {
            $errors['discount_type'] = 'Discount type must be percentage or fixed.';
        }

        if (!isset($data['discount_value']) || !is_numeric($data['discount_value']) || (float) $data['discount_value'] <= 0) {
            $errors['discount_value'] = 'Discount value must be greater than zero.';
        } elseif (($data['discount_type'] ?? '') === 'percentage' && (float) $data['discount_value'] > 100) {
            $errors['discount_value'] = 'Percentage discount cannot be more than 100.';
        }

        if (!isset($data['target_type']) || !in_array($data['target_type'], $this->targetTypes, true)) {
            $errors['target_type'] = 'Select a valid discount target.';
        }

        $needsTarget = in_array($data['target_type'] ?? '', ['food', 'category', 'addon'], true);
        if ($needsTarget) {
            if (!isset($data['target_id']) || filter_var($data['target_id'], FILTER_VALIDATE_INT) === false || (int) $data['target_id'] <= 0) {
                $errors['target_id'] = 'Select a valid target item.';
            }
        }

        if (!isset($data['restaurant_id']) || filter_var($data['restaurant_id'], FILTER_VALIDATE_INT) === false || (int) $data['restaurant_id'] <= 0) {
            $errors['restaurant_id'] = 'Restaurant ID must be a valid positive integer.';
        }

        return $errors;
    }

    public function validateUpdate(array $data): array
    {
        return $this->validateCreate($data);
    }
}
