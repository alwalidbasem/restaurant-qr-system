<?php

class EmployeeValidator
{
    private array $allowedRoles = [
        'owner',
        'manager',
        'chife',
        'inventory_manager',
        'casher',
        'delivery_manager'
    ];

    public function validateCreate(array $data): array
    {
        $errors = [];

        foreach (['name', 'pfp', 'description'] as $field) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                $errors[$field] = str_replace('_', ' ', ucfirst($field)) . ' is required.';
            }
        }

        if (isset($data['role']) && !in_array($data['role'], $this->allowedRoles, true)) {
            $errors['role'] = 'Invalid employee role.';
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
