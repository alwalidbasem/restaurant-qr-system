<?php

class RestaurantValidator
{
    public function validateCreate(array $data): array
    {
        $errors = [];

        foreach (['name', 'location', 'active_unitl', 'manager_number', 'txt_details', 'main_code'] as $field) {
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

        return $errors;
    }

    public function validateUpdate(array $data): array
    {
        return $this->validateCreate($data);
    }
}
