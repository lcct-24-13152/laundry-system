<?php
session_start();
include "../config/db.php";

/* 
BACK BUTTON LOGIC
- Kapag galing sa Add Transaction, balik sa add_transaction.php?new=1
- Kapag galing sa History/Transactions, balik sa transactions.php
*/
$backPage = "add_transaction.php?new=1";

if(isset($_GET['from']) && $_GET['from'] == "history"){
    $backPage = "transactions.php";
}

if(!isset($_SESSION['admin'])){
    header("Location: ../auth/login.php");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$res = $conn->query("
SELECT t.*, s.service_name 
FROM transactions t
LEFT JOIN services s ON t.service_id=s.id
WHERE t.id='$id'
");

if(!$res || $res->num_rows == 0){
    echo "Transaction not found.";
    exit();
}

$data = $res->fetch_assoc();

/* =====================================================
   FIX DELIVERY DISPLAY
   - Kung delivery column ay Delivery, display Delivery
   - Kung walang laman ang delivery pero may address, display Delivery
   - Otherwise Pick-up
===================================================== */
$deliveryRaw = trim($data['delivery'] ?? '');
$addressRaw = trim($data['address'] ?? '');

if(strtolower($deliveryRaw) == "delivery"){
    $deliveryDisplay = "Delivery";
}elseif($addressRaw != ""){
    $deliveryDisplay = "Delivery";
}else{
    $deliveryDisplay = "Pick-up";
}

/* =====================================================
   OFFICIAL RECEIPT LAUNDRY TRANSACTION CYCLE COUNT
   RULE:
   - Kung max loyalty rule = 10:
     10th = 10, 11th = 1, 12th = 2
   - Kung max loyalty rule = 20:
     20th = 20, 21st = 1
   - Kung max loyalty rule = 5:
     5th = 5, 6th = 1

   IMPORTANT:
   Hindi gagamit ng old saved loyalty_count.
   Iko-compute ulit based sa current transaction ID.
===================================================== */

$actualTransactionNumber = 0;
$laundryTransactions = 0;
$maxWash = 0;

$customerIdForCount = intval($data['customer_id'] ?? 0);
$customerNameForCount = trim($data['customer_name'] ?? '');
$currentTransactionId = intval($data['id'] ?? $id);

/* Get highest loyalty required transaction from loyalty_rules */
$maxRuleRes = $conn->query("
    SELECT MAX(washes_required) AS max_wash
    FROM loyalty_rules
");

if($maxRuleRes && $maxRuleRes->num_rows > 0){
    $maxRow = $maxRuleRes->fetch_assoc();
    $maxWash = intval($maxRow['max_wash'] ?? 0);
}

/*
   Count transaction position of this receipt.
   Gumamit muna ng customer_id para mas accurate.
   If walang customer_id, fallback sa customer_name.
*/
if($customerIdForCount > 0){

    $countStmt = $conn->prepare("
        SELECT COUNT(*) AS total_transactions
        FROM transactions
        WHERE customer_id = ?
        AND id <= ?
    ");

    if($countStmt){
        $countStmt->bind_param("ii", $customerIdForCount, $currentTransactionId);
        $countStmt->execute();
        $countResult = $countStmt->get_result();

        if($countResult && $countResult->num_rows > 0){
            $countRow = $countResult->fetch_assoc();
            $actualTransactionNumber = intval($countRow['total_transactions']);
        }

        $countStmt->close();
    }

}else{

    $countStmt = $conn->prepare("
        SELECT COUNT(*) AS total_transactions
        FROM transactions
        WHERE LOWER(TRIM(customer_name)) = LOWER(TRIM(?))
        AND id <= ?
    ");

    if($countStmt){
        $countStmt->bind_param("si", $customerNameForCount, $currentTransactionId);
        $countStmt->execute();
        $countResult = $countStmt->get_result();

        if($countResult && $countResult->num_rows > 0){
            $countRow = $countResult->fetch_assoc();
            $actualTransactionNumber = intval($countRow['total_transactions']);
        }

        $countStmt->close();
    }
}

/*
   Cycle display:
   Example maxWash = 10
   actual 1  = display 1
   actual 10 = display 10
   actual 11 = display 1
*/
$laundryTransactions = $actualTransactionNumber;

if($maxWash > 0 && $actualTransactionNumber > $maxWash){
    $laundryTransactions = $actualTransactionNumber % $maxWash;

    if($laundryTransactions == 0){
        $laundryTransactions = $maxWash;
    }
}

/* QR GENERATOR */
include "../phpqrcode/qrlib.php";

$customer_id = $data['customer_id'];

$tracking_code = $data['tracking_code'];

// QR link uses APP_URL from config/config.php, or detects the address automatically.
// Tip: on localhost, phones can't open "localhost" — set APP_URL to your ngrok link when demoing.
$tracking_link = app_url()."/customer/track.php?code=".urlencode($tracking_code);

$qr_file = "../qrcodes/".$tracking_code.".png";

QRcode::png($tracking_link, $qr_file, QR_ECLEVEL_L, 3);

/* DATE & TIME */
$created = $data['date_received'] ?? date("Y-m-d H:i:s");
$receiptDate = date("F d, Y", strtotime($created));
date_default_timezone_set('Asia/Manila');
$receiptTime = date("h:i A");

/* ADD ONS */
$addonsText = !empty($data['addons']) ? $data['addons'] : "None";

/* LAUNDRY SUPPLIES */
$detergent = $data['detergent'] ?? 0;
$fabcon = $data['fabcon'] ?? 0;
$bleach = $data['bleach'] ?? 0;
?>

<!DOCTYPE html>
<html>
<head>
<title>Receipt</title>

<style>
*{
    box-sizing:border-box;
}

body{
    font-family:'Courier New', monospace;
    background:#eef3f9;
    margin:0;
    padding:0;
}

.wrapper{
    display:flex;
    justify-content:center;
    align-items:flex-start;
    margin-top:22px;
    margin-bottom:30px;
}

.receipt{
    width:58mm;
    max-width:58mm;
    background:#fff;
    padding:16px 17px;
    border-radius:9px;
    box-shadow:0 10px 25px rgba(0,0,0,.12);
}

.shop{
    text-align:center;
}

.shop h2{
    margin:0;
    font-size:18px;
    letter-spacing:2px;
    line-height:1.2;
}

.shop small{
    color:#888;
    font-size:11px;
}

.line{
    border-top:1.5px dashed #ccc;
    margin:10px 0;
}

.row{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    font-size:12px;
    margin:4px 0;
    gap:8px;
}

.label{
    color:#555;
    flex:1;
}

.value{
    font-weight:600;
    text-align:right;
    flex:1.3;
    word-break:break-word;
}

.total{
    font-size:15px;
    font-weight:bold;
}

.balance{
    font-size:15px;
    font-weight:bold;
    color:#e74c3c;
}

.footer{
    text-align:center;
    font-size:10.5px;
    color:#777;
    margin-top:8px;
    line-height:1.4;
}

.qr-container{
    text-align:center;
    margin-top:10px;
    margin-bottom:8px;
}

.qr-img{
    width:92px;
    height:92px;
    object-fit:contain;
    border:5px solid #f5f5f5;
    border-radius:8px;
}

.qr-text{
    font-size:9.5px;
    color:#888;
    margin-top:4px;
    margin-bottom:0;
}

.print-btn{
    margin-top:12px;
    width:100%;
    padding:8px;
    border:none;
    background:#2d6cdf;
    color:white;
    border-radius:7px;
    cursor:pointer;
    font-size:12px;
    font-weight:600;
}

.print-btn:hover{
    background:#1e40af;
}


@media print {

    body{
        width:58mm;
        margin:0;
        padding:0;
        background:white;
    }

      .print-btn{
        display:none;
    }
    

    .receipt {
        width: 58mm;
        font-size: 10px;
    }

     .wrapper{
        margin:0;
        padding:0;
        display:block;
    }

     .receipt{
        width:58mm;
        max-width:58mm;
        margin:0;
        padding:10px;
        box-shadow:none;
        border-radius:0;
    }

      @page{
        size:58mm auto;
        margin:0;
    }
}
</style>
</head>

<body>

<div class="wrapper">   

<div class="receipt">

<div class="shop">
<h2>CML LAUNDRY SHOP</h2>
<small>Official Receipt</small>
</div>

<div class="line"></div>

<div class="row">
<span class="label">Customer</span>
<span class="value"><?= htmlspecialchars($data['customer_name'] ?? '') ?></span>
</div>

<div class="row">
<span class="label">Laundry Transactions</span>
<span class="value"><?= $laundryTransactions ?></span>
</div>

<div class="row">
<span class="label">Customer ID</span>
<span class="value">
CUST-<?= str_pad($data['customer_id'],5,'0',STR_PAD_LEFT) ?>
</span>
</div>

<div class="qr-container">
    <img src="../qrcodes/<?= htmlspecialchars($tracking_code) ?>.png?v=<?= time() ?>" class="qr-img">

    <p class="qr-text">
        Scan to track your laundry
    </p>
</div>

<div class="row">
<span class="label">Phone</span>
<span class="value"><?= htmlspecialchars($data['phone'] ?? '') ?></span>
</div>

<?php if($deliveryDisplay == "Delivery"): ?>
<div class="row">
<span class="label">Address</span>
<span class="value"><?= htmlspecialchars($data['address'] ?? '') ?></span>
</div>
<?php endif; ?>

<div class="row">
<span class="label">Laundry Type</span>
<span class="value">
<?= !empty($data['laundry_type']) ? nl2br(htmlspecialchars(str_replace(", ", "\n", $data['laundry_type']))) : "None" ?>
</span>
</div>

<div class="row">
<span class="label">Load / Weight</span>
<span class="value">
<?= !empty($data['load_type']) ? htmlspecialchars($data['load_type']) : 'None' ?>
</span>
</div>

<div class="row">
<span class="label">Service</span>
<span class="value"><?= htmlspecialchars($data['service_name'] ?? 'Service') ?></span>
</div>

<div class="row">
<span class="label">Laundry Mode</span>
<span class="value"><?= htmlspecialchars($data['laundry_mode'] ?? 'None') ?></span>
</div>

<div class="row">
<span class="label">Add-ons</span>
<span class="value">
<?= !empty($data['addons']) ? nl2br(htmlspecialchars(str_replace(", ", "\n", $data['addons']))) : "None" ?>
</span>
</div>

<div class="row">
<span class="label">Extra Service</span>
<span class="value">
<?= !empty($data['extra_service']) ? nl2br(htmlspecialchars(str_replace(", ", "\n", $data['extra_service']))) : "None" ?>
</span>
</div>

<div class="row">
<span class="label">Delivery</span>
<span class="value"><?= htmlspecialchars($deliveryDisplay) ?></span>
</div>

<div class="row">
<span class="label">Date Received</span>
<span class="value"><?= !empty($data['date_received']) ? date("M d, Y", strtotime($data['date_received'])) : '-' ?></span>
</div>

<div class="row">
<span class="label">Time</span>
<span class="value"><?= $receiptTime ?></span>
</div>

<div class="line"></div>

<div class="row">
<span class="label">Laundry Supplies</span>
<span class="value">
<?= !empty($data['supplies_summary']) ? nl2br(htmlspecialchars(str_replace(", ", "\n", $data['supplies_summary']))) : "None" ?>
</span>
</div>

<div class="line"></div>

<div class="row">
<span class="label">Payment</span>
<span class="value"><?= htmlspecialchars($data['payment_status'] ?? 'Pending') ?></span>
</div>

<div class="row">
<span class="label">Payment Method</span>
<span class="value"><?= htmlspecialchars($data['payment_method'] ?? 'Unspecified') ?></span>
</div>

<div class="line"></div>

<?php
$subtotalValue = (float)($data['subtotal'] ?? 0);
$loyaltyDiscount = (float)($data['loyalty_discount'] ?? 0);
$finalTotal = (float)($data['total'] ?? 0);
$downpaymentValue = (float)($data['downpayment'] ?? 0);
$finalBalance = (float)($data['balance'] ?? 0);
?>

<div class="row">
<span class="label">Subtotal</span>
<span class="value">
₱<?= number_format($subtotalValue, 2) ?>
</span>
</div>

<div class="row">
<span class="label">Loyalty Discount</span>
<span class="value">
-₱<?= number_format($loyaltyDiscount, 2) ?>
</span>
</div>

<div class="row total">
<span>Total</span>
<span>₱<?= number_format($finalTotal, 2) ?></span>
</div>

<div class="row">
<span>Downpayment</span>
<span>₱<?= number_format($downpaymentValue, 2) ?></span>
</div>

<div class="row balance">
<span>Balance</span>
<span>₱<?= number_format($finalBalance, 2) ?></span>
</div>

<div class="line"></div>

<div class="footer">
<?= $receiptDate ?> <?= $receiptTime ?><br>
Thank you for choosing CML Laundry 💙
</div>



</div>
</div>


</body>
</html>