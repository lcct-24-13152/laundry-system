<?php
include "../config/db.php";

$username = "CML LAUNDRY";
$password = password_hash("CML123", PASSWORD_DEFAULT);

$sql = "INSERT INTO admins(username,password) VALUES('$username','$password')";

if(mysqli_query($conn,$sql)){
    echo "Admin account created successfully!";
}else{
    echo "Error: " . mysqli_error($conn);
}
?>