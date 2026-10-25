<?php
session_start();
include "../config/db.php";
include __DIR__ . "/navbar.php";

if(!isset($_SESSION['admin'])){
    header("Location: ../auth/login.php");
    exit();
}
?>

<link rel="stylesheet" href="../assets/css/style.css">

<?php
/* ===== SALES TOTALS ===== */
$todaySales = $conn->query("SELECT IFNULL(SUM(total),0) t FROM transactions WHERE DATE(date_received)=CURDATE()")->fetch_assoc()['t'];
$weekSales  = $conn->query("SELECT IFNULL(SUM(total),0) t FROM transactions WHERE YEARWEEK(date_received,1)=YEARWEEK(CURDATE(),1)")->fetch_assoc()['t'];
$monthSales = $conn->query("SELECT IFNULL(SUM(total),0) t 
FROM transactions 
WHERE MONTH(date_received)=MONTH(CURDATE())  
AND YEAR(date_received)=YEAR(CURDATE())")->fetch_assoc()['t'];
$yearSales  = $conn->query("SELECT IFNULL(SUM(total),0) t 
FROM transactions 
WHERE YEAR(date_received)=YEAR(CURDATE())")->fetch_assoc()['t'];


/* ===== CHART DATA ===== */
$dailySales=[];
for($i=6;$i>=0;$i--){
  $q=$conn->query("SELECT IFNULL(SUM(total),0) t FROM transactions WHERE DATE(date_received)=DATE_SUB(CURDATE(),INTERVAL $i DAY)");
  $dailySales[]=$q->fetch_assoc()['t'];
}

$weeklySales=[];
for($i=3;$i>=0;$i--){
  $q=$conn->query("SELECT IFNULL(SUM(total),0) t FROM transactions WHERE YEARWEEK(date_received,1)=YEARWEEK(CURDATE()-INTERVAL $i WEEK,1)");
  $weeklySales[]=$q->fetch_assoc()['t'];
}

$monthlySales=[];
for($m=1;$m<=12;$m++){
  $q=$conn->query("SELECT IFNULL(SUM(total),0) t FROM transactions WHERE MONTH(date_received)=$m  AND YEAR(date_received)=YEAR(CURDATE())");
  $monthlySales[]=$q->fetch_assoc()['t'];
}

$yearlySales=[];
for($y=4;$y>=0;$y--){
  $q=$conn->query("SELECT IFNULL(SUM(total),0) t FROM transactions WHERE YEAR(date_received)=YEAR(CURDATE())-$y");
  $yearlySales[]=$q->fetch_assoc()['t'];
}

/* ===== TRANSACTION COUNTS ===== */
$todayCount = $conn->query("SELECT COUNT(*) c FROM transactions WHERE DATE(date_received)=CURDATE()")->fetch_assoc()['c'];
$weekCount  = $conn->query("SELECT COUNT(*) c FROM transactions WHERE YEARWEEK(date_received,1)=YEARWEEK(CURDATE(),1)")->fetch_assoc()['c'];
$monthCount = $conn->query("SELECT COUNT(*) c FROM transactions WHERE MONTH(date_received)=MONTH(CURDATE()) AND YEAR(date_received)=YEAR(CURDATE())")->fetch_assoc()['c'];
$yearCount  = $conn->query("SELECT COUNT(*) c FROM transactions WHERE YEAR(date_received)=YEAR(CURDATE())")->fetch_assoc()['c'];

/* ===== INVENTORY GROUPED BY CATEGORY ===== */
$inventory = $conn->query("SELECT * FROM inventory ORDER BY category ASC, item_name ASC");

$inventoryGroups = [];

while($row = $inventory->fetch_assoc()){
    $category = trim($row['category']);
    if($category == ''){
        $category = 'Uncategorized';
    }

    $inventoryGroups[$category][] = $row;
}
?>

<div class="dashboard-container">

    <div class="dashboard-header">
        <div>
            <h1>Dashboard</h1>
            <p>Quick business overview and sales monitoring</p>
        </div>
    </div>

    <!-- SALES CARDS -->
    <div class="stats-grid">

        <button type="button" class="stat-card" onclick="openSalesModal('dailyModal')">
            <div class="stat-icon">₱</div>
            <div>
                <div class="stat-title">Today Sales</div>
                <div class="stat-value">₱<?=number_format($todaySales,2)?></div>
                <div class="stat-sub"><?= $todayCount ?> Transaction<?= $todayCount == 1 ? '' : 's' ?></div>
            </div>
        </button>

        <button type="button" class="stat-card" onclick="openSalesModal('weeklyModal')">
            <div class="stat-icon">↗</div>
            <div>
                <div class="stat-title">This Week</div>
                <div class="stat-value">₱<?=number_format($weekSales,2)?></div>
                <div class="stat-sub"><?= $weekCount ?> Transaction<?= $weekCount == 1 ? '' : 's' ?></div>
            </div>
        </button>

        <button type="button" class="stat-card" onclick="openSalesModal('monthlyModal')">
            <div class="stat-icon">📅</div>
            <div>
                <div class="stat-title">This Month</div>
                <div class="stat-value">₱<?=number_format($monthSales,2)?></div>
                <div class="stat-sub"><?= $monthCount ?> Transaction<?= $monthCount == 1 ? '' : 's' ?></div>
            </div>
        </button>

        <button type="button" class="stat-card" onclick="openSalesModal('yearlyModal')">
            <div class="stat-icon">◎</div>
            <div>
                <div class="stat-title">This Year</div>
                <div class="stat-value">₱<?=number_format($yearSales,2)?></div>
                <div class="stat-sub"><?= $yearCount ?> Transaction<?= $yearCount == 1 ? '' : 's' ?></div>
            </div>
        </button>

    </div>

    <!-- INVENTORY STATUS -->
    <div class="inventory-card">
        <div class="section-header">
            <div>
                <h3>Inventory Status</h3>
                <p>Grouped by category for cleaner stock monitoring</p>
            </div>
        </div>

        <div class="inventory-category-grid">

            <?php foreach($inventoryGroups as $category => $items): ?>

                <div class="inventory-category-card">
                    <div class="category-title">
                        <span><?= htmlspecialchars(strtoupper($category)) ?></span>
                        <small><?= count($items) ?> item<?= count($items) == 1 ? '' : 's' ?></small>
                    </div>

                    <div class="mini-table-wrapper">
                        <table class="mini-inventory-table">
                            <tr>
                                <th>Item</th>
                                <th>Stock</th>
                                <th>Status</th>
                            </tr>

                            <?php foreach($items as $row): 

                                $stock = floatval($row['quantity']);
                                $critical = floatval($row['critical_level']);
                                $reorder = floatval($row['reorder_level']);

                                if($stock <= $critical){
                                    $status = "<span class='critical'>CRITICAL</span>";
                                }
                                elseif($stock <= $reorder){
                                    $status = "<span class='low'>LOW</span>";
                                }
                                else{
                                    $status = "<span class='ok'>OK</span>";
                                }
                            ?>

                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($row['item_name']) ?></strong>
                                    <small>Unit: <?= htmlspecialchars($row['unit']) ?></small>
                                </td>
                                <td>
                                    <?= number_format($row['quantity'], 2) ?>
                                </td>
                                <td><?= $status ?></td>
                            </tr>

                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>

            <?php endforeach; ?>

        </div>
    </div>

</div>

<!-- DAILY SALES MODAL -->
<div id="dailyModal" class="sales-modal-overlay">
    <div class="sales-modal">
        <div class="sales-modal-header">
            <div>
                <h3>Daily Sales</h3>
                <p>Sales for the last 7 days</p>
            </div>
            <button type="button" onclick="closeSalesModal('dailyModal')">×</button>
        </div>

        <div class="sales-modal-body">
            <canvas id="dailyChart"></canvas>
        </div>
    </div>
</div>

<!-- WEEKLY SALES MODAL -->
<div id="weeklyModal" class="sales-modal-overlay">
    <div class="sales-modal">
        <div class="sales-modal-header">
            <div>
                <h3>Weekly Sales</h3>
                <p>Sales for the last 4 weeks</p>
            </div>
            <button type="button" onclick="closeSalesModal('weeklyModal')">×</button>
        </div>

        <div class="sales-modal-body">
            <canvas id="weeklyChart"></canvas>
        </div>
    </div>
</div>

<!-- MONTHLY SALES MODAL -->
<div id="monthlyModal" class="sales-modal-overlay">
    <div class="sales-modal">
        <div class="sales-modal-header">
            <div>
                <h3>Monthly Sales</h3>
                <p>Sales per month this year</p>
            </div>
            <button type="button" onclick="closeSalesModal('monthlyModal')">×</button>
        </div>

        <div class="sales-modal-body">
            <canvas id="monthlyChart"></canvas>
        </div>
    </div>
</div>

<!-- YEARLY SALES MODAL -->
<div id="yearlyModal" class="sales-modal-overlay">
    <div class="sales-modal">
        <div class="sales-modal-header">
            <div>
                <h3>Yearly Sales</h3>
                <p>Sales for the last 5 years</p>
            </div>
            <button type="button" onclick="closeSalesModal('yearlyModal')">×</button>
        </div>

        <div class="sales-modal-body">
            <canvas id="yearlyChart"></canvas>
        </div>
    </div>
</div>

<style>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap');

*{
    font-family:'Poppins', sans-serif;
    box-sizing:border-box;
}

body{
    background:#f4f8ff;
    color:#0f172a;
}

.dashboard-container{
    width:100%;
    max-width:1500px;
    margin:0 auto;
    padding:24px 28px 36px;
}

.dashboard-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:20px;
}

.dashboard-header h1{
    margin:0;
    font-size:26px;
    font-weight:800;
    color:#0f172a;
    letter-spacing:-.5px;
}

.dashboard-header p{
    margin:4px 0 0;
    color:#64748b;
    font-size:13px;
}

/* SALES CARDS */
.stats-grid{
    display:grid;
    grid-template-columns:repeat(4, minmax(0,1fr));
    gap:16px;
    margin-bottom:20px;
}

.stat-card{
    border:none;
    outline:none;
    background:linear-gradient(135deg,#2563eb,#3b82f6);
    color:#fff;
    border-radius:18px;
    padding:16px 18px;
    min-height:102px;
    display:flex;
    align-items:center;
    gap:14px;
    box-shadow:0 12px 28px rgba(37,99,235,.20);
    transition:.2s ease;
    overflow:hidden;
    position:relative;
    cursor:pointer;
    text-align:left;
}

.stat-card::after{
    content:"";
    position:absolute;
    width:112px;
    height:112px;
    right:-42px;
    top:-42px;
    background:rgba(255,255,255,.14);
    border-radius:50%;
}

.stat-card:hover{
    transform:translateY(-3px);
    box-shadow:0 16px 34px rgba(37,99,235,.26);
}

.stat-icon{
    width:40px;
    height:40px;
    border-radius:13px;
    background:rgba(255,255,255,.18);
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:19px;
    font-weight:800;
    flex-shrink:0;
}

.stat-title{
    font-size:13px;
    font-weight:500;
    opacity:.92;
    margin-bottom:5px;
}

.stat-value{
    font-size:24px;
    font-weight:800;
    letter-spacing:.2px;
    line-height:1.1;
}

.stat-sub{
    margin-top:6px;
    font-size:11px;
    font-weight:500;
    opacity:.88;
}

/* INVENTORY */
.inventory-card{
    background:#ffffff;
    padding:18px;
    border-radius:18px;
    border:1px solid #e6edf7;
    box-shadow:0 10px 24px rgba(15,23,42,.06);
}

.section-header{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:10px;
    margin-bottom:14px;
}

.section-header h3{
    margin:0;
    font-size:18px;
    font-weight:800;
    color:#0f172a;
}

.section-header p{
    margin:3px 0 0;
    font-size:12px;
    color:#64748b;
}

.inventory-category-grid{
    display:grid;
    grid-template-columns:repeat(3, minmax(0,1fr));
    gap:14px;
}

.inventory-category-card{
    background:linear-gradient(180deg,#ffffff,#f8fbff);
    border:1px solid #e6edf7;
    border-radius:16px;
    padding:14px;
    box-shadow:0 6px 16px rgba(15,23,42,.04);
}

.category-title{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    margin-bottom:10px;
}

.category-title span{
    color:#1e3a8a;
    font-size:13px;
    font-weight:800;
    letter-spacing:.4px;
}

.category-title small{
    background:#eff6ff;
    color:#2563eb;
    padding:4px 8px;
    border-radius:999px;
    font-size:10px;
    font-weight:700;
    white-space:nowrap;
}

.mini-table-wrapper{
    width:100%;
    overflow-x:auto;
}

.mini-inventory-table{
    width:100%;
    border-collapse:collapse;
}

.mini-inventory-table th{
    background:#f1f6ff;
    padding:9px 10px;
    text-align:left;
    font-size:11px;
    font-weight:800;
    color:#334155;
    border-bottom:1px solid #e6edf7;
    white-space:nowrap;
}

.mini-inventory-table td{
    padding:9px 10px;
    border-bottom:1px solid #edf2f7;
    font-size:11.5px;
    color:#0f172a;
    vertical-align:middle;
}

.mini-inventory-table td strong{
    display:block;
    font-size:11.8px;
    color:#0f172a;
}

.mini-inventory-table td small{
    display:block;
    margin-top:2px;
    color:#64748b;
    font-size:10px;
}

.mini-inventory-table tr:last-child td{
    border-bottom:none;
}

.critical,
.low,
.ok{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:68px;
    padding:4px 8px;
    border-radius:999px;
    font-size:10px;
    font-weight:800;
}

.critical{
    background:#fee2e2;
    color:#b91c1c;
}

.low{
    background:#fef3c7;
    color:#92400e;
}

.ok{
    background:#dcfce7;
    color:#15803d;
}

/* SALES MODAL */
.sales-modal-overlay{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(15,23,42,.45);
    z-index:99999;
    align-items:center;
    justify-content:center;
    padding:24px;
}

.sales-modal-overlay.show{
    display:flex;
}

.sales-modal{
    width:min(940px, 96vw);
    background:#ffffff;
    border-radius:22px;
    border:1px solid #e6edf7;
    box-shadow:0 28px 80px rgba(15,23,42,.28);
    overflow:hidden;
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

.sales-modal-header{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:16px;
    padding:18px 20px;
    border-bottom:1px solid #e6edf7;
    background:linear-gradient(135deg,#ffffff,#eff6ff);
}

.sales-modal-header h3{
    margin:0;
    font-size:20px;
    font-weight:800;
    color:#0f172a;
}

.sales-modal-header p{
    margin:4px 0 0;
    color:#64748b;
    font-size:12px;
}

.sales-modal-header button{
    width:34px;
    height:34px;
    border:none;
    border-radius:50%;
    background:#dbeafe;
    color:#1d4ed8;
    font-size:22px;
    font-weight:800;
    cursor:pointer;
    transition:.2s ease;
}

.sales-modal-header button:hover{
    background:#bfdbfe;
}

.sales-modal-body{
    padding:18px 20px 22px;
    height:390px;
}

.sales-modal-body canvas{
    width:100%!important;
    height:100%!important;
}

/* RESPONSIVE */
@media(max-width:1200px){
    .stats-grid{
        grid-template-columns:repeat(2,1fr);
    }

    .inventory-category-grid{
        grid-template-columns:repeat(2,1fr);
    }
}

@media(max-width:900px){
    .dashboard-container{
        padding:20px 16px 30px;
    }

    .inventory-category-grid{
        grid-template-columns:1fr;
    }

    .sales-modal-body{
        height:330px;
    }
}

@media(max-width:600px){
    .stats-grid{
        grid-template-columns:1fr;
    }

    .dashboard-header h1{
        font-size:24px;
    }

    .stat-card{
        min-height:96px;
    }

    .stat-value{
        font-size:22px;
    }

    .sales-modal-overlay{
        padding:12px;
    }

    .sales-modal-body{
        height:280px;
        padding:14px;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
const dailyData   = <?= json_encode($dailySales); ?>;
const weeklyData  = <?= json_encode($weeklySales); ?>;
const monthlyData = <?= json_encode($monthlySales); ?>;
const yearlyData  = <?= json_encode($yearlySales); ?>;

Chart.defaults.font.family = 'Poppins';
Chart.defaults.color = '#64748b';

let dailyChartInstance = null;
let weeklyChartInstance = null;
let monthlyChartInstance = null;
let yearlyChartInstance = null;

function openSalesModal(id){
    document.getElementById(id).classList.add("show");

    setTimeout(() => {
        if(id === "dailyModal" && dailyChartInstance === null){
            dailyChartInstance = new Chart(document.getElementById("dailyChart"),{
                type:'line',
                data:{
                    labels:['6d','5d','4d','3d','2d','Yesterday','Today'],
                    datasets:[{
                        data:dailyData,
                        borderColor:'#2563eb',
                        backgroundColor:'rgba(59,130,246,.22)',
                        fill:true,
                        tension:.42,
                        pointRadius:3,
                        pointHoverRadius:5,
                        borderWidth:3
                    }]
                },
                options:{
                    responsive:true,
                    maintainAspectRatio:false,
                    plugins:{ legend:{display:false} },
                    scales:{
                        x:{ grid:{display:false} },
                        y:{ beginAtZero:true, grid:{color:'rgba(148,163,184,.22)'} }
                    }
                }
            });
        }

        if(id === "weeklyModal" && weeklyChartInstance === null){
            weeklyChartInstance = new Chart(document.getElementById("weeklyChart"),{
                type:'line',
                data:{
                    labels:['Week 1','Week 2','Week 3','Week 4'],
                    datasets:[{
                        data:weeklyData,
                        borderColor:'#2563eb',
                        backgroundColor:'rgba(59,130,246,.22)',
                        fill:true,
                        tension:.42,
                        pointRadius:3,
                        pointHoverRadius:5,
                        borderWidth:3
                    }]
                },
                options:{
                    responsive:true,
                    maintainAspectRatio:false,
                    plugins:{ legend:{display:false} },
                    scales:{
                        x:{ grid:{display:false} },
                        y:{ beginAtZero:true, grid:{color:'rgba(148,163,184,.22)'} }
                    }
                }
            });
        }

        if(id === "monthlyModal" && monthlyChartInstance === null){
            monthlyChartInstance = new Chart(document.getElementById("monthlyChart"),{
                type:'line',
                data:{
                    labels:['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
                    datasets:[{
                        data:monthlyData,
                        borderColor:'#2563eb',
                        backgroundColor:'rgba(59,130,246,.22)',
                        fill:true,
                        tension:.42,
                        pointRadius:3,
                        pointHoverRadius:5,
                        borderWidth:3
                    }]
                },
                options:{
                    responsive:true,
                    maintainAspectRatio:false,
                    plugins:{ legend:{display:false} },
                    scales:{
                        x:{ grid:{display:false} },
                        y:{ beginAtZero:true, grid:{color:'rgba(148,163,184,.22)'} }
                    }
                }
            });
        }

        if(id === "yearlyModal" && yearlyChartInstance === null){
            yearlyChartInstance = new Chart(document.getElementById("yearlyChart"),{
                type:'line',
                data:{
                    labels:['4 yrs','3 yrs','2 yrs','Last yr','This yr'],
                    datasets:[{
                        data:yearlyData,
                        borderColor:'#1d4ed8',
                        backgroundColor:'rgba(191,219,254,.55)',
                        fill:true,
                        tension:.42,
                        pointRadius:3,
                        pointHoverRadius:5,
                        borderWidth:3
                    }]
                },
                options:{
                    responsive:true,
                    maintainAspectRatio:false,
                    plugins:{ legend:{display:false} },
                    scales:{
                        x:{ grid:{display:false} },
                        y:{ beginAtZero:true, grid:{color:'rgba(148,163,184,.22)'} }
                    }
                }
            });
        }
    }, 80);
}

function closeSalesModal(id){
    document.getElementById(id).classList.remove("show");
}

window.addEventListener("click", function(e){
    document.querySelectorAll(".sales-modal-overlay").forEach(modal => {
        if(e.target === modal){
            modal.classList.remove("show");
        }
    });
});

document.addEventListener("keydown", function(e){
    if(e.key === "Escape"){
        document.querySelectorAll(".sales-modal-overlay").forEach(modal => {
            modal.classList.remove("show");
        });
    }
});
</script>