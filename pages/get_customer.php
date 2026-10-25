<?php
include "../config/db.php";

header('Content-Type: application/json');

$name = isset($_GET['name']) ? trim($_GET['name']) : '';

if ($name === '') {
    echo json_encode([
        "status" => "empty",
        "id" => null
    ]);
    exit;
}

$name = strtolower(preg_replace('/\s+/', ' ', $name));
$name = mysqli_real_escape_string($conn, $name);

$sql = "
    SELECT id, customer_name, phone, address
    FROM customers
    WHERE LOWER(TRIM(customer_name)) = '$name'
    LIMIT 1
";

$res = $conn->query($sql);

if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();

    echo json_encode([
        "status" => "found",
        "id" => $row["id"],
        "customer_name" => $row["customer_name"],
        "phone" => $row["phone"],
        "address" => $row["address"]
    ]);
} else {
    echo json_encode([
        "status" => "not_found",
        "id" => null
    ]);
}
?>