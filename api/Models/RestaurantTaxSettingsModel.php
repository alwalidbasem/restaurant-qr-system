<?php

require_once __DIR__ . '/../../config/security/encrypt.php';

class RestaurantTaxSettings
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getByRestaurantId(int $restaurantId, bool $includeSecrets = false): array
    {
        $stmt = $this->db->prepare("
            SELECT settings.*, restaurants.name AS restaurant_name, restaurants.location, restaurants.manager_number
            FROM restaurant_tax_settings settings
            INNER JOIN restaurants ON restaurants.id = settings.restaurant_id
            WHERE settings.restaurant_id = :restaurant_id
            LIMIT 1
        ");
        $stmt->execute([':restaurant_id' => $restaurantId]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$settings) {
            $settings = $this->defaults($restaurantId);
        }

        $settings['has_secret_key'] = !empty($settings['jofotara_secret_key_encrypted']);

        if ($includeSecrets) {
            $settings['jofotara_client_id'] = decrypt_secret($settings['jofotara_client_id_encrypted'] ?? null);
            $settings['jofotara_secret_key'] = decrypt_secret($settings['jofotara_secret_key_encrypted'] ?? null);
        }

        return $settings;
    }

    public function safeForOutput(array $settings): array
    {
        unset($settings['jofotara_client_id_encrypted'], $settings['jofotara_secret_key_encrypted'], $settings['jofotara_secret_key']);
        $settings['jofotara_secret_key_masked'] = !empty($settings['has_secret_key']) ? '************' : '';

        return $settings;
    }

    public function save(int $restaurantId, array $data): array
    {
        $existing = $this->getByRestaurantId($restaurantId);
        $secret = trim((string) ($data['jofotara_secret_key'] ?? ''));
        $clientId = trim((string) ($data['jofotara_client_id'] ?? ''));
        $errors = $data['configuration_errors'] ?? null;

        $payload = [
            ':restaurant_id' => $restaurantId,
            ':einvoicing_enabled' => !empty($data['einvoicing_enabled']) ? 1 : 0,
            ':taxpayer_type' => $data['taxpayer_type'] ?? 'income_tax_only',
            ':legal_seller_name' => $data['legal_seller_name'] ?? null,
            ':trade_name' => $data['trade_name'] ?? null,
            ':seller_address' => $data['seller_address'] ?? null,
            ':seller_city' => $data['seller_city'] ?? null,
            ':seller_postal_code' => $data['seller_postal_code'] ?? null,
            ':seller_phone' => $data['seller_phone'] ?? null,
            ':seller_national_number' => $data['seller_national_number'] ?? null,
            ':seller_tax_number' => $data['seller_tax_number'] ?? null,
            ':income_source_sequence' => $data['income_source_sequence'] ?? null,
            ':jofotara_client_id_encrypted' => $clientId !== ''
                ? encrypt_secret($clientId)
                : ($existing['jofotara_client_id_encrypted'] ?? null),
            ':jofotara_secret_key_encrypted' => $secret !== ''
                ? encrypt_secret($secret)
                : ($existing['jofotara_secret_key_encrypted'] ?? null),
            ':default_tax_rate' => $data['default_tax_rate'] ?? 0,
            ':prices_include_tax' => !empty($data['prices_include_tax']) ? 1 : 0,
            ':invoice_prefix' => trim((string) ($data['invoice_prefix'] ?? 'INV')) ?: 'INV',
            ':automatic_submission' => !empty($data['automatic_submission']) ? 1 : 0,
            ':print_after_accepted' => !empty($data['print_after_accepted']) ? 1 : 0,
            ':invoice_print_full_page' => !empty($data['invoice_print_full_page']) ? 1 : 0,
            ':invoice_print_width_mm' => $data['invoice_print_width_mm'] ?? 80,
            ':invoice_print_height_mm' => $data['invoice_print_height_mm'] ?? 297,
            ':configuration_status' => $data['configuration_status'] ?? 'not_configured',
            ':configuration_errors' => $errors === null ? null : json_encode($errors, JSON_UNESCAPED_UNICODE)
        ];

        $stmt = $this->db->prepare("
            INSERT INTO restaurant_tax_settings (
                restaurant_id, einvoicing_enabled, taxpayer_type, legal_seller_name, trade_name,
                seller_address, seller_city, seller_postal_code, seller_phone, seller_national_number,
                seller_tax_number, income_source_sequence, jofotara_client_id_encrypted,
                jofotara_secret_key_encrypted, default_tax_rate, prices_include_tax, invoice_prefix,
                automatic_submission, print_after_accepted, invoice_print_full_page,
                invoice_print_width_mm, invoice_print_height_mm, configuration_status, configuration_errors
            )
            VALUES (
                :restaurant_id, :einvoicing_enabled, :taxpayer_type, :legal_seller_name, :trade_name,
                :seller_address, :seller_city, :seller_postal_code, :seller_phone, :seller_national_number,
                :seller_tax_number, :income_source_sequence, :jofotara_client_id_encrypted,
                :jofotara_secret_key_encrypted, :default_tax_rate, :prices_include_tax, :invoice_prefix,
                :automatic_submission, :print_after_accepted, :invoice_print_full_page,
                :invoice_print_width_mm, :invoice_print_height_mm, :configuration_status, :configuration_errors
            )
            ON DUPLICATE KEY UPDATE
                einvoicing_enabled = VALUES(einvoicing_enabled),
                taxpayer_type = VALUES(taxpayer_type),
                legal_seller_name = VALUES(legal_seller_name),
                trade_name = VALUES(trade_name),
                seller_address = VALUES(seller_address),
                seller_city = VALUES(seller_city),
                seller_postal_code = VALUES(seller_postal_code),
                seller_phone = VALUES(seller_phone),
                seller_national_number = VALUES(seller_national_number),
                seller_tax_number = VALUES(seller_tax_number),
                income_source_sequence = VALUES(income_source_sequence),
                jofotara_client_id_encrypted = VALUES(jofotara_client_id_encrypted),
                jofotara_secret_key_encrypted = VALUES(jofotara_secret_key_encrypted),
                default_tax_rate = VALUES(default_tax_rate),
                prices_include_tax = VALUES(prices_include_tax),
                invoice_prefix = VALUES(invoice_prefix),
                automatic_submission = VALUES(automatic_submission),
                print_after_accepted = VALUES(print_after_accepted),
                invoice_print_full_page = VALUES(invoice_print_full_page),
                invoice_print_width_mm = VALUES(invoice_print_width_mm),
                invoice_print_height_mm = VALUES(invoice_print_height_mm),
                configuration_status = VALUES(configuration_status),
                configuration_errors = VALUES(configuration_errors)
        ");
        $stmt->execute($payload);

        return $this->getByRestaurantId($restaurantId);
    }

    public function validateConfiguration(array $settings): array
    {
        require_once __DIR__ . '/../Validators/TaxSettingsValidator.php';
        $validator = new TaxSettingsValidator();

        return $validator->validate(
            $settings,
            !empty($settings['einvoicing_enabled']),
            !empty($settings['jofotara_secret_key_encrypted']),
            !empty($settings['jofotara_client_id_encrypted'])
        );
    }

    private function defaults(int $restaurantId): array
    {
        return [
            'restaurant_id' => $restaurantId,
            'einvoicing_enabled' => 0,
            'taxpayer_type' => 'income_tax_only',
            'legal_seller_name' => null,
            'trade_name' => null,
            'seller_address' => null,
            'seller_city' => null,
            'seller_postal_code' => null,
            'seller_phone' => null,
            'seller_national_number' => null,
            'seller_tax_number' => null,
            'income_source_sequence' => null,
            'jofotara_client_id_encrypted' => null,
            'jofotara_secret_key_encrypted' => null,
            'default_tax_rate' => '0.000',
            'prices_include_tax' => 1,
            'invoice_prefix' => 'INV',
            'automatic_submission' => 1,
            'print_after_accepted' => 0,
            'invoice_print_full_page' => 0,
            'invoice_print_width_mm' => '80.00',
            'invoice_print_height_mm' => '297.00',
            'configuration_status' => 'not_configured',
            'configuration_errors' => null,
            'has_secret_key' => false,
        ];
    }
}
