<?php

class FoodValidator
{
    public function validateCreate(array $data): array
    {
        $errors = [];

        foreach (['name_ar', 'name_en', 'image_url'] as $field) {
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

        if (!isset($data['price'])) {
            $errors['price'] = 'Price is required.';
        } elseif (!is_numeric($data['price']) || (float) $data['price'] < 0) {
            $errors['price'] = 'Price must be a valid positive number.';
        }

        if (isset($data['profit']) && (!is_numeric($data['profit']) || (float) $data['profit'] < 0)) {
            $errors['profit'] = 'Profit must be a valid non-negative number.';
        }

        if (isset($data['tax_category']) && !in_array($data['tax_category'], ['default', 'S', 'Z', 'O'], true)) {
            $errors['tax_category'] = 'Tax category must be default, S, Z, or O.';
        }

        if (isset($data['tax_rate']) && $data['tax_rate'] !== '' && (!is_numeric($data['tax_rate']) || (float) $data['tax_rate'] < 0 || (float) $data['tax_rate'] > 100)) {
            $errors['tax_rate'] = 'Tax rate must be between 0 and 100.';
        }

        if (isset($data['special_tax_amount']) && (!is_numeric($data['special_tax_amount']) || (float) $data['special_tax_amount'] < 0)) {
            $errors['special_tax_amount'] = 'Special tax amount must be a valid non-negative number.';
        }

        if (isset($data['note_enabled']) && !is_bool($data['note_enabled']) && !in_array((string) $data['note_enabled'], ['0', '1'], true)) {
            $errors['note_enabled'] = 'Note enabled must be 0 or 1.';
        }

        foreach (['category_id', 'restaurant_id'] as $field) {
            if (!isset($data[$field])) {
                $errors[$field] = str_replace('_', ' ', ucfirst($field)) . ' is required.';
            } elseif (
                filter_var($data[$field], FILTER_VALIDATE_INT) === false ||
                (int) $data[$field] <= 0
            ) {
                $errors[$field] = str_replace('_', ' ', ucfirst($field)) . ' must be a valid positive integer.';
            }
        }

        return $errors;
    }

    public function validateUpdate(array $data): array
    {
        return $this->validateCreate($data);
    }
}
