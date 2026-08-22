<?php

class TableValidator
{
    private array $allowedStatuses = [
        'free',
        'waiting_order',
        'order_done'
    ];


    public function validateCreate(array $data): array
    {
        $errors = [];


        // table_number
        if (!isset($data['table_number'])) {

            $errors['table_number'] = 'Table number is required.';

        } elseif (
            !filter_var(
                $data['table_number'],
                FILTER_VALIDATE_INT
            )
        ) {

            $errors['table_number'] =
                'Table number must be a valid integer.';

        } elseif ((int) $data['table_number'] <= 0) {

            $errors['table_number'] =
                'Table number must be greater than 0.';
        }


        // table_status
        if (isset($data['table_status'])) {

            if (
                !in_array(
                    $data['table_status'],
                    $this->allowedStatuses,
                    true
                )
            ) {

                $errors['table_status'] =
                    'Invalid table status.';
            }
        }


        // table_floor
        if (isset($data['table_floor'])) {

            if (
                filter_var(
                    $data['table_floor'],
                    FILTER_VALIDATE_INT
                ) === false
            ) {

                $errors['table_floor'] =
                    'Table floor must be a valid integer.';
            }
        }


        // position
        if (isset($data['position'])) {

            if (!is_array($data['position'])) {

                $errors['position'] =
                    'Position must be an object/array.';

            } else {

                if (
                    !isset($data['position']['x']) ||
                    !is_numeric($data['position']['x'])
                ) {
                    $errors['position.x'] =
                        'Position X is required and must be numeric.';
                }


                if (
                    !isset($data['position']['y']) ||
                    !is_numeric($data['position']['y'])
                ) {
                    $errors['position.y'] =
                        'Position Y is required and must be numeric.';
                }
            }
        }


        // order_id
        if (
            isset($data['order_id']) &&
            $data['order_id'] !== null
        ) {

            if (
                filter_var(
                    $data['order_id'],
                    FILTER_VALIDATE_INT
                ) === false ||
                (int) $data['order_id'] <= 0
            ) {

                $errors['order_id'] =
                    'Order ID must be a valid positive integer.';
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
        // Same rules for now
        return $this->validateCreate($data);
    }


    public function validateStatus(string $status): array
    {
        $errors = [];

        if (
            !in_array(
                $status,
                $this->allowedStatuses,
                true
            )
        ) {

            $errors['table_status'] =
                'Invalid table status.';
        }

        return $errors;
    }
}
