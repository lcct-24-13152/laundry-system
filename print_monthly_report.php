<?php
session_start();
include "../config/db.php";

require_once "../dompdf/autoload.inc.php";

use Dompdf\Dompdf;

$dompdf = new Dompdf();

/* GET MONTH */

$month = date("m");
$year  = date("Y");

if(isset($_GET['month'])){
    $parts = explode("-", $_GET['month']);
    $year  = $parts[0];
    $month = $parts[1];
}

/* QUERY */

$res = $conn->query("
SELECT DATE(date_received) as day,
COUNT(*) as transactions,
SUM(total) as sales
FROM transactions
WHERE MONTH(date_received)='$month'
AND YEAR(date_received)='$year'
GROUP BY DATE(date_received)
ORDER BY DATE(date_received)
");

$totalTransactions = 0;
$totalSales = 0;

/* START HTML */

$html = '
<h2 style="text-align:center;">CML Laundry Management System</h2>
<h3 style="text-align:center;">Monthly Sales Report</h3>

<table border="1" width="100%" cellpadding="6" cellspacing="0">

<tr style="background:#eee;">
<th>Date</th>
<th>Transactions</th>
<th>Total Sales</th>
</tr>
';

/* LOOP */

while($row = $res->fetch_assoc()){

$totalTransactions += $row['transactions'];
$totalSales += $row['sales'];

$html .= '
<tr>
<td>'.date("M d, Y", strtotime($row['day'])).'</td>
<td>'.$row['transactions'].'</td>
<td>₱'.number_format($row['sales'],2).'</td>
</tr>
';

}

$html .= '
<tr>
<td><b>Total</b></td>
<td><b>'.$totalTransactions.'</b></td>
<td><b>₱'.number_format($totalSales,2).'</b></td>
</tr>
</table>

<p>Generated: '.date("F d, Y").'</p>
';

/* DOMPDF */

$dompdf->loadHtml($html);
$dompdf->setPaper("A4","portrait");
$dompdf->render();

$dompdf->stream("Monthly_Report.pdf",["Attachment"=>false]);

echo "
<script>
window.onafterprint = function(){
    window.close();
}
</script>
";
?>