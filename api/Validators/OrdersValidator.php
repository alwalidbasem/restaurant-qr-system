<?php

class OrdersValidator
{
    private array $allowedStatuses = [
        'waiting',
        'canceled',
        'finished'
    ];

    private array $allowedPaymentStatuses = [
        'unpaid',
        'paid'
    ];

    private array $allowedPaymentMethods = [
        'cash',
        'credit',
        'cash_credit'
    ];

    private array $allowedOrderTypes = [
        'table',
        'takeaway'
    ];

    public function validateCreate(array $data): array
    {
        $errors = [];
        $orderType = $data['order_type'] ?? 'table';

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

        if (!in_array($orderType, $this->allowedOrderTypes, true)) {
            $errors['order_type'] = 'Invalid order type.';
        }

        if ($orderType !== 'takeaway') {
            $this->validateRequiredTableId($data, $errors);
        } elseif (isset($data['table_id']) && $data['table_id'] !== null && $data['table_id'] !== '') {
            $this->validateRequiredTableId($data, $errors);
        }

        if (isset($data['status']) && !in_array($data['status'], $this->allowedStatuses, true)) {
            $errors['status'] = 'Invalid order status.';
        }

        $this->validatePaymentFields($data, $errors);

        foreach (['extra_price', 'price', 'profit'] as $field) {
            if (isset($data[$field]) && (!is_numeric($data[$field]) || (float) $data[$field] < 0)) {
                $errors[$field] = str_replace('_', ' ', ucfirst($field)) . ' must be a valid non-negative number.';
            }
        }

        $this->validateOrderFoodFields($data, $errors);

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
        $errors = [];
        $orderType = $data['order_type'] ?? 'table';

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

        if (!in_array($orderType, $this->allowedOrderTypes, true)) {
            $errors['order_type'] = 'Invalid order type.';
        }

        if ($orderType !== 'takeaway') {
            $this->validateRequiredTableId($data, $errors);
        } elseif (isset($data['table_id']) && $data['table_id'] !== null && $data['table_id'] !== '') {
            $this->validateRequiredTableId($data, $errors);
        }

        if (isset($data['food_id']) && (
            filter_var($data['food_id'], FILTER_VALIDATE_INT) === false ||
            (int) $data['food_id'] <= 0
        )) {
            $errors['food_id'] = 'Food ID must be a valid positive integer.';
        }

        if (isset($data['status']) && !in_array($data['status'], $this->allowedStatuses, true)) {
            $errors['status'] = 'Invalid order status.';
        }

        $this->validatePaymentFields($data, $errors);

        foreach (['extra_price', 'price', 'profit'] as $field) {
            if (isset($data[$field]) && (!is_numeric($data[$field]) || (float) $data[$field] < 0)) {
                $errors[$field] = str_replace('_', ' ', ucfirst($field)) . ' must be a valid non-negative number.';
            }
        }

        $this->validateOrderFoodFields($data, $errors);

        if (isset($data['details']) && $data['details'] !== null && !is_string($data['details'])) {
            $errors['details'] = 'Details must be text.';
        }

        if (isset($data['created_at']) && strtotime((string) $data['created_at']) === false) {
            $errors['created_at'] = 'Created at must be a valid date/time.';
        }

        return $errors;
    }

    private function validateRequiredTableId(array $data, array &$errors): void
    {
        if (!isset($data['table_id']) || $data['table_id'] === null || $data['table_id'] === '') {
            $errors['table_id'] = 'Table ID is required.';
        } elseif (
            filter_var($data['table_id'], FILTER_VALIDATE_INT) === false ||
            (int) $data['table_id'] <= 0
        ) {
            $errors['table_id'] = 'Table ID must be a valid positive integer.';
        }
    }

    public function validateStatus(string $status): array
    {
        if (!in_array($status, $this->allowedStatuses, true)) {
            return ['status' => 'Invalid order status.'];
        }

        return [];
    }

    private function validateOrderFoodFields(array $data, array &$errors): void
    {
        if (isset($data['qty']) && (
            filter_var($data['qty'], FILTER_VALIDATE_INT) === false ||
            (int) $data['qty'] <= 0
        )) {
            $errors['qty'] = 'Quantity must be a valid positive integer.';
        }

        if (isset($data['addon_id']) && $data['addon_id'] !== null && $data['addon_id'] !== '') {
            $addonIds = is_array($data['addon_id']) ? $data['addon_id'] : [$data['addon_id']];

            foreach ($addonIds as $addonId) {
                if (
                    filter_var($addonId, FILTER_VALIDATE_INT) === false ||
                    (int) $addonId <= 0
                ) {
                    $errors['addon_id'] = 'Addon ID must be a valid positive integer or an array of positive integers.';
                    break;
                }
            }
        }
    }

    private function validatePaymentFields(array $data, array &$errors): void
    {
        if (isset($data['payment_status']) && !in_array($data['payment_status'], $this->allowedPaymentStatuses, true)) {
            $errors['payment_status'] = 'Invalid payment status.';
        }

        if (isset($data['payment_method']) && $data['payment_method'] !== null && !in_array($data['payment_method'], $this->allowedPaymentMethods, true)) {
            $errors['payment_method'] = 'Invalid payment method.';
        }

        foreach (['total_paid_cash', 'total_paid_credit'] as $field) {
            if (isset($data[$field]) && (!is_numeric($data[$field]) || (float) $data[$field] < 0)) {
                $errors[$field] = str_replace('_', ' ', ucfirst($field)) . ' must be a valid non-negative number.';
            }
        }
    }
}
