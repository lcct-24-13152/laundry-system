
<?php
session_start();
include "../config/db.php";
$laundryTypes = $conn->query("SELECT * FROM laundry_types ORDER BY type_name ASC");
include "navbar.php";

$detergents = $conn->query("
    SELECT id, item_name, category, unit, quantity, usage_value, usage_type, size_value
    FROM inventory
    WHERE category = 'Detergent'
    ORDER BY item_name ASC
");

$fabric_softeners = $conn->query("
    SELECT id, item_name, category, unit, quantity, usage_value, usage_type, size_value
    FROM inventory
    WHERE category = 'Fabric Softener'
    ORDER BY item_name ASC
");

$bleaches = $conn->query("
    SELECT id, item_name, category, unit, quantity, usage_value, usage_type, size_value
    FROM inventory
    WHERE category = 'Bleach'
    ORDER BY item_name ASC
");
// GET SUPPLIES BY CATEGORY
$loyalty_rules_data = [];
$getRules = $conn->query("SELECT * FROM loyalty_rules ORDER BY washes_required ASC");

while($r = $getRules->fetch_assoc()){
    $loyalty_rules_data[] = $r;
}
/* ✅ STEP 3 DITO */
$customerData = [];

$getCust = $conn->query("SELECT customer_name FROM transactions");

while($c = $getCust->fetch_assoc()){
    $customerData[] = strtolower(trim($c['customer_name']));
}


/* GET ALL SUPPLIES FROM INVENTORY */
$allSupplies = [];

$getPrices = $conn->query("SELECT item_name, price, unit FROM inventory ORDER BY id ASC");

while($p = $getPrices->fetch_assoc()){
    $allSupplies[] = $p;
}

if(!isset($_SESSION['admin'])){
header("Location: ../auth/login.php");
exit();
}

/* GET MAX RULE */
$maxRuleRes = $conn->query("SELECT MAX(washes_required) as max_wash FROM loyalty_rules");
$maxRow = $maxRuleRes->fetch_assoc();
$maxWash = intval($maxRow['max_wash'] ?? 0);

/* 🔥 FIX: INITIALIZE */
$totalTransactions = 0;

$customerNameTemp = ''; // optional safe

/* 🔥 GET COUNT BASED SA CUSTOMER (SAFE DEFAULT) */
if(isset($_GET['customer_name'])){
    $customerNameTemp = strtolower(trim($_GET['customer_name']));
}

$countRes = $conn->query("
SELECT COUNT(*) as total 
FROM transactions 
WHERE LOWER(TRIM(customer_name)) = '$customerNameTemp'
");

if($countRes && $countRes->num_rows > 0){
    $row = $countRes->fetch_assoc();
    $totalTransactions = intval($row['total']);
}

/* 🔥 CYCLE COUNT */
if($maxWash > 0){

    if($totalTransactions >= $maxWash){

        $displayLoyaltyCount = $totalTransactions % $maxWash;

        if($displayLoyaltyCount == 0){
            $displayLoyaltyCount = 1;
        }

    }else{
        $displayLoyaltyCount = $totalTransactions;
    }

}else{
    $displayLoyaltyCount = $totalTransactions;
}

$customerDiscountData = [];

$getDiscountData = $conn->query("
SELECT LOWER(TRIM(customer_name)) as customer_name, 
SUM(loyalty_discount) as total_discount
FROM transactions
GROUP BY LOWER(TRIM(customer_name))
");

while($d = $getDiscountData->fetch_assoc()){
    $customerDiscountData[] = $d;
} 
/* ===============================
SAVE TRANSACTION + INVENTORY DEDUCT
================================ */

if(isset($_POST['save'])){

$lname = mysqli_real_escape_string($conn,$_POST['lname']);
$fname = mysqli_real_escape_string($conn,$_POST['fname']);

$customer = trim($lname . ", " . $fname); // DISPLAY
$customer_key = strtolower(trim($lname . ", " . $fname)); // FOR SEARCH ONLY

/* 🔥 GENERATE OR GET CUSTOMER ID */

// ✅ GET INPUTS FIRST (IMPORTANT)
$phone = mysqli_real_escape_string($conn,$_POST['phone']);
$address = mysqli_real_escape_string($conn,$_POST['address'] ?? '');

$customer_name = trim(strtolower($lname . ", " . $fname));

// ✅ CHECK OR CREATE CUSTOMER
$check = $conn->query("
    SELECT id FROM customers 
    WHERE LOWER(TRIM(customer_name)) = '$customer_key'
    LIMIT 1
");

if($check && $check->num_rows > 0){
    $row = $check->fetch_assoc();
    $customer_id = $row['id'];
}else{
    $conn->query("
        INSERT INTO customers (customer_name, phone, address)
        VALUES ('$customer_name', '$phone', '$address')
    ");
    $customer_id = $conn->insert_id;
}

$addons_data = !empty($_POST['addons_data']) ? $_POST['addons_data'] : "None";

$extra_service_name = mysqli_real_escape_string($conn,$_POST['extra_service_name']);
$extra_service_price = floatval($_POST['extra_service_price']);

$final_total_from_form = floatval($_POST['total_amount']);
$subtotal = $final_total_from_form;
$total_amount = $final_total_from_form;

$downpayment = floatval($_POST['downpayment'] ?? 0);
$balance = floatval($_POST['balance'] ?? 0);

$service_id = $_POST['service_id'];
$weight = floatval($_POST['weight'] ?? 0);

$service_price = floatval($_POST['service_price'] ?? 0);
$delivery = mysqli_real_escape_string($conn, $_POST['delivery'] ?? 'Pick-up');

$payment_method = mysqli_real_escape_string($conn, $_POST['payment_method'] ?? '');

if($payment_method == ""){
    $payment_method = "Unspecified";
}
$payment_type = mysqli_real_escape_string($conn, $_POST['payment_type'] ?? '');

$laundry_type = mysqli_real_escape_string($conn, $_POST['laundry_type'] ?? 'None');
$laundry_mode = mysqli_real_escape_string($conn, $_POST['laundry_mode'] ?? '');
$load = mysqli_real_escape_string($conn,$_POST['load'] ?? '');
$min_kg_used = floatval($_POST['min_kg_used'] ?? 0);
$max_kg_used = floatval($_POST['max_kg_used'] ?? 0);

$gcash_reference = $_POST['gcash_reference'] ?? '';
$gcash_receipt = '';

if($payment_method == "GCash"){

if(!empty($_FILES['gcash_receipt']['name'])){

$gcash_receipt = time().'_'.$_FILES['gcash_receipt']['name'];

move_uploaded_file(
$_FILES['gcash_receipt']['tmp_name'],
"../uploads/".$gcash_receipt
);

}

}


/* ===============================
LOYALTY COUNT (DYNAMIC RESET)
================================ */

$countRes = $conn->query("
SELECT loyalty_count 
FROM transactions 
WHERE customer_id = '$customer_id'
ORDER BY id DESC 
LIMIT 1
");

$discount = 0;
$discount_percent = 0;
$loyaltyCount = 0;
$displayLoyaltyCount = 0;

if($countRes && $countRes->num_rows > 0){
    $row = $countRes->fetch_assoc();
    $prevCount = intval($row['loyalty_count']);
    $totalTransactions = $prevCount + 1;
}else{
    $totalTransactions = 1;
}

/* KUHANIN ANG PINAKAMATAAS NA RULE */
$maxRuleRes = $conn->query("
    SELECT MAX(washes_required) as max_wash
    FROM loyalty_rules
");

$maxRow = $maxRuleRes->fetch_assoc();
$maxWash = intval($maxRow['max_wash'] ?? 0);

/* DYNAMIC CYCLE */
if($maxWash > 0){

    if($totalTransactions > $maxWash){
        // RESET CYCLE
        $displayLoyaltyCount = $totalTransactions % $maxWash;

        if($displayLoyaltyCount == 0){
            $displayLoyaltyCount = 1; // 🔥 BACK TO 1
        }

    }else{
        // NORMAL COUNT
        $displayLoyaltyCount = $totalTransactions;
    }

}else{
    $displayLoyaltyCount = $totalTransactions;
}

/* CHECK KUNG MAY KATUMBAS NA DISCOUNT ANG CURRENT CYCLE */
$getRule = $conn->query("
    SELECT discount_amount 
    FROM loyalty_rules
    WHERE washes_required = '$displayLoyaltyCount'
    LIMIT 1
");

if($getRule && $getRule->num_rows > 0){
    $rule = $getRule->fetch_assoc();
    $discount_percent = floatval($rule['discount_amount']);
}else{
    $discount_percent = 0;
}

/* ITO ANG MASE-SAVE SA DB */
$loyaltyCount = $displayLoyaltyCount;


/* COMPUTE DISCOUNT PARA SA DB */
$discount = floatval($discount_percent); // FIXED PESO

$subtotal = $final_total_from_form; // original
$total_amount = $final_total_from_form - $discount; // 🔥 DITO NABABAWAS

if($total_amount < 0){
    $total_amount = 0;
}
/* ===============================
PAYMENT STATUS
================================ */

if($payment_type == "Downpayment"){

    $payment_status = "Downpayment";
    $downpayment = floatval($_POST['downpayment'] ?? 0);
    $balance = $total_amount - $downpayment;

    if($balance < 0){
        $balance = 0;
    }

}elseif($payment_type == "Fully Paid"){

    $payment_status = "Fully Paid";
    $downpayment = $total_amount;
    $balance = 0;

}else{

    // ✅ NONE SELECTED
    $payment_status = "Pending";
    $downpayment = 0;
    $balance = $total_amount;

}
$tracking_code = "CML".$customer_id.time();

$billableKG = ($weight < 6) ? 6 : $weight;

/* DEFAULT SUPPLIES ML USED */
$detergentML = 0;
$fabconML = 0;
$bleachML = 0;

$selected_detergent = mysqli_real_escape_string($conn, $_POST['selected_detergent'] ?? '');
$selected_fabcon    = mysqli_real_escape_string($conn, $_POST['selected_fabcon'] ?? '');
$selected_bleach    = mysqli_real_escape_string($conn, $_POST['selected_bleach'] ?? '');

$supplies_summary = mysqli_real_escape_string($conn, $_POST['supplies_full'] ?? 'None');

/* ===============================
INVENTORY DEDUCT
================================ */
$detergentML = 0;
$fabconML = 0;
$bleachML = 0;

if(!empty($selected_detergent)){
    $det = $conn->query("
SELECT usage_value, usage_type 
FROM inventory 
WHERE item_name='$selected_detergent' 
LIMIT 1
")->fetch_assoc();

$usageValue = floatval($det['usage_value'] ?? 0);
$usageType = $det['usage_type'] ?? 'kg';

if($usageType == "kg"){
    $detergentML = $billableKG * $usageValue;
}else{
    $detergentML = $billableKG * $usageValue;
}

    $conn->query("UPDATE inventory 
                  SET quantity = quantity - $detergentML
                  WHERE item_name = '$selected_detergent'");
}

if(!empty($selected_fabcon)){
    $fab = $conn->query("
    SELECT usage_value, usage_type 
    FROM inventory 
    WHERE item_name='$selected_fabcon' 
    LIMIT 1
    ")->fetch_assoc();

    $usageValue = floatval($fab['usage_value'] ?? 0);

    $fabconML = $billableKG * $usageValue;

    $conn->query("UPDATE inventory 
                  SET quantity = quantity - $fabconML
                  WHERE item_name = '$selected_fabcon'");
}
if(!empty($selected_bleach)){
    $ble = $conn->query("
    SELECT usage_value, usage_type 
    FROM inventory 
    WHERE item_name='$selected_bleach' 
    LIMIT 1
    ")->fetch_assoc();

    $usageValue = floatval($ble['usage_value'] ?? 0);

    $bleachML = $billableKG * $usageValue;

    $conn->query("UPDATE inventory 
                  SET quantity = quantity - $bleachML
                  WHERE item_name = '$selected_bleach'");
}
/* ===============================
SAVE TRANSACTION
================================ */

$date_received = date("Y-m-d H:i:s");

$conn->query("INSERT INTO transactions
(customer_id,customer_name,phone,address,tracking_code,service_id,weight,detergent,fabcon,bleach,
addons,extra_service,extra_service_price,subtotal,loyalty_discount,total,
downpayment,balance,payment_method,payment_status,date_received,loyalty_count,
gcash_reference,gcash_receipt,laundry_type,min_kg_used,max_kg_used,laundry_mode,load_type,supplies_summary)
VALUES
('$customer_id','$customer','$phone','$address','$tracking_code','$service_id','$weight',
'$detergentML','$fabconML','$bleachML',
'$addons_data','$extra_service_name','$extra_service_price',
'$subtotal','$discount','$total_amount','$downpayment','$balance',
'$payment_method','$payment_status','$date_received','$loyaltyCount',
'$gcash_reference','$gcash_receipt','$laundry_type','$min_kg_used','$max_kg_used','$laundry_mode','$load','$supplies_summary')");

$last_id = $conn->insert_id;

header("Location: receipt.php?id=".$last_id);
exit();

}

?>
<style>
.addon-card select{
    width:180px;
    height:35px;
    border-radius:8px;
    border:1px solid #ccc;
    padding:0 10px;
    background:#fff;
}
.transaction-layout{
display:grid;
grid-template-columns: 2fr 1fr;
gap:25px;
align-items:start;
width:100%;
}
.panel{
    background:#fff;
    padding:20px;
    border-radius:12px;
    box-shadow:none; /* 🔥 tanggal lahat ng shadow */
}

.receipt-preview{
position:sticky;
top:20px;
height:fit-content;
}

/* ADD ONS UI */
.addons-grid{
    display:flex;
    flex-direction:column;
    gap:10px;
    width:100%;
}

.addon-card input{
width:70px;
text-align:center;
}

.addons-grid {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.addon-card{
    display:flex;
    align-items:center;
    justify-content:space-between;
    width:100%;
    min-height:60px;
    padding:10px 15px;
    border-radius:8px;
    background:#f5f6fa;
    box-sizing:border-box;
    gap:15px;
}

.addon-card > div{
    display:flex;
    align-items:center;
    gap:10px;
    flex:1;
}

.addonTypeSelect{
    width:180px; /* 🔥 same width lahat */
    height:38px;
    border-radius:8px;
    border:1px solid #ccc;
    padding:0 10px;
    background:#fff;
}
/* CHECKBOX SIDE */
.addon-card label {
    display: flex;
    align-items: center;
    gap: 8px;
}

/* INPUT SIDE (ADD ONS) */
.addon-card input[type="number"]{
    width:70px;
    height:38px;
    text-align:center;
    border-radius:8px;
    border:1px solid #ccc;
}

.addon-card span{
    min-width:150px;
    font-weight:500;
}
#suppliesPreviewBox{
line-height:1.7;
color:#334155;
font-size:14px;
}


.form-row{
    display: grid;
    grid-template-columns: repeat(3, 1fr); /* ✅ tamang value */
    gap: 20px;
    width: 100%;
}
.form-group{
    width: 100%;
    display: flex;
    flex-direction: column;
}

.form-group .label{
    margin-bottom: 6px;
    font-size: 13px;
    font-weight: 500;
}

/* INPUT FIX */
.form-group input{
    width: 100%;
    height: 50px;
    padding: 10px 15px;
    box-sizing: border-box;
}

.pos-form{
    width: 100%;
}

.panel{
    width: 100%;
}

.container{
    width: 100%;
    max-width: 100%; /* 🔥 alisin limit */
}
.transaction-layout{
    display: grid;
    grid-template-columns: 3fr 1.2fr; /* 🔥 mas malaki form */
    gap: 25px;
}

.name-row{
    width: 100%;
}

.name-row .form-row{
    display: grid;
    grid-template-columns: repeat(3, minmax(0,1fr));
    gap: 20px;
    width: 100%;
}

.service-box select{
    width: 100%;
    max-width: 300px;
    display: block;
}

.service-box{
    background: transparent !important;
    box-shadow: none !important;
    border: none !important;
    padding: 0 !important;
}

.double-box{
    display:flex;
    gap:20px;
    align-items:flex-start;
    flex-wrap:wrap;
}

.double-box .service-box{
    flex:1;
    min-width:320px;
    max-width:500px;
}

.gcash-row{
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    width: 100%;
}

.gcash-group{
    display: flex;
    flex-direction: column;
}

.gcash-group input{
    width: 100%;
    height: 50px;
    padding: 10px;
}
</style>

<div class="container">

<div class="page-title">
<h1>Laundry Transaction</h1>
<p>Fast cashier screen for encoding customer laundry.</p>
</div>

<div class="transaction-layout">

<!-- LEFT SIDE FORM -->
<div class="panel pos-form">

<form method="POST" enctype="multipart/form-data" class="form">

<div class="name-row">

<div class="form-row">

<div class="form-group">
<div class="label">Last Name</div>
<input id="lname" name="lname" list="customerList" placeholder="Select or type last name" required>

<datalist id="customerList">
<?php
$customers = $conn->query("SELECT DISTINCT customer_name, phone FROM transactions ORDER BY customer_name ASC");

while($cust = $customers->fetch_assoc()){
    echo "<option value='".$cust['customer_name']."'>";
}
?>
</datalist>
</div>

<div class="form-group">
<div class="label">First Name</div>
<input id="fname" name="fname" list="customerList" placeholder="Select or type first name" required>
</div>

<div class="form-group">
<div class="label">Phone Number</div>
<input id="phone" name="phone" value="+63" required>
</div>

</div>

</div>

<div class="form-wide">

<div>
<div class="label">Delivery</div>
<select id="delivery" name="delivery">
<option value="Pick-up">Pick-up</option>
<option value="Delivery">Delivery (+₱50)</option>
</select>
</div>

<div id="addressBox" style="display:none;">
<div class="label">Delivery Address</div>
<input id="address" name="address">
</div>

<div class="service-box">
<div class="label">Select Service</div>

<select id="service" name="service_id" required>
    <option value="">Select Service</option>

    <?php
    $res = $conn->query("
        SELECT * 
        FROM services 
        WHERE is_active = 1 
        ORDER BY service_name ASC
    ");

    while($row = $res->fetch_assoc()):
    ?>

    <option 
        value="<?= $row['id'] ?>" 
        data-price="<?= $row['price_per_kg'] ?>">
        
        <?= $row['service_name'] ?> (₱<?= number_format($row['price_per_kg'],2) ?>)

    </option>

    <?php endwhile; ?>
</select>

</div>
</div>

<div class="double-box">

<div class="service-box">
<div class="label">Laundry Mode</div>

<select id="laundryMode" name="laundry_mode">
<?php
$modes = $conn->query("SELECT * FROM laundry_mode ORDER BY id ASC");
while($mode = $modes->fetch_assoc()){
?>
<option value="<?= $mode['mode_name'] ?>" data-price="<?= $mode['price'] ?>">
<?= $mode['mode_name'] ?> (+₱<?= $mode['price'] ?>)
</option>
<?php } ?>
</select>

</div>

</div>

<div class="double-box">

    <!-- LEFT: LAUNDRY TYPE -->
    <div class="service-box">
        <div class="label">Laundry Type</div>

        <div class="addons-grid">

        <?php
        $types = $conn->query("SELECT * FROM laundry_types ORDER BY id ASC");

        while($type = $types->fetch_assoc()){
        ?>

        <div class="addon-card">

            <label style="flex:1">

                <input
type="checkbox"
class="laundryType"
data-type="<?= htmlspecialchars($type['pricing_type'] ?? 'kg') ?>"
data-pricekg="<?= (float)($type['price_per_kg'] ?? 0) ?>"
data-priceload="<?= (float)($type['price_per_load'] ?? 0) ?>"
data-name="<?= htmlspecialchars($type['type_name'] ?? '') ?>"
data-min="<?= (float)($type['min_kg'] ?? 0) ?>"
data-max="<?= (float)($type['max_kg'] ?? 0) ?>">

               <?= $type['type_name'] ?>
            </label>

            <div style="display:flex; gap:5px;">

<input
type="number"
class="typeWeight"
placeholder="0"
min="0"
step="0.1"
style="width:60px"
disabled>

<select class="typeUnit" style="height:35px; border-radius:8px;">
    <option value="kg">kg</option>
    <option value="load">Load</option>
</select>

</div>

        </div>

        <?php } ?>

        </div>
    </div>

    <!-- RIGHT: LAUNDRY SUPPLIES -->
    <div class="service-box">
        <div class="label">Laundry Supplies Used</div>

        <div class="addons-grid">

            <!-- DETERGENT -->
            <div class="addon-card">
                <span>Detergent</span>

              <select class="supplySelect" id="detergent_select" data-type="detergent">
    <option value="">Select Detergent</option>

    <?php
    $detergents = $conn->query("SELECT * FROM inventory WHERE LOWER(category)='detergent' ORDER BY item_name ASC");
    while($d = $detergents->fetch_assoc()){
    ?>

    <option 
        value="<?= htmlspecialchars($d['item_name']) ?>"
        data-price="<?= (float)$d['price'] ?>"
        data-size="<?= htmlspecialchars($d['size_value'] ?? 0) ?>"
        data-unit="<?= htmlspecialchars($d['unit'] ?? '') ?>"
        data-usage="<?= isset($d['usage_value']) ? $d['usage_value'] : 0 ?>"
        data-usage-type="<?= htmlspecialchars($d['usage_type'] ?? 'kg') ?>"
    >
        <?= htmlspecialchars($d['item_name']) ?> 
        (<?= $d['size_value'] ?> <?= $d['unit'] ?>) 
        ₱<?= number_format($d['price'], 2) ?>
    </option>

    <?php } ?>
</select>
<input type="number" class="supplyQty" id="detergent_qty" value="0" min="0">
            </div>

            <!-- FABCON -->
            <div class="addon-card">
                <span>Fabric Softener</span>
                <select class="supplySelect" id="fabcon_select" data-type="fabcon">
    <option value="">Select Fabric Softener</option>

    <?php
    $fabcons = $conn->query("SELECT * FROM inventory WHERE LOWER(category)='fabric softener' ORDER BY item_name ASC");
    while($f = $fabcons->fetch_assoc()){
    ?>

    <option 
        value="<?= htmlspecialchars($f['item_name']) ?>"
        data-price="<?= (float)$f['price'] ?>"
        data-size="<?= htmlspecialchars($f['size_value'] ?? 0) ?>"
        data-unit="<?= htmlspecialchars($f['unit'] ?? '') ?>"
        data-usage="<?= isset($f['usage_value']) ? $f['usage_value'] : 0 ?>"
        data-usage-type="<?= htmlspecialchars($f['usage_type'] ?? 'kg') ?>"
    >
        <?= htmlspecialchars($f['item_name']) ?> 
        (<?= $f['size_value'] ?> <?= $f['unit'] ?>) 
        ₱<?= number_format($f['price'], 2) ?>
    </option>

    <?php } ?>
</select>
<input type="number" class="supplyQty" id="fabcon_qty" value="0" min="0">
            </div>

            <!-- BLEACH -->
            <div class="addon-card">
                <span>Bleach</span>
               <select class="supplySelect" id="bleach_select" data-type="bleach">
    <option value="">Select Bleach</option>

    <?php
    $bleaches = $conn->query("SELECT * FROM inventory WHERE LOWER(category)='bleach' ORDER BY item_name ASC");
    while($b = $bleaches->fetch_assoc()){
    ?>

    <option 
        value="<?= htmlspecialchars($b['item_name']) ?>"
        data-price="<?= (float)$b['price'] ?>"
        data-size="<?= htmlspecialchars($b['size_value'] ?? 0) ?>"
        data-unit="<?= htmlspecialchars($b['unit'] ?? '') ?>"
        data-usage="<?= isset($b['usage_value']) ? $b['usage_value'] : 0 ?>"
        data-usage-type="<?= htmlspecialchars($b['usage_type'] ?? 'kg') ?>"
    >
        <?= htmlspecialchars($b['item_name']) ?> 
        (<?= $b['size_value'] ?> <?= $b['unit'] ?>) 
        ₱<?= number_format($b['price'], 2) ?>
    </option>

    <?php } ?>
</select>
<input type="number" class="supplyQty" id="bleach_qty" value="0" min="0">
            </div>

        </div>

        <div id="suppliesPreviewBox" style="margin-top:10px;"></div>
    </div>

</div>

<div class="double-box">

<!-- EXTRA SERVICE -->
<div class="service-box">

<div class="label">Extra Service</div>

<div class="addons-grid">

<?php
$extra_services = $conn->query("SELECT * FROM extra_services");
while($ex = $extra_services->fetch_assoc()){
?>

<div class="addon-card">

<label style="flex:1">
<input
type="checkbox"
class="extraServiceCheck"
data-price="<?= (float)$ex['price'] ?>"
data-name="<?= htmlspecialchars($ex['service_name']) ?>">

<?= $ex['service_name'] ?> (+₱<?= $ex['price'] ?>)
</label>

<input type="number"
class="extraQty"
value="0"
min="0">

</div>

<?php } ?>

</div>

</div>

<!-- ADD ONS -->
<!-- ADD ONS -->
<div class="service-box">

<div class="label">Add Ons</div>

<div class="addons-grid">

<?php
$addons = $conn->query("
SELECT 
    a.*, 
    i.size_value, 
    i.unit 
FROM addons a
LEFT JOIN inventory i 
ON LOWER(a.addon_name) = LOWER(i.item_name)
ORDER BY a.category ASC, a.addon_name ASC
");

$addonGroups = [];

while($a = $addons->fetch_assoc()){
    $cat = trim($a['category']);
    if($cat == '') $cat = 'Others';

    $addonGroups[$cat][] = $a;
}
?>

<div class="addons-grid">

<?php foreach($addonGroups as $category => $items): ?>

<div class="addon-card">

    <span style="min-width:150px;">
        <?= strtoupper($category) ?>
    </span>

    <select class="addonDropdown" data-category="<?= $category ?>">
        <option value="">Select <?= $category ?></option>

        <?php foreach($items as $item): ?>

           <option 
    value="<?= $item['id'] ?>"
    data-price="<?= $item['price'] ?>"
    data-name="<?= htmlspecialchars($item['addon_name']) ?>"
    data-size="<?= $item['size_value'] ?>"
    data-unit="<?= $item['unit'] ?>"
>

<?= $item['addon_name'] ?> 
(<?= $item['size_value'] ?> <?= $item['unit'] ?>) 
₱<?= number_format($item['price'],2) ?>

</option>

        <?php endforeach; ?>

    </select>

    <input type="number"
           class="addonQtyDropdown"
           value="0"
           min="0">

</div>

<?php endforeach; ?>

</div>

</div>

</div>
</div>

<div class="form-wide">

<div>
<div class="label">Payment Method</div>
<select id="paymentMethod" name="payment_method">
<option value="">None</option>
<option value="Cash">Cash</option>
<option value="GCash">GCash</option>
</select>
</div>

<div>
<div class="label">Payment Type</div>
<select id="ptype" name="payment_type">
<option value="">None</option>
<option value="Fully Paid">Fully Paid</option>
<option value="Downpayment">Downpayment</option>
</select>
</div>

<div id="downBox" style="display:none;">
<div class="label">Downpayment</div>
<input id="downpay" name="downpayment" type="number" value="0">
</div>

</div>

<div id="gcashBox" style="display:none;">

<div class="gcash-row">

<div class="gcash-group">
<div class="label">GCash Reference Number</div>
<input name="gcash_reference">
</div>

<div class="gcash-group">
<div class="label">Upload GCash Receipt</div>
<input type="file" name="gcash_receipt" accept="image/*">
</div>

</div>

</div>

<div class="total-row">

<div class="total-card">
<small>TOTAL AMOUNT</small>
<h1 id="totalPreview">₱0.00</h1>
</div>

<input type="hidden" name="service_price" id="service_price">
<input type="hidden" name="customer_id" id="customer_id">
<input type="hidden" name="weight" id="weight">
<input type="hidden" name="laundry_type" id="laundry_type">
<input type="hidden" name="min_kg_used" id="min_kg_used">
<input type="hidden" name="max_kg_used" id="max_kg_used">
<input type="hidden" name="service_id" id="service_id">


<input type="hidden" name="addons_data" id="addons_data">
<input type="hidden" name="extra_service_name" id="extra_service_name">
<input type="hidden" name="extra_service_price" id="extra_service_price">
<input type="hidden" name="total_amount" id="total_amount">
<input type="hidden" name="load" id="load">
<input type="hidden" id="tracking_code">
<input type="hidden" id="customer_id_hidden">
<input type="hidden" name="downpayment" id="downpayment_hidden">
<input type="hidden" name="balance" id="balance_hidden">
<input type="hidden" name="selected_detergent" id="selected_detergent">
<input type="hidden" name="selected_fabcon" id="selected_fabcon">
<input type="hidden" name="selected_bleach" id="selected_bleach">
<input type="hidden" name="supplies_full" id="supplies_full">
<button class="btn big" name="save">
SAVE<br>TRANSACTION
</button>


</div>

</form>

</div>

<!-- RIGHT SIDE RECEIPT PREVIEW -->

<div class="panel receipt-preview">

<h2>Receipt Preview</h2>
<hr>

<p><strong>Customer:</strong> <span id="r_customer">—</span></p>
<p><strong>Customer ID:</strong> <span id="r_customer_id">—</span></p>

<!-- QR REMOVED -->
<p>
<strong>Laundry Transactions:</strong>
<span id="r_count">0</span>
</p>

<p>
<strong>Total Loyalty Earned:</strong>
<span id="r_total_loyalty">₱0.00</span>
</p>

<p><strong>Phone:</strong> <span id="r_phone">—</span></p>
<p><strong>Laundry Type:</strong> <span id="r_type">—</span></p>
<p><strong>Load / Weight:</strong> <span id="r_weight">—</span></p>
<p><strong>Service:</strong> <span id="r_service">—</span></p>
<p><strong>Laundry Mode:</strong> <span id="r_mode">—</span></p>

<p><strong>Delivery:</strong> <span id="r_delivery">—</span></p>
<p id="r_address_row" style="display:none;">
<strong>Address:</strong> <span id="r_address">—</span>
</p>

<p><strong>Payment:</strong> <span id="r_payment">—</span></p>

<p><strong>Add Ons:</strong> <span id="r_addons">None</span></p>
<p><strong>Extra Service:</strong> <span id="r_extra_service">None</span></p>

<p><strong>Laundry Supplies:</strong> <span id="r_supplies">None</span></p>


<p id="loyaltyRow" style="display:none;">
<strong>Loyalty Discount:</strong> <span id="r_loyalty">₱0.00</span>
</p>


<hr>

<h3>Total: <span id="r_total">₱0.00</span></h3>

<p id="downPreview" style="display:none;">  
Downpayment: <span id="r_down"></span>
</p>

<p id="balancePreview" style="display:none;">
Balance: <span id="r_balance"></span>
</p>

</div>

</div>
</div>

<script>
   
    const phoneInput = document.getElementById("phone");

phoneInput.addEventListener("input", () => {
    if(!phoneInput.value.startsWith("+63")){
        phoneInput.value = "+63";
    }
}); 
const customerData = <?= json_encode($customerData ?? []) ?>;
const discountData = <?= json_encode($customerDiscountData ?? []) ?>;
const weight=document.getElementById("weight");
const service=document.getElementById("service");
const lname = document.getElementById("lname");
const fname = document.getElementById("fname");

function fetchCustomer() {
    let last = lname.value.trim();
    let first = fname.value.trim();

    let fullname = (last + ", " + first)
        .toLowerCase()
        .replace(/\s+/g, " ")
        .trim();

    if (fullname === "," || fullname === "" || last === "" || first === "") {
        document.getElementById("customer_id").value = "";
        document.getElementById("r_customer_id").innerText = "—";
        compute();
        return;
    }

    fetch("get_customer.php?name=" + encodeURIComponent(fullname))
        .then(response => response.json())
        .then(data => {
            if (data && data.id) {
                document.getElementById("customer_id").value = data.id;
                document.getElementById("r_customer_id").innerText =
                    "CUST-" + String(data.id).padStart(5, "0");
            } else {
                document.getElementById("customer_id").value = "";
                document.getElementById("r_customer_id").innerText = "NEW CUSTOMER";
            }

            compute();
        })
        .catch(error => {
            console.log("Customer fetch error:", error);
            document.getElementById("customer_id").value = "";
            document.getElementById("r_customer_id").innerText = "—";
            compute();
        });
}
const phone=document.getElementById("phone");

const delivery=document.getElementById("delivery");
const ptype=document.getElementById("ptype");
const paymentMethod=document.getElementById("paymentMethod");
const downpay=document.getElementById("downpay");

const addressBox=document.getElementById("addressBox");
const gcashBox=document.getElementById("gcashBox");
const downBox=document.getElementById("downBox");

const laundryMode=document.getElementById("laundryMode");
const addonDropdowns = document.querySelectorAll(".addonDropdown");
const addonQtyDropdowns = document.querySelectorAll(".addonQtyDropdown");
const extraQtys = document.querySelectorAll(".extraQty");

extraQtys.forEach(q => {
    q.addEventListener("input", compute);
});
const laundryTypes = document.querySelectorAll(".laundryType");
const typeWeights = document.querySelectorAll(".typeWeight");
const typeUnits = document.querySelectorAll(".typeUnit");
const supplySelects = document.querySelectorAll(".supplySelect");
const supplyQtys = document.querySelectorAll(".supplyQty");

supplySelects.forEach(s => {
    s.addEventListener("change", compute);
});

supplyQtys.forEach(q => {
    q.addEventListener("input", compute);
});
// STEP 2 (existing mo)
typeWeights.forEach(w=>{
    w.addEventListener("input", compute);
});

// STEP 4 (ETO)
typeUnits.forEach(u=>{
    u.addEventListener("change", compute);
});

// STEP 5 (checkbox logic mo)
laundryTypes.forEach((t,i)=>{
    t.addEventListener("change", ()=>{
        typeWeights[i].disabled = !t.checked;

        if(!t.checked){
            typeWeights[i].value = "";
        }

        compute();
    });
});
let laundryCount = <?= isset($loyaltyCount) ? $loyaltyCount : 0 ?>;

function updateCustomerCount(){

    let fullname = (lname.value + ", " + fname.value)
        .toLowerCase()
        .replace(/\s+/g, " ")
        .trim();

    if(!fullname || fullname === ","){
        document.getElementById("r_count").innerHTML = "0";
        document.getElementById("r_total_loyalty").innerHTML = "₱0.00";
        return;
    }

    let count = customerData.filter(
        c => c.toLowerCase().trim() === fullname
    ).length;

    let maxWash = 0;
    let rules = <?= json_encode($loyalty_rules_data) ?>;

    rules.forEach(r => {
        if(parseInt(r.washes_required) > maxWash){
            maxWash = parseInt(r.washes_required);
        }
    });

    let cycleCount = count;

    if(maxWash > 0){
        if(count >= maxWash){
            cycleCount = count % maxWash;

            if(cycleCount === 0){
                cycleCount = 1;
            }
        }
    }

    document.getElementById("r_count").innerHTML = cycleCount;

    let foundDiscount = discountData.find(
        d => d.customer_name === fullname
    );

    let totalDiscount = foundDiscount
        ? parseFloat(foundDiscount.total_discount)
        : 0;

    document.getElementById("r_total_loyalty").innerHTML =
        "₱" + totalDiscount.toFixed(2);
}
function toggleDelivery(){
    if(delivery.value === "Delivery"){
        addressBox.style.display = "block";
    } else {
        addressBox.style.display = "none";
    }
}
function togglePayment(){
    if(paymentMethod.value === "GCash"){
        gcashBox.style.display = "block";
    } else {
        gcashBox.style.display = "none";
    }
}

function toggleDown(){

    if(ptype.value === "Downpayment"){
        downBox.style.display = "block";
        document.getElementById("downPreview").style.display = "block";
        document.getElementById("balancePreview").style.display = "block";
    }else{
        downBox.style.display = "none";
        document.getElementById("downPreview").style.display = "none";
        document.getElementById("balancePreview").style.display = "none";
    }

}

function getLoadBreakdown(qty, minKg, maxKg, selectedUnit = "kg") {
    qty = parseFloat(qty) || 0;
    minKg = parseFloat(minKg) || 0;
    maxKg = parseFloat(maxKg) || 0;

    if (qty <= 0 || minKg <= 0 || maxKg <= 0) {
        return {
            text: "",
            hasValue: false,
            fullCount: 0,
            minimumCount: 0
        };
    }

    let fullCount = Math.floor(qty / maxKg);
    let remainder = qty % maxKg;
    let parts = [];

    if (fullCount > 0) {
        parts.push(
            fullCount + " Full (" + maxKg + (selectedUnit === "load" ? " load" : "kg") + ")"
        );
    }

    if (remainder > 0) {
        parts.push(
            "1 Minimum (" + remainder.toFixed(1) + (selectedUnit === "load" ? "load" : "kg") + ")"
        );
    }

    if (fullCount === 0 && qty > 0) {
        parts = [
            "1 Minimum (" + qty.toFixed(1) + (selectedUnit === "load" ? "load" : "kg") + ")"
        ];
    }

    return {
        text: parts.join(" + "),
        hasValue: true,
        fullCount: fullCount,
        minimumCount: remainder > 0 || fullCount === 0 ? 1 : 0
    };
}

function formatMinMaxDisplay(value, selectedUnit, labelKg = "kg") {
    value = parseFloat(value) || 0;

    if (value <= 0) return "—";

    if (selectedUnit === "load") {
    return "1 Load";
}

return value + "kg";
}

function compute(){
    let globalUnit = null;
    let laundryTotal = 0;
    let totalKG = 0;
    let totalLOAD = 0;

    let addonTotal = 0;
    let addonList = [];

    let extra = 0;
    let extraList = [];
    let extraPreviewList = [];

    // =========================
    // DELIVERY / MODE
    // =========================
    const deliveryFee = delivery.value == "Delivery" ? 50 : 0;
    const modeFee = parseFloat(
        laundryMode.options[laundryMode.selectedIndex].dataset.price
    ) || 0;

    // =========================
    // LOAD DISPLAY + LAUNDRY TOTAL
    // =========================
    let previewMin = "—";
    let previewMax = "—";
    let firstMin = 0;
    let firstMax = 0;
    let firstUnit = "kg";
    let loadParts = [];
    let loadGroups = {};

    laundryTypes.forEach((t, index) => {
    if (t.checked) {

        let selectedUnit = typeUnits[index].value;

        // 🔥 SET GLOBAL UNIT (FIRST SELECTED)
        if (!globalUnit) {
            globalUnit = selectedUnit;
        }

        // 🔥 IGNORE OTHER UNIT
        if (globalUnit && selectedUnit !== globalUnit) {
            return;
        }

        let qty = parseFloat(typeWeights[index].value) || 0;
        let minKg = parseFloat(t.dataset.min) || 0;
        let maxKg = parseFloat(t.dataset.max) || 0;
        let priceKg = parseFloat(t.dataset.pricekg) || 0;
        let priceLoad = parseFloat(t.dataset.priceload) || 0;

        if (qty > 0) {

            if (selectedUnit === "kg") {
                laundryTotal += qty * priceKg;
                totalKG += qty;
            } else {
                let usePrice = priceLoad > 0 ? priceLoad : priceKg;
                laundryTotal += qty * usePrice;
                totalLOAD += qty;
            }

                // same min/max + same unit lang ang grouping
                let key = minKg + "-" + maxKg + "-" + selectedUnit;

                if (!loadGroups[key]) {
                    loadGroups[key] = {
                        totalQty: 0,
                        minKg: minKg,
                        maxKg: maxKg,
                        unit: selectedUnit
                    };
                }

                loadGroups[key].totalQty += qty;
            }
        }
    });

    // total weight in kg
  let totalWeight = 0;

if (globalUnit === "kg") {
    totalWeight = totalKG;
} else if (globalUnit === "load") {
    totalWeight = totalLOAD;
}

    const billableKG = totalWeight <= 0 ? 0 : (totalWeight < 6 ? 6 : totalWeight);

    Object.values(loadGroups).forEach(group => {
        let result = getLoadBreakdown(
            group.totalQty,
            group.minKg,
            group.maxKg,
            group.unit
        );

        if (result.hasValue) {
            loadParts.push(result.text);
        }
    });

    if (firstMin > 0) previewMin = formatMinMaxDisplay(firstMin, firstUnit);
    if (firstMax > 0) previewMax = formatMinMaxDisplay(firstMax, firstUnit);

    let finalLoadDisplay = "—";

    if (loadParts.length > 0) {
        finalLoadDisplay = loadParts.join(" + ");

        if (totalWeight > 0) {
            if (globalUnit === "kg") {
    finalLoadDisplay += " (Total: " + totalWeight.toFixed(1) + "kg)";
} else if (globalUnit === "load") {
    finalLoadDisplay += " (Total: " + totalWeight.toFixed(1) + " load)";
}
        }
    }

    let breakdownText = "";

if (finalLoadDisplay !== "—") {
    breakdownText += finalLoadDisplay + "<br>";
}

if (globalUnit === "kg") {
    breakdownText += "Total Weight: " + totalWeight.toFixed(1) + "kg";
} else {
    breakdownText += "Total Load: " + totalWeight.toFixed(1) + " load";
}

document.getElementById("r_weight").innerHTML = breakdownText;
    document.getElementById("load").value = finalLoadDisplay;
    document.getElementById("min_kg_used").value = firstMin > 0 ? firstMin : 0;
    document.getElementById("max_kg_used").value = firstMax > 0 ? firstMax : 0;

    // =========================
    // SERVICE ID
    // =========================
    document.getElementById("service_id").value =
        service.options[service.selectedIndex].value;

    // =========================
    // ADD ONS
    // =========================
    addonDropdowns.forEach((dropdown, index) => {
        let qty = parseFloat(addonQtyDropdowns[index].value) || 0;
        let selectedOption = dropdown.options[dropdown.selectedIndex];
        let price = parseFloat(selectedOption.dataset.price) || 0;
        let name = selectedOption.dataset.name || "";
let size = selectedOption.dataset.size || "";
let unit = selectedOption.dataset.unit || "";

if(size && unit){
    name += " (" + size + " " + unit + ")";
}

        if(qty > 0 && dropdown.value !== ""){
            addonTotal += qty * price;
            addonList.push(name + " x" + qty);
        }
    });

    // =========================
    // EXTRA SERVICES
    // =========================
    extraServices.forEach((ex, index)=>{
    let qty = parseFloat(extraQtys[index].value) || 0;

    if(ex.checked && qty > 0){
        let price = parseFloat(ex.dataset.price) || 0;
        let total = price * qty;

        extra += total;

        extraList.push(ex.dataset.name + " x" + qty);
        extraPreviewList.push(
            ex.dataset.name + " x" + qty + " (₱" + total.toFixed(2) + ")"
        );
    }
});

    document.getElementById("r_extra_service").innerHTML =
        extraPreviewList.length ? extraPreviewList.join("<br>") : "None";


    // =========================
    // SUPPLIES
    // =========================
    let suppliesText = "";
    let suppliesTotal = 0;

   supplySelects.forEach((select, index) => {
    let qty = parseFloat(supplyQtys[index].value) || 0;
    let selectedOption = select.options[select.selectedIndex];
    let selectedName = selectedOption.value;
    let selectedPrice = parseFloat(selectedOption.dataset.price) || 0;
    let selectedSize = parseFloat(selectedOption.dataset.size) || 0;
    let selectedUnit = selectedOption.dataset.unit || "";

    if(selectedName !== "" && qty > 0){
        let cost = qty * selectedPrice;

        suppliesTotal += cost;

        suppliesText += selectedName + " x" + qty +
            " (" + selectedSize + " " + selectedUnit + ")" +
            " (₱" + cost.toFixed(2) + ")<br>";
    }
});

  
    document.getElementById("suppliesPreviewBox").innerHTML = suppliesText || "";

    // =========================
    // TOTAL
    // =========================
    let servicePrice = parseFloat(
        service.options[service.selectedIndex]?.dataset?.price
    ) || 0;

  let total =
    (parseFloat(laundryTotal) || 0) +
    ((parseFloat(servicePrice) || 0) * (parseFloat(totalWeight) || 0)) +
    (parseFloat(addonTotal) || 0) +
    (parseFloat(extra) || 0) +
    (parseFloat(deliveryFee) || 0) +
    (parseFloat(suppliesTotal) || 0) +
    (parseFloat(modeFee) || 0);

    if(isNaN(total)){
        total = 0;
    }

    if(totalWeight <= 0){
        total = 0;
    }

    let breakdownFull = `
Service: ₱${(servicePrice * totalWeight).toFixed(2)}<br>
Laundry Type: ₱${laundryTotal.toFixed(2)}<br>
Mode: ₱${modeFee.toFixed(2)}<br>
Add-ons: ₱${addonTotal.toFixed(2)}<br>
Extra: ₱${extra.toFixed(2)}<br>
Supplies: ₱${suppliesTotal.toFixed(2)}<br>
Delivery: ₱${deliveryFee.toFixed(2)}
`;

document.getElementById("r_supplies").innerHTML =
    (suppliesText || "None") + "<hr>" + breakdownFull;
    // =========================
    // LOYALTY
    // =========================
    let currentCount = parseInt(document.getElementById("r_count").innerText) || 0;
    let rules = <?= json_encode($loyalty_rules_data) ?>;
    let loyalty = 0;

    if(rules.length > 0){
        let maxWash = Math.max(...rules.map(r => parseInt(r.washes_required)));
        let cycleCount = currentCount;

        if(maxWash > 0){
            if(currentCount >= maxWash){
                cycleCount = currentCount % maxWash;

                if(cycleCount === 0){
                    cycleCount = 1;
                }
            }
        }

        let found = rules.find(
            r => cycleCount === parseInt(r.washes_required)
        );

        if(found){
            loyalty = parseFloat(found.discount_amount) || 0;
        }else{
            loyalty = 0;
        }
    }

    if(isNaN(loyalty)){
        loyalty = 0;
    }

    if(loyalty > 0){
        document.getElementById("loyaltyRow").style.display = "block";
        document.getElementById("r_loyalty").innerHTML = "-₱" + loyalty.toFixed(2);
    }else{
        document.getElementById("loyaltyRow").style.display = "none";
        document.getElementById("r_loyalty").innerHTML = "₱0.00";
    }

    let finalTotal = total - loyalty;

    if(finalTotal < 0){
        finalTotal = 0;
    }

    document.getElementById("totalPreview").innerHTML = "₱" + finalTotal.toFixed(2);
    document.getElementById("r_total").innerHTML = "₱" + finalTotal.toFixed(2);

    // =========================
    // CUSTOMER
    // =========================
    document.getElementById("r_customer").innerHTML =
        (lname.value || "") + ", " + (fname.value || "");
lname.addEventListener("input", compute);
fname.addEventListener("input", compute);
    let customerId = document.getElementById("customer_id").value;

    if(customerId){
        document.getElementById("r_customer_id").innerHTML =
            "CUST-" + String(customerId).padStart(5, "0");
    }else{
        document.getElementById("r_customer_id").innerHTML = "—";
    }

    let trackingCode = customerId ? "CML" + customerId + Date.now() : "";
    document.getElementById("tracking_code").value = trackingCode;

    document.getElementById("r_phone").innerHTML = phone.value || "—";

    // =========================
    // LAUNDRY TYPE PREVIEW WITH PRICE
    // =========================
    let typePreviewList = [];

    laundryTypes.forEach((t,i)=>{
        if(t.checked){
            let qty = parseFloat(typeWeights[i].value) || 0;
            let selectedUnit = typeUnits[i].value;
            let price = 0;

            if(selectedUnit === "kg"){
                price = parseFloat(t.dataset.pricekg) || 0;
            }else{
                price = parseFloat(t.dataset.priceload);
                if(!price || price <= 0){
                    price = parseFloat(t.dataset.pricekg) || 0;
                }
            }

            if(qty > 0){
                let lineTotal = qty * price;
                let minVal = parseFloat(t.dataset.min) || 0;
let maxVal = parseFloat(t.dataset.max) || 0;

let minDisplay = selectedUnit === "kg" 
    ? minVal + "kg" 
    : "1 Load";

let maxDisplay = selectedUnit === "kg" 
    ? maxVal + "kg" 
    : "1 Load";
                typePreviewList.push(
                    t.dataset.name + " " + qty +
                    (selectedUnit === "kg" ? "kg" : " load") +
                    " | Min: " + minDisplay +
                    " | Max: " + maxDisplay +
                    " (₱" + lineTotal.toFixed(2) + ")"
                );
            }
        }
    });

    document.getElementById("r_type").innerHTML =
    typePreviewList.length ? typePreviewList.join("<br>") : "None";

    document.getElementById("laundry_type").value =
        typePreviewList.length ? typePreviewList.join("<br>") : "None";

    // =========================
    // SERVICE / MODE / DELIVERY / PAYMENT
    // =========================
    document.getElementById("r_service").innerHTML =
        service.options[service.selectedIndex].text;

    document.getElementById("weight").value = totalWeight;

    let modeText = laundryMode.options[laundryMode.selectedIndex].text;
    document.getElementById("r_mode").innerHTML = modeText;
    document.getElementById("r_delivery").innerHTML = delivery.value;

    if(delivery.value === "Delivery"){
        document.getElementById("r_address_row").style.display = "block";
        document.getElementById("r_address").innerHTML =
            document.getElementById("address").value || "—";
    }else{
        document.getElementById("r_address_row").style.display = "none";
    }

    let paymentPreview = "Pending";

    if(ptype.value === "Fully Paid"){
        paymentPreview = "Fully Paid";
    }else if(ptype.value === "Downpayment"){
        paymentPreview = "Downpayment";
    }

    document.getElementById("r_payment").innerHTML = paymentPreview;

    // =========================
    // ADDONS PREVIEW
    // =========================
    let addonPreviewList = [];

    addonDropdowns.forEach((dropdown, index) => {
        let qty = parseInt(addonQtyDropdowns[index].value) || 0;
        let selectedOption = dropdown.options[dropdown.selectedIndex];
        let price = parseFloat(selectedOption.dataset.price) || 0;
        let name = selectedOption.dataset.name || "";

        if(qty > 0 && dropdown.value !== ""){
            let lineTotal = qty * price;

            addonPreviewList.push(
                name + " x" + qty + " (₱" + lineTotal.toFixed(2) + ")"
            );
        }
    });

    document.getElementById("r_addons").innerHTML =
    addonPreviewList.length ? addonPreviewList.join("<br>") : "None";
    // =========================
    // DOWNPAYMENT / BALANCE
    // =========================
    let down = 0;
    let balance = 0;

    if(ptype.value === "Downpayment"){
        down = parseFloat(downpay.value) || 0;
        balance = finalTotal - down;

        if(balance < 0){
            balance = 0;
        }
    }else if(ptype.value === "Fully Paid"){
        down = finalTotal;
        balance = 0;
    }else{
        down = 0;
        balance = finalTotal;
    }

    document.getElementById("downpayment_hidden").value = down.toFixed(2);
    document.getElementById("balance_hidden").value = balance.toFixed(2);

    document.getElementById("r_down").innerHTML = "₱" + down.toFixed(2);
    document.getElementById("r_balance").innerHTML = "₱" + balance.toFixed(2);

    // =========================
    // HIDDEN INPUTS
    // =========================
    document.getElementById("addons_data").value =
        addonList.length > 0 ? addonList.join(", ") : "None";

    document.getElementById("extra_service_name").value =
        extraList.length ? extraList.join(", ") : "None";

    document.getElementById("extra_service_price").value = extra;
    document.getElementById("total_amount").value = finalTotal;

    document.getElementById("selected_detergent").value =
        document.getElementById("detergent_select").value;

    let suppliesFull = suppliesText ? suppliesText.replace(/<br>/g, ", ") : "None";
    document.getElementById("supplies_full").value = suppliesFull;

    document.getElementById("selected_fabcon").value =
        document.getElementById("fabcon_select").value;

    document.getElementById("selected_bleach").value =
        document.getElementById("bleach_select").value;

    updateCustomerCount();
}
delivery.addEventListener("change", ()=>{ 
    toggleDelivery(); 
    compute(); 
});

ptype.addEventListener("change", ()=>{ 
    toggleDown(); 
    compute(); 
});

paymentMethod.addEventListener("change", ()=>{ 
    togglePayment(); 
    compute(); 
});

service.addEventListener("change", compute);
laundryMode.addEventListener("change", compute);
downpay.addEventListener("input", compute);
phone.addEventListener("input", compute);

document.getElementById("address").addEventListener("input", compute);

addonQtyDropdowns.forEach(a => a.addEventListener("input", compute));
addonDropdowns.forEach(a => a.addEventListener("change", compute));
extraServices.forEach(ex => ex.addEventListener("change", compute));

laundryTypes.forEach((t,i)=>{
    t.addEventListener("change", ()=>{
        typeWeights[i].disabled = !t.checked;

        if(!t.checked){
            typeWeights[i].value = "";
        }else{
            typeWeights[i].focus();
        }

        compute();
    });

    typeWeights[i].addEventListener("input", compute);
    typeUnits[i].addEventListener("change", compute);
});

lname.addEventListener("input", fetchCustomer);
fname.addEventListener("input", fetchCustomer);
lname.addEventListener("change", fetchCustomer);
fname.addEventListener("change", fetchCustomer);

lname.addEventListener("input", updateCustomerCount);
fname.addEventListener("input", updateCustomerCount);

toggleDelivery();
togglePayment();
toggleDown();
fetchCustomer();
compute();
</script>