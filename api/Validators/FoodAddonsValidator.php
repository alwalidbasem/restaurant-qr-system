<?php

class FoodAddonsValidator
{
    public function validateCreate(array $data): array
    {
        $errors = [];

        foreach (['name_ar', 'name_en'] as $field) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                $errors[$field] = str_replace('_', ' ', ucfirst($field)) . ' is required.';
            } elseif (strlen((string) $data[$field]) > 255) {
                $errors[$field] = str_replace('_', ' ', ucfirst($field)) . ' must be 255 characters or less.';
            }
        }

        foreach (['restaurant_id'] as $field) {
            if (!isset($data[$field])) {
                $errors[$field] = str_replace('_', ' ', ucfirst($field)) . ' is required.';
            } elseif (
                filter_var($data[$field], FILTER_VALIDATE_INT) === false ||
                (int) $data[$field] <= 0
            ) {
                $errors[$field] = str_replace('_', ' ', ucfirst($field)) . ' must be a valid positive integer.';
            }
        }

        $foodId = $data['food_id'] ?? null;
        $categoryId = $data['category_id'] ?? null;
        $hasFood = $foodId !== null && $foodId !== '';
        $hasCategory = $categoryId !== null && $categoryId !== '';

        if ($hasFood === $hasCategory) {
            $errors['addon_scope'] = 'Select either one food or one category for this addon.';
        }

        foreach (['food_id' => $foodId, 'category_id' => $categoryId] as $field => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value <= 0) {
                $errors[$field] = str_replace('_', ' ', ucfirst($field)) . ' must be a valid positive integer.';
            }
        }

        foreach (['extra_price', 'extra_profit'] as $field) {
            if (isset($data[$field]) && (!is_numeric($data[$field]) || (float) $data[$field] < 0)) {
                $errors[$field] = str_replace('_', ' ', ucfirst($field)) . ' must be a valid non-negative number.';
            }
        }

        return $errors;
    }

    public function validateUpdate(array $data): array
    {
        return $this->validateCreate($data);
    }
}
