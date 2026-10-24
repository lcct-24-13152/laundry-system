<?php
session_start();
include "../config/db.php";

if(!isset($_SESSION['admin'])){
    header("Location: ../auth/login.php");
    exit();
}

date_default_timezone_set('Asia/Manila');

/* ===============================
   GET FILTER DATE
================================ */
$filterDate = $_GET['date'] ?? '';

/* ===============================
   BUILD CONDITION
================================ */
if(!empty($filterDate)){
    $filterDate = $conn->real_escape_string($filterDate);

    $dailyCondition = "DATE(date_received) = '$filterDate'";
    $weeklyCondition = "YEARWEEK(date_received,1)=YEARWEEK('$filterDate',1)";
    $monthlyCondition = "MONTH(date_received)=MONTH('$filterDate') AND YEAR(date_received)=YEAR('$filterDate')";
    $yearlyCondition = "YEAR(date_received)=YEAR('$filterDate')";
    $overallCondition = "DATE(date_received) = '$filterDate'";
}else{
    $dailyCondition = "DATE(date_received)=CURDATE()";
    $weeklyCondition = "YEARWEEK(date_received,1)=YEARWEEK(CURDATE(),1)";
    $monthlyCondition = "MONTH(date_received)=MONTH(CURDATE()) AND YEAR(date_received)=YEAR(CURDATE())";
    $yearlyCondition = "YEAR(date_received)=YEAR(CURDATE())";
    $overallCondition = "1=1";
}

/* ===============================
   HELPER FUNCTION
================================ */
function getReportData($conn, $condition){
    $query = $conn->query("
        SELECT 
            COUNT(*) AS total_transactions,
            SUM(total) AS total_sales,
            SUM(detergent) AS total_detergent,
            SUM(fabcon) AS total_fabcon,
            SUM(bleach) AS total_bleach
        FROM transactions
        WHERE $condition
    ");

    $row = $query->fetch_assoc();

    return [
        "sales" => floatval($row['total_sales'] ?? 0),
        "transactions" => intval($row['total_transactions'] ?? 0),
        "detergent" => floatval($row['total_detergent'] ?? 0),
        "fabcon" => floatval($row['total_fabcon'] ?? 0),
        "bleach" => floatval($row['total_bleach'] ?? 0)
    ];
}

/* ===============================
   REPORT DATA
================================ */
$daily = getReportData($conn, $dailyCondition);
$weekly = getReportData($conn, $weeklyCondition);
$monthly = getReportData($conn, $monthlyCondition);
$yearly = getReportData($conn, $yearlyCondition);
$overall = getReportData($conn, $overallCondition);

/* ===============================
   TOP SERVICES (FILTERED)
================================ */
$topServices = $conn->query("
    SELECT 
        IFNULL(s.service_name, 'No Service') AS service_name,
        COUNT(*) AS total_count,
        SUM(t.total) AS total_sales
    FROM transactions t
    LEFT JOIN services s ON t.service_id = s.id
    WHERE $overallCondition
    GROUP BY s.service_name
    ORDER BY total_count DESC
    LIMIT 5
");

/* ===============================
   RECENT TRANSACTIONS (FILTERED)
================================ */
$recentTransactions = $conn->query("
    SELECT t.*, s.service_name
    FROM transactions t
    LEFT JOIN services s ON t.service_id = s.id
    WHERE $overallCondition
    ORDER BY t.id DESC
    LIMIT 8
");
?>

<!DOCTYPE html>
<html>
<head>

<title>Reports</title>

<style>
*{
    box-sizing:border-box;
}

body{
    font-family: Arial, sans-serif;
    padding: 28px;
    margin: 0;
    background: linear-gradient(135deg, #f8fbff, #eef4ff);
    color: #0f172a;
}

.top-buttons {
    position: fixed;
    top: 20px;
    left: 20px;
    display: flex;
    gap: 10px;
    z-index: 999;
}

.back-btn,
.print-top-btn {
    border: none;
    color: white;
    padding: 9px 16px;
    border-radius: 10px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 700;
    box-shadow: 0 5px 14px rgba(0,0,0,.12);
}

.back-btn {
    background: #2563eb;
}

.print-top-btn {
    background: #16a34a;
}

.back-btn:hover {
    background: #1d4ed8;
}

.print-top-btn:hover {
    background: #15803d;
}

.report-header{
    text-align:center;
    margin-bottom:28px;
}

.report-header h1{
    font-size:34px;
    margin:0;
    font-weight:800;
    letter-spacing:.5px;
}

.report-header h3{
    font-size:18px;
    margin:8px 0 0;
    color:#334155;
}

.report-wrapper{
    width: 90%;
    max-width: 1250px;
    margin: 0 auto;
}

.sales-summary{
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
    margin-bottom: 22px;
}

.sales-box{
    background: rgba(255,255,255,.9);
    border: 1px solid #dbe4f0;
    padding: 20px 16px;
    border-radius: 18px;
    text-align: center;
    box-shadow: 0 10px 28px rgba(37,99,235,.08);
}

.sales-box .period{
    font-size:14px;
    color:#475569;
    font-weight:700;
    margin-bottom:8px;
}

.sales-box .amount{
    font-size:24px;
    font-weight:800;
    color:#0f172a;
    margin-bottom:8px;
}

.sales-box .count{
    font-size:13px;
    font-weight:700;
    color:#2563eb;
    background:#eff6ff;
    display:inline-block;
    padding:5px 12px;
    border-radius:20px;
}

.section-card{
    background: rgba(255,255,255,.95);
    border: 1px solid #dbe4f0;
    border-radius: 18px;
    padding: 22px;
    margin-bottom: 22px;
    box-shadow: 0 10px 28px rgba(15,23,42,.07);
}

.section-card h2{
    margin:0 0 18px;
    text-align:center;
    font-size:22px;
}

.supplies-grid{
    display:grid;
    grid-template-columns: repeat(4, 1fr);
    gap:14px;
}

.supply-period{
    border:1px solid #e2e8f0;
    background:#f8fafc;
    border-radius:14px;
    padding:15px;
}

.supply-period h4{
    margin:0 0 12px;
    font-size:15px;
    text-align:center;
    color:#1e293b;
}

.supply-row{
    display:flex;
    justify-content:space-between;
    padding:7px 0;
    border-bottom:1px dashed #cbd5e1;
    font-size:13px;
}

.supply-row:last-child{
    border-bottom:none;
}

.supply-row span:first-child{
    color:#475569;
    font-weight:700;
}

.supply-row span:last-child{
    color:#0f172a;
    font-weight:800;
}

.overall-grid{
    display:grid;
    grid-template-columns: repeat(4, 1fr);
    gap:14px;
}

.overall-item{
    background:#f8fafc;
    border:1px solid #e2e8f0;
    padding:18px;
    border-radius:14px;
    text-align:center;
}

.overall-item small{
    display:block;
    color:#475569;
    font-size:13px;
    font-weight:700;
    margin-bottom:8px;
}

.overall-item strong{
    font-size:22px;
    color:#0f172a;
}

.double-section{
    display:grid;
    grid-template-columns: 1fr 1fr;
    gap:22px;
}

.report-table{
    width:100%;
    border-collapse:collapse;
    font-size:13px;
}

.report-table th{
    background:#eff6ff;
    color:#1e3a8a;
    padding:10px;
    border:1px solid #dbe4f0;
    text-align:left;
}

.report-table td{
    padding:10px;
    border:1px solid #e5e7eb;
}

.report-table tr:hover td{
    background:#f8fbff;
}

.badge{
    display:inline-block;
    padding:5px 10px;
    border-radius:20px;
    font-size:12px;
    font-weight:700;
}

.badge-blue{
    background:#dbeafe;
    color:#1d4ed8;
}

.badge-green{
    background:#dcfce7;
    color:#15803d;
}

.footer-note{
    text-align:center;
    color:#64748b;
    font-size:12px;
    margin-top:20px;
}

@media print {
    body{
        background:white;
        padding:8mm;
    }

    .top-buttons{
        display:none !important;
    }

    .report-wrapper{
        width:100%;
        max-width:100%;
    }

    .report-header{
        margin-bottom:14px;
    }

    .report-header h1{
        font-size:24px;
    }

    .report-header h3{
        font-size:14px;
    }

    .sales-summary{
        grid-template-columns: repeat(4, 1fr);
        gap:8px;
        margin-bottom:12px;
    }

    .sales-box{
        padding:9px 6px;
        border-radius:8px;
        box-shadow:none;
    }

    .sales-box .period{
        font-size:9px;
        margin-bottom:3px;
    }

    .sales-box .amount{
        font-size:12px;
        margin-bottom:3px;
    }

    .sales-box .count{
        font-size:8px;
        padding:2px 6px;
    }

    .section-card{
        box-shadow:none;
        border-radius:8px;
        padding:10px;
        margin-bottom:10px;
    }

    .section-card h2{
        font-size:14px;
        margin-bottom:8px;
    }

    .supplies-grid,
    .overall-grid{
        gap:6px;
    }

    .supply-period,
    .overall-item{
        padding:7px;
        border-radius:6px;
    }

    .supply-period h4{
        font-size:9px;
        margin-bottom:5px;
    }

    .supply-row{
        font-size:8px;
        padding:3px 0;
    }

    .overall-item small{
        font-size:8px;
        margin-bottom:3px;
    }

    .overall-item strong{
        font-size:11px;
    }

    .double-section{
        grid-template-columns:1fr 1fr;
        gap:10px;
    }

    .report-table{
        font-size:8px;
    }

    .report-table th,
    .report-table td{
        padding:4px;
    }

    .badge{
        font-size:7px;
        padding:2px 5px;
    }

    .footer-note{
        font-size:8px;
        margin-top:8px;
    }

    @page{
        size:landscape;
        margin:8mm;
    }
}
</style>

</head>

<body>

<div class="top-buttons">
    <button onclick="goBack()" class="back-btn">← Back</button>
    <button onclick="window.print()" class="print-top-btn">🖨 Print</button>
</div>

<div class="report-header">
    <h1>CML Laundry Shop</h1>
    <h3>Sales and Supplies Report</h3>
</div>

<div class="report-wrapper">

    <div class="sales-summary">

        <div class="sales-box">
            <div class="period">Daily Sales</div>
            <div class="amount">₱<?= number_format($daily['sales'], 2) ?></div>
            <div class="count"><?= $daily['transactions'] ?> Transaction<?= $daily['transactions'] == 1 ? '' : 's' ?></div>
        </div>

        <div class="sales-box">
            <div class="period">Weekly Sales</div>
            <div class="amount">₱<?= number_format($weekly['sales'], 2) ?></div>
            <div class="count"><?= $weekly['transactions'] ?> Transaction<?= $weekly['transactions'] == 1 ? '' : 's' ?></div>
        </div>

        <div class="sales-box">
            <div class="period">Monthly Sales</div>
            <div class="amount">₱<?= number_format($monthly['sales'], 2) ?></div>
            <div class="count"><?= $monthly['transactions'] ?> Transaction<?= $monthly['transactions'] == 1 ? '' : 's' ?></div>
        </div>

        <div class="sales-box">
            <div class="period">Yearly Sales</div>
            <div class="amount">₱<?= number_format($yearly['sales'], 2) ?></div>
            <div class="count"><?= $yearly['transactions'] ?> Transaction<?= $yearly['transactions'] == 1 ? '' : 's' ?></div>
        </div>

    </div>

    <div class="section-card">
        <h2>Supplies Used Breakdown</h2>

        <div class="supplies-grid">

            <div class="supply-period">
                <h4>Daily Supplies</h4>
                <div class="supply-row">
                    <span>Detergent</span>
                    <span><?= number_format($daily['detergent'], 0) ?> mL</span>
                </div>
                <div class="supply-row">
                    <span>Fabcon</span>
                    <span><?= number_format($daily['fabcon'], 0) ?> mL</span>
                </div>
                <div class="supply-row">
                    <span>Bleach</span>
                    <span><?= number_format($daily['bleach'], 0) ?> mL</span>
                </div>
            </div>

            <div class="supply-period">
                <h4>Weekly Supplies</h4>
                <div class="supply-row">
                    <span>Detergent</span>
                    <span><?= number_format($weekly['detergent'], 0) ?> mL</span>
                </div>
                <div class="supply-row">
                    <span>Fabcon</span>
                    <span><?= number_format($weekly['fabcon'], 0) ?> mL</span>
                </div>
                <div class="supply-row">
                    <span>Bleach</span>
                    <span><?= number_format($weekly['bleach'], 0) ?> mL</span>
                </div>
            </div>

            <div class="supply-period">
                <h4>Monthly Supplies</h4>
                <div class="supply-row">
                    <span>Detergent</span>
                    <span><?= number_format($monthly['detergent'], 0) ?> mL</span>
                </div>
                <div class="supply-row">
                    <span>Fabcon</span>
                    <span><?= number_format($monthly['fabcon'], 0) ?> mL</span>
                </div>
                <div class="supply-row">
                    <span>Bleach</span>
                    <span><?= number_format($monthly['bleach'], 0) ?> mL</span>
                </div>
            </div>

            <div class="supply-period">
                <h4>Yearly Supplies</h4>
                <div class="supply-row">
                    <span>Detergent</span>
                    <span><?= number_format($yearly['detergent'], 0) ?> mL</span>
                </div>
                <div class="supply-row">
                    <span>Fabcon</span>
                    <span><?= number_format($yearly['fabcon'], 0) ?> mL</span>
                </div>
                <div class="supply-row">
                    <span>Bleach</span>
                    <span><?= number_format($yearly['bleach'], 0) ?> mL</span>
                </div>
            </div>

        </div>
    </div>

    <div class="section-card">
        <h2>Overall Report Summary</h2>

        <div class="overall-grid">

            <div class="overall-item">
                <small>Total Transactions</small>
                <strong><?= $overall['transactions'] ?></strong>
            </div>

            <div class="overall-item">
                <small>Total Revenue</small>
                <strong>₱<?= number_format($overall['sales'], 2) ?></strong>
            </div>

            <div class="overall-item">
                <small>Total Supplies Used</small>
                <strong><?= number_format($overall['detergent'] + $overall['fabcon'] + $overall['bleach'], 0) ?> mL</strong>
            </div>

            <div class="overall-item">
                <small>Report Date</small>
                <strong><?= date("M d, Y") ?></strong>
            </div>

        </div>
    </div>

    <div class="double-section">

        <div class="section-card">
            <h2>Top Services</h2>

            <table class="report-table">
                <tr>
                    <th>Service</th>
                    <th>Transactions</th>
                    <th>Sales</th>
                </tr>

                <?php if($topServices && $topServices->num_rows > 0): ?>
                    <?php while($s = $topServices->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['service_name']) ?></td>
                        <td><span class="badge badge-blue"><?= $s['total_count'] ?></span></td>
                        <td>₱<?= number_format(floatval($s['total_sales'] ?? 0), 2) ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" style="text-align:center;">No service data found.</td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>

        <div class="section-card">
            <h2>Recent Transactions</h2>

            <table class="report-table">
                <tr>
                    <th>ID</th>
                    <th>Customer</th>
                    <th>Total</th>
                    <th>Date</th>
                </tr>

                <?php if($recentTransactions && $recentTransactions->num_rows > 0): ?>
                    <?php while($r = $recentTransactions->fetch_assoc()): ?>
                    <tr>
                        <td>#<?= $r['id'] ?></td>
                        <td><?= htmlspecialchars($r['customer_name']) ?></td>
                        <td>₱<?= number_format(floatval($r['total'] ?? 0), 2) ?></td>
                        <td><?= !empty($r['date_received']) ? date("M d, Y", strtotime($r['date_received'])) : '-' ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align:center;">No recent transactions found.</td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>

    </div>

    <div class="footer-note">
        Generated by CML Laundry Management System • <?= date("F d, Y h:i A") ?>
    </div>

</div>

<script>
function goBack() {
    if (document.referrer && document.referrer !== window.location.href) {
        window.location.href = document.referrer;
    } else {
        window.location.href = "transactions.php";
    }
}
</script>

</body>
</html>