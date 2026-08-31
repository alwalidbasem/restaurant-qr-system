<?php

class TaxSettingsValidator
{
    public const TAXPAYER_TYPES = ['income_tax_only', 'general_sales_tax', 'special_sales_tax'];

    public function validate(array $data, bool $activating = false, bool $hasExistingSecret = false, bool $hasExistingClientId = false): array
    {
        $errors = [];
        $taxpayerType = (string) ($data['taxpayer_type'] ?? '');

        if (!in_array($taxpayerType, self::TAXPAYER_TYPES, true)) {
            $errors['taxpayer_type'] = 'Taxpayer type is required.';
        }

        foreach (['default_tax_rate'] as $field) {
            if (isset($data[$field]) && (!is_numeric($data[$field]) || (float) $data[$field] < 0 || (float) $data[$field] > 100)) {
                $errors[$field] = 'Tax rate must be between 0 and 100.';
            }
        }

        if (empty($data['invoice_print_full_page'])) {
            if (isset($data['invoice_print_width_mm']) && (!is_numeric($data['invoice_print_width_mm']) || (float) $data['invoice_print_width_mm'] < 40 || (float) $data['invoice_print_width_mm'] > 300)) {
                $errors['invoice_print_width_mm'] = 'Invoice print width must be between 40mm and 300mm.';
            }

            if (isset($data['invoice_print_height_mm']) && (!is_numeric($data['invoice_print_height_mm']) || (float) $data['invoice_print_height_mm'] < 80 || (float) $data['invoice_print_height_mm'] > 500)) {
                $errors['invoice_print_height_mm'] = 'Invoice print height must be between 80mm and 500mm.';
            }
        }

        if ($activating) {
            foreach (['legal_seller_name', 'seller_address', 'seller_phone', 'income_source_sequence'] as $field) {
                if (trim((string) ($data[$field] ?? '')) === '') {
                    $errors[$field] = str_replace('_', ' ', ucfirst($field)) . ' is required before enabling JoFotara.';
                }
            }

            if (!$hasExistingClientId && trim((string) ($data['jofotara_client_id'] ?? '')) === '') {
                $errors['jofotara_client_id'] = 'Client ID is required before enabling JoFotara.';
            }

            if (!$hasExistingSecret && trim((string) ($data['jofotara_secret_key'] ?? '')) === '') {
                $errors['jofotara_secret_key'] = 'Secret key is required before enabling JoFotara.';
            }

            if ($taxpayerType === 'general_sales_tax' || $taxpayerType === 'special_sales_tax') {
                if (trim((string) ($data['seller_tax_number'] ?? '')) === '') {
                    $errors['seller_tax_number'] = 'Tax number is required for sales tax taxpayers.';
                }
            } elseif ($taxpayerType === 'income_tax_only' && trim((string) ($data['seller_national_number'] ?? '')) === '') {
                $errors['seller_national_number'] = 'National number is required for income-tax-only taxpayers.';
            }
        }

        return $errors;
    }
}
