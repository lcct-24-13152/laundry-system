<?php
session_start();
include "../config/db.php";
include __DIR__ . "/navbar.php";

if(!isset($_SESSION['admin'])){
    header("Location: ../auth/login.php");
    exit();
}
?>

<div class="container">

<div class="page-title">
    <h1>Settings</h1>
    <p>Select a configuration category.</p>
</div>

<div class="settings-grid">

    <a href="account_settings.php" class="settings-card">
        <div class="settings-icon">🔐</div>
        <h3>Account Settings</h3>
        <p>Change username, password, and email.</p>
    </a>

</div>

</div>

<style>
.settings-grid{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:30px;
    margin-top:30px;
}

.settings-card{
    background:white;
    padding:35px;
    border-radius:20px;
    text-align:center;
    text-decoration:none;
    color:#111;
    box-shadow:0 10px 25px rgba(0,0,0,.08);
    transition:.2s;
}

.settings-card:hover{
    transform:translateY(-5px);
}

.settings-icon{
    font-size:40px;
    margin-bottom:15px;
}

@media(max-width:768px){
    .settings-grid{
        grid-template-columns:1fr;
    }
}
</style>