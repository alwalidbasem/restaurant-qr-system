<?php

class StaffValidator
{
    public function validateCreate(array $data): array
    {
        $errors = [];

        foreach (['name', 'username', 'password'] as $field) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                $errors[$field] = str_replace('_', ' ', ucfirst($field)) . ' is required.';
            }
        }

        if (isset($data['password']) && strlen((string) $data['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }

        if (isset($data['permissions']) && !is_string($data['permissions'])) {
            $errors['permissions'] = 'Permissions must be a comma-separated string.';
        }

        foreach (['details', 'hidden_details'] as $field) {
            if (isset($data[$field]) && $data[$field] !== null && !is_string($data[$field])) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' must be text.';
            }
        }

        if (isset($data['salary']) && (
            !is_numeric($data['salary']) ||
            (float) $data['salary'] < 0
        )) {
            $errors['salary'] = 'Salary must be a valid non-negative number.';
        }

        if (!isset($data['restaurant_id'])) {
            $errors['restaurant_id'] = 'Restaurant ID is required.';
        } elseif (
            filter_var($data['restaurant_id'], FILTER_VALIDATE_INT) === false ||
            (int) $data['restaurant_id'] <= 0
        ) {
            $errors['restaurant_id'] = 'Restaurant ID must be a valid positive integer.';
        }

        if (isset($data['branch_id']) && $data['branch_id'] !== null && $data['branch_id'] !== '' && (
            filter_var($data['branch_id'], FILTER_VALIDATE_INT) === false ||
            (int) $data['branch_id'] <= 0
        )) {
            $errors['branch_id'] = 'Branch ID must be a valid positive integer.';
        }

        return $errors;
    }

    public function validateUpdate(array $data): array
    {
        $errors = $this->validateCreate(array_merge($data, [
            'password' => $data['password'] ?? 'unchanged-password'
        ]));

        if (isset($data['password']) && trim((string) $data['password']) !== '' && strlen((string) $data['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }

        return $errors;
    }
}
