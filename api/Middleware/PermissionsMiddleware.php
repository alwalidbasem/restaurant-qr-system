<?php

require_once __DIR__ . '/../Controllers/Auth/AuthController.php';

class PermissionsMiddleware
{
    private PDO $conn;
    private array $permissions;
    private array $public_data;
    private array $webAdmins;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $this->permissions = require __DIR__ . '/../Middleware/permissions_config/definitions.php';
        $this->public_data = require __DIR__ . '/../Middleware/permissions_config/public_data.php';
        $webAdmins = require __DIR__ . '/../Middleware/permissions_config/restaurant_crud_admins.php';
        $this->webAdmins = array_map('intval', $webAdmins['employee_ids'] ?? $webAdmins);
    }

    // STEP ONE GET API-KEY if exists (FROM COOKIES)
    public function employeePermissions(): array
    {
        $auth = new AuthController($this->conn, true);
        $response = $auth->isAuth();

        $employee = $response['data']['employee'] ?? null;

        if (!$employee || !isset($employee['permissions'])) {
            return [];
        }

        return array_map(
            'trim',
            explode(',', (string) $employee['permissions'])
        );
    }


    private function deny(int $statusCode, string $message): void
    {
        http_response_code($statusCode);

        echo json_encode([
            'success' => false,
            'message' => $message
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        exit;
    }









    // GET Data handller 
    // IF (has API_KEY & his id is into webAdminIds && resturant id = 1) -> Show any Resturant data
    // IF has API_KEY & request data for his own resturant   -> Show HIS Resturant data
    // IF has API_KEY & request data for other resturant   -> Show public Resturant data only
    // IF dont has  API_KEY  -> Show public Resturant data only

    
    // Step one we use this final_data before any post for api_response in the controllers
    public function final_data(array $data,string $crud_name){


        $AuthController = new AuthController($this->conn, true);
        $Auth = $AuthController->isAuth();
        $isAuth = $Auth['data']['authenticated'];
        $employeeData = $Auth['data']['employee'];
        if($isAuth){
            $restaurant_id = $data[0]['restaurant_id'] ?? $data['restaurant_id'] ?? 0;
            if(
                (int) $employeeData['restaurant_id'] === (int) $restaurant_id
                || (class_exists('controllersHelper') && controllersHelper::employeeCanAccessRestaurant($this->conn, $employeeData, (int) $restaurant_id))
            ){
                return $data;
            }else{
                if($employeeData['restaurant_id'] == 1 && in_array((int) $employeeData['id'], $this->webAdmins, true)){
                    return $data;
                }else{
                    return $this->showPublicData($crud_name,$data);
                }
            }
        }else{
            return $this->showPublicData($crud_name,$data);
        }
    
    
    }


    // Data Array filter (helper for final_data)
    public function showPublicData(string $allowedData, array $data): array
    {
        if ($allowedData != 'admin') {
            
            $allowedData = array_key_exists($allowedData, $this->public_data) ? ($this->public_data)[$allowedData] :  ['order_id'] ;
        }else{
            return $data;
        }

        $filter = function (array $row) use ($allowedData): array {
            return array_intersect_key(
                $row,
                array_flip($allowedData)
            );
        };

        if (isset($data[0]) && is_array($data[0])) {
            return array_map($filter, $data);
        }

        return $filter($data);
    }





























    public function isQualifiedEmployee(string $permission,bool $isArray = false): bool {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $employeePermissions = $this->employeePermissions();

        // Get permission names in their exact order
        $definedPermissions = array_keys($this->permissions);

        // Find requested permission index
        $permissionIndex = array_search(
            $permission,
            $definedPermissions,
            true
        );

        // Permission doesn't exist in definitions.php
        if ($permissionIndex === false) {
            if ($isArray) {
                return false;
            }

            $this->deny(403, 'Unknown permission: ' . $permission);
            return false;
        }

        $allowed = ($employeePermissions[$permissionIndex] ?? '0') === '1';

        if ($isArray) {
            return $allowed;
        }

        if (!$allowed) {
            $this->deny(403, 'Permission denied: ' . $permission);
            return false;
        }

        return true;
    }

    public function isSuperAdmin(){
        $AuthController = new AuthController($this->conn, true);
        $Auth = $AuthController->isAuth();
        $isAuth = $Auth['data']['authenticated'];
        $employeeData = $Auth['data']['employee'];
        if($isAuth){
            return ($employeeData['restaurant_id'] == 1 && in_array((int) $employeeData['id'], $this->webAdmins, true)) ? true : false;
        }else{
            return false;
        }
    }



    public function WhatIsYourRole(string $permission,int $restaurant_id){
        if($this->isSuperAdmin()){
            return [
                "role"=>"SuperAdmin",
                "restaurant_id"=>1,
                "hasRequiredPermission"=>true,
                "data_can_be_shown"=>"all"
            ];
        }else{
            $auth = new AuthController($this->conn, true);
            $response = $auth->isAuth();
            $employee = $response['data']['employee'] ?? null;
            if($employee != null && $employee['restaurant_id'] == $restaurant_id){
                if($this->isQualifiedEmployee($permission,true)){
                    return [
                        "role"=>"Employee",
                        "restaurant_id"=>$employee['restaurant_id'],
                        "hasRequiredPermission"=>true,
                        "data_can_be_shown"=>"all"
                    ];
                }else{
                    return [
                        "role"=>"Employee",
                        "restaurant_id"=>$employee['restaurant_id'],
                        "hasRequiredPermission"=>false,
                        "data_can_be_shown"=>"public"
                    ];
                }
            }else{
                return [
                    "role"=>"user",
                    "restaurant_id"=>null,
                    "data_can_be_shown"=>"public"
                ];
            }
        }
    }

}
