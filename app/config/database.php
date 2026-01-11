<?php
$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "";
$DB_NAME = "beauty_pageant_db";

$conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if (!$conn) {
    // Stop execution safely if DB connection fails
    throw new Exception("Database connection failed");
}

// Ensure proper character encoding
mysqli_set_charset($conn, "utf8mb4");
