<?php
include "../config/db.php";

if(isset($_POST['save'])){
$name=$_POST['name'];
$price=$_POST['price'];

$conn->query("UPDATE pricing_settings SET price='$price' WHERE name='$name'");
header("Location: pricing_settings.php");
}
?>

<h2>Change Laundry Pricing</h2>

<table border="1">
<tr>
<th>Item</th>
<th>Price</th>
<th>Save</th>
</tr>

<?php
$res=$conn->query("SELECT * FROM pricing_settings");

while($row=$res->fetch_assoc()){
?>

<form method="POST">
<tr>
<td><?= $row['name'] ?></td>

<td>
<input type="number" step="0.01" name="price" value="<?= $row['price'] ?>">
</td>

<td>
<input type="hidden" name="name" value="<?= $row['name'] ?>">
<button name="save">Save</button>
</td>
</tr>
</form>

<?php } ?>

</table>