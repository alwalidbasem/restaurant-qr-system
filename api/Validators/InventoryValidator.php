<?php

class InventoryValidator
{
    public function validateCreate(array $data): array
    {
        $errors = [];

        if (!isset($data['name']) || trim((string) $data['name']) === '') {
            $errors['name'] = 'Name is required.';
        }

        if (isset($data['quantity']) && (
            filter_var($data['quantity'], FILTER_VALIDATE_INT) === false ||
            (int) $data['quantity'] < 0
        )) {
            $errors['quantity'] = 'Quantity must be a valid non-negative integer.';
        }

        foreach (['price', 'profit'] as $field) {
            if (isset($data[$field]) && (!is_numeric($data[$field]) || (float) $data[$field] < 0)) {
                $errors[$field] = ucfirst($field) . ' must be a valid non-negative number.';
            }
        }

        if (!isset($data['restaurant_id'])) {
            $errors['restaurant_id'] = 'Restaurant ID is required.';
        } elseif (
            filter_var($data['restaurant_id'], FILTER_VALIDATE_INT) === false ||
            (int) $data['restaurant_id'] <= 0
        ) {
            $errors['restaurant_id'] = 'Restaurant ID must be a valid positive integer.';
        }

        return $errors;
    }

    public function validateUpdate(array $data): array
    {
        return $this->validateCreate($data);
    }
}
