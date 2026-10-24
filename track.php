<?php
include "../config/db.php";

$code = $_GET['code'] ?? '';

if (empty($code)) {
    echo "No tracking code.";
    exit();
}

$code = mysqli_real_escape_string($conn, $code);

$res = $conn->query("SELECT * FROM transactions WHERE tracking_code='$code'");

if (!$res) {
    die("SQL Error: " . $conn->error);
}

if ($res->num_rows == 0) {
    echo "Invalid tracking code.";
    exit();
}

$data = $res->fetch_assoc();

/* GET DELIVERY TYPE */
$delivery = $data['delivery'] ?? 'Pick-up';

/* STATUS LIST BASED ON DELIVERY TYPE */
if ($delivery == "Delivery") {
    $statuses = ["Pending", "Processing", "Out for Delivery", "Claimed"];
} else {
    $statuses = ["Pending", "Processing", "Ready for Pickup", "Claimed"];
}

$current = $data['status'] ?? "Pending";

/* FIX STATUS IF NOT INCLUDED IN SELECTED DELIVERY FLOW */
if ($delivery == "Delivery" && $current == "Ready for Pickup") {
    $current = "Out for Delivery";
}

if ($delivery != "Delivery" && $current == "Out for Delivery") {
    $current = "Ready for Pickup";
}

/* GET CURRENT INDEX */
$current_index = array_search($current, $statuses);

if ($current_index === false) {
    $current_index = 0;
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Track Laundry</title>

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
body{
    font-family:Arial, sans-serif;
    background:#eef3f9;
    padding:20px;
    margin:0;
}

.container{
    max-width:480px;
    margin:70px auto 0;
    background:white;
    padding:25px;
    border-radius:16px;
    box-shadow:0 10px 25px rgba(0,0,0,.1);
}

h2{
    text-align:center;
    margin:0 0 10px;
    font-size:26px;
}

.code{
    text-align:center;
    color:#888;
    margin-bottom:25px;
    font-size:17px;
}

.progress{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    margin-bottom:25px;
    position:relative;
}

.step{
    text-align:center;
    flex:1;
    position:relative;
}

.circle{
    width:28px;
    height:28px;
    border-radius:50%;
    background:#ccc;
    margin:auto;
    line-height:28px;
    color:white;
    font-size:13px;
    font-weight:bold;
    position:relative;
    z-index:2;
}

.circle.active{
    background:#2d6cdf;
}

.line{
    height:3px;
    background:#ccc;
    position:absolute;
    top:13px;
    left:50%;
    width:100%;
    z-index:1;
}

.line.active-line{
    background:#2d6cdf;
}

.step:last-child .line{
    display:none;
}

.label{
    font-size:13px;
    margin-top:7px;
    line-height:1.2;
}

.info{
    margin-top:20px;
    font-size:16px;
}

.info p{
    margin:8px 0;
}

strong{
    font-weight:700;
}

@media(max-width:480px){
    body{
        padding:15px;
    }

    .container{
        margin-top:55px;
        padding:22px;
        max-width:100%;
    }

    h2{
        font-size:24px;
    }

    .code{
        font-size:16px;
    }

    .label{
        font-size:12px;
    }

    .info{
        font-size:15px;
    }
}
</style>
</head>

<body>
<div class="container">

<h2>🧺 Laundry Tracking</h2>
<div class="code"><?= htmlspecialchars($code) ?></div>

<div class="progress">
<?php foreach($statuses as $index => $status): ?>
<div class="step">
    <div class="circle <?= $index <= $current_index ? 'active' : '' ?>">
        <?= $index + 1 ?>
    </div>

    <div class="label"><?= htmlspecialchars($status) ?></div>

    <div class="line <?= $index < $current_index ? 'active-line' : '' ?>"></div>
</div>
<?php endforeach; ?>
</div>

<div class="info">
    <p><strong>Customer:</strong> <?= htmlspecialchars($data['customer_name']) ?></p>
    <p><strong>Status:</strong> <?= htmlspecialchars($current) ?></p>
    <p><strong>Weight:</strong> <?= htmlspecialchars(number_format((float)$data['weight'], 2)) ?> kg</p>
    <p><strong>Payment:</strong> <?= htmlspecialchars($data['payment_status']) ?></p>
</div>

</div>
</body>
</html>