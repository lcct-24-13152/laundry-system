<?php
session_start();
include "../config/db.php";
include "navbar.php";

if(!isset($_SESSION['admin'])){
    header("Location: ../auth/login.php");
    exit();
}

$msg = "";

$settings = $conn->query("SELECT * FROM laundry_pricing_settings LIMIT 1");

if($settings->num_rows == 0){
    $conn->query("INSERT INTO laundry_pricing_settings (max_load_kg, full_load_price, min_load_price)
                  VALUES (7,218,203)");
}

$row = $conn->query("SELECT * FROM laundry_pricing_settings LIMIT 1")->fetch_assoc();

if(isset($_POST['save_settings'])){
    $max = floatval($_POST['max_load_kg']);
    $full = floatval($_POST['full_load_price']);
    $min = floatval($_POST['min_load_price']);

    $conn->query("
        UPDATE laundry_pricing_settings
        SET max_load_kg='$max',
            full_load_price='$full',
            min_load_price='$min'
        WHERE id='{$row['id']}'
    ");

    $msg = "Load pricing settings updated successfully.";
    $row = $conn->query("SELECT * FROM laundry_pricing_settings LIMIT 1")->fetch_assoc();
}
?>

<div class="container">
    <div class="page-title">
        <h1>Load Pricing Settings</h1>
        <p>Manage dynamic laundry load computation.</p>
    </div>

    <?php if($msg): ?>
        <div class="alert"><?= $msg ?></div>
    <?php endif; ?>

    <div class="settings-card">
        <form method="POST">
            <label>Maximum Load KG</label>
            <input type="number" step="0.01" name="max_load_kg" value="<?= $row['max_load_kg'] ?>" required>

            <label>Full Load Price</label>
            <input type="number" step="0.01" name="full_load_price" value="<?= $row['full_load_price'] ?>" required>

            <label>Minimum Load Price</label>
            <input type="number" step="0.01" name="min_load_price" value="<?= $row['min_load_price'] ?>" required>

            <button type="submit" name="save_settings" class="btn">Save Settings</button>
        </form>
    </div>
</div>

<style>
.settings-card{
    background:white;
    padding:30px;
    border-radius:18px;
    box-shadow:0 10px 25px rgba(0,0,0,.08);
    max-width:500px;
}

.settings-card form{
    display:flex;
    flex-direction:column;
    gap:12px;
}

.settings-card input{
    padding:12px;
    border:1px solid #d1d5db;
    border-radius:10px;
}

.alert{
    background:#dcfce7;
    color:#166534;
    padding:12px 18px;
    border-radius:12px;
    margin-bottom:15px;
    font-weight:600;
}
</style>