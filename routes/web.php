<?php
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\FoodController;
use App\Controllers\OrderController;
use App\Controllers\TableController;
use App\Controllers\StaffController;


return [
    // =========================
    // Authentication Pages
    // =========================
    // ['GET', '/admin/login', [AuthController::class, 'showLogin']],
    // ['POST', '/admin/login', [AuthController::class, 'login']],
    // ['POST', '/admin/logout', [AuthController::class, 'logout']],


    // =========================
    // Admin Dashboard
    // =========================
    // ['GET', '/admin', [DashboardController::class, 'index']],


    // =========================
    // Menu Pages
    // =========================
    // ['GET', '/admin/menu', [FoodController::class, 'index']],


    // =========================
    // Orders Pages
    // =========================
    // ['GET', '/admin/orders', [OrderController::class, 'index']],


    // =========================
    // Tables Pages
    // =========================
    // ['GET', '/admin/tables', [TableController::class, 'index']],


    // =========================
    // staff Pages
    // =========================
    // ['GET', '/admin/staff', [StaffController::class, 'index']],


    // =========================
    // Customer Pages
    // =========================
    // ['GET', '/', [FoodController::class, 'customerMenu']],
    // ['GET', '/menu', [FoodController::class, 'customerMenu']],

];
?>