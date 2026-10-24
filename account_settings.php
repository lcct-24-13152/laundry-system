    <?php
    session_start();
    include "../config/db.php";
    include "navbar.php";

    if(!isset($_SESSION['admin'])){
        header("Location: ../auth/login.php");
        exit();
    }

    $username_session = $_SESSION['admin'];

    /* GET ADMIN BY USERNAME */
    $query = $conn->query("SELECT * FROM admins WHERE username='$username_session'");
    $user = $query->fetch_assoc();

    if(!$user){
        die("Admin not found in database.");
    }

    $admin_id = $user['id'];
    $message = "";

    /* UPDATE PASSWORD */
    if(isset($_POST['update_password'])){
        $current = $_POST['current'];
        $new = $_POST['new'];
        $confirm = $_POST['confirm'];

        if($current == $user['password']){
            if($new == $confirm){
                $conn->query("UPDATE admins SET password='$new' WHERE id='$admin_id'");
                $message = "Password updated successfully.";
            } else {
                $message = "New passwords do not match.";
            }
        } else {
            $message = "Current password is incorrect.";
        }
    }

    /* UPDATE EMAIL */
if(isset($_POST['update_email'])){
    $new_email = trim($_POST['new_email']);

    if($new_email != ""){
        $conn->query("UPDATE admins SET email='$new_email' WHERE id='$admin_id'");
        $message = "Email updated successfully.";
        $user['email'] = $new_email;
    }
}
    ?>

    <link rel="stylesheet" href="../assets/css/style.css">

    <div class="container">

    <div class="page-title">
        <h1>Account Settings</h1>
        <p>Manage your login credentials securely.</p>
    </div>

    <?php if($message != ""): ?>
    <div class="alert-message">
        <?= $message ?>
    </div>
    <?php endif; ?>

    <div class="settings-wrapper">

    <!-- PASSWORD -->
    <div class="settings-card">
        <h3>Change Password</h3>
        <form method="POST" class="settings-form">
            <label>Current Password</label>
            <input type="password" name="current" required>

            <label>New Password</label>
            <input type="password" name="new" required>

            <label>Confirm New Password</label>
            <input type="password" name="confirm" required>

            <button name="update_password" class="btn">Update Password</button>
        </form>
    </div>

    <!-- EMAIL -->
    <div class="settings-card">
        <h3>Change Email</h3>

        <form method="POST" class="settings-form">
            <label>Current Email</label>
            <input value="<?= $user['email'] ?? 'liriocher25@gmail.com' ?>" disabled>

            <label>New Email</label>
            <input type="email" name="new_email" required>

            <button name="update_email" class="btn">Update Email</button>
        </form>
    </div>

</div>
    <style>
.settings-wrapper{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:30px;
    margin-top:30px;
}

    .settings-card{
        background:white;
        padding:30px;
        border-radius:18px;
        box-shadow:0 10px 25px rgba(0,0,0,.08);
    }

    .settings-card h3{
        margin-bottom:20px;
    }

    .settings-form{
        display:flex;
        flex-direction:column;
        gap:12px;
    }

    .settings-form label{
        font-size:13px;
        color:#64748b;
        font-weight:600;
    }

    .alert-message{
        background:#e0f2fe;
        color:#0369a1;
        padding:12px 18px;
        border-radius:12px;
        margin-top:15px;
        font-weight:600;
    }

    @media(max-width:900px){
        .settings-wrapper{
            grid-template-columns:1fr;
        }
    }
    </style>