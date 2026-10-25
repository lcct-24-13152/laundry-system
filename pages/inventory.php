<?php
session_start();
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
include "../config/db.php";
include "navbar.php";

if(!isset($_SESSION['admin'])){
    header("Location: ../auth/login.php");
    exit();
}

$category = $_GET['category'] ?? '';

/* ADD ITEM */
if(isset($_POST['add_item'])){
    $name = trim($_POST['name']);
    $category_post = trim($_POST['category']);
    $qty = floatval($_POST['qty']);
    $unit = trim($_POST['unit']);
    $reorder = floatval($_POST['reorder']);
    $size_value = floatval($_POST['size_value'] ?? 0);
    $usage_value = floatval($_POST['usage_value'] ?? 0);
    $usage_type = 'kg';
    $critical = floatval($_POST['critical_level']);

    if($name != "" && $category_post != "" && $unit != ""){

        $name_safe = mysqli_real_escape_string($conn, $name);
        $category_safe = mysqli_real_escape_string($conn, $category_post);
        $unit_safe = mysqli_real_escape_string($conn, $unit);

        $check = $conn->query("
            SELECT id FROM inventory 
            WHERE LOWER(TRIM(item_name)) = LOWER(TRIM('$name_safe'))
            AND LOWER(TRIM(category)) = LOWER(TRIM('$category_safe'))
            LIMIT 1
        ");

        if($check && $check->num_rows > 0){

            $error_message = "Item already exists!";

        }else{

            $sql = "
                INSERT INTO inventory(
                    item_name, category, quantity, unit, reorder_level, critical_level, size_value,
                    usage_value, usage_type
                )
                VALUES(
                    '$name_safe','$category_safe','$qty','$unit_safe','$reorder','$critical','$size_value',
                    '$usage_value','$usage_type'
                )
            ";

            if($conn->query($sql)){

                $addon_name = $name_safe;
                $addon_category = $category_safe;
                $addon_size_value = $size_value;
                $addon_unit = $unit_safe;
                $addon_price = 0;

                $checkAddon = $conn->query("
                    SELECT id FROM addons
                    WHERE LOWER(TRIM(addon_name)) = LOWER(TRIM('$addon_name'))
                    LIMIT 1
                ");

                if($checkAddon && $checkAddon->num_rows > 0){

                    $addonRow = $checkAddon->fetch_assoc();
                    $addonId = intval($addonRow['id']);

                    $conn->query("
                        UPDATE addons
                        SET 
                            category = '$addon_category',
                            size_value = '$addon_size_value',
                            unit = '$addon_unit'
                        WHERE id = '$addonId'
                    ");

                }else{

                    $conn->query("
                        INSERT INTO addons(addon_name, category, price, size_value, unit)
                        VALUES('$addon_name', '$addon_category', '$addon_price', '$addon_size_value', '$addon_unit')
                    ");
                }

                header("Location: inventory.php?category=supplies&added=1#addonsSection");
                exit();

            }else{
                $error_message = "Add item failed: " . $conn->error;
            }
        }

    }else{
        $error_message = "Please fill in all required fields.";
    }
}

/* ADD NEW ADDON */
if(isset($_POST['add_addon'])){
    $name = trim($_POST['addon_name']);
    $price = floatval($_POST['addon_price_new']);

    if($name != ""){
        $name = mysqli_real_escape_string($conn, $name);

        $check = $conn->query("
            SELECT id FROM addons
            WHERE LOWER(TRIM(addon_name)) = LOWER(TRIM('$name'))
            LIMIT 1
        ");

        if($check->num_rows == 0){
            $conn->query("
                INSERT INTO addons(addon_name, category, price, size_value, unit)
                VALUES('$name', 'Other', '$price', '0', '')
            ");
        }
    }

    header("Location: inventory.php?category=supplies&added=1#addonsSection");
    exit();
}

/* SAVE SUPPLY PRICES */
if(isset($_POST['save_all_supplies'])){

    if(isset($_POST['price'])){
        foreach($_POST['price'] as $id => $price){
            $id = intval($id);
            $price = floatval($price);
            $conn->query("UPDATE inventory SET price='$price' WHERE id='$id'");
        }
    }

    if(isset($_POST['usage_value'])){
        foreach($_POST['usage_value'] as $id => $val){
            $id = intval($id);
            $val = floatval($val);
            $type = 'kg';
            $conn->query("
            UPDATE inventory 
            SET usage_value='$val', usage_type='$type'
            WHERE id='$id'
            ");
        }
    }

    header("Location: inventory.php?category=supplies#inventoryTable");
    exit();
}

/* SAVE ADDON PRICES */
if(isset($_POST['save_all_addons']) && isset($_POST['addon_price'])){
    foreach($_POST['addon_price'] as $id => $price){
        $conn->query("UPDATE addons SET price='$price' WHERE id='$id'");
    }

    header("Location: inventory.php?category=supplies&saved=1#addonsSection");
    exit();
}

if(isset($_POST['delete_selected']) && !empty($_POST['delete_ids'])){
    foreach($_POST['delete_ids'] as $id){
        $id = intval($id);
        $conn->query("DELETE FROM inventory WHERE id=$id");
    }

    header("Location: inventory.php?category=supplies#inventoryTable");
    exit();
}

/* DELETE ADDON */
if(isset($_POST['delete_addon'])){
    $id = $_POST['delete_addon'];
    $conn->query("DELETE FROM addons WHERE id=$id");

    header("Location: inventory.php?category=supplies&deleted=1#addonsSection");
    exit();
}

/* ADD SERVICE */
if(isset($_POST['add_service'])){
    $service = $_POST['service_name'];
    $price = $_POST['price_per_kg'];

    $conn->query("INSERT INTO services(service_name,price_per_kg)
    VALUES('$service','$price')");
}

/* SAVE ALL SERVICE PRICES */
if(isset($_POST['save_all_services']) && isset($_POST['service_price'])){
    foreach($_POST['service_price'] as $id => $price){
        $conn->query("UPDATE services SET price_per_kg='$price' WHERE id='$id'");
    }
}

/* DELETE SERVICE */
if(isset($_POST['delete_service'])){
    $id = intval($_POST['delete_service']);
    $conn->query("UPDATE services SET is_active = 0 WHERE id = $id");
}

if(isset($_POST['stock_in'])){
    $id = intval($_POST['id']);
    $qty = isset($_POST['qty']) ? floatval($_POST['qty']) : 0;

    if($qty > 0){
        $conn->query("UPDATE inventory SET quantity = quantity + $qty WHERE id = $id");
        $conn->query("UPDATE inventory SET notified = 0 WHERE id = $id AND quantity > critical_level");
    }

    header("Location: inventory.php?category=supplies#inventoryTable");
    exit();
}

/* STOCK OUT */
if(isset($_POST['stock_out'])){
    $id = intval($_POST['id']);
    $qty = isset($_POST['qty']) ? floatval($_POST['qty']) : 0;

    if($qty > 0){
        $conn->query("UPDATE inventory SET quantity = quantity - $qty WHERE id = $id");

        include "send_stock_alert.php";

        $check = $conn->query("
        SELECT * FROM inventory 
        WHERE quantity <= critical_level AND notified = 0
        ");

        $critical_items = [];

        while($row = $check->fetch_assoc()){
            $critical_items[] = $row;
        }

        if(count($critical_items) > 0){
            sendStockAlert($critical_items);

            foreach($critical_items as $item){
                $conn->query("UPDATE inventory SET notified = 1 WHERE id = ".$item['id']);
            }
        }
    }

    header("Location: inventory.php?category=supplies#inventoryTable");
    exit();
}

/* DELETE ITEM */
if(isset($_POST['delete_item'])){
    $id = $_POST['id'];
    $conn->query("DELETE FROM inventory WHERE id=$id");
}

/* ADD EXTRA SERVICE */
if(isset($_POST['add_extra_service'])){
    $name = $_POST['extra_service_name'];
    $price = $_POST['extra_service_price'];

    $conn->query("INSERT INTO extra_services(service_name,price)
    VALUES('$name','$price')");
}

/* SAVE ALL EXTRA SERVICE PRICES */
if(isset($_POST['save_all_extra_services']) && isset($_POST['extra_price'])){
    foreach($_POST['extra_price'] as $id => $price){
        $conn->query("UPDATE extra_services SET price='$price' WHERE id='$id'");
    }
}

/* DELETE EXTRA SERVICE */
if(isset($_POST['delete_extra_service'])){
    $id = $_POST['delete_extra_service'];
    $conn->query("DELETE FROM extra_services WHERE id=$id");
}

/* ADD LAUNDRY MODE */
if(isset($_POST['add_laundry_mode'])){
    $mode_name = $_POST['mode_name'];
    $mode_price_new = $_POST['mode_price_new'];

    $conn->query("INSERT INTO laundry_mode(mode_name,price)
    VALUES('$mode_name','$mode_price_new')");

    header("Location: inventory.php?category=services");
    exit();
}

/* DELETE LAUNDRY MODE */
if(isset($_POST['delete_laundry_mode'])){
    $id = $_POST['delete_laundry_mode'];
    $conn->query("DELETE FROM laundry_mode WHERE id='$id'");

    header("Location: inventory.php?category=services");
    exit();
}

/* SAVE LAUNDRY MODE PRICES */
if(isset($_POST['save_laundry_modes']) && isset($_POST['mode_price'])){
    foreach($_POST['mode_price'] as $id => $price){
        $conn->query("UPDATE laundry_mode SET price='$price' WHERE id='$id'");
    }
}

/* ADD LOYALTY RULE */
if(isset($_POST['add_loyalty_rule'])){
    $wash = intval($_POST['wash_required']);
    $discount = floatval($_POST['discount_amount']);

    $conn->query("INSERT INTO loyalty_rules (washes_required, discount_amount)
                VALUES ('$wash', '$discount')");

    header("Location: inventory.php?category=loyalty");
    exit();
}

/* DELETE LOYALTY RULE */
if(isset($_POST['delete_loyalty_rule'])){
    $id = intval($_POST['delete_loyalty_rule']);

    $conn->query("DELETE FROM loyalty_rules WHERE id='$id'");

    header("Location: inventory.php?category=loyalty");
    exit();
}

$alertItems = $conn->query("
SELECT * FROM inventory
WHERE quantity <= critical_level
ORDER BY item_name ASC
");

$items = $conn->query("
SELECT * FROM inventory 
ORDER BY category ASC, item_name ASC
");

$pricing         = $conn->query("SELECT * FROM inventory WHERE price IS NOT NULL ORDER BY id ASC");
$services        = $conn->query("SELECT * FROM services WHERE is_active = 1 ORDER BY id DESC");
$addons          = $conn->query("SELECT * FROM addons ORDER BY category ASC, addon_name ASC");
$extra_services  = $conn->query("SELECT * FROM extra_services ORDER BY id ASC");
$laundry_modes   = $conn->query("SELECT * FROM laundry_mode ORDER BY id ASC");
$loyalty_rules   = $conn->query("SELECT * FROM loyalty_rules ORDER BY washes_required ASC");

$maxLoyaltyWash = 0;

$maxLoyaltyRes = $conn->query("
    SELECT MAX(washes_required) AS max_wash
    FROM loyalty_rules
");

if($maxLoyaltyRes && $maxLoyaltyRes->num_rows > 0){
    $maxLoyaltyRow = $maxLoyaltyRes->fetch_assoc();
    $maxLoyaltyWash = intval($maxLoyaltyRow['max_wash'] ?? 0);
}

/* CUSTOMER LOYALTY DATA */
$loyalty = $conn->query("
SELECT 
    customer_name,
    MAX(phone) as phone,
    COUNT(*) as washes,
    SUM(loyalty_discount) as total_claimed_discount
FROM transactions
GROUP BY customer_name
ORDER BY washes DESC
");
?>

<div class="container">

<?php
$page_title = "Inventory Management";
$page_desc  = "Select a management category.";

if($category == "supplies"){
    $page_title = "Add Supplies";
}
elseif($category == "services"){
    $page_title = "Laundry Services";
}
elseif($category == "loyalty"){
    $page_title = "Loyalty Discount";
}
elseif($category == "types"){
    $page_title = "Laundry Type";
}

if($category == "supplies"){
    $page_title = "Add Supplies";
    $page_desc  = "Manage and add laundry supplies.";
}
elseif($category == "services"){
    $page_title = "Laundry Services";
    $page_desc  = "Manage laundry services and pricing.";
}
elseif($category == "loyalty"){
    $page_title = "Loyalty Discount";
    $page_desc  = "Manage customer loyalty rewards.";
}
?>

<div class="page-title">
    <h1><?= $page_title ?></h1>
    <p><?= $page_desc ?></p>
</div>

<?php if(isset($error_message)): ?>
<div class="system-alert">
⚠️ <?= $error_message ?>
</div>
<?php endif; ?>

<?php if($category==""): ?>

    <div class="inventory-cards">
        <a href="inventory.php?category=supplies" class="inventory-card">
            <div class="card-icon">🧴</div>
            <h3>Add Supplies</h3>
            <p>Manage laundry supplies and stock levels.</p>
        </a>

        <a href="inventory.php?category=services" class="inventory-card">
            <div class="card-icon">🧺</div>
            <h3>Laundry Services</h3>
            <p>Create and manage laundry services.</p>
        </a>

        <a href="laundry_types.php" class="inventory-card">
            <div class="card-icon">🧼</div>
            <h3>Laundry Types</h3>
            <p>Manage laundry types and price per kg / load.</p>
        </a>

        <a href="inventory.php?category=loyalty" class="inventory-card">
            <div class="card-icon">🎁</div>
            <h3>Loyalty Discount</h3>
            <p>Add, delete, and manage loyalty discount rules.</p>
        </a>
    </div>

<?php endif; ?>

<?php if($category=="supplies"): ?>

    <?php if($alertItems && $alertItems->num_rows > 0): ?>
        <div class="low-stock-alert">
            ⚠️ Warning! Some items are running low:
            <?php while($a = $alertItems->fetch_assoc()): ?>
                <strong><?= $a['item_name'] ?></strong>
                (<?= $a['quantity'] ?> <?= $a['unit'] ?>),
            <?php endwhile; ?>
        </div>
    <?php endif; ?>

    <a href="inventory.php" class="back-btn">← Back</a>

    <div class="section-actions">
        <button type="button" class="open-modal-btn" onclick="openSupplyModal()">+ Add Supply</button>
    </div>

    <form method="POST" action="">

        <div class="table-card inventory-table" id="inventoryTable">

            <div class="table-inner">
                <table class="table inventory-main-table">
                    <tr>
                        <th>Item</th>
                        <th>Category</th>
                        <th>Stock</th>
                        <th>Unit</th>
                        <th>Size</th>
                        <th style="width:170px;">Usage</th>
                        <th>Status</th>
                        <th colspan="2">Stock Control</th>
                        <th>Price</th>
                        <th>Delete</th>
                    </tr>

                    <?php
                    $currentCategory = '';

                    while($row = $items->fetch_assoc()):
                        $cat = strtoupper(trim($row['category'] ?? 'OTHERS'));

                        if($currentCategory != $cat):
                            $currentCategory = $cat;
                    ?>
                    <tr class="category-row">
                        <td colspan="11"><?= $currentCategory ?></td>
                    </tr>
                    <?php endif; ?>

                    <tr>

                    <td><strong><?= $row['item_name'] ?></strong></td>
                    <td>
                    <?php
                    $cat = $row['category'] ?? '';

                    if($cat == 'Detergent'){
                        echo "<span class='badge' style='background:#dbeafe;color:#1d4ed8;'>Detergent</span>";
                    }elseif($cat == 'Fabric Softener'){
                        echo "<span class='badge' style='background:#fce7f3;color:#be185d;'>Fabric Softener</span>";
                    }elseif($cat == 'Bleach'){
                        echo "<span class='badge' style='background:#f3f4f6;color:#374151;'>Bleach</span>";
                    }else{
                        echo "<span class='badge low'>Uncategorized</span>";
                    }
                    ?>
                    </td>
                    <td><?= number_format($row['quantity'],2) ?></td>
                    <td><?= $row['unit'] ?></td>

                    <td>
                        <?= (!empty($row['size_value']) && $row['size_value'] > 0)
                        ? $row['size_value'] . ' ' . $row['unit']
                        : '-' ?>
                    </td>

                    <td>
                        <div class="usage-box">
                            <input 
                                type="number"
                                step="0.01"
                                name="usage_value[<?= $row['id'] ?>]"
                                value="<?= $row['usage_value'] ?>"
                            >
                        </div> 
                    </td>

                    <td>
                    <?php
                    $critical = floatval($row['critical_level']);
                    $reorder  = floatval($row['reorder_level']);

                    if($row['quantity'] <= $critical){
                        echo "<span class='badge critical'>CRITICAL ⚠️</span>";
                    }
                    elseif($row['quantity'] <= $reorder){
                        echo "<span class='badge low'>LOW</span>";
                    }
                    else{
                        echo "<span class='badge ok'>OK</span>";
                    }
                    ?>
                    </td>

                    <td colspan="2">
                        <div class="stock-group">

                            <form method="POST" class="stock-form">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <input name="qty" type="number" class="qty-input" placeholder="+qty">
                                <button type="submit" name="stock_in" class="stock-btn plus">+</button>
                            </form>

                            <form method="POST" class="stock-form">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <input name="qty" type="number" class="qty-input" placeholder="-qty">
                                <button type="submit" name="stock_out" class="stock-btn minus">-</button>
                            </form>

                        </div>
                    </td>

                    <td>
                        <input
                        type="number"
                        step="0.01"
                        class="price-input small-price-input"
                        name="price[<?= $row['id'] ?>]"
                        value="<?= $row['price'] ?>">
                    </td>

                    <td>
                        <input type="checkbox" name="delete_ids[]" value="<?= $row['id'] ?>">
                    </td>

                    </tr>
                    <?php endwhile; ?>

                    <tr>
                        <td colspan="11" style="text-align:right;">

                            <button type="submit" name="save_all_supplies" class="save-btn">
                                Save All
                            </button>

                            <button type="submit" name="delete_selected" class="delete-btn"
                            onclick="return confirm('Delete selected items?')">
                                Delete Selected
                            </button>

                        </td>
                    </tr>
                </table>
            </div>
        </div>

    </form>

    <div class="panel section-card addon-section" id="addonsSection">

        <div class="section-head-row">
            <h3 class="section-title no-margin">Add Ons</h3>
            <button type="button" class="open-modal-btn small-modal-btn" onclick="openAddonModal()">+ Add On</button>
        </div>

        <form method="POST">
            <div class="table-inner">
                <table class="table pricing-table">
                    <tr>
                        <th style="width:25%">Category</th>
                        <th style="width:25%">Add On</th>
                        <th style="width:20%">Size</th>
                        <th style="width:15%">Price</th>
                        <th style="width:15%">Delete</th>
                    </tr>

                    <?php
                    $currentCategory = '';

                    while($a = $addons->fetch_assoc()):
                        $addonCategory = trim($a['category']);
                        $addonCategory = ($addonCategory != '') ? strtoupper($addonCategory) : 'OTHERS';

                        if($currentCategory != $addonCategory):
                            $currentCategory = $addonCategory;
                    ?>
                    <tr>
                        <td colspan="5" style="background:#e2e8f0;font-weight:700;">
                            <?= $currentCategory ?>
                        </td>
                    </tr>
                    <?php endif; ?>

                    <tr>
                        <td></td>
                        <td class="price-label"><?= $a['addon_name'] ?></td>

                        <td>
                            <?= (!empty($a['size_value']) && !empty($a['unit']))
                                ? $a['size_value'] . ' ' . $a['unit']
                                : '-' ?>
                        </td>

                        <td>
                            <input type="number"
                            step="0.01"
                            class="price-input"
                            name="addon_price[<?= $a['id'] ?>]"
                            value="<?= $a['price'] ?>">
                        </td>

                        <td>
                            <button type="submit"
                            name="delete_addon"
                            value="<?= $a['id'] ?>"
                            class="delete-btn">Delete</button>
                        </td>
                    </tr>

                    <?php endwhile; ?>

                    <tr>
                        <td colspan="4"></td>
                        <td class="save-delete-col">
                            <button type="submit" name="save_all_addons" class="save-btn">Save All</button>
                        </td>
                    </tr>
                </table>
            </div>
        </form>

    </div>

<?php endif; ?>

<?php if($category=="services"): ?>

<a href="inventory.php" class="back-btn">← Back</a>

<div class="panel section-card">

    <div class="section-head-row">
        <h3 class="section-title no-margin">Laundry Services</h3>
        <button type="button" class="open-modal-btn small-modal-btn" onclick="openServiceModal()">+ Add Service</button>
    </div>

    <form method="POST">
        <table class="table loyalty-rules-table service-table">
            <tr>
                <th>Service</th>
                <th>Price</th>
                <th>Select</th>
            </tr>

            <?php while($s = $services->fetch_assoc()): ?>
                <tr>
                    <td class="price-label"><strong><?= $s['service_name'] ?></strong></td>
                    <td>
                        <input
                        type="number"
                        step="0.01"
                        class="price-input small-price-input"
                        name="service_price[<?= $s['id'] ?>]"
                        value="<?= $s['price_per_kg'] ?>">
                    </td>
                    <td>
                        <button type="submit" name="delete_service" value="<?= $s['id'] ?>" class="delete-btn">Delete</button>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>

        <div class="save-all-container">
            <button type="submit" name="save_all_services" class="save-btn">Save All</button>
        </div>
    </form>
</div>

<div class="panel section-card">

    <div class="section-head-row">
        <h3 class="section-title no-margin">Extra Services</h3>
        <button type="button" class="open-modal-btn small-modal-btn" onclick="openExtraServiceModal()">+ Add Extra Service</button>
    </div>

    <form method="POST">
        <table class="table pricing-table compact-table service-table">
            <tr>
                <th>Extra Service</th>
                <th>Price</th>
                <th>Delete</th>
            </tr>

            <?php while($e = $extra_services->fetch_assoc()): ?>
                <tr>
                    <td class="price-label"><?= $e['service_name'] ?></td>
                    <td>
                        <input
                        type="number"
                        step="0.01"
                        class="service-price-box"
                        name="extra_price[<?= $e['id'] ?>]"
                        value="<?= $e['price'] ?>">
                    </td>
                    <td>
                        <button type="submit" name="delete_extra_service" value="<?= $e['id'] ?>" class="delete-btn">Delete</button>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>

        <div class="save-all-container">
            <button type="submit" name="save_all_extra_services" class="save-btn">Save All</button>
        </div>
    </form>
</div>

<div class="panel section-card">

    <div class="section-head-row">
        <h3 class="section-title no-margin">Laundry Mode</h3>
        <button type="button" class="open-modal-btn small-modal-btn" onclick="openLaundryModeModal()">+ Add Mode</button>
    </div>

    <form method="POST">
        <table class="table pricing-table compact-table">
            <tr>
                <th>Mode</th>
                <th>Price</th>
                <th>Delete</th>
            </tr>

            <?php while($m = $laundry_modes->fetch_assoc()): ?>
                <tr>
                    <td class="price-label"><?= $m['mode_name'] ?></td>
                    <td>
                        <input
                        type="number"
                        step="0.01"
                        class="service-price-box"
                        name="mode_price[<?= $m['id'] ?>]"
                        value="<?= $m['price'] ?>">
                    </td>
                    <td>
                        <button type="submit" name="delete_laundry_mode" value="<?= $m['id'] ?>" class="delete-btn">Delete</button>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>

        <div class="save-all-container">
            <button type="submit" name="save_laundry_modes" class="save-btn">Save All</button>
        </div>
    </form>
</div>

<?php endif; ?>

<?php if($category=="loyalty"): ?>

<a href="inventory.php" class="back-btn">← Back</a>

<div class="panel section-card">

    <div class="section-head-row">
        <h3 class="section-title no-margin">Change Loyalty Discount</h3>
        <button type="button" class="open-modal-btn small-modal-btn" onclick="openLoyaltyModal()">+ Add Loyalty</button>
    </div>

    <table class="table loyalty-rules-table">
        <tr>
            <th class="rule-trans">Transactions Required</th>
            <th class="rule-discount">Discount</th>
            <th class="rule-delete">Delete</th>
        </tr>

        <?php
        $rules_list = $conn->query("SELECT * FROM loyalty_rules ORDER BY washes_required ASC");
        while($r = $rules_list->fetch_assoc()):
        ?>
        <tr>
            <td class="price-label rule-trans"><?= $r['washes_required'] ?></td>
            <td class="rule-discount">₱<?= number_format($r['discount_amount'],2) ?></td>
            <td class="rule-delete">
                <form method="POST" style="display:inline;">
                    <button type="submit"
                            name="delete_loyalty_rule"
                            value="<?= $r['id'] ?>"
                            class="delete-btn">
                        Delete
                    </button>
                </form>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>

<div class="panel section-card">
    <h3 class="section-title">Customer Loyalty Points</h3>

    <table class="table loyalty-customers-table">
        <tr>
            <th class="col-name">Customer Name</th>
            <th class="col-phone">Phone</th>
            <th class="col-trans">Total Transactions</th>
            <th class="col-discount">Total Claimed Discount</th>
            <th class="col-status">Status</th>
        </tr>

        <?php while($c = $loyalty->fetch_assoc()): ?>
        <?php
            $actualWashes = intval($c['washes']);
            $displayWashes = $actualWashes;

            if($maxLoyaltyWash > 0 && $actualWashes > $maxLoyaltyWash){
                $displayWashes = $actualWashes % $maxLoyaltyWash;

                if($displayWashes == 0){
                    $displayWashes = $maxLoyaltyWash;
                }
            }
        ?>
        <tr>
            <td class="col-name"><?= $c['customer_name'] ?></td>
            <td class="col-phone"><?= $c['phone'] ?></td>

            <td class="col-trans"><?= $displayWashes ?></td>

            <td class="col-discount">
                <span class="badge ok">
                    ₱<?= number_format($c['total_claimed_discount'] ?? 0, 2) ?>
                </span>
            </td>

            <td class="col-status">
            <?php
                $washCount = $displayWashes;

                $exactRule = $conn->query("
                    SELECT * FROM loyalty_rules
                    WHERE washes_required = $washCount
                    LIMIT 1
                ");

                if($exactRule && $exactRule->num_rows > 0){
                    echo "
                    <div class='status-box'>
                        <span class='badge ok'>Eligible</span>
                    </div>
                    ";
                }else{
                    $nextRule = $conn->query("
                        SELECT * FROM loyalty_rules
                        WHERE washes_required > $washCount
                        ORDER BY washes_required ASC
                        LIMIT 1
                    ");

                    if($nextRule && $nextRule->num_rows > 0){
                        $next = $nextRule->fetch_assoc();
                        $remaining = intval($next['washes_required']) - $washCount;

                        echo "
                        <div class='status-box'>
                            <span class='badge low'>{$remaining} washes left</span>
                        </div>
                        ";
                    }else{
                        echo "<span class='badge low'>Max</span>";
                    }
                }
            ?>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>

<?php endif; ?>

</div>

<!-- ADD SUPPLY MODAL -->
<div id="supplyModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <div>
                <h3>Add New Supply</h3>
                <p>Fill out the supply details below.</p>
            </div>
            <button type="button" class="modal-close" onclick="closeSupplyModal()">×</button>
        </div>

        <form method="POST" action="" class="modal-form">

            <div class="modal-field modal-wide">
                <label>Item Name</label>
                <input name="name" placeholder="Enter supply name" required>
            </div>

            <div class="modal-field">
                <label>Category</label>
                <select name="category" required>
                    <option value="">Select Category</option>
                    <option value="Detergent">Detergent</option>
                    <option value="Fabric Softener">Fabric Softener</option>
                    <option value="Bleach">Bleach</option>
                </select>
            </div>

            <div class="modal-field">
                <label>Starting Quantity</label>
                <input name="qty" type="number" step="0.01" placeholder="0.00" required>
            </div>

            <div class="modal-field">
                <label>Unit</label>
                <select name="unit" required>
                    <option value="">Select Unit</option>
                    <option value="ml">ml</option>
                    <option value="L">L</option>
                    <option value="kg">kg</option>
                    <option value="grams">grams</option>
                    <option value="pcs">pcs</option>
                </select>
            </div>

            <div class="modal-field">
                <label>Size Value</label>
                <input name="size_value" type="number" step="0.01" placeholder="e.g. 30">
            </div>

            <div class="modal-field">
                <label>Usage</label>
                <input name="usage_value" type="number" step="0.01" placeholder="e.g. 5">
            </div>

            <div class="modal-field">
                <label>Reorder Level</label>
                <input name="reorder" type="number" placeholder="Low stock limit" required>
            </div>

            <div class="modal-field">
                <label>Critical Level</label>
                <input name="critical_level" type="number" placeholder="Critical limit" required>
            </div>

            <div class="modal-actions">
                <button type="button" class="modal-cancel" onclick="closeSupplyModal()">Cancel</button>
                <button type="submit" name="add_item" class="modal-save">Save Supply</button>
            </div>

        </form>
    </div>
</div>

<!-- ADD ON MODAL -->
<div id="addonModal" class="modal-overlay">
    <div class="modal-box small-modal">
        <div class="modal-header">
            <div>
                <h3>Add New Add On</h3>
                <p>Enter add-on name and price.</p>
            </div>
            <button type="button" class="modal-close" onclick="closeAddonModal()">×</button>
        </div>

        <form method="POST" class="modal-form one-column-form">

            <div class="modal-field">
                <label>Add On Name</label>
                <input name="addon_name" placeholder="Enter add-on name" required>
            </div>

            <div class="modal-field">
                <label>Price</label>
                <input type="number" step="0.01" name="addon_price_new" placeholder="0.00" required>
            </div>

            <div class="modal-actions">
                <button type="button" class="modal-cancel" onclick="closeAddonModal()">Cancel</button>
                <button type="submit" name="add_addon" class="modal-save">Save Add On</button>
            </div>

        </form>
    </div>
</div>

<!-- ADD SERVICE MODAL -->
<div id="serviceModal" class="modal-overlay">
    <div class="modal-box small-modal">
        <div class="modal-header">
            <div>
                <h3>Add Laundry Service</h3>
                <p>Enter service name and price.</p>
            </div>
            <button type="button" class="modal-close" onclick="closeServiceModal()">×</button>
        </div>

        <form method="POST" class="modal-form one-column-form">

            <div class="modal-field">
                <label>Service Name</label>
                <input name="service_name" placeholder="Enter service name" required>
            </div>

            <div class="modal-field">
                <label>Price</label>
                <input name="price_per_kg" type="number" step="0.01" placeholder="0.00" required>
            </div>

            <div class="modal-actions">
                <button type="button" class="modal-cancel" onclick="closeServiceModal()">Cancel</button>
                <button type="submit" name="add_service" class="modal-save">Save Service</button>
            </div>

        </form>
    </div>
</div>

<!-- ADD EXTRA SERVICE MODAL -->
<div id="extraServiceModal" class="modal-overlay">
    <div class="modal-box small-modal">
        <div class="modal-header">
            <div>
                <h3>Add Extra Service</h3>
                <p>Enter extra service name and price.</p>
            </div>
            <button type="button" class="modal-close" onclick="closeExtraServiceModal()">×</button>
        </div>

        <form method="POST" class="modal-form one-column-form">

            <div class="modal-field">
                <label>Extra Service Name</label>
                <input name="extra_service_name" placeholder="Enter extra service name" required>
            </div>

            <div class="modal-field">
                <label>Price</label>
                <input name="extra_service_price" type="number" step="0.01" placeholder="0.00" required>
            </div>

            <div class="modal-actions">
                <button type="button" class="modal-cancel" onclick="closeExtraServiceModal()">Cancel</button>
                <button type="submit" name="add_extra_service" class="modal-save">Save Extra Service</button>
            </div>

        </form>
    </div>
</div>

<!-- ADD LAUNDRY MODE MODAL -->
<div id="laundryModeModal" class="modal-overlay">
    <div class="modal-box small-modal">
        <div class="modal-header">
            <div>
                <h3>Add Laundry Mode</h3>
                <p>Enter mode name and price.</p>
            </div>
            <button type="button" class="modal-close" onclick="closeLaundryModeModal()">×</button>
        </div>

        <form method="POST" class="modal-form one-column-form">

            <div class="modal-field">
                <label>Mode Name</label>
                <input name="mode_name" placeholder="Enter mode name" required>
            </div>

            <div class="modal-field">
                <label>Price</label>
                <input name="mode_price_new" type="number" step="0.01" placeholder="0.00" required>
            </div>

            <div class="modal-actions">
                <button type="button" class="modal-cancel" onclick="closeLaundryModeModal()">Cancel</button>
                <button type="submit" name="add_laundry_mode" class="modal-save">Save Mode</button>
            </div>

        </form>
    </div>
</div>

<!-- ADD LOYALTY MODAL -->
<div id="loyaltyModal" class="modal-overlay">
    <div class="modal-box small-modal">
        <div class="modal-header">
            <div>
                <h3>Add Loyalty Discount</h3>
                <p>Enter required transactions and discount amount.</p>
            </div>
            <button type="button" class="modal-close" onclick="closeLoyaltyModal()">×</button>
        </div>

        <form method="POST" class="modal-form one-column-form">

            <div class="modal-field">
                <label>Transactions Required</label>
                <input type="number" name="wash_required" placeholder="e.g. 5" required>
            </div>

            <div class="modal-field">
                <label>Discount (₱)</label>
                <input type="number" step="0.01" name="discount_amount" placeholder="0.00" required>
            </div>

            <div class="modal-actions">
                <button type="button" class="modal-cancel" onclick="closeLoyaltyModal()">Cancel</button>
                <button type="submit" name="add_loyalty_rule" class="modal-save">Save Loyalty</button>
            </div>

        </form>
    </div>
</div>

<style>
:root{
    --primary:#2563eb;
    --primary-dark:#1d4ed8;
    --success:#16a34a;
    --success-soft:#dcfce7;
    --danger:#dc2626;
    --danger-soft:#fee2e2;
    --warning:#f59e0b;
    --warning-soft:#fef3c7;
    --text:#0f172a;
    --muted:#64748b;
    --border:#e5e7eb;
    --bg:#f4f7fb;
    --card:#ffffff;
    --shadow:0 8px 22px rgba(15,23,42,.06);
}

*{
    box-sizing:border-box;
}

body{
    background:var(--bg);
    color:var(--text);
}

.container{
    max-width:1350px;
    margin:0 auto;
    padding:16px;
}

.page-title{
    background:linear-gradient(135deg,#ffffff,#eff6ff);
    border:1px solid var(--border);
    border-radius:16px;
    padding:18px 22px;
    margin-bottom:18px;
    box-shadow:var(--shadow);
}

.page-title h1{
    margin:0;
    font-size:24px;
    font-weight:800;
    color:var(--text);
}

.page-title p{
    margin:5px 0 0;
    font-size:12px;
    color:var(--muted);
}

.system-alert{
    background:var(--danger-soft);
    color:#991b1b;
    padding:11px 14px;
    border-radius:12px;
    margin-bottom:15px;
    font-size:12px;
    font-weight:700;
    border:1px solid #fecaca;
}

.back-btn{
    display:inline-flex;
    align-items:center;
    gap:6px;
    margin-bottom:15px;
    background:var(--primary);
    color:white;
    padding:8px 14px;
    border-radius:9px;
    text-decoration:none;
    font-weight:700;
    font-size:12px;
    box-shadow:0 6px 14px rgba(37,99,235,.16);
    transition:.2s ease;
}

.back-btn:hover{
    background:var(--primary-dark);
    transform:translateY(-1px);
}

.section-actions{
    display:flex;
    justify-content:flex-end;
    margin-bottom:14px;
}

.open-modal-btn{
    background:var(--primary);
    color:white;
    border:none;
    padding:9px 15px;
    border-radius:10px;
    font-size:12px;
    font-weight:800;
    cursor:pointer;
    box-shadow:0 6px 14px rgba(37,99,235,.16);
    transition:.2s ease;
}

.open-modal-btn:hover{
    background:var(--primary-dark);
    transform:translateY(-1px);
}

.small-modal-btn{
    padding:8px 13px;
}

.inventory-cards{
    display:grid;
    grid-template-columns:repeat(4, minmax(0,1fr));
    gap:16px;
    margin-top:20px;
}

.inventory-card{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:16px;
    padding:24px 18px;
    box-shadow:var(--shadow);
    transition:.25s ease;
    text-decoration:none;
    color:var(--text);
    text-align:center;
    min-height:145px;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    position:relative;
    overflow:hidden;
}

.inventory-card::before{
    content:"";
    position:absolute;
    top:0;
    left:0;
    right:0;
    height:4px;
    background:linear-gradient(90deg,#2563eb,#22c55e);
}

.inventory-card:hover{
    transform:translateY(-5px);
    box-shadow:0 14px 34px rgba(15,23,42,.12);
    background:#fbfdff;
}

.card-icon{
    width:46px;
    height:46px;
    border-radius:14px;
    background:#eff6ff;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:26px;
    margin-bottom:10px;
}

.inventory-card h3{
    margin:3px 0;
    font-size:16px;
    font-weight:800;
    color:var(--text);
}

.inventory-card p{
    margin-top:6px;
    color:var(--muted);
    font-size:12px;
    line-height:1.4;
}

.panel,
.section-card{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:16px;
    padding:16px;
    box-shadow:var(--shadow);
    margin-bottom:16px;
}

.section-title{
    text-align:center;
    margin:0 0 16px;
    font-size:17px;
    font-weight:800;
    color:var(--text);
}

.no-margin{
    margin:0;
}

.section-head-row{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    margin-bottom:14px;
}

.low-stock-alert{
    background:#fff7ed;
    color:#9a3412;
    padding:11px 14px;
    border-radius:12px;
    margin:16px 0;
    font-size:12px;
    font-weight:700;
    border:1px solid #fed7aa;
    box-shadow:0 6px 16px rgba(249,115,22,.07);
}

.table-card{
    width:100%;
    padding:0;
    background:var(--card);
    border:1px solid var(--border);
    border-radius:16px;
    box-shadow:var(--shadow);
    overflow:hidden;
}

.table-inner{
    width:100%;
    overflow-x:auto;
}

.table,
.inventory-main-table,
.pricing-table,
.loyalty-rules-table,
.loyalty-customers-table{
    width:100%;
    border-collapse:collapse;
    table-layout:auto;
}

.inventory-main-table{
    min-width:950px;
}

.table th,
.inventory-main-table th,
.pricing-table th,
.loyalty-rules-table th,
.loyalty-customers-table th{
    background:#f8fafc;
    padding:9px 10px;
    font-size:11px;
    font-weight:900;
    color:#334155;
    border-bottom:1px solid var(--border);
    text-align:left;
    white-space:nowrap;
}

.table td,
.inventory-main-table td,
.pricing-table td,
.loyalty-rules-table td,
.loyalty-customers-table td{
    padding:8px 10px;
    font-size:12px;
    color:#111827;
    vertical-align:middle;
    border-top:1px solid #eef2f7;
}

.table tr:hover td,
.inventory-main-table tr:hover td,
.pricing-table tr:hover td,
.loyalty-rules-table tr:hover td,
.loyalty-customers-table tr:hover td{
    background:#f9fbff;
}

.category-row{
    background:#e2e8f0 !important;
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:1px;
    color:#334155;
}

.category-row td{
    background:#e2e8f0 !important;
    color:#334155;
    font-size:11px;
}

.inventory-main-table th:nth-child(3),
.inventory-main-table td:nth-child(3),
.inventory-main-table th:nth-child(4),
.inventory-main-table td:nth-child(4),
.inventory-main-table th:nth-child(5),
.inventory-main-table td:nth-child(5),
.inventory-main-table th:nth-child(6),
.inventory-main-table td:nth-child(6),
.inventory-main-table th:nth-child(7),
.inventory-main-table td:nth-child(7),
.inventory-main-table th:nth-child(8),
.inventory-main-table td:nth-child(8),
.inventory-main-table th:nth-child(9),
.inventory-main-table td:nth-child(9),
.inventory-main-table th:nth-child(10),
.inventory-main-table td:nth-child(10),
.inventory-main-table th:nth-child(11),
.inventory-main-table td:nth-child(11){
    text-align:center;
}

.table input,
.inventory-main-table input,
.pricing-table input{
    width:100%;
    padding:6px 8px;
    border-radius:8px;
    border:1px solid #cbd5e1;
    outline:none;
    box-sizing:border-box;
    background:#fff;
    font-size:12px;
    transition:.2s ease;
}

.table input:focus,
.inventory-main-table input:focus,
.pricing-table input:focus{
    border-color:var(--primary);
    box-shadow:0 0 0 3px rgba(37,99,235,.09);
}

.price-input,
.small-price-input{
    width:65px !important;
    max-width:75px;
    text-align:center !important;
}

.table td input[type="number"]{
    text-align:center;
}

.badge{
    padding:5px 9px;
    border-radius:999px;
    font-size:10.5px;
    font-weight:800;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:58px;
}

.badge.ok{
    background:var(--success-soft);
    color:#15803d;
}

.badge.low{
    background:var(--warning-soft);
    color:#92400e;
}

.badge.critical{
    background:var(--danger-soft);
    color:var(--danger);
}

.stock-group{
    display:flex;
    justify-content:center;
    align-items:center;
    gap:6px;
    flex-wrap:nowrap;
}

.stock-form{
    display:flex;
    flex-direction:row !important;
    align-items:center !important;
    justify-content:center;
    gap:5px;
    margin:0;
}

.qty-input{
    width:55px !important;
    min-width:55px;
    max-width:55px;
    height:30px;
    text-align:center;
    padding:5px;
    flex-shrink:0;
    font-size:11px;
}

.stock-btn{
    width:30px;
    height:30px;
    margin:0;
    padding:0;
    border:none;
    border-radius:8px;
    color:#fff;
    display:flex;
    align-items:center;
    justify-content:center;
    font-weight:900;
    cursor:pointer;
    transition:.2s ease;
}

.stock-btn.plus{
    background:#22c55e;
}

.stock-btn.minus{
    background:#ef4444;
}

.stock-btn:hover{
    transform:scale(1.05);
    filter:brightness(.95);
}

.save-btn{
    background:#22c55e;
    color:#fff;
    border:none;
    padding:8px 14px;
    border-radius:9px;
    cursor:pointer;
    font-size:12px;
    font-weight:800;
    box-shadow:0 6px 14px rgba(34,197,94,.14);
    transition:.2s ease;
}

.save-btn:hover{
    background:#16a34a;
    transform:translateY(-1px);
}

.delete-btn{
    background:#ef4444;
    color:#fff;
    border:none;
    padding:7px 13px;
    border-radius:9px;
    cursor:pointer;
    font-size:12px;
    font-weight:800;
    transition:.2s ease;
}

.delete-btn:hover{
    background:#dc2626;
    transform:translateY(-1px);
}

.addon-section{
    margin-top:20px;
}

.compact-form,
.service-form-grid{
    display:grid;
    grid-template-columns:1.4fr .8fr auto;
    gap:10px;
    align-items:end;
    margin-bottom:14px;
    background:#f8fafc;
    border:1px solid var(--border);
    border-radius:13px;
    padding:14px;
}

.compact-form label,
.service-form-grid label{
    font-size:11px;
    font-weight:800;
    color:#334155;
    margin-bottom:5px;
}

.compact-form input,
.service-form-grid input{
    width:100%;
    height:38px;
    padding:8px 10px;
    font-size:12px;
    border-radius:9px;
    border:1px solid #cbd5e1;
    background:#fff;
    outline:none;
}

.add-btn{
    background:var(--primary);
    color:#fff;
    border:none;
    padding:8px 14px;
    border-radius:9px;
    font-weight:800;
    cursor:pointer;
    min-width:135px;
    height:38px;
    font-size:12px;
    box-shadow:0 6px 14px rgba(37,99,235,.16);
    transition:.2s ease;
}

.add-btn:hover{
    background:var(--primary-dark);
    transform:translateY(-1px);
}

.service-price-box{
    width:75px !important;
    min-width:75px;
    max-width:75px;
    height:34px;
    padding:7px 9px;
    border-radius:9px;
    border:1px solid #cbd5e1;
    text-align:center;
    font-size:12px;
}

.save-all-container{
    display:flex;
    justify-content:center;
    margin-top:14px;
}

.service-table th,
.service-table td{
    vertical-align:middle;
}

.service-table th:nth-child(1),
.service-table td:nth-child(1){
    width:45%;
    text-align:left;
}

.service-table th:nth-child(2),
.service-table td:nth-child(2){
    width:25%;
    text-align:center;
}

.service-table th:nth-child(3),
.service-table td:nth-child(3){
    width:30%;
    text-align:center;
}

.loyalty-rules-table,
.loyalty-customers-table{
    background:#fff;
    border:1px solid var(--border);
    border-radius:14px;
    overflow:hidden;
}

.loyalty-rules-table .rule-trans{
    width:40%;
    text-align:left;
}

.loyalty-rules-table .rule-discount{
    width:35%;
    text-align:center;
}

.loyalty-rules-table .rule-delete{
    width:25%;
    text-align:center;
}

.loyalty-customers-table .col-name{
    width:22%;
    text-align:left;
}

.loyalty-customers-table .col-phone{
    width:16%;
    text-align:center;
}

.loyalty-customers-table .col-trans{
    width:16%;
    text-align:center;
}

.loyalty-customers-table .col-discount{
    width:22%;
    text-align:center;
}

.loyalty-customers-table .col-status{
    width:24%;
    text-align:center;
}

.status-box{
    display:flex;
    justify-content:center;
    align-items:center;
    gap:6px;
    flex-wrap:wrap;
}

.usage-box{
    display:flex !important;
    flex-direction:row !important;
    align-items:center;
    justify-content:center;
    gap:5px;
    white-space:nowrap;
}

.usage-box input{
    width:52px !important;
    min-width:52px;
    max-width:52px;
    height:30px;
    border-radius:8px;
    border:1px solid #cbd5e1;
    text-align:center;
    padding:4px;
    font-size:12px;
    box-sizing:border-box;
}

/* MODAL */
.modal-overlay{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(15,23,42,.55);
    z-index:99999;
    align-items:center;
    justify-content:center;
    padding:20px;
}

.modal-overlay.show{
    display:flex;
}

.modal-box{
    width:720px;
    max-width:100%;
    background:#fff;
    border-radius:18px;
    box-shadow:0 24px 70px rgba(0,0,0,.25);
    overflow:hidden;
    animation:modalPop .18s ease;
}

.small-modal{
    width:430px;
}

@keyframes modalPop{
    from{
        opacity:0;
        transform:scale(.96) translateY(8px);
    }
    to{
        opacity:1;
        transform:scale(1) translateY(0);
    }
}

.modal-header{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:15px;
    padding:18px 20px;
    background:linear-gradient(135deg,#eff6ff,#ffffff);
    border-bottom:1px solid var(--border);
}

.modal-header h3{
    margin:0;
    font-size:19px;
    font-weight:900;
    color:var(--text);
}

.modal-header p{
    margin:4px 0 0;
    font-size:12px;
    color:var(--muted);
}

.modal-close{
    width:32px;
    height:32px;
    border:none;
    border-radius:50%;
    background:#e5e7eb;
    color:#334155;
    font-size:22px;
    font-weight:800;
    cursor:pointer;
}

.modal-close:hover{
    background:#d1d5db;
}

.modal-form{
    padding:18px 20px 20px;
    display:grid;
    grid-template-columns:repeat(2, minmax(0,1fr));
    gap:12px;
}

.one-column-form{
    grid-template-columns:1fr;
}

.modal-wide{
    grid-column:span 2;
}

.modal-field{
    display:flex;
    flex-direction:column;
}

.modal-field label{
    font-size:11px;
    font-weight:800;
    color:#334155;
    margin-bottom:5px;
}

.modal-field input,
.modal-field select{
    width:100%;
    height:38px;
    padding:8px 10px;
    font-size:12px;
    border-radius:9px;
    border:1px solid #cbd5e1;
    outline:none;
}

.modal-field input:focus,
.modal-field select:focus{
    border-color:var(--primary);
    box-shadow:0 0 0 3px rgba(37,99,235,.10);
}

.modal-actions{
    grid-column:1 / -1;
    display:flex;
    justify-content:flex-end;
    gap:10px;
    margin-top:4px;
}

.modal-cancel{
    background:#e5e7eb;
    color:#111827;
    border:none;
    padding:9px 15px;
    border-radius:9px;
    font-size:12px;
    font-weight:800;
    cursor:pointer;
}

.modal-save{
    background:var(--primary);
    color:white;
    border:none;
    padding:9px 15px;
    border-radius:9px;
    font-size:12px;
    font-weight:800;
    cursor:pointer;
}

.modal-save:hover{
    background:var(--primary-dark);
}

@media(max-width:1200px){
    .inventory-cards{
        grid-template-columns:repeat(2,1fr);
    }
}

@media(max-width:900px){
    .container{
        padding:14px;
    }

    .compact-form,
    .service-form-grid{
        grid-template-columns:1fr;
    }

    .add-btn,
    .save-btn,
    .delete-btn{
        font-size:12px;
        padding:7px 12px;
        border-radius:8px;
    }

    .compact-form-btn{
        justify-content:flex-start;
    }

    .service-price-box{
        width:100% !important;
        max-width:100%;
    }

    .inventory-card{
        min-height:135px;
    }
}

@media(max-width:700px){
    .page-title{
        padding:16px;
    }

    .page-title h1{
        font-size:22px;
    }

    .inventory-cards{
        grid-template-columns:1fr;
    }

    .table{
        display:block;
        overflow-x:auto;
        white-space:nowrap;
    }

    .stock-group{
        gap:4px;
    }

    .qty-input{
        width:52px !important;
        min-width:52px;
    }

    .section-head-row{
        flex-direction:column;
        align-items:stretch;
    }

    .open-modal-btn{
        width:100%;
    }

    .modal-form{
        grid-template-columns:1fr;
    }

    .modal-wide{
        grid-column:span 1;
    }

    .modal-actions{
        flex-direction:column;
    }

    .modal-cancel,
    .modal-save{
        width:100%;
    }
}
</style>

<script>
function openSupplyModal(){
    document.getElementById("supplyModal").classList.add("show");
}

function closeSupplyModal(){
    document.getElementById("supplyModal").classList.remove("show");
}

function openAddonModal(){
    document.getElementById("addonModal").classList.add("show");
}

function closeAddonModal(){
    document.getElementById("addonModal").classList.remove("show");
}

function openServiceModal(){
    document.getElementById("serviceModal").classList.add("show");
}

function closeServiceModal(){
    document.getElementById("serviceModal").classList.remove("show");
}

function openExtraServiceModal(){
    document.getElementById("extraServiceModal").classList.add("show");
}

function closeExtraServiceModal(){
    document.getElementById("extraServiceModal").classList.remove("show");
}

function openLaundryModeModal(){
    document.getElementById("laundryModeModal").classList.add("show");
}

function closeLaundryModeModal(){
    document.getElementById("laundryModeModal").classList.remove("show");
}

function openLoyaltyModal(){
    document.getElementById("loyaltyModal").classList.add("show");
}

function closeLoyaltyModal(){
    document.getElementById("loyaltyModal").classList.remove("show");
}

window.addEventListener("click", function(e){
    const modals = [
        "supplyModal",
        "addonModal",
        "serviceModal",
        "extraServiceModal",
        "laundryModeModal",
        "loyaltyModal"
    ];

    modals.forEach(function(id){
        const modal = document.getElementById(id);
        if(e.target === modal){
            modal.classList.remove("show");
        }
    });
});

document.addEventListener("keydown", function(e){
    if(e.key === "Escape"){
        closeSupplyModal();
        closeAddonModal();
        closeServiceModal();
        closeExtraServiceModal();
        closeLaundryModeModal();
        closeLoyaltyModal();
    }
});

document.addEventListener("DOMContentLoaded", function () {
    const savedScroll = sessionStorage.getItem("inventoryScrollY");

    if (savedScroll && !window.location.hash) {
        window.scrollTo(0, parseInt(savedScroll));
        sessionStorage.removeItem("inventoryScrollY");
    }

    document.querySelectorAll("form").forEach(function(form){
        form.addEventListener("submit", function(){
            sessionStorage.setItem("inventoryScrollY", window.scrollY);
        });
    });

    if (window.location.hash) {
        setTimeout(function(){
            const target = document.querySelector(window.location.hash);
            if(target){
                target.scrollIntoView({
                    behavior: "smooth",
                    block: "start"
                });
            }
        }, 100);
    }
});
</script>