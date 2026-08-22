<?php

class CategoryValidator
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

        foreach (['description_ar', 'description_en'] as $field) {
            if (isset($data[$field]) && !is_string($data[$field])) {
                $errors[$field] = str_replace('_', ' ', ucfirst($field)) . ' must be text.';
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
