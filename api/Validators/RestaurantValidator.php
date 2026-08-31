<?php

class RestaurantValidator
{
    public function validateCreate(array $data): array
    {
        $errors = [];
        if (!isset($data['active_until']) && isset($data['active_unitl'])) {
            $data['active_until'] = $data['active_unitl'];
        }

        foreach (['name', 'location', 'active_until', 'manager_number', 'txt_details', 'main_code'] as $field) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                $errors[$field] = str_replace('_', ' ', ucfirst($field)) . ' is required.';
            }
        }

        if (isset($data['id']) && (
            filter_var($data['id'], FILTER_VALIDATE_INT) === false ||
            (int) $data['id'] <= 0
        )) {
            $errors['id'] = 'ID must be a valid positive integer.';
        }

        if (isset($data['location']) && strlen((string) $data['location']) > 255) {
            $errors['location'] = 'Location must be 255 characters or less.';
        }

        if (isset($data['main_code']) && strlen((string) $data['main_code']) > 100) {
            $errors['main_code'] = 'Main code must be 100 characters or less.';
        }

        if (isset($data['parent_restaurant_id']) && $data['parent_restaurant_id'] !== null && $data['parent_restaurant_id'] !== '' && (
            filter_var($data['parent_restaurant_id'], FILTER_VALIDATE_INT) === false ||
            (int) $data['parent_restaurant_id'] <= 0
        )) {
            $errors['parent_restaurant_id'] = 'Parent restaurant ID must be a valid positive integer.';
        }

        if (isset($data['branch_limit']) && (
            filter_var($data['branch_limit'], FILTER_VALIDATE_INT) === false ||
            (int) $data['branch_limit'] < 0
        )) {
            $errors['branch_limit'] = 'Branch limit must be zero or more.';
        }

        if (isset($data['branch_settings']) && is_array($data['branch_settings']) === false && is_string($data['branch_settings']) === false && $data['branch_settings'] !== null) {
            $errors['branch_settings'] = 'Branch settings must be text or JSON data.';
        }

        return $errors;
    }

    public function validateUpdate(array $data): array
    {
        return $this->validateCreate($data);
    }
}
