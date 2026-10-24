<?php
include "../config/db.php";

$id = $_POST['id'];
$status = $_POST['status'];

$conn->query("UPDATE transactions SET status='$status' WHERE id='$id'");

header("Location: transactions.php");
?>