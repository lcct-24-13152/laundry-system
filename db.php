<?php
require_once __DIR__ . '/bootstrap.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("DB ERROR: " . $conn->connect_error);
}
?>
