<?php

class OrdersValidator
{
    private array $allowedStatuses = [
        'waiting',
        'canceled',
        'finished'
    ];

    public function validateCreate(array $data): array
    {
        $errors = [];

        foreach (['food_id', 'table_id', 'restaurant_id'] as $field) {
            if (!isset($data[$field])) {
                $errors[$field] = str_replace('_', ' ', ucfirst($field)) . ' is required.';
            } elseif (
                filter_var($data[$field], FILTER_VALIDATE_INT) === false ||
                (int) $data[$field] <= 0
            ) {
                $errors[$field] = str_replace('_', ' ', ucfirst($field)) . ' must be a valid positive integer.';
            }
        }

        if (isset($data['status']) && !in_array($data['status'], $this->allowedStatuses, true)) {
            $errors['status'] = 'Invalid order status.';
        }

        foreach (['extra_price', 'price', 'profit'] as $field) {
            if (isset($data[$field]) && (!is_numeric($data[$field]) || (float) $data[$field] < 0)) {
                $errors[$field] = str_replace('_', ' ', ucfirst($field)) . ' must be a valid non-negative number.';
            }
        }

        if (isset($data['details']) && $data['details'] !== null && !is_string($data['details'])) {
            $errors['details'] = 'Details must be text.';
        }

        if (isset($data['created_at']) && strtotime((string) $data['created_at']) === false) {
            $errors['created_at'] = 'Created at must be a valid date/time.';
        }

        return $errors;
    }

    public function validateUpdate(array $data): array
    {
        return $this->validateCreate($data);
    }

    public function validateStatus(string $status): array
    {
        if (!in_array($status, $this->allowedStatuses, true)) {
            return ['status' => 'Invalid order status.'];
        }

        return [];
    }
}
