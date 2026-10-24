<?php
session_start();
include "../config/db.php";
$laundryTypes = $conn->query("SELECT * FROM laundry_types ORDER BY type_name ASC");
include "navbar.php";
$pricingSettings = $conn->query("SELECT * FROM laundry_pricing_settings LIMIT 1");
$pricingRow = $pricingSettings ? $pricingSettings->fetch_assoc() : [];

$max_load_kg = floatval($pricingRow['max_load_kg'] ?? 7);
$full_load_price = floatval($pricingRow['full_load_price'] ?? 218);
$min_load_price = floatval($pricingRow['min_load_price'] ?? 203);

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

$loyalty_rules_data = [];
$getRules = $conn->query("SELECT * FROM loyalty_rules ORDER BY washes_required ASC");

while($r = $getRules->fetch_assoc()){
    $loyalty_rules_data[] = $r;
}

$customerData = [];

$getCust = $conn->query("SELECT customer_name FROM transactions");

while($c = $getCust->fetch_assoc()){
    $customerData[] = strtolower(trim($c['customer_name']));
}

$allSupplies = [];

$getPrices = $conn->query("SELECT item_name, price, unit FROM inventory ORDER BY id ASC");

while($p = $getPrices->fetch_assoc()){
    $allSupplies[] = $p;
}

if(!isset($_SESSION['admin'])){
header("Location: ../auth/login.php");
exit();
}

$maxRuleRes = $conn->query("SELECT MAX(washes_required) as max_wash FROM loyalty_rules");
$maxRow = $maxRuleRes->fetch_assoc();
$maxWash = intval($maxRow['max_wash'] ?? 0);

$totalTransactions = 0;

$customerNameTemp = '';

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


/* CRITICAL STOCK EMAIL NOTIFICATION */
function checkCriticalStockAndNotify($conn, $itemName){
    if(empty($itemName)){
        return;
    }

    $itemNameSafe = mysqli_real_escape_string($conn, $itemName);

    $q = $conn->query("
        SELECT id, item_name, quantity, unit, reorder_level
        FROM inventory
        WHERE item_name = '$itemNameSafe'
        LIMIT 1
    ");

    if($q && $q->num_rows > 0){
        $item = $q->fetch_assoc();

        $name = $item['item_name'];
        $quantity = floatval($item['quantity']);
        $unit = $item['unit'] ?? '';
        $reorderLevel = floatval($item['reorder_level'] ?? 0);

        if($reorderLevel > 0 && $quantity <= $reorderLevel){
            if(function_exists('sendCriticalStockEmail')){
                sendCriticalStockEmail($name, $quantity, $unit, $reorderLevel);
            }
        }
    }
}

if(isset($_POST['save'])){

$lname = mysqli_real_escape_string($conn,$_POST['lname']);
$fname = mysqli_real_escape_string($conn,$_POST['fname']);

$customer = trim($lname . ", " . $fname);
$customer_key = strtolower(trim($lname . ", " . $fname));

$phone = mysqli_real_escape_string($conn,$_POST['phone']);
$address = mysqli_real_escape_string($conn,$_POST['address'] ?? '');

$customer_name = trim(strtolower($lname . ", " . $fname));

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
$laundry_mode_id = intval($_POST['laundry_mode_id'] ?? 0);
$laundry_mode = '';
$laundry_mode_price = 0;

if($laundry_mode_id > 0){
    $modeQuery = $conn->query("
        SELECT mode_name, price 
        FROM laundry_mode 
        WHERE id = '$laundry_mode_id'
        LIMIT 1
    ");

    if($modeQuery && $modeQuery->num_rows > 0){
        $modeRow = $modeQuery->fetch_assoc();
        $laundry_mode = mysqli_real_escape_string($conn, $modeRow['mode_name']);
        $laundry_mode_price = floatval($modeRow['price']);
    }
}
$load = mysqli_real_escape_string($conn,$_POST['load'] ?? '');
$min_kg_used = floatval($_POST['min_kg_used'] ?? 0);
$max_kg_used = floatval($_POST['max_kg_used'] ?? 0);

if($payment_method == "GCash"){
    $gcash_reference = mysqli_real_escape_string($conn, $_POST['gcash_reference'] ?? '');
}else{
    $gcash_reference = '';
}

$gcash_receipt = '';

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

$maxRuleRes = $conn->query("
    SELECT MAX(washes_required) as max_wash
    FROM loyalty_rules
");

$maxRow = $maxRuleRes->fetch_assoc();
$maxWash = intval($maxRow['max_wash'] ?? 0);

if($maxWash > 0){

    if($totalTransactions > $maxWash){
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

$loyaltyCount = $displayLoyaltyCount;

$discount = floatval($discount_percent);

$subtotal = $final_total_from_form;
$total_amount = $final_total_from_form - $discount;

if($total_amount < 0){
    $total_amount = 0;
}

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

    $payment_status = "Pending";
    $downpayment = 0;
    $balance = $total_amount;

}

$tracking_code = "CML".$customer_id.time();

$billableKG = ($weight < 6) ? 6 : $weight;

$detergentML = 0;
$fabconML = 0;
$bleachML = 0;

$selected_detergent = mysqli_real_escape_string($conn, $_POST['selected_detergent'] ?? '');
$selected_fabcon    = mysqli_real_escape_string($conn, $_POST['selected_fabcon'] ?? '');
$selected_bleach    = mysqli_real_escape_string($conn, $_POST['selected_bleach'] ?? '');

$supplies_summary = mysqli_real_escape_string($conn, $_POST['supplies_full'] ?? 'None');

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

    checkCriticalStockAndNotify($conn, $selected_detergent);
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

    checkCriticalStockAndNotify($conn, $selected_fabcon);
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

    checkCriticalStockAndNotify($conn, $selected_bleach);
}

$date_received = date("Y-m-d H:i:s");

$conn->query("INSERT INTO transactions
(customer_id,customer_name,phone,address,tracking_code,service_id,weight,detergent,fabcon,bleach,
addons,extra_service,extra_service_price,subtotal,loyalty_discount,total,
downpayment,balance,payment_method,payment_status,delivery,date_received,loyalty_count,
gcash_reference,gcash_receipt,laundry_type,min_kg_used,max_kg_used,laundry_mode,load_type,supplies_summary)
VALUES
('$customer_id','$customer','$phone','$address','$tracking_code','$service_id','$weight',
'$detergentML','$fabconML','$bleachML',
'$addons_data','$extra_service_name','$extra_service_price',
'$subtotal','$discount','$total_amount','$downpayment','$balance',
'$payment_method','$payment_status','$delivery','$date_received','$loyaltyCount',
'$gcash_reference','$gcash_receipt','$laundry_type','$min_kg_used','$max_kg_used','$laundry_mode','$load','$supplies_summary')");

$last_id = $conn->insert_id;

$saved_transaction_id = $last_id;


}

?>

<style>
:root{
    --blue:#2563eb;
    --blue-dark:#1d4ed8;
    --blue-soft:#eff6ff;
    --blue-light:#dbeafe;
    --text:#0f172a;
    --muted:#64748b;
    --border:#d9e2f1;
    --border-soft:#e8eef8;
    --bg:#f5f8ff;
    --card:#ffffff;
    --shadow:0 10px 28px rgba(37,99,235,.08);
    --shadow-soft:0 4px 14px rgba(15,23,42,.04);
}

*{
    box-sizing:border-box;
}

body{
     background:white;
    color:var(--text);
}

.container{
    width:100%;
    max-width:100%;
    padding:14px;
}

.page-title{
    background:linear-gradient(135deg,#ffffff,#f0f6ff);
    border:1px solid var(--border);
    border-radius:18px;
    padding:16px 20px;
    margin-bottom:14px;
    box-shadow:var(--shadow-soft);
}

.page-title h1{
    margin:0;
    font-size:22px;
    font-weight:900;
    color:#1e3a8a;
    letter-spacing:-.3px;
}

.page-title p{
    margin:4px 0 0;
    font-size:12px;
    font-weight:600;
    color:var(--muted);
}

.transaction-layout{
    display:grid;
    grid-template-columns:minmax(0, 2.5fr) 365px;
    gap:16px;
    align-items:start;
    width:100%;
}

.panel{
    width:100%;
    background:rgba(255,255,255,.96);
    padding:16px;
    border-radius:20px;
    border:1px solid var(--border);
    box-shadow:var(--shadow);
}

.pos-form,
.form{
    width:100%;
}

.form-wide{
    display:grid;
    grid-template-columns:repeat(3, minmax(0,1fr));
    gap:12px;
    width:100%;
    margin-bottom:14px;
}

.service-details-grid{
    display:grid;
    grid-template-columns:repeat(3, minmax(0,1fr));
    gap:12px;
    width:100%;
    margin-bottom:14px;
    align-items:start;
}

.delivery-column{
    width:100%;
    display:flex;
    flex-direction:column;
    gap:10px;
}

#addressBox{
    width:100%;
}

.form-group,
.gcash-group{
    width:100%;
    display:flex;
    flex-direction:column;
}

.label,
.form-group .label{
    margin-bottom:5px;
    font-size:11px;
    font-weight:850;
    color:#1e293b;
}

input,
select{
    width:100%;
    height:38px;
    padding:8px 11px;
    border-radius:11px;
    border:1px solid #d5deed;
    background:#ffffff;
    outline:none;
    font-size:12px;
    color:var(--text);
    transition:.2s ease;
}

input:hover,
select:hover{
    border-color:#b8cdf5;
}

input:focus,
select:focus{
    border-color:var(--blue);
    box-shadow:0 0 0 4px rgba(37,99,235,.10);
}

input[type="file"]{
    padding:7px;
    height:38px;
}

.quick-actions{
    display:grid;
    grid-template-columns:repeat(4, minmax(0,1fr));
    gap:12px;
    margin-top:4px;
    margin-bottom:14px;
}

.popup-btn{
    background:linear-gradient(180deg,#ffffff,#f8fbff);
    color:#1d4ed8;
    border:1px solid #d5deed;
    border-radius:16px;
    padding:13px 10px;
    min-height:60px;
    cursor:pointer;
    font-size:12px;
    font-weight:900;
    box-shadow:var(--shadow-soft);
    transition:.2s ease;
}

.popup-btn span{
    display:block;
    font-size:10.5px;
    font-weight:600;
    color:#64748b;
    margin-top:3px;
}

.popup-btn:hover{
    background:#eff6ff;
    border-color:#93c5fd;
    transform:translateY(-1px);
    box-shadow:0 8px 18px rgba(37,99,235,.10);
}

.addons-grid{
    display:flex;
    flex-direction:column;
    gap:8px;
    width:100%;
}

.addon-card{
    display:flex;
    align-items:center;
    justify-content:space-between;
    width:100%;
    min-height:42px;
    padding:8px 10px;
    border-radius:13px;
    background:#ffffff;
    border:1px solid #dce6f5;
    box-sizing:border-box;
    gap:10px;
    box-shadow:0 2px 8px rgba(15,23,42,.03);
}

.addon-card > div{
    display:flex;
    align-items:center;
    gap:8px;
    flex:1;
}

.addon-card label{
    display:flex;
    align-items:center;
    gap:7px;
    font-size:12px;
    font-weight:700;
    color:#1e293b;
}

.addon-card span{
    min-width:115px;
    font-size:12px;
    font-weight:850;
    color:#1e3a8a;
}

.addon-card input[type="checkbox"]{
    width:16px;
    height:16px;
    flex-shrink:0;
    cursor:pointer;
}

.addon-card input[type="number"],
.addon-card input,
.typeWeight,
.extraQty,
.otherAddonQty,
.addonQtyDropdown,
.supplyQty{
    width:68px !important;
    min-width:68px;
    height:31px;
    text-align:center;
    border-radius:9px;
    border:1px solid #d5deed;
    font-size:11px;
    padding:5px;
}

.addon-card select,
.addonDropdown,
.supplySelect,
.addonTypeSelect{
    width:100%;
    max-width:210px;
    height:34px;
    border-radius:10px;
    border:1px solid #d5deed;
    padding:0 8px;
    background:#fff;
    font-size:11px;
}

#suppliesPreviewBox{
    line-height:1.5;
    color:#334155;
    font-size:12px;
    padding:9px 11px;
    background:#f8fbff;
    border-radius:13px;
    border:1px dashed #bcd3f8;
}

#gcashBox{
    margin-bottom:0;
}

.total-row{
    display:grid;
    grid-template-columns:1fr auto;
    align-items:center;
    gap:12px;
    margin-top:10px;
    padding:14px;
    background:#ffffff;
    border:1px solid var(--border);
    border-radius:18px;
    box-shadow:var(--shadow-soft);
}

.total-card{
    background:linear-gradient(135deg,#eff6ff,#ffffff);
    color:#1e3a8a;
    border:1px solid #cfe0ff;
    border-radius:16px;
    padding:11px 15px;
    min-height:64px;
    display:flex;
    flex-direction:column;
    justify-content:center;
}

.total-card small{
    font-size:11px;
    color:#1d4ed8;
    font-weight:900;
    letter-spacing:.4px;
}

.total-card h1{
    margin:3px 0 0;
    font-size:27px;
    font-weight:950;
    color:#1d4ed8;
}

.btn.big{
    background:linear-gradient(135deg,#2563eb,#1d4ed8);
    color:white;
    border:none;
    border-radius:16px;
    padding:10px 20px;
    min-width:150px;
    height:64px;
    font-size:13px;
    font-weight:950;
    cursor:pointer;
    box-shadow:0 10px 22px rgba(37,99,235,.22);
    transition:.2s ease;
}

.btn.big:hover{
    background:linear-gradient(135deg,#1d4ed8,#1e40af);
    transform:translateY(-1px);
}

.receipt-preview{
    position:sticky;
    top:14px;
    height:fit-content;
    max-height:calc(100vh - 28px);
    overflow:auto;
}

.receipt-preview h2{
    margin:0 0 8px;
    font-size:18px;
    font-weight:900;
    color:#1e3a8a;
}

.receipt-preview p{
    margin:7px 0;
    font-size:12px;
    line-height:1.45;
    color:#334155;
}

.receipt-preview strong{
    color:#0f172a;
}

.receipt-preview h3{
    margin:10px 0 0;
    font-size:19px;
    color:#1d4ed8;
}

.receipt-preview hr{
    border:none;
    border-top:1px solid #d5deed;
    margin:11px 0;
}

.modal-overlay{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(15,23,42,.38);
    z-index:99999;
    align-items:center;
    justify-content:center;
    padding:18px;
}

.modal-overlay.show{
    display:flex;
}

.modal-box{
    width:780px;
    max-width:100%;
    max-height:88vh;
    background:#ffffff;
    border-radius:20px;
    border:1px solid #d5deed;
    box-shadow:0 24px 70px rgba(15,23,42,.22);
    overflow:hidden;
    display:flex;
    flex-direction:column;
    animation:modalPop .18s ease;
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
    gap:12px;
    padding:16px 18px;
    background:linear-gradient(135deg,#ffffff,#eff6ff);
    border-bottom:1px solid #d5deed;
}

.modal-header h3{
    margin:0;
    color:#1e3a8a;
    font-size:18px;
    font-weight:950;
}

.modal-header p{
    margin:4px 0 0;
    color:#64748b;
    font-size:12px;
    font-weight:600;
}

.modal-close{
    width:32px;
    height:32px;
    border:1px solid #d5deed;
    border-radius:50%;
    background:#ffffff;
    color:#1d4ed8;
    font-size:21px;
    font-weight:900;
    cursor:pointer;
    transition:.2s ease;
}

.modal-close:hover{
    background:#eff6ff;
}

.modal-content{
    padding:16px 18px;
    overflow:auto;
}

.modal-footer{
    display:flex;
    justify-content:flex-end;
    gap:10px;
    padding:12px 18px;
    border-top:1px solid #d5deed;
    background:#ffffff;
}

.modal-done{
    background:linear-gradient(135deg,#2563eb,#1d4ed8);
    color:white;
    border:none;
    padding:9px 18px;
    border-radius:11px;
    font-size:12px;
    font-weight:850;
    cursor:pointer;
    box-shadow:0 7px 16px rgba(37,99,235,.16);
}

.modal-done:hover{
    background:linear-gradient(135deg,#1d4ed8,#1e40af);
}

/* Hide Print Receipt button inside loaded receipt */
#receiptContent button,
#receiptContent .print-btn,
#receiptContent .receipt-print-btn,
#receiptContent .btn-print,
#receiptContent [onclick*="print"],
#receiptContent [onclick*="Print"]{
    display:none !important;
}

@media(max-width:1200px){
    .transaction-layout{
        grid-template-columns:1fr;
    }

    .receipt-preview{
        position:relative;
        top:auto;
        max-height:none;
    }
}

@media(max-width:900px){
    .form-wide,
    .service-details-grid,
    .quick-actions{
        grid-template-columns:1fr;
    }

    .total-row{
        grid-template-columns:1fr;
    }

    .btn.big{
        width:100%;
    }
}

@media(max-width:700px){
    .container{
        padding:10px;
    }

    .panel{
        padding:12px;
        border-radius:16px;
    }

    .page-title{
        padding:13px;
    }

    .page-title h1{
        font-size:19px;
    }

    .addon-card{
        flex-direction:column;
        align-items:stretch;
    }

    .addon-card > div{
        width:100%;
    }

    .addon-card select,
    .addonDropdown,
    .supplySelect{
        max-width:100%;
    }

    .addon-card input[type="number"],
    .typeWeight,
    .extraQty,
    .otherAddonQty,
    .addonQtyDropdown,
    .supplyQty{
        width:100% !important;
    }
.modal-footer button:hover{
    opacity:.95;
    transform:translateY(-1px);
}
    .modal-footer{
        flex-direction:column;
    }

    .modal-done{
        width:100%;
    }
}
</style>

<div class="container">

<div class="page-title">
<h1>Laundry Transaction</h1>
<p>Fast cashier screen for encoding customer laundry.</p>
</div>

<div class="transaction-layout">

<div class="panel pos-form">

<form method="POST" enctype="multipart/form-data" class="form">

<div class="form-wide">

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

<div class="service-details-grid">

<div class="delivery-column">
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
</div>

<div class="service-box">
<div class="label">Select Service</div>

<select id="service" name="service_id" required>
    <option value="">Select Service</option>

    <?php
    $res = $conn->query("
        SELECT * FROM services 
        WHERE is_active = 1 
        ORDER BY service_name ASC
    ");

    while($row = $res->fetch_assoc()):
    ?>

    <option 
        value="<?= $row['id'] ?>" 
        data-price="<?= $row['price_per_kg'] ?>">
        
        <?= $row['service_name'] ?>

    </option>

    <?php endwhile; ?>
</select>

</div>

<div class="service-box">
<div class="label">Laundry Mode</div>

<select id="laundryMode" name="laundry_mode_id">
<?php
$modes = $conn->query("SELECT * FROM laundry_mode ORDER BY id ASC");
while($mode = $modes->fetch_assoc()){
?>
<option 
    value="<?= $mode['id'] ?>" 
    data-name="<?= htmlspecialchars($mode['mode_name']) ?>"
    data-price="<?= number_format((float)$mode['price'], 2, '.', '') ?>">
    <?= htmlspecialchars($mode['mode_name']) ?> (+₱<?= number_format((float)$mode['price'], 2) ?>)
</option>
<?php } ?>
</select>

<input type="hidden" name="laundry_mode" id="laundry_mode">
<input type="hidden" name="laundry_mode_price" id="laundry_mode_price">

</div>

</div>

<div class="quick-actions">
    <button type="button" class="popup-btn" onclick="openModal('laundryTypeModal')">
        Laundry Type
        <span>Choose type and kg</span>
    </button>

    <button type="button" class="popup-btn" onclick="openModal('suppliesModal')">
        Supplies Used
        <span>Detergent, fabcon, bleach</span>
    </button>

    <button type="button" class="popup-btn" onclick="openModal('extraServiceModal')">
        Extra Service
        <span>Optional services</span>
    </button>

    <button type="button" class="popup-btn" onclick="openModal('addonsModal')">
        Add Ons
        <span>Additional items</span>
    </button>
</div>

<div class="form-wide">

<div>
<div class="label">Payment Type</div>
<select id="ptype" name="payment_type">
<option value="">None</option>
<option value="Fully Paid">Fully Paid</option>
<option value="Downpayment">Downpayment</option>
</select>
</div>

<div>
<div class="label">Payment Method</div>
<select id="paymentMethod" name="payment_method">
<option value="">None</option>
<option value="Cash">Cash</option>
<option value="GCash">GCash</option>
</select>
</div>

<div id="gcashBox" style="display:none;">
<div class="gcash-group">
<div class="label">GCash Reference Number</div>
<input type="text" name="gcash_reference" id="gcash_reference">
</div>
</div>

</div>

<div class="form-wide" id="downBox" style="display:none;">
<div>
<div class="label">Downpayment</div>
<input id="downpay" name="downpayment" type="number" value="0">
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

<!-- LAUNDRY TYPE MODAL -->
<div id="laundryTypeModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <div>
                <h3>Laundry Type</h3>
                <p>Select laundry type and input kilogram.</p>
            </div>
            <button type="button" class="modal-close" onclick="closeModal('laundryTypeModal')">×</button>
        </div>

        <div class="modal-content">
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
                    data-minprice="<?= (float)($type['min_load_price'] ?? 0) ?>"
                    data-name="<?= htmlspecialchars($type['type_name'] ?? '') ?>"
                    data-min="<?= (float)($type['min_kg'] ?? 0) ?>"
                    data-max="<?= (float)($type['max_kg'] ?? 0) ?>">

                   <?= $type['type_name'] ?>
                </label>

                <div style="display:flex; gap:5px; align-items:center;">
                    <input
                    type="number"
                    class="typeWeight"
                    placeholder="0"
                    min="0"
                    step="0.1"
                    style="width:80px"
                    disabled>

                    <span style="font-weight:bold; color:#1e3a8a;">kg</span>
                </div>

            </div>

            <?php } ?>

            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="modal-done" onclick="closeModal('laundryTypeModal')">Done</button>
        </div>
    </div>
</div>

<!-- SUPPLIES MODAL -->
<div id="suppliesModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <div>
                <h3>Laundry Supplies Used</h3>
                <p>Select detergent, fabric softener, and bleach used.</p>
            </div>
            <button type="button" class="modal-close" onclick="closeModal('suppliesModal')">×</button>
        </div>

        <div class="modal-content">
            <div class="addons-grid">

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
                            data-usage-type="<?= htmlspecialchars($d['usage_type'] ?? 'kg') ?>">
                            <?= htmlspecialchars($d['item_name']) ?> 
                            (<?= $d['size_value'] ?> <?= $d['unit'] ?>)
                        </option>

                        <?php } ?>
                    </select>
                    <input type="number" class="supplyQty" id="detergent_qty" value="0" min="0">
                </div>

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
                            data-usage-type="<?= htmlspecialchars($f['usage_type'] ?? 'kg') ?>">
                            <?= htmlspecialchars($f['item_name']) ?> 
                            (<?= $f['size_value'] ?> <?= $f['unit'] ?>)
                        </option>

                        <?php } ?>
                    </select>
                    <input type="number" class="supplyQty" id="fabcon_qty" value="0" min="0">
                </div>

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
                            data-usage-type="<?= htmlspecialchars($b['usage_type'] ?? 'kg') ?>">
                            <?= htmlspecialchars($b['item_name']) ?> 
                            (<?= $b['size_value'] ?> <?= $b['unit'] ?>)
                        </option>

                        <?php } ?>
                    </select>
                    <input type="number" class="supplyQty" id="bleach_qty" value="0" min="0">
                </div>

            </div>

            <div id="suppliesPreviewBox" style="margin-top:10px;"></div>
        </div>

        <div class="modal-footer">
            <button type="button" class="modal-done" onclick="closeModal('suppliesModal')">Done</button>
        </div>
    </div>
</div>

<!-- EXTRA SERVICE MODAL -->
<div id="extraServiceModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <div>
                <h3>Extra Service</h3>
                <p>Select extra services and quantity.</p>
            </div>
            <button type="button" class="modal-close" onclick="closeModal('extraServiceModal')">×</button>
        </div>

        <div class="modal-content">
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

        <div class="modal-footer">
            <button type="button" class="modal-done" onclick="closeModal('extraServiceModal')">Done</button>
        </div>
    </div>
</div>

<!-- ADD ONS MODAL -->
<div id="addonsModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <div>
                <h3>Add Ons</h3>
                <p>Select additional add-ons and quantity.</p>
            </div>
            <button type="button" class="modal-close" onclick="closeModal('addonsModal')">×</button>
        </div>

        <div class="modal-content">
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

                <?php if(strtoupper($category) === 'OTHERS' || strtoupper($category) === 'OTHER'): ?>
                    
                    <div style="font-weight: bold; padding: 6px 5px; color: #1e3a8a; font-size: 12px;">Others</div>
                    
                    <?php foreach($items as $item): ?>
                    <div class="addon-card">
                        <label style="flex:1">
                            <input type="checkbox" 
                                   class="otherAddonCheck" 
                                   data-price="<?= $item['price'] ?>" 
                                   data-name="<?= htmlspecialchars($item['addon_name']) ?>"
                                   data-id="<?= $item['id'] ?>">
                            <?= $item['addon_name'] ?> (₱<?= number_format($item['price'],2) ?>)
                        </label>
                        <input type="number" class="otherAddonQty" value="0" min="0">
                    </div>
                    <?php endforeach; ?>

                <?php else: ?>

                    <div class="addon-card">
                        <span style="min-width:120px;"><?= strtoupper($category) ?></span>
                        <select class="addonDropdown" data-category="<?= $category ?>">
                            <option value="">Select <?= $category ?></option>
                            <?php foreach($items as $item): ?>
                               <option value="<?= $item['id'] ?>"
                                       data-price="<?= $item['price'] ?>"
                                       data-name="<?= htmlspecialchars($item['addon_name']) ?>"
                                       data-size="<?= $item['size_value'] ?>"
                                       data-unit="<?= $item['unit'] ?>">
                                    <?= $item['addon_name'] ?> (<?= $item['size_value'] ?> <?= $item['unit'] ?>) ₱<?= number_format($item['price'],2) ?>
                               </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" class="addonQtyDropdown" value="0" min="0">
                    </div>

                <?php endif; ?>

            <?php endforeach; ?>

            </div>

            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="modal-done" onclick="closeModal('addonsModal')">Done</button>
        </div>
    </div>
</div>

</form>

</div>

<div class="panel receipt-preview">

<h2>Receipt Preview</h2>
<hr>

<p><strong>Customer:</strong> <span id="r_customer">—</span></p>
<p><strong>Customer ID:</strong> <span id="r_customer_id">—</span></p>

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

<!-- RECEIPT MODAL AFTER SAVE -->
<div id="receiptModal" class="modal-overlay">
    <div class="modal-box" style="width:420px; max-width:95%;">
        <div class="modal-header">
            <div>
                <h3>Official Receipt</h3>
                <p>Customer transaction receipt</p>
            </div>
            <button type="button" class="modal-close" onclick="closeReceiptModal()">×</button>
        </div>

        <div class="modal-content" id="receiptContent">
            Loading receipt...
        </div>

        <div class="modal-footer" style="display:flex; gap:10px; justify-content:flex-end;">

    <button 
        type="button" 
        onclick="printReceiptModal()"
        style="
            background:#22c55e;
            color:white;
            border:none;
            padding:10px 18px;
            border-radius:10px;
            font-size:12px;
            font-weight:800;
            cursor:pointer;
        ">
        🖨 Print Receipt
    </button>

    <button 
        type="button" 
        class="modal-done" 
        onclick="closeReceiptModal()">
        Close
    </button>

</div>
    </div>
</div>

<script>
function openReceiptModal(id){
    const modal = document.getElementById("receiptModal");
    const content = document.getElementById("receiptContent");

    if(!modal || !content){
        console.log("Receipt modal is missing.");
        return;
    }

    modal.classList.add("show");
    content.innerHTML = "Loading receipt...";

    fetch("receipt.php?id=" + encodeURIComponent(id))
        .then(response => response.text())
        .then(html => {
            content.innerHTML = html;
        })
        .catch(error => {
            content.innerHTML = "Receipt failed to load.";
            console.log(error);
        });
}

function closeReceiptModal(){
    const modal = document.getElementById("receiptModal");
    if(modal){
        modal.classList.remove("show");
    }
}

function printReceiptModal(){
    let printContent = document.getElementById("receiptContent").innerHTML;
    let win = window.open("", "", "width=400,height=600");

    win.document.write(`
        <html>
        <head>
            <title>Print Receipt</title>
            <style>
                body{
                    font-family: Arial, sans-serif;
                    width: 58mm;
                    margin: 0 auto;
                    padding: 10px;
                    font-size: 12px;
                }
                button, .no-print, .modal-footer, .modal-header{
                    display:none !important;
                }
                img{
                    max-width:100%;
                }
            </style>
        </head>
        <body>${printContent}</body>
        </html>
    `);

    win.document.close();
    win.focus();
    win.print();
    win.close();
}

function openModal(id){
    document.getElementById(id).classList.add("show");
}

function closeModal(id){
    document.getElementById(id).classList.remove("show");
}

window.addEventListener("click", function(e){
    document.querySelectorAll(".modal-overlay").forEach(function(modal){
        if(e.target === modal){
            modal.classList.remove("show");
        }
    });
});

document.addEventListener("keydown", function(e){
    if(e.key === "Escape"){
        document.querySelectorAll(".modal-overlay").forEach(function(modal){
            modal.classList.remove("show");
        });
    }
});

const phoneInput = document.getElementById("phone");

phoneInput.addEventListener("input", () => {
    if(!phoneInput.value.startsWith("+63")){
        phoneInput.value = "+63";
    }
}); 

const customerData = <?= json_encode($customerData ?? []) ?>;
const discountData = <?= json_encode($customerDiscountData ?? []) ?>;
const loyaltyRules = <?= json_encode($loyalty_rules_data ?? []) ?>;

const MAX_LOAD_KG = <?= $max_load_kg ?>;
const FULL_LOAD_PRICE = <?= $full_load_price ?>;
const MIN_LOAD_PRICE = <?= $min_load_price ?>;
const weight=document.getElementById("weight");
const service=document.getElementById("service");
const lname = document.getElementById("lname");
const fname = document.getElementById("fname");

function fetchCustomer() {
    let selectedFull = lname.value.trim();

    if (!selectedFull) {
        fname.value = "";
        phone.value = "+63";
        document.getElementById("customer_id").value = "";
        document.getElementById("r_customer_id").innerText = "—";
        compute();
        return;
    }

    fetch("get_customer.php?name=" + encodeURIComponent(selectedFull))
        .then(response => response.json())
        .then(data => {
            if (data && data.id) {
                document.getElementById("customer_id").value = data.id;
                document.getElementById("r_customer_id").innerText =
                    "CUST-" + String(data.id).padStart(5, "0");

                if (data.customer_name) {
                    let parts = data.customer_name.split(",");

                    if (parts.length === 2) {
                        lname.value = parts[0].trim();
                        fname.value = parts[1].trim();
                    }
                }

                if (data.phone) {
                    phone.value = data.phone;
                }
            } else {
                document.getElementById("customer_id").value = "";
                document.getElementById("r_customer_id").innerText = "NEW CUSTOMER";
            }

            compute();
        })
        .catch(error => {
            console.log("Customer fetch error:", error);
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
const gcashReference=document.getElementById("gcash_reference");
const downBox=document.getElementById("downBox");

const laundryMode=document.getElementById("laundryMode");
const addonDropdowns = document.querySelectorAll(".addonDropdown");
const addonQtyDropdowns = document.querySelectorAll(".addonQtyDropdown");
const extraQtys = document.querySelectorAll(".extraQty");
const extraServices = document.querySelectorAll(".extraServiceCheck");

extraQtys.forEach(q => {
    q.addEventListener("input", compute);
});

const laundryTypes = document.querySelectorAll(".laundryType");
const typeWeights = document.querySelectorAll(".typeWeight");

const supplySelects = document.querySelectorAll(".supplySelect");
const supplyQtys = document.querySelectorAll(".supplyQty");

supplySelects.forEach(s => {
    s.addEventListener("change", compute);
});

supplyQtys.forEach(q => {
    q.addEventListener("input", compute);
});

typeWeights.forEach(w=>{
    w.addEventListener("input", compute);
});

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
        return 0;
    }

    let oldCount = customerData.filter(
        c => c.toLowerCase().replace(/\s+/g, " ").trim() === fullname
    ).length;

    let actualPreviewCount = oldCount + 1;

    let maxWash = 0;

    if(loyaltyRules.length > 0){
        maxWash = Math.max(...loyaltyRules.map(r => parseInt(r.washes_required) || 0));
    }

    let previewCount = actualPreviewCount;

    if(maxWash > 0 && actualPreviewCount > maxWash){
        previewCount = actualPreviewCount % maxWash;

        if(previewCount === 0){
            previewCount = maxWash;
        }
    }

    let foundDiscount = discountData.find(
        d => d.customer_name === fullname
    );

    let totalDiscount = foundDiscount
        ? parseFloat(foundDiscount.total_discount)
        : 0;

    document.getElementById("r_count").innerHTML = previewCount;
    document.getElementById("r_total_loyalty").innerHTML =
        "₱" + totalDiscount.toFixed(2);

    return actualPreviewCount;
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
        gcashReference.required = true;
    } else {
        gcashBox.style.display = "none";
        gcashReference.required = false;
        gcashReference.value = "";
    }
}

function toggleDown(){

    if(ptype.value === "Downpayment"){
        downBox.style.display = "grid";
        document.getElementById("downPreview").style.display = "block";
        document.getElementById("balancePreview").style.display = "block";
    }else{
        downBox.style.display = "none";
        document.getElementById("downPreview").style.display = "none";
        document.getElementById("balancePreview").style.display = "none";
    }

}

function compute(){

    let baseTotal = 0;
    let addonTotal = 0;
    let addonList = [];
    let addonPreviewList = [];

    let extra = 0;
    let extraList = [];
    let extraPreviewList = [];

    let typePreviewList = [];
    let weightPreviewList = [];
    let loadHiddenList = [];

    let totalQty = 0;
    let minKgUsed = 0;
    let maxKgUsed = 0;

    const deliveryFee = delivery.value === "Delivery" ? 50 : 0;

    let laundryModePrice = 0;
    let laundryModeName = "";

    if(laundryMode && laundryMode.selectedIndex >= 0){
        let selectedLaundryModeOption = laundryMode.options[laundryMode.selectedIndex];

        laundryModePrice = parseFloat(
            selectedLaundryModeOption.getAttribute("data-price")
        ) || 0;

        laundryModeName = selectedLaundryModeOption.getAttribute("data-name") || "";
    }

    document.getElementById("laundry_mode").value = laundryModeName;
    document.getElementById("laundry_mode_price").value = laundryModePrice.toFixed(2);

    let globalFullLoads = 0;
    let globalMinLoads = 0;
    let distinctLoads = {};

    laundryTypes.forEach((t, index) => {
        if(t.checked){
            let kg = parseFloat(typeWeights[index].value) || 0;
            let typeName = t.dataset.name || "";
            let minKg = parseFloat(t.dataset.min) || 0;
            let maxKg = parseFloat(t.dataset.max) || 0;
            let fullLoadPrice = parseFloat(t.dataset.priceload) || 0;
            let minLoadPrice = parseFloat(t.dataset.minprice) || 0;

            if(kg > 0){
                totalQty += kg;
                minKgUsed = minKg;
                maxKgUsed = maxKg;

                let localFull = 0;
                let localMin = 0;
                let typeTotal = 0;

                if(kg <= minKg){
                    localMin = 1;
                    typeTotal = minLoadPrice;
                } else {
                    localFull = Math.floor(kg / maxKg);
                    let rem = kg % maxKg;
                    typeTotal = localFull * fullLoadPrice;
                    if(rem > 0){
                        localMin = 1;
                        typeTotal += minLoadPrice;
                    }
                }

                baseTotal += typeTotal;
                
                let breakdownStr = "";
                if(localFull > 0) breakdownStr += localFull + " Full Load";
                if(localFull > 0 && localMin > 0) breakdownStr += " + ";
                if(localMin > 0) breakdownStr += "Minimum Load";

                typePreviewList.push(typeName + ": " + kg + " kg (" + breakdownStr + ")");
                weightPreviewList.push(typeName + ": " + kg + " kg");

                if(!distinctLoads[breakdownStr]) distinctLoads[breakdownStr] = [];
                distinctLoads[breakdownStr].push(typeName);
            }
        }
    });

    let finalBreakdownArray = [];
    for (let key in distinctLoads) {
        let count = distinctLoads[key].length;
        let names = distinctLoads[key].join(", ");
        
        let label = key;
        if(count > 1) {
            label = key.replace(/1 /g, count + " ").replace(/Minimum Load/g, count + " Minimum Load");
            if(!label.includes(count)) label = count + " " + key;
        }
        finalBreakdownArray.push(names + ": " + label);
    }

    document.getElementById("r_weight").innerHTML = finalBreakdownArray.join("<br>") || "—";
    document.getElementById("load").value = finalBreakdownArray.join(", ") || "—";
    document.getElementById("weight").value = totalQty;
    document.getElementById("r_type").innerHTML = typePreviewList.join("<br>") || "None";
    document.getElementById("laundry_type").value = laundryTypeNamesOnly();

    function laundryTypeNamesOnly() {
        let names = [];
        laundryTypes.forEach((t) => { if(t.checked) names.push(t.dataset.name); });
        return names.join(", ");
    }

    document.getElementById("min_kg_used").value = minKgUsed;
    document.getElementById("max_kg_used").value = maxKgUsed;
    document.getElementById("service_id").value = service.options[service.selectedIndex]?.value || "";
    document.getElementById("service_price").value = "0.00";

    addonDropdowns.forEach((dropdown, index) => {
        let qty = parseFloat(addonQtyDropdowns[index].value) || 0;
        let selectedOption = dropdown.options[dropdown.selectedIndex];
        let price = parseFloat(selectedOption?.dataset.price) || 0;
        let name = selectedOption?.dataset.name || "";

        if(qty > 0 && dropdown.value !== ""){
            let lineTotal = qty * price;
            addonTotal += lineTotal;

            addonList.push(name + " x" + qty);
            addonPreviewList.push(name + " x" + qty + " (₱" + lineTotal.toFixed(2) + ")");
        }
    });

    const otherAddonChecks = document.querySelectorAll(".otherAddonCheck");
    const otherAddonQtys = document.querySelectorAll(".otherAddonQty");

    otherAddonChecks.forEach((checkbox, index) => {
        let qty = parseFloat(otherAddonQtys[index].value) || 0;

        if(checkbox.checked && qty > 0){
            let price = parseFloat(checkbox.dataset.price) || 0;
            let name = checkbox.dataset.name || "";
            let lineTotal = qty * price;

            addonTotal += lineTotal;
            addonList.push(name + " x" + qty);
            addonPreviewList.push(name + " x" + qty + " (₱" + lineTotal.toFixed(2) + ")");
        }
    });

    document.getElementById("r_addons").innerHTML =
        addonPreviewList.length ? addonPreviewList.join(", ") : "None";

    extraServices.forEach((ex, index) => {
        let qty = parseFloat(extraQtys[index].value) || 0;

        if(ex.checked && qty > 0){
            let price = parseFloat(ex.dataset.price) || 0;
            let lineTotal = price * qty;

            extra += lineTotal;
            extraList.push(ex.dataset.name + " x" + qty);
            extraPreviewList.push(ex.dataset.name + " x" + qty + " (₱" + lineTotal.toFixed(2) + ")");
        }
    });

    document.getElementById("r_extra_service").innerHTML =
        extraPreviewList.length ? extraPreviewList.join(", ") : "None";

let suppliesText = "";
let suppliesList = [];

let detergentName = document.getElementById("detergent_select").value;
let fabconName = document.getElementById("fabcon_select").value;
let bleachName = document.getElementById("bleach_select").value;

let detergentQty = parseFloat(document.getElementById("detergent_qty").value) || 0;
let fabconQty = parseFloat(document.getElementById("fabcon_qty").value) || 0;
let bleachQty = parseFloat(document.getElementById("bleach_qty").value) || 0;

let detergentUsed = totalQty * 5;
let fabconUsed = totalQty * 3;
let bleachUsed = totalQty * 2;

if(totalQty > 0 && detergentName !== "" && detergentQty > 0){
    suppliesList.push(
        detergentName + " (" + detergentUsed.toFixed(0) + " mL) x" + detergentQty
    );
}

if(totalQty > 0 && fabconName !== "" && fabconQty > 0){
    suppliesList.push(
        fabconName + " (" + fabconUsed.toFixed(0) + " mL) x" + fabconQty
    );
}

if(totalQty > 0 && bleachName !== "" && bleachQty > 0){
    suppliesList.push(
        bleachName + " (" + bleachUsed.toFixed(0) + " mL) x" + bleachQty
    );
}

suppliesText = suppliesList.join("<br>");

document.getElementById("r_supplies").innerHTML =
    suppliesText !== "" ? suppliesText : "None";

document.getElementById("suppliesPreviewBox").innerHTML =
    suppliesText !== "" ? suppliesText : "";

document.getElementById("supplies_full").value =
    suppliesText !== "" ? suppliesText.replace(/<br>/g, ", ") : "None";

    let total = baseTotal + laundryModePrice + addonTotal + extra + deliveryFee;

    if(
        totalQty <= 0 &&
        baseTotal <= 0 &&
        laundryModePrice <= 0 &&
        addonTotal <= 0 &&
        extra <= 0 &&
        deliveryFee <= 0
    ){
        total = 0;
    }

    let currentCount = updateCustomerCount();
    let rules = loyaltyRules;
    let loyalty = 0;

if(rules.length > 0){
    let maxWash = Math.max(...rules.map(r => parseInt(r.washes_required)));
    let cycleCount = currentCount;

    if(maxWash > 0 && currentCount > maxWash){
        cycleCount = currentCount % maxWash;
        if(cycleCount === 0) cycleCount = maxWash;
    }

    let found = rules.find(r => cycleCount === parseInt(r.washes_required));
    if(found) loyalty = parseFloat(found.discount_amount) || 0;
}

    if(loyalty > 0){
        document.getElementById("loyaltyRow").style.display = "block";
        document.getElementById("r_loyalty").innerHTML = "-₱" + loyalty.toFixed(2);
    }else{
        document.getElementById("loyaltyRow").style.display = "none";
        document.getElementById("r_loyalty").innerHTML = "₱0.00";
    }

    let finalTotal = total - loyalty;
    if(finalTotal < 0) finalTotal = 0;

    document.getElementById("totalPreview").innerHTML = "₱" + finalTotal.toFixed(2);
    document.getElementById("r_total").innerHTML = "₱" + finalTotal.toFixed(2);

    let fullName = "—";
    if(lname.value.trim() !== "" || fname.value.trim() !== ""){
        fullName = lname.value.trim() + ", " + fname.value.trim();
    }
    document.getElementById("r_customer").innerHTML = fullName;
    document.getElementById("r_phone").innerHTML = phone.value.trim() !== "" ? phone.value : "—";

    let customerId = document.getElementById("customer_id").value;
    if(customerId){
        document.getElementById("r_customer_id").innerHTML = "CUST-" + String(customerId).padStart(5, "0");
    }else{
        document.getElementById("r_customer_id").innerHTML = "—";
    }

    let trackingCode = customerId ? "CML" + customerId + Date.now() : "";
    document.getElementById("tracking_code").value = trackingCode;

    let serviceText = service.value !== "" ? service.options[service.selectedIndex].text : "—";
    document.getElementById("r_service").innerHTML = serviceText;

    document.getElementById("r_mode").innerHTML =
        laundryModeName !== ""
        ? laundryModeName + " (+₱" + laundryModePrice.toFixed(2) + ")"
        : "—";

    document.getElementById("r_delivery").innerHTML = delivery.value === "Delivery" ? "Delivery (+₱" + deliveryFee.toFixed(2) + ")" : "Pick-up";

    if(delivery.value === "Delivery"){
        document.getElementById("r_address_row").style.display = "block";
        document.getElementById("r_address").innerHTML = document.getElementById("address").value.trim() !== "" ? document.getElementById("address").value : "—";
    }else{
        document.getElementById("r_address_row").style.display = "none";
        document.getElementById("r_address").innerHTML = "—";
    }

    let paymentPreview = "Pending";
    if(ptype.value === "Fully Paid") paymentPreview = "Fully Paid";
    else if(ptype.value === "Downpayment") paymentPreview = "Downpayment";
    document.getElementById("r_payment").innerHTML = paymentPreview;

    let down = 0;
    let balance = 0;

    if(ptype.value === "Downpayment"){
        down = parseFloat(downpay.value) || 0;
        balance = finalTotal - down;
        if(balance < 0) balance = 0;
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

    document.getElementById("addons_data").value = addonList.length ? addonList.join(", ") : "None";
    document.getElementById("extra_service_name").value = extraList.length ? extraList.join(", ") : "None";
    document.getElementById("extra_service_price").value = extra.toFixed(2);
    document.getElementById("total_amount").value = finalTotal.toFixed(2);

document.getElementById("selected_detergent").value = document.getElementById("detergent_select").value;
document.getElementById("selected_fabcon").value = document.getElementById("fabcon_select").value;
document.getElementById("selected_bleach").value = document.getElementById("bleach_select").value;

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
lname.addEventListener("input", compute);
fname.addEventListener("input", compute);

document.getElementById("address").addEventListener("input", compute);

addonQtyDropdowns.forEach(a => a.addEventListener("input", compute));
addonDropdowns.forEach(a => a.addEventListener("change", compute));
extraServices.forEach(ex => ex.addEventListener("change", compute));

document.querySelectorAll(".otherAddonCheck, .otherAddonQty").forEach(el => {
    el.addEventListener("input", compute);
});

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
});

lname.addEventListener("change", fetchCustomer);
fname.addEventListener("change", fetchCustomer);

lname.addEventListener("input", updateCustomerCount);
fname.addEventListener("input", updateCustomerCount);

document.querySelectorAll("input, select").forEach(field => {
    field.addEventListener("input", compute);
    field.addEventListener("change", compute);
});

toggleDelivery();
togglePayment();
toggleDown();
fetchCustomer();
compute();


</script>

<?php if(isset($saved_transaction_id)): ?>
<script>
window.addEventListener("load", function(){
    openReceiptModal(<?= (int)$saved_transaction_id ?>);
});
</script>
<?php endif; ?>
