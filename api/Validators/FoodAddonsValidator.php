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

        foreach (['food_id', 'restaurant_id'] as $field) {
            if (!isset($data[$field])) {
                $errors[$field] = str_replace('_', ' ', ucfirst($field)) . ' is required.';
            } elseif (
                filter_var($data[$field], FILTER_VALIDATE_INT) === false ||
                (int) $data[$field] <= 0
            ) {
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
