<?php
session_start();
include "../config/db.php";
include "navbar.php";

if(!isset($_SESSION['admin'])){
    header("Location: ../auth/login.php");
    exit();
}

/* ===============================
   CREATE DEFAULT SETTINGS ROW (ONE TIME)
   - para iwas error kahit wala pa sa DB
=================================*/
$conn->query("CREATE TABLE IF NOT EXISTS system_settings(
    id INT PRIMARY KEY,
    detergent_price DECIMAL(10,2) NOT NULL DEFAULT 5,
    fabcon_price DECIMAL(10,2) NOT NULL DEFAULT 4,
    bleach_price DECIMAL(10,2) NOT NULL DEFAULT 3
)");

$check = $conn->query("SELECT id FROM system_settings WHERE id=1");
if($check && $check->num_rows==0){
    $conn->query("INSERT INTO system_settings(id,detergent_price,fabcon_price,bleach_price)
                  VALUES(1,5,4,3)");
}

/* ===============================
   UPDATE SERVICE PRICE
=================================*/
if(isset($_POST['save_service'])){
    $sid   = (int)$_POST['service_id'];
    $price = (float)$_POST['price'];

    // NOTE: adjust column name if yours is price_per_kg
    // try update price_per_kg first; if fails, change to price
    $conn->query("UPDATE services SET price_per_kg='$price' WHERE id='$sid'");
}

/* ===============================
   UPDATE SUPPLIES ADD-ONS PRICE
=================================*/
if(isset($_POST['save_addons'])){
    $det = (float)$_POST['detergent_price'];
    $fab = (float)$_POST['fabcon_price'];
    $ble = (float)$_POST['bleach_price'];

    $conn->query("UPDATE system_settings
                  SET detergent_price='$det',
                      fabcon_price='$fab',
                      bleach_price='$ble'
                  WHERE id=1");
}

/* GET SERVICES */
$services = $conn->query("SELECT * FROM services");

/* GET ADDONS SETTINGS */
$addons = $conn->query("SELECT * FROM system_settings WHERE id=1")->fetch_assoc();
?>

<link rel="stylesheet" href="../assets/css/style.css">

<div class="container">

  <div class="page-title">
    <div>
      <h1>Pricing Settings</h1>
      <p>Manage service pricing and supplies add-ons.</p>
    </div>
  </div>

  <!-- ✅ SERVICE PRICING -->
  <div class="panel" style="margin-top:18px;">
    <h2 style="margin:0 0 12px;">Service Pricing (per kg)</h2>

    <div class="table-card">
      <table class="table" style="min-width:900px;">
        <thead>
          <tr>
            <th>Service</th>
            <th style="width:260px;">Price (₱ per kg)</th>
            <th style="width:160px;">Action</th>
          </tr>
        </thead>

        <tbody>
        <?php while($row=$services->fetch_assoc()): ?>
          <tr>
            <td><b><?= $row['service_name'] ?></b></td>

            <td>
              <form method="POST" style="display:flex; gap:10px; align-items:center;">
                <input type="hidden" name="service_id" value="<?= $row['id'] ?>">
                <input type="number" step="0.01" name="price"
                  value="<?= $row['price_per_kg'] ?>"
                  style="max-width:170px; padding:10px 12px; border-radius:12px;"
                  required>
            </td>

            <td>
                <button class="btn small" name="save_service" style="height:40px;">
                  Save
                </button>
              </form>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>

      </table>
    </div>
  </div>


  <!-- ✅ SUPPLIES ADD-ONS PRICING (ADDED ONLY) -->
  <div class="panel" style="margin-top:25px;">
    <h2 style="margin:0 0 6px;">Supplies Add-ons (per kg)</h2>
    <p style="margin:0 0 16px; color:#64748b; font-size:13px;">
      Update the automatic add-ons price used in every transaction.
    </p>

    <form method="POST" class="form" style="gap:14px;">

      <div class="form-wide" style="margin-top:0;">
        <div>
          <div class="label">Detergent (₱/kg)</div>
          <input type="number" step="0.01" name="detergent_price"
                 value="<?= $addons['detergent_price'] ?>" required>
        </div>

        <div>
          <div class="label">Fabric Conditioner (₱/kg)</div>
          <input type="number" step="0.01" name="fabcon_price"
                 value="<?= $addons['fabcon_price'] ?>" required>
        </div>

        <div>
          <div class="label">Bleach (₱/kg)</div>
          <input type="number" step="0.01" name="bleach_price"
                 value="<?= $addons['bleach_price'] ?>" required>
        </div>
      </div>

      <div style="display:flex; gap:12px; align-items:center; margin-top:6px;">
        <button class="btn" name="save_addons" style="height:44px; padding:0 22px;">
          Update Add-ons
        </button>

        <small style="color:#64748b;">
          * These values will be used in computing total amount (minimum 6kg applies).
        </small>
      </div>

    </form>
  </div>

</div> 