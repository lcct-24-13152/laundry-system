<?php
include "../config/db.php"; // Siguraduhin na tama ang path papunta sa db.php mo

$name = isset($_GET['name']) ? $_GET['name'] : '';

// Hanapin ang ID ng customer base sa pangalan
$sql = "SELECT id FROM customers WHERE LOWER(TRIM(customer_name)) = LOWER(TRIM('$name')) LIMIT 1";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode(['id' => $row['id']]);
} else {
    echo json_encode(['id' => null]);
}
?>