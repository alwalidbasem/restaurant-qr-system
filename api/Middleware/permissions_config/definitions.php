<?php

return [
    'is_superadmin' => [
        'restaurants.create' => 'Create new restaurant',
        'restaurants.get' => 'Get restaurants info',
        'restaurants.update' => 'Edit and join any branch, brand, or restaurant',
        'restaurants.delete' => 'Delete any branch, brand, or restaurant',
    ],

    'is_owner' => null,

    'is_manager' => [
        'branches.create' => 'Create branches',
        'branches.get' => 'Read branches',
        'branches.up    date' => 'Update branches',
        'branches.delete' => 'Delete branches',
        'branches_logs.get' => 'Read branch manager logs',
        'managers_log.get' => 'Read manager logs',
    ],

    'is_employee' => [
        'staff.create' => 'Create staff',
        'staff.get' => 'Read staff',
        'staff.update' => 'Update staff',
        'staff.delete' => 'Delete staff',
        'inventory.create' => 'Create inventory',
        'inventory.get' => 'Read inventory',
        'inventory.update' => 'Update inventory',
        'inventory.delete' => 'Delete inventory',
        'orders.create' => 'Create orders',
        'orders.get' => 'Read orders',
        'orders.update' => 'Update orders',
        'orders.delete' => 'Delete orders',
        'foods.create' => 'Create foods and food addons',
        'foods.get' => 'Read foods and food addons',
        'foods.update' => 'Update foods and food addons',
        'foods.delete' => 'Delete foods and food addons',
        'categories.create' => 'Create categories',
        'categories.get' => 'Read categories',
        'categories.update' => 'Update categories',
        'categories.delete' => 'Delete categories',
        'tables.create' => 'Create tables',
        'tables.get' => 'Read tables',
        'tables.update' => 'Update tables',
        'tables.delete' => 'Delete tables',
        'logs.get' => 'Read activity logs',
        'discounts.create' => 'Create discounts',
        'discounts.get' => 'Read discounts',
        'discounts.update' => 'Update discounts',
        'discounts.delete' => 'Delete discounts',
    ],
];
