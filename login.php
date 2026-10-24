<?php
session_start();
include "../config/db.php";

/* 1. INITIALIZE SESSIONS */
if(!isset($_SESSION['login_attempts'])){ $_SESSION['login_attempts'] = 0; }
if(!isset($_SESSION['lock_time'])){ $_SESSION['lock_time'] = 0; }

/* 2. AUTOMATIC RESET (SERVER SIDE) */
if($_SESSION['login_attempts'] >= 3){
    $elapsed = time() - $_SESSION['lock_time'];
    if($elapsed >= 60){
        $_SESSION['login_attempts'] = 0;
        $_SESSION['lock_time'] = 0;
        unset($_SESSION['login_error']); // Clear error pag tapos na lock
    }
}

$remaining_time = 0;
if($_SESSION['login_attempts'] >= 3){
    $remaining_time = 60 - (time() - $_SESSION['lock_time']);
}

/* 3. LOGIN LOGIC */
if(isset($_POST['login'])){
    $user = mysqli_real_escape_string($conn, trim($_POST['username']));
    $pass = mysqli_real_escape_string($conn, trim($_POST['password']));

    if($_SESSION['login_attempts'] >= 3){
        $_SESSION['login_error'] = "System Locked.";
    } 
    else {
        $sql = "SELECT * FROM admins WHERE username = '$user' LIMIT 1";
        $check = mysqli_query($conn, $sql);
        $row = mysqli_fetch_assoc($check);

        if($row && password_verify($pass, $row['password'])){
    // SUCCESS
    $_SESSION['admin'] = $row['username'];
    $_SESSION['login_attempts'] = 0;
    $_SESSION['lock_time'] = 0;
    unset($_SESSION['login_error']);
    header("Location: ../pages/dashboard.php");
    exit();
} else {
    // FAILED LOGIN
    $_SESSION['login_attempts']++; 
    
    if($_SESSION['login_attempts'] >= 3){
        $_SESSION['lock_time'] = time();
        $_SESSION['login_error'] = "System Locked.";
    } else {
        $left = 3 - $_SESSION['login_attempts'];
        $_SESSION['login_error'] = "Invalid Login! <br> <small>$left attempt(s) remaining.</small>";
    }
}
}
    // REDIRECT pagkatapos ng POST para hindi mag-resubmit sa refresh
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Kunin ang error mula sa session tapos burahin para hindi magpakita sa fresh load
$error = isset($_SESSION['login_error']) ? $_SESSION['login_error'] : "";
if ($_SESSION['login_attempts'] < 3) {
    unset($_SESSION['login_error']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../assets/css/style.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { margin: 0; padding: 0; }
        .login-wrapper {
            position: fixed; top: 0; left: 0; width: 100%; height: 100vh;
            background: url('../assets/image/laundry-new-bg.jpg') no-repeat center center/cover;
            display: flex; justify-content: center; align-items: center;
        }
        .login-wrapper::before {
            content: ""; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.2); z-index: 0;
        }
        .login-card {
            position: relative; z-index: 1;
            background: rgba(255, 249, 249, 0.4);
            backdrop-filter: blur(0px); 
            padding: 35px; border-radius: 15px; width: 350px;
            box-shadow: 0 8px 25px rgba(125, 125, 125, 0.41);
            text-align: center;
        }
        .login-logo { width:200px; display:block; margin:0 auto -10px; }
        .login-card label { display:block; margin-top: 15px; margin-bottom:6px; color:#000; font-weight:600; text-align: left; }
        .login-card input { width:100%; margin-bottom:18px; background: rgba(255,255,255,0.8); border:1px solid #ccc; padding:10px; border-radius:8px; color:#000; box-sizing: border-box; }
        .forgot-password { text-align:right; margin-bottom:25px; }
        .forgot-password a { font-size:13px; text-decoration:none; color:#2f5bd3; font-weight:600; }
        .login-btn { width:100%; padding:10px; border:none; background:#2f5bd3; color:white; border-radius:8px; font-weight:bold; cursor:pointer; }
        .login-btn:disabled { background: #888; cursor: not-allowed; }
        .password-wrapper { position: relative; }
        .toggle-password { position: absolute; right: 10px; top: 35%; transform: translateY(-50%); cursor: pointer; font-size: 18px; }
        .error-text { color: red; font-weight: bold; margin-bottom: 15px; display: block; line-height: 1.4; }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="login-card">
        <img src="../assets/image/logo-final.png" class="login-logo">

        <?php if(!empty($error)): ?>
            <span class="error-text" id="err-box">
                <?= $error ?>
                <?php if($remaining_time > 0): ?>
                    <br><span id="timer-display">Try again in <?= $remaining_time ?>s</span>
                <?php endif; ?>
            </span>
        <?php endif; ?>

        <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>">
            <label>Username</label>
            <input type="text" name="username" autocomplete="off" required <?php echo ($remaining_time > 0) ? 'disabled' : ''; ?>>

            <label>Password</label>
            <div class="password-wrapper">
                <input type="password" id="password" name="password" required <?php echo ($remaining_time > 0) ? 'disabled' : ''; ?>>
                <span class="toggle-password" onclick="togglePassword(this)">
                    <i class="fa-solid fa-eye-slash"></i>
                </span>
            </div>

            <div class="forgot-password">
                <a href="forgot-password.php">Forgot Password?</a>
            </div>

            <button type="submit" class="login-btn" name="login" <?php echo ($remaining_time > 0) ? 'disabled' : ''; ?>>Login</button>
        </form>
    </div>
</div>

<script>
    let timeLeft = <?= ($remaining_time > 0) ? $remaining_time : 0 ?>;
    if(timeLeft > 0) {
        let countdown = setInterval(() => {
            timeLeft--;
            if(timeLeft <= 0) {
                clearInterval(countdown);
                location.reload(); 
            } else {
                let display = document.getElementById("timer-display");
                if(display) display.innerText = "Try again in " + timeLeft + "s";
            }
        }, 1000);
    }

    function togglePassword(icon) {
        const passwordInput = document.getElementById("password");
        const iconTag = icon.querySelector("i");
        if (passwordInput.type === "password") {
            passwordInput.type = "text";
            iconTag.classList.replace("fa-eye-slash", "fa-eye");
        } else {
            passwordInput.type = "password";
            iconTag.classList.replace("fa-eye", "fa-eye-slash");
        }
    }
</script>

</body>
</html>