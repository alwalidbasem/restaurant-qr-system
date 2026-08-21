<?php
    $servername = $_ENV['servername'];
    $username = $_ENV['username'];
    $password = $_ENV['password'];
    $dbname = $_ENV['dbname'];

    try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    } catch(PDOException $e) {
        print('Error while connecting to the database');
        exit();
    }
?>
