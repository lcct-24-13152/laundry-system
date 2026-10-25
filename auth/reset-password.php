<?php
session_start();
date_default_timezone_set('Asia/Manila');
include "../config/db.php";

$error = "";
$success = "";
$valid_token = false;
$token = "";
$user = null;

/* =========================
   CHECK TOKEN FROM URL
========================= */
if(!isset($_GET['token']) || trim($_GET['token']) == ""){
    $error = "Invalid reset link.";
}else{
    $token = trim($_GET['token']);

    /* CHECK TOKEN USING PREPARED STATEMENT */
    $stmt = $conn->prepare("
        SELECT id, username, email, reset_token, token_expiry
        FROM admins
        WHERE reset_token = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $check = $stmt->get_result();

    if($check && $check->num_rows > 0){
        $user = $check->fetch_assoc();

        if(!empty($user['token_expiry']) && strtotime($user['token_expiry']) >= time()){
            $valid_token = true;
        }else{
            $error = "Reset link has expired. Please request a new reset link.";
        }
    }else{
        $error = "Invalid or expired reset link.";
    }

    $stmt->close();
}

/* =========================
   UPDATE PASSWORD
========================= */
if(isset($_POST['update_pass']) && $valid_token){

    $newpass = trim($_POST['new_pass_secure'] ?? '');
    $confirm = trim($_POST['confirm_pass_secure'] ?? '');

    if($newpass == "" || $confirm == ""){
        $error = "Please complete all fields.";
    }elseif($newpass != $confirm){
        $error = "Passwords do not match!";
    }elseif(strlen($newpass) < 6){
        $error = "Password must be at least 6 characters.";
    }else{

        $hashed = password_hash($newpass, PASSWORD_DEFAULT);
        $id = intval($user['id']);

        $update = $conn->prepare("
            UPDATE admins 
            SET 
                password = ?,
                reset_token = NULL,
                token_expiry = NULL
            WHERE id = ?
            LIMIT 1
        ");
        $update->bind_param("si", $hashed, $id);

        if($update->execute()){
            if($update->affected_rows > 0){
                $success = "Password updated successfully. Redirecting to login...";
                $valid_token = false;

                echo "
                    <script>
                        setTimeout(function(){
                            window.location.replace('login.php');
                        }, 1800);
                    </script>
                ";
            }else{
                $error = "Password was not updated. Please request a new reset link.";
            }
        }else{
            $error = "Database error. Failed to update password.";
        }

        $update->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reset Password - CML Laundry</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
*{
    box-sizing:border-box;
}

body{
    margin:0;
    padding:0;
    font-family:Segoe UI, Arial, sans-serif;
    min-height:100vh;
    background:
        linear-gradient(rgba(0,0,0,.32), rgba(0,0,0,.32)),
        url('../assets/image/laundry-new-bg.jpg') no-repeat center center fixed;
    background-size:cover;
    display:flex;
    justify-content:center;
    align-items:center;
    padding:20px;
}

.card{
    width:100%;
    max-width:430px;
    background:rgba(255,255,255,0.94);
    padding:38px;
    border-radius:22px;
    box-shadow:0 22px 60px rgba(0,0,0,.30);
    backdrop-filter:blur(10px);
}

.logo{
    text-align:center;
    margin-bottom:25px;
}

.logo-icon{
    width:72px;
    height:72px;
    background:linear-gradient(135deg,#1877f2,#14b8a6);
    color:white;
    border-radius:22px;
    display:flex;
    justify-content:center;
    align-items:center;
    margin:0 auto 15px;
    font-size:30px;
    box-shadow:0 12px 26px rgba(24,119,242,.25);
}

.logo h2{
    margin:0 0 8px;
    color:#0f172a;
    font-size:27px;
    font-weight:800;
}

.logo p{
    margin:0;
    color:#64748b;
    font-size:14px;
    line-height:1.6;
}

.alert{
    padding:14px 15px;
    border-radius:14px;
    margin-bottom:18px;
    font-size:14px;
    font-weight:700;
    line-height:1.5;
    display:flex;
    gap:10px;
    align-items:flex-start;
}

.alert-error{
    background:#fee2e2;
    color:#991b1b;
    border:1px solid #fecaca;
}

.alert-success{
    background:#dcfce7;
    color:#166534;
    border:1px solid #bbf7d0;
}

.password-note{
    background:#f1f5f9;
    color:#475569;
    padding:12px 14px;
    border-radius:14px;
    font-size:13px;
    margin-bottom:16px;
    line-height:1.6;
    border:1px solid #e2e8f0;
}

.password-note strong{
    color:#1877f2;
}

.form-group{
    margin-bottom:18px;
    position:relative;
}

label{
    display:block;
    font-size:12px;
    color:#334155;
    font-weight:800;
    margin-bottom:8px;
    text-transform:uppercase;
    letter-spacing:.3px;
}

input{
    width:100%;
    height:52px;
    padding:0 48px 0 15px;
    border-radius:14px;
    border:1.5px solid #cbd5e1;
    font-size:15px;
    outline:none;
    background:#f8fafc;
    color:#0f172a;
    transition:.2s;
}

input:focus{
    background:white;
    border-color:#1877f2;
    box-shadow:0 0 0 4px rgba(24,119,242,.15);
}

.toggle-password{
    position:absolute;
    right:16px;
    top:42px;
    cursor:pointer;
    color:#64748b;
    font-size:17px;
    transition:.2s;
}

.toggle-password:hover{
    color:#1877f2;
}

button{
    width:100%;
    height:52px;
    border:none;
    border-radius:14px;
    background:#1877f2;
    color:white;
    font-size:15px;
    font-weight:800;
    cursor:pointer;
    transition:.2s;
    box-shadow:0 10px 24px rgba(24,119,242,.22);
}

button:hover{
    background:#1669d8;
    transform:translateY(-1px);
}

.back-login{
    display:block;
    text-align:center;
    margin-top:20px;
    color:#1877f2;
    text-decoration:none;
    font-size:14px;
    font-weight:800;
}

.back-login:hover{
    text-decoration:underline;
}

.footer-note{
    margin-top:18px;
    text-align:center;
    color:#94a3b8;
    font-size:12px;
    line-height:1.5;
}

@media(max-width:480px){
    .card{
        padding:30px 24px;
    }

    .logo h2{
        font-size:24px;
    }
}
</style>
</head>

<body>

<div class="card">

    <div class="logo">
        <div class="logo-icon">
            <i class="fa-solid fa-key"></i>
        </div>
        <h2>Create New Password</h2>
        <p>Enter your new admin password for CML Laundry Management System.</p>
    </div>

    <?php if(!empty($error)): ?>
        <div class="alert alert-error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if(!empty($success)): ?>
        <div class="alert alert-success">
            <i class="fa-solid fa-circle-check"></i>
            <span><?= htmlspecialchars($success) ?></span>
        </div>
    <?php endif; ?>

    <?php if($valid_token): ?>
        <form method="POST" autocomplete="off" spellcheck="false" target="_self" onsubmit="return validatePasswordForm()">

            <input type="text" style="display:none">
            <input type="password" style="display:none">

            <div class="password-note">
                <i class="fa-solid fa-circle-info"></i>
                <strong>Password requirement:</strong> use at least 6 characters.
            </div>

            <div class="form-group">
                <label>New Password</label>
                <input 
                    type="password" 
                    id="new_password" 
                    name="new_pass_secure"
                    placeholder="Enter new password"
                    required
                    autocomplete="new-password"
                >

                <span class="toggle-password" onclick="togglePassword('new_password', this)">
                    <i class="fa-solid fa-eye"></i>
                </span>
            </div>

            <div class="form-group">
                <label>Confirm Password</label>
                <input 
                    type="password" 
                    id="confirm_password" 
                    name="confirm_pass_secure"
                    placeholder="Confirm new password"
                    required
                    autocomplete="new-password"
                >

                <span class="toggle-password" onclick="togglePassword('confirm_password', this)">
                    <i class="fa-solid fa-eye"></i>
                </span>
            </div>

            <button type="submit" name="update_pass">
                <i class="fa-solid fa-rotate-right"></i>
                Update Password
            </button>

        </form>
    <?php endif; ?>

    <a href="login.php" class="back-login">
        <i class="fa-solid fa-arrow-left"></i>
        Back to Login
    </a>

    <p class="footer-note">
        CML Laundry Management System · Admin Security
    </p>

</div>

<script>
function togglePassword(fieldId, icon) {
    const password = document.getElementById(fieldId);
    const iconTag = icon.querySelector("i");

    if (password.type === "password") {
        password.type = "text";
        iconTag.classList.remove("fa-eye");
        iconTag.classList.add("fa-eye-slash");
    } else {
        password.type = "password";
        iconTag.classList.remove("fa-eye-slash");
        iconTag.classList.add("fa-eye");
    }
}

function validatePasswordForm(){
    const pass = document.getElementById("new_password").value.trim();
    const confirm = document.getElementById("confirm_password").value.trim();

    if(pass.length < 6){
        alert("Password must be at least 6 characters.");
        return false;
    }

    if(pass !== confirm){
        alert("Passwords do not match.");
        return false;
    }

    return true;
}

window.onload = function(){
    const newPass = document.getElementById("new_password");
    const confirmPass = document.getElementById("confirm_password");

    if(newPass){
        newPass.value = "";
    }

    if(confirmPass){
        confirmPass.value = "";
    }
};
</script>

</body>
</html>