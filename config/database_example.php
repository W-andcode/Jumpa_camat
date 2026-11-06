<?php

$host     = "localhost";
$database = "databasename";
$name     = "username";
$pass     = "password";

try {
    $conn = new PDO(
        "mysql:host=$host;port=3306;dbname=$database;charset=utf8mb4",
        $name,
        $pass
    );

    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    error_log("Koneksi database gagal: " . $e->getMessage());
    die("Terjadi kesalahan pada server. Silakan coba lagi nanti.");
}
