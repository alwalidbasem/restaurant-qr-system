<?php

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../../Models/RestaurantModel.php';
require_once __DIR__ . '/../../Models/RestaurantTaxSettingsModel.php';
require_once __DIR__ . '/../../Services/JoFotaraService.php';
require_once __DIR__ . '/../../Validators/TaxSettingsValidator.php';

class RestaurantTaxSettingsController
{
    private PDO $db;
    private Restaurant $restaurantModel;
    private RestaurantTaxSettings $settingsModel;
    private TaxSettingsValidator $validator;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->restaurantModel = new Restaurant($db);
        $this->settingsModel = new RestaurantTaxSettings($db);
        $this->validator = new TaxSettingsValidator();
    }

    public function show(int $restaurantId): array
    {
        if (!$this->restaurantModel->exists($restaurantId)) {
            return controllersHelper::apiResponse(['success' => false, 'message' => 'Restaurant not found.'], 404);
        }

        return controllersHelper::apiResponse([
            'success' => true,
            'data' => $this->settingsModel->safeForOutput($this->settingsModel->getByRestaurantId($restaurantId))
        ]);
    }

    public function update(int $restaurantId): void
    {
        if (!$this->restaurantModel->exists($restaurantId)) {
            controllersHelper::jsonResponse(['success' => false, 'message' => 'Restaurant not found.'], 404);
            return;
        }

        $existing = $this->settingsModel->getByRestaurantId($restaurantId);
        $data = array_merge($existing, controllersHelper::getJsonInput());
        $data['einvoicing_enabled'] = !empty($data['einvoicing_enabled']) ? 1 : 0;
        $errors = $this->validator->validate(
            $data,
            !empty($data['einvoicing_enabled']),
            !empty($existing['jofotara_secret_key_encrypted']),
            !empty($existing['jofotara_client_id_encrypted'])
        );
        $data['configuration_errors'] = $errors;
        $data['configuration_status'] = $this->configurationStatus($data, $errors);

        if (!empty($errors)) {
            controllersHelper::jsonResponse(['success' => false, 'errors' => $errors], 422);
            return;
        }

        $saved = $this->settingsModel->save($restaurantId, $data);
        controllersHelper::logActivity($this->db, $restaurantId, 'restaurant.update', 'Update tax and e-invoicing settings', 'restaurant', $restaurantId);
        controllersHelper::jsonResponse([
            'success' => true,
            'message' => 'Tax and e-invoicing settings saved.',
            'data' => $this->settingsModel->safeForOutput($saved)
        ]);
    }

    public function test(int $restaurantId): void
    {
        $settings = $this->settingsModel->getByRestaurantId($restaurantId, true);
        $result = (new JoFotaraService())->validateLocalConfiguration($settings);

        controllersHelper::jsonResponse($result, $result['success'] ? 200 : 422);
    }

    private function configurationStatus(array $data, array $errors): string
    {
        if (!empty($errors)) {
            return 'configuration_error';
        }

        return !empty($data['einvoicing_enabled']) ? 'active' : 'configured';
    }
}
