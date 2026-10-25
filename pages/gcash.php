<?php
session_start();
include "../config/db.php";
include "navbar.php";

if(!isset($_SESSION['admin'])){
    header("Location: ../auth/login.php");
    exit();
}

/* 🔎 DATE FILTER LOGIC */
$where = "payment_method='GCash'";

if(!empty($_GET['from'])){
    $from = $_GET['from'] . " 00:00:00";
    $where .= " AND created_at >= '$from'";
}

if(!empty($_GET['to'])){
    $to = $_GET['to'] . " 23:59:59";
    $where .= " AND created_at <= '$to'";
}

/* 🔥 UPDATED QUERY */
$res = $conn->query("
SELECT t.*, s.service_name
FROM transactions t
JOIN services s ON t.service_id=s.id
WHERE $where
ORDER BY id DESC
");
?>

<div class="container">

<h1>GCash Payments</h1>
<p>All GCash transactions and uploaded receipts.</p>

<!-- 🔎 DATE SEARCH -->
<form method="GET" class="search-bar">

    <div class="field">
        <label>From Date</label>
        <input type="date" name="from" value="<?= $_GET['from'] ?? '' ?>">
    </div>

    <div class="field">
        <label>To Date</label>
        <input type="date" name="to" value="<?= $_GET['to'] ?? '' ?>">
    </div>

    <button class="btn search-btn">Search</button>

    <!-- ALWAYS VISIBLE DELETE -->
    <a href="gcash.php" class="btn delete-btn">Delete</a>

</form>


<div class="table-card">
<table class="table">

<thead>
<tr>
    <th>ID</th>
    <th>Customer</th>
    <th>Service</th>
    <th>Total</th>
    <th>Reference</th>
    <th>Receipt</th>
    <th>Date</th>
</tr>
</thead>

<tbody>

<?php while($row=$res->fetch_assoc()): ?>

<tr>
<td>#<?= $row['id'] ?></td>

<td>
<b><?= $row['customer_name'] ?></b><br>
<small><?= $row['phone'] ?></small>
</td>

<td><?= $row['service_name'] ?></td>

<td>₱<?= number_format($row['total'],2) ?></td>

<td>
<span class="badge gcash">
<?= $row['gcash_reference'] ?: 'No Ref' ?>
</span>
</td>

<td>
<?php if($row['gcash_receipt']!=""): ?>
<a href="../uploads/<?= $row['gcash_receipt'] ?>" target="_blank" class="btn small">
View Receipt
</a>
<?php else: ?>
<small>No upload</small>
<?php endif; ?>
</td>

<td><?= date("M d, Y h:i A", strtotime($row['date_received'])) ?></td>

</tr>

<?php endwhile; ?>

</tbody>
</table>
</div>

</div>
