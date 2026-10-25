<?php
session_start();
include "../config/db.php";
include "../config/sms.php";
include "navbar.php";

if(!isset($_SESSION['admin'])){
    header("Location: ../auth/login.php");
    exit();
}

/* CHECK IF COLUMN EXISTS */
function columnExists($conn, $table, $column){
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);

    $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($check && $check->num_rows > 0);
}

$hasDeliveryColumn = columnExists($conn, "transactions", "delivery");

/* SAVE GCASH REFERENCE */
if(isset($_POST['save_gcash_reference'])){

    $id = intval($_POST['transaction_id']);
    $gcash_reference = trim($_POST['gcash_reference']);

    $stmt = $conn->prepare("UPDATE transactions SET gcash_reference=? WHERE id=?");
    $stmt->bind_param("si", $gcash_reference, $id);

    if($stmt->execute()){
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    }else{
        echo "<script>alert('Failed to save GCash reference number.');</script>";
    }
}

/* UPDATE LAUNDRY STATUS */
if(isset($_POST['update_status'])){

    $id = intval($_POST['id']);
    $status = trim($_POST['status']);

    $stmt = $conn->prepare("UPDATE transactions SET status=? WHERE id=?");
    $stmt->bind_param("si", $status, $id);
    $stmt->execute();

    /* GET CUSTOMER INFO */
    $get = $conn->prepare("SELECT customer_name, phone, tracking_code FROM transactions WHERE id=?");
    $get->bind_param("i", $id);
    $get->execute();
    $result = $get->get_result();
    $data = $result->fetch_assoc();

    if($data){
        $name = $data['customer_name'];
        $phone = $data['phone'];
        $tracking = $data['tracking_code'];

        /* SMS NOTIFICATION */
        if($status == "Ready for Pickup"){
            $message = "Hello $name! Your laundry is READY FOR PICKUP. Tracking Code: $tracking - CML Laundry";
            sendSMS($phone,$message);
        }

        if($status == "Out for Delivery"){
            $message = "Hello $name! Your laundry is OUT FOR DELIVERY. Tracking Code: $tracking - CML Laundry";
            sendSMS($phone,$message);
        }
    }

    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

if(isset($_GET['paid'])){
    $id = intval($_GET['paid']);

    $conn->query("UPDATE transactions SET
    payment_status='Fully Paid',
    downpayment = total,
    balance = 0
    WHERE id=$id
    ");

    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

/* SEARCH */
$where = "WHERE 1=1";

if(isset($_GET['search']) && trim($_GET['search']) != ""){
    $search = $conn->real_escape_string(trim($_GET['search']));

    $where .= " AND (
        t.customer_name LIKE '%$search%' OR
        t.phone LIKE '%$search%' OR
        t.payment_method LIKE '%$search%' OR
        t.gcash_reference LIKE '%$search%' OR
        t.payment_status LIKE '%$search%' OR
        t.status LIKE '%$search%' OR
        t.tracking_code LIKE '%$search%' OR
        t.extra_service LIKE '%$search%' OR
        t.addons LIKE '%$search%' OR
        s.service_name LIKE '%$search%' OR
        DATE(t.date_received) = '$search'
    ";

    if($hasDeliveryColumn){
        $where .= " OR t.delivery LIKE '%$search%'";
    }

    $where .= ")";
}

if(isset($_GET['date']) && trim($_GET['date']) != ""){
    $date = $conn->real_escape_string(trim($_GET['date']));
    $where .= " AND DATE(t.date_received) = '$date'";
}
?>

<div class="page-container">

<div class="page-header">

<div>
<h1>History</h1>
<p>All laundry history</p>
</div>

</div>

<div class="search-card">
<form class="filters" method="GET">

<div class="search-group">
<input type="text" name="search"
placeholder="Search name, phone, payment..."
value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
</div>

<div class="search-group">
<input type="date" name="date"
value="<?= htmlspecialchars($_GET['date'] ?? '') ?>">
</div>

<button type="submit" class="btn-search">Search</button>
<a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn-reset">Clear</a>

<a href="print_transactions_pdf.php?date=<?= urlencode($_GET['date'] ?? '') ?>&search=<?= urlencode($_GET['search'] ?? '') ?>" class="btn-print">
🖨 View Reports
</a>

</form>
</div>

<div class="table-card">
<table class="table">

<thead>
<tr>
<th>ID</th>
<th>Customer</th>
<th>Contact</th>
<th>Service</th>
<th>Extra Service</th>
<th>Add-ons</th>
<th>Date Received</th>
<th>Total</th>
<th>Paid</th>
<th>Balance</th>
<th>Payment</th>
<th>Delivery</th>
<th>Status</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php
$query = "
SELECT 
    t.*, 
    s.service_name 
FROM transactions t
LEFT JOIN services s ON t.service_id = s.id
$where
ORDER BY t.id DESC
";

$res = $conn->query($query);

if(!$res):
?>

<tr>
<td colspan="14" style="text-align:center; padding:20px; color:#ef4444; font-weight:700;">
    Query Error: <?= htmlspecialchars($conn->error) ?>
</td>
</tr>

<?php
else:
if($res->num_rows > 0):

while($row = $res->fetch_assoc()):

$deliveryValue = ''; // initialize

if($hasDeliveryColumn){

    $raw = strtolower(trim($row['delivery'] ?? ''));

    if($raw === 'delivery'){
        $deliveryValue = 'Delivery';
    }
    elseif($raw === 'pickup' || $raw === 'pick-up'){
        $deliveryValue = 'Pick-up';
    }

}

$row['date_received'] = $row['date_received'] ?? date("Y-m-d H:i:s");

/* EXTRA SERVICE NAME */
$extra = !empty($row['extra_service']) ? $row['extra_service'] : "None";

/* ADD ONS */
$addonsText = !empty($row['addons']) ? $row['addons'] : "None";

$gcashReference = $row['gcash_reference'] ?? '';

$currentStatus = $row['status'] ?? 'Pending';

if($deliveryValue === "Delivery"){

    $statusOptions = ["Pending","Processing","Out for Delivery","Claimed"];

    if($currentStatus == "Ready for Pickup"){
        $currentStatus = "Out for Delivery";
    }

}else{

    $statusOptions = ["Pending","Processing","Ready for Pickup","Claimed"];

    if($currentStatus == "Out for Delivery"){
        $currentStatus = "Ready for Pickup";
    }
}
?>

<tr>

<td>#<?= isset($row['id']) ? $row['id'] : 0 ?></td>

<td>
<div class="customer-name"><?= htmlspecialchars($row['customer_name'] ?? '') ?></div>
<div class="customer-address"><?= htmlspecialchars($row['address'] ?? '') ?></div>
</td>

<td><?= htmlspecialchars($row['phone'] ?? '') ?></td>

<td>
<?= !empty($row['service_name']) ? htmlspecialchars($row['service_name']) : 'No Service' ?>
</td>

<td><?= htmlspecialchars($extra) ?></td>

<td><?= htmlspecialchars($addonsText) ?></td>

<td>
<?= date("M d, Y", strtotime($row['date_received'])) ?><br>
<small><?= date("h:i A", strtotime($row['date_received'])) ?></small>
</td>

<td>₱<?= number_format((float)($row['total'] ?? 0),2) ?></td>
<td>₱<?= number_format((float)($row['downpayment'] ?? 0),2) ?></td>
<td>₱<?= number_format((float)($row['balance'] ?? 0),2) ?></td>

<td>
<?php if(($row['payment_method'] ?? '') == "GCash"): ?>

<button 
    type="button"
    class="badge gcash gcash-click"
    onclick="openGcashModal(
        '<?= $row['id'] ?>',
        '<?= htmlspecialchars($row['customer_name'] ?? '', ENT_QUOTES) ?>',
        '<?= htmlspecialchars($gcashReference, ENT_QUOTES) ?>'
    )">
    GCash
</button>

<?php if(!empty($gcashReference)): ?>
    <div class="ref-text">
        Ref: <?= htmlspecialchars($gcashReference) ?>
    </div>
<?php else: ?>
    <div class="ref-empty">
        No ref yet
    </div>
<?php endif; ?>

<?php else: ?>

<span class="badge cash">Cash</span>

<?php endif; ?>
</td>

<td>
<?php if($deliveryValue === "Delivery"): ?>
<span class="badge delivery">Delivery</span>
<?php else: ?>
<span class="badge pickup">Pick-up</span>
<?php endif; ?>
</td>

<td>

<form method="POST" class="status-form">

<input type="hidden" name="id" value="<?= $row['id'] ?>">

<select name="status" class="status-select">

<?php foreach($statusOptions as $option): ?>
<option value="<?= htmlspecialchars($option) ?>" <?= ($currentStatus == $option) ? "selected" : "" ?>>
    <?= htmlspecialchars($option) ?>
</option>
<?php endforeach; ?>

</select>

<button name="update_status" class="status-btn">
Update
</button>

</form>

</td>

<td class="actions">

<div class="action-buttons">

<button type="button" onclick="openReceiptModal(<?= $row['id'] ?>)" class="receipt-btn">
Receipt
</button>

<?php if(($row['balance'] ?? 0) > 0): ?>
<a href="?paid=<?= $row['id'] ?>" class="paid-btn">
Mark as Paid
</a>
<?php endif; ?>

</div>

</td>

</tr>

<?php endwhile; ?>

<?php else: ?>

<tr>
<td colspan="14" style="text-align:center; padding:22px; color:#64748b; font-weight:600;">
    No transaction found.
</td>
</tr>

<?php endif; ?>
<?php endif; ?>

</tbody>
</table>
</div>
</div>

<!-- GCASH REFERENCE MODAL -->
<div id="gcashModal" class="gcash-modal">
    <div class="gcash-modal-content">

        <div class="modal-header">
            <h3>GCash Reference</h3>
            <button type="button" class="modal-close" onclick="closeGcashModal()">×</button>
        </div>

        <p class="modal-subtitle">
            Enter or update the GCash reference number for this transaction.
        </p>

        <form method="POST">

            <input type="hidden" name="transaction_id" id="transaction_id">

            <label>Customer Name</label>
            <input type="text" id="gcash_customer_name" readonly>

            <label>Reference Number</label>
            <input 
                type="text" 
                name="gcash_reference" 
                id="gcash_reference" 
                placeholder="Enter GCash reference number"
                required
            >

            <div class="modal-actions">
                <button type="submit" name="save_gcash_reference" class="save-ref-btn">
                    Save Reference
                </button>

                <button type="button" onclick="closeGcashModal()" class="cancel-ref-btn">
                    Cancel
                </button>
            </div>

        </form>

    </div>
</div>

<!-- RECEIPT MODAL -->
<div id="receiptModal" class="receipt-modal">
    <div class="receipt-modal-content">

        <div class="receipt-modal-header">
            <h3>Receipt Preview</h3>

            <div class="receipt-modal-actions">
                <button type="button" onclick="printReceiptFromModal()" class="receipt-print-btn">
                    🖨 Print
                </button>

                <button type="button" onclick="closeReceiptModal()" class="receipt-close-btn">
                    ×
                </button>
            </div>
        </div>

        <iframe id="receiptFrame" src=""></iframe>

    </div>
</div>

<style>

/* SEARCH CARD */
.search-card{
background:white;
padding:12px;
border-radius:12px;
box-shadow:0 8px 22px rgba(0,0,0,.05);
margin:15px 0;
}

.filters{
display:flex;
gap:10px;
align-items:center;
flex-wrap:wrap;
}

.search-group input{
padding:9px 12px;
border:1px solid #e5e7eb;
border-radius:8px;
width:210px;
font-size:12px;
}

.btn-search{
background:#2d6cdf;
color:white;
border:none;
padding:9px 15px;
border-radius:8px;
font-weight:600;
cursor:pointer;
font-size:12px;
}

.btn-reset{
padding:9px 15px;
background:#f1f5f9;
border-radius:8px;
text-decoration:none;
color:#333;
font-weight:600;
font-size:12px;
}

/* TABLE */
.table-card{
background:white;
border-radius:14px;
box-shadow:0 10px 28px rgba(0,0,0,.08);
overflow:auto;
}

.table{
width:100%;
border-collapse:collapse;
font-size:11px;
}

.table thead{
background:#f8fafc;
}

.table th{
padding:9px 10px;
text-align:left;
font-weight:600;
color:#475569;
font-size:11px;
white-space:nowrap;
}

.table td{
padding:8px 10px;
border-top:1px solid #f1f5f9;
vertical-align:middle;
font-size:11px;
}

.table tr:hover{
background:#f9fbff;
}

.customer-name{
font-weight:600;
font-size:11px;
}

.customer-address{
font-size:10px;
color:#64748b;
}

/* BADGES */
.badge{
    display:inline-block;
    padding:4px 8px;
    border-radius:16px;
    font-size:10px;
    font-weight:600;
    color:white;
    white-space:nowrap;
    min-width:62px;
    text-align:center;
    border:none;
}

.paid{
background:#22c55e;
}

.dp{
background:#f59e0b;
}

.cash{
background:#9000ff;
}

.gcash{
background:#3b82f6;
}

.gcash-click{
cursor:pointer;
transition:.2s;
}

.gcash-click:hover{
background:#2563eb;
transform:scale(1.03);
}

.delivery{
background:#ef4444;
}

.pickup{
background:#14b8a6;
}

.ref-text{
font-size:9px;
color:#2563eb;
margin-top:4px;
font-weight:600;
}

.ref-empty{
font-size:9px;
color:#94a3b8;
margin-top:4px;
font-weight:600;
}

/* ACTION BUTTON FIX */
.actions{
width:150px;
}

.action-buttons{
display:flex;
gap:5px;
align-items:center;
flex-wrap:wrap;
}

.receipt-btn{
background:#64748b;
padding:5px 9px;
border-radius:8px;
color:white;
text-decoration:none;
font-size:10px;
font-weight:600;
}

.paid-btn{
background:#22c55e;
padding:5px 9px;
border-radius:8px;
color:white;
text-decoration:none;
font-size:10px;
font-weight:700;
}

.paid-btn:hover{
background:#16a34a;
}

/* STATUS FORM */
.status-form{
display:flex;
gap:4px;
align-items:center;
}

.status-select{
padding:4px 7px;
border:1px solid #e5e7eb;
border-radius:7px;
font-size:10px;
background:white;
max-width:115px;
}

.status-btn{
background:#2d6cdf;
color:white;
border:none;
padding:4px 8px;
border-radius:7px;
font-size:10px;
font-weight:600;
cursor:pointer;
transition:.2s;
}

.status-btn:hover{
background:#1e4fd1;
}

.btn-print{
background:#0f172a;
color:white;
padding:9px 15px;
border-radius:8px;
font-weight:600;
text-decoration:none;
margin-left:5px;
font-size:12px;
}

.btn-print:hover{
background:#020617;
}

.print-btn{
background:#0f172a;
color:white;
padding:8px 14px;
border-radius:8px;
font-weight:600;
text-decoration:none;
font-size:12px;
}

.print-btn:hover{
background:#020617;
}

.page-container{
max-width:1500px;
margin:auto;
padding:0 14px;
}

.page-header h1{
font-size:24px;
margin-bottom:4px;
}

.page-header p{
font-size:13px;
margin-top:0;
color:#64748b;
}

/* GCASH MODAL */
.gcash-modal{
display:none;
position:fixed;
top:0;
left:0;
width:100%;
height:100%;
background:rgba(15,23,42,.55);
z-index:99999;
align-items:center;
justify-content:center;
padding:20px;
}

.gcash-modal-content{
background:white;
width:390px;
max-width:100%;
border-radius:18px;
padding:22px;
box-shadow:0 22px 60px rgba(0,0,0,.25);
animation:modalPop .2s ease;
}

@keyframes modalPop{
from{
    transform:scale(.95);
    opacity:0;
}
to{
    transform:scale(1);
    opacity:1;
}
}

.modal-header{
display:flex;
justify-content:space-between;
align-items:center;
margin-bottom:8px;
}

.modal-header h3{
margin:0;
font-size:20px;
color:#0f172a;
}

.modal-close{
background:#f1f5f9;
border:none;
width:30px;
height:30px;
border-radius:50%;
font-size:20px;
font-weight:700;
cursor:pointer;
color:#334155;
}

.modal-close:hover{
background:#e2e8f0;
}

.modal-subtitle{
font-size:12px;
color:#64748b;
margin-bottom:18px;
line-height:1.5;
}

.gcash-modal-content label{
display:block;
font-size:12px;
font-weight:700;
color:#334155;
margin-bottom:6px;
margin-top:10px;
}

.gcash-modal-content input{
width:100%;
padding:11px 12px;
border:1px solid #e2e8f0;
border-radius:10px;
font-size:13px;
outline:none;
box-sizing:border-box;
}

.gcash-modal-content input:focus{
border-color:#3b82f6;
box-shadow:0 0 0 3px rgba(59,130,246,.15);
}

.gcash-modal-content input[readonly]{
background:#f8fafc;
color:#64748b;
}

.modal-actions{
display:flex;
gap:10px;
margin-top:20px;
}

.save-ref-btn{
flex:1;
background:#2563eb;
color:white;
border:none;
padding:10px;
border-radius:10px;
font-weight:700;
cursor:pointer;
font-size:12px;
}

.save-ref-btn:hover{
background:#1d4ed8;
}

.cancel-ref-btn{
flex:1;
background:#e5e7eb;
color:#111827;
border:none;
padding:10px;
border-radius:10px;
font-weight:700;
cursor:pointer;
font-size:12px;
}

.cancel-ref-btn:hover{
background:#d1d5db;
}

/* RECEIPT MODAL */
.receipt-modal{
display:none;
position:fixed;
top:0;
left:0;
width:100%;
height:100%;
background:rgba(15,23,42,.65);
z-index:999999;
align-items:center;
justify-content:center;
padding:20px;
}

.receipt-modal-content{
width:390px;
height:92vh;
background:white;
border-radius:18px;
overflow:hidden;
box-shadow:0 25px 70px rgba(0,0,0,.35);
display:flex;
flex-direction:column;
animation:modalPop .2s ease;
}

.receipt-modal-header{
display:flex;
justify-content:space-between;
align-items:center;
padding:12px 14px;
background:#0f172a;
color:white;
}

.receipt-modal-header h3{
margin:0;
font-size:15px;
font-weight:700;
}

.receipt-modal-actions{
display:flex;
gap:8px;
align-items:center;
}

.receipt-print-btn{
background:#22c55e;
color:white;
border:none;
padding:7px 10px;
border-radius:8px;
font-size:11px;
font-weight:700;
cursor:pointer;
}

.receipt-print-btn:hover{
background:#16a34a;
}

.receipt-close-btn{
background:#ef4444;
color:white;
border:none;
width:28px;
height:28px;
border-radius:50%;
font-size:18px;
font-weight:700;
cursor:pointer;
line-height:1;
}

.receipt-close-btn:hover{
background:#dc2626;
}

#receiptFrame{
width:100%;
height:100%;
border:none;
background:#eef3f9;
}

@media print{
    .search-card,
    .btn-print,
    .btn-search,
    .btn-reset,
    .status-btn,
    .receipt-btn,
    .paid-btn,
    .gcash-modal{
        display:none !important;
    }

    .page-container{
        max-width:100%;
        padding:0;
    }

    .table{
        font-size:9px;
    }

    .table th,
    .table td{
        padding:5px 6px;
        font-size:9px;
    }

    .badge{
        font-size:8px;
        padding:3px 6px;
        min-width:auto;
    }
}

</style>

<script>
function openGcashModal(transactionId, customerName, referenceNumber){
    document.getElementById("transaction_id").value = transactionId;
    document.getElementById("gcash_customer_name").value = customerName;
    document.getElementById("gcash_reference").value = referenceNumber;
    document.getElementById("gcashModal").style.display = "flex";
}

function closeGcashModal(){
    document.getElementById("gcashModal").style.display = "none";
}

/* RECEIPT MODAL FIX */
function openReceiptModal(transactionId){
    const modal = document.getElementById("receiptModal");
    const frame = document.getElementById("receiptFrame");

    frame.src = "receipt.php?id=" + transactionId + "&from=history";
    modal.style.display = "flex";
}

function closeReceiptModal(){
    const modal = document.getElementById("receiptModal");
    const frame = document.getElementById("receiptFrame");

    modal.style.display = "none";
    frame.src = "";
}

function printReceiptFromModal(){
    const frame = document.getElementById("receiptFrame");

    if(frame && frame.contentWindow){

        frame.contentWindow.focus();

        /* PRINT */
        frame.contentWindow.print();

        /* AUTO CLOSE MODAL AFTER PRINT */
        setTimeout(() => {
            closeReceiptModal();
        }, 1000);
    }
}

window.addEventListener("click", function(e){
    const gcashModal = document.getElementById("gcashModal");
    const receiptModal = document.getElementById("receiptModal");

    if(e.target === gcashModal){
        closeGcashModal();
    }

    if(e.target === receiptModal){
        closeReceiptModal();
    }
});
</script>