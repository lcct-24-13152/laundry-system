<?php
session_start();
date_default_timezone_set('Asia/Manila');

include "../config/db.php";

require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';
require '../PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$current_step = "email";
$error = "";
$success = "";

/* 
    IMPORTANT:
    Gamitin lang ang localhost dito.
    HUWAG ngrok sa forgot password.
    Ngrok ay para lang sa QR code tracking.
*/

if(isset($_POST['send_link'])){

    $input = trim($_POST['email']);

    if($input != ""){

        $input = mysqli_real_escape_string($conn, $input);

        /* GET ADMIN BY EMAIL OR USERNAME */
        $check = $conn->query("
            SELECT * FROM admins 
            WHERE email='$input' OR username='$input'
            LIMIT 1
        ");

        if($check && $check->num_rows > 0){

            $user = $check->fetch_assoc();
            $email = $user['email'];

            if(empty($email)){
                $error = "This account has no email address saved.";
            }else{

                /* GENERATE TOKEN */
                $token = bin2hex(random_bytes(32));
                $expiry = date("Y-m-d H:i:s", strtotime("+30 minutes"));

                /* SAVE TOKEN */
                $update = $conn->query("
                    UPDATE admins 
                    SET reset_token='$token', token_expiry='$expiry'
                    WHERE id='".$user['id']."'
                ");

                if($update){

                    $baseURL = app_url() . "/auth/reset-password.php";
                    $resetLink = $baseURL . "?token=" . urlencode($token);

                    $mail = new PHPMailer(true);

                    try{

                        /* SMTP SETTINGS */
                        $mail->isSMTP();
                        $mail->Host       = 'smtp.gmail.com';
                        $mail->SMTPAuth   = true;

                        // Set MAIL_USER and MAIL_PASS in config/config.php
                        $mail->Username   = MAIL_USER;
                        $mail->Password   = MAIL_PASS;

                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port       = 587;

                        $mail->setFrom(MAIL_USER, MAIL_FROM_NAME);
                        $mail->addAddress($email);

                        $mail->isHTML(true);
                        $mail->Subject = 'Reset Your Password - CML Laundry';

                        $mail->Body = '
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <meta charset="UTF-8">
                            <title>Password Reset</title>
                        </head>
                        <body style="margin:0; padding:0; background:#f4f7fb; font-family:Arial, sans-serif;">

                            <table width="100%" cellpadding="0" cellspacing="0" style="padding:35px 0;">
                                <tr>
                                    <td align="center">

                                        <table width="520" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:18px; overflow:hidden; box-shadow:0 15px 40px rgba(0,0,0,0.12);">

                                            <tr>
                                                <td style="background:linear-gradient(135deg,#1877f2,#14b8a6); padding:32px; text-align:center;">
                                                    <h1 style="color:#ffffff; margin:0; font-size:27px;">
                                                        CML Laundry
                                                    </h1>
                                                    <p style="color:#e0f2fe; margin:8px 0 0; font-size:14px;">
                                                        Password Reset Request
                                                    </p>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td style="padding:35px 32px; color:#334155;">

                                                    <h2 style="margin:0 0 15px; color:#0f172a; font-size:22px;">
                                                        Hello, '.htmlspecialchars($user['username']).'!
                                                    </h2>

                                                    <p style="font-size:15px; line-height:1.7; margin:0 0 18px;">
                                                        We received a request to reset your admin password for your CML Laundry Management System account.
                                                    </p>

                                                    <p style="font-size:15px; line-height:1.7; margin:0 0 25px;">
                                                        Click the button below to create a new password.
                                                    </p>

                                                    <div style="text-align:center; margin:30px 0;">
                                                        <a href="'.$resetLink.'" 
                                                           style="background:#1877f2; color:#ffffff; text-decoration:none; padding:14px 30px; border-radius:10px; font-weight:bold; display:inline-block;">
                                                            Reset Password
                                                        </a>
                                                    </div>

                                                    <p style="font-size:14px; line-height:1.6; color:#475569;">
                                                        This link will expire in <strong>30 minutes</strong>.
                                                    </p>

                                                    <p style="font-size:13px; line-height:1.6; color:#64748b;">
                                                        If you did not request this password reset, you may ignore this email.
                                                    </p>

                                                    <hr style="border:none; border-top:1px solid #e5e7eb; margin:28px 0;">

                                                    <p style="font-size:12px; color:#94a3b8; margin:0;">
                                                        CML Laundry Management System
                                                    </p>

                                                </td>
                                            </tr>

                                        </table>

                                    </td>
                                </tr>
                            </table>

                        </body>
                        </html>
                        ';

                        $mail->AltBody = "Reset your password using this link: " . $resetLink;

                        $mail->send();

                        $success = "Reset link sent to your email! Please check your inbox.";

                    } catch(Exception $e){
                        $error = "Email failed: " . $mail->ErrorInfo;
                    }

                }else{
                    $error = "Failed to save reset token.";
                }
            }

        }else{
            $error = "Email or username not found!";
        }

    }else{
        $error = "Please enter your email or username.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password - CML Laundry</title>

    <style>
        *{
            box-sizing:border-box;
        }

        body{
            margin:0;
            padding:0;
            font-family:Segoe UI, Arial, sans-serif;
        }

        .login-wrapper{
            position:fixed;
            top:0;
            left:0;
            width:100%;
            height:100vh;
            background:url('../assets/image/laundry-new-bg.jpg') no-repeat center center;
            background-size:cover;
            display:flex;
            justify-content:center;
            align-items:center;
            padding:20px;
        }

        .login-wrapper::before{
            content:"";
            position:absolute;
            top:0;
            left:0;
            width:100%;
            height:100%;
            background:rgba(0,0,0,0.35);
            z-index:0;
        }

        .card{
            position:relative;
            z-index:1;
            width:430px;
            background:rgba(255,255,255,0.92);
            padding:38px;
            border-radius:22px;
            box-shadow:0 18px 50px rgba(0,0,0,.30);
            backdrop-filter:blur(10px);
            box-sizing:border-box;
        }

        .logo-box{
            text-align:center;
            margin-bottom:25px;
        }

        .logo-box h2{
            margin:0 0 8px;
            color:#111827;
            font-size:28px;
            font-weight:800;
        }

        .logo-box p{
            margin:0;
            color:#64748b;
            font-size:14px;
            line-height:1.5;
        }

        .input-group{
            margin-bottom:18px;
        }

        label{
            display:block;
            font-size:14px;
            color:#334155;
            font-weight:700;
            margin-bottom:8px;
        }

        input{
            width:100%;
            height:52px;
            padding:0 15px;
            border-radius:12px;
            border:1px solid #cbd5e1;
            box-sizing:border-box;
            font-size:15px;
            outline:none;
            background:#ffffff;
        }

        input:focus{
            border-color:#1877f2;
            box-shadow:0 0 0 3px rgba(24,119,242,0.15);
        }

        button{
            width:100%;
            height:52px;
            border:none;
            border-radius:12px;
            background:#1877f2;
            color:white;
            font-size:15px;
            font-weight:800;
            cursor:pointer;
            transition:.2s;
        }

        button:hover{
            background:#1669d8;
            transform:translateY(-1px);
        }

        button:disabled{
            background:#94a3b8;
            cursor:not-allowed;
            transform:none;
            opacity:0.8;
        }

        .message{
            text-align:center;
            margin-bottom:18px;
            font-size:14px;
            padding:12px 14px;
            border-radius:12px;
            line-height:1.5;
        }

        .error{
            color:#991b1b;
            background:#fee2e2;
            border:1px solid #fecaca;
        }

        .success{
            color:#166534;
            background:#dcfce7;
            border:1px solid #bbf7d0;
        }

        .back-login{
            text-align:center;
            margin-top:20px;
        }

        .back-login a{
            color:#1877f2;
            font-size:14px;
            font-weight:700;
            text-decoration:none;
        }

        .back-login a:hover{
            text-decoration:underline;
        }

        .note{
            margin-top:16px;
            font-size:12px;
            color:#64748b;
            text-align:center;
            line-height:1.5;
        }
    </style>
</head>

<body>

<div class="login-wrapper">

    <div class="card">

        <div class="logo-box">
            <h2>Forgot Password</h2>
            <p>Enter your email or username and we will send a password reset link.</p>
        </div>

        <?php if(!empty($error)): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if(!empty($success)): ?>
            <div class="message success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if($current_step == "email") { ?>
            <form method="POST" id="forgotForm" target="_self">

                <input type="hidden" name="send_link" value="1">

                <div class="input-group">
                    <label>Email or Username</label>
                    <input 
                        type="text" 
                        name="email" 
                        placeholder="Enter your email or username" 
                        required
                    >
                </div>

                <button type="submit" id="sendBtn">
                    Send Reset Link
                </button>

            </form>
        <?php } ?>

        <div class="back-login">
            <a href="login.php">Back to Login</a>
        </div>

        <div class="note">
            This reset link is sent using PHPMailer. Ngrok is only used for QR code tracking.
        </div>

    </div>

</div>

<script>
const forgotForm = document.getElementById("forgotForm");
const sendBtn = document.getElementById("sendBtn");

if(forgotForm && sendBtn){
    forgotForm.addEventListener("submit", function(){
        sendBtn.innerText = "Sending...";
        sendBtn.style.opacity = "0.8";
        sendBtn.style.cursor = "not-allowed";

        setTimeout(function(){
            sendBtn.disabled = true;
        }, 150);
    });
}
</script>

</body>
</html>