<link rel="stylesheet" href="../assets/css/style.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
/* keep nav links aligned */
.nav-links{
  display:flex;
  align-items:center;
  gap:12px;
}

.menu-wrapper{
  position:relative;
  display:inline-block;
}

/* hamburger button */
.hamburger-btn{
  border:none;
  color:white;
  font-size:18px;
  font-weight:700;
  padding:10px 18px;
  border-radius:14px;
  background:rgba(255,255,255,.15);
  cursor:pointer;
  transition:.2s;
}
.hamburger-btn:hover{
  background:rgba(255,255,255,.30);
}

/* dropdown */
.dropdown-menu{
  position:absolute;
  top:58px;
  right:0;
  width:220px;
  background:#ffffff !important;
  border-radius:14px;
  box-shadow:0 12px 30px rgba(0,0,0,.18);
  padding:10px;
  display:none;
  z-index:99999;
}

.dropdown-menu.show{ 
  display:block;
}

.dropdown-menu a,
.dropdown-menu a:visited{
  display:block;
  padding:12px 14px;
  border-radius:10px;
  text-decoration:none;
  font-weight:600;
  font-size:16px;
  line-height:1.4;
  color:#0f172a !important;
  opacity:1 !important;
  visibility:visible !important;
}

.dropdown-menu a:hover{
  background:#f1f5f9;
}

.menu-divider{
  height:1px;
  background:#e2e8f0;
  margin:8px 0;
}

.menu-logout,
.menu-logout:visited{
  color:#ef4444 !important;
}
</style>

<div class="navbar">

  <div class="nav-left">
    <img src="../assets/image/logo.png" class="logo">

    <div class="brand-text">
      <div class="brand-title">CML Laundry</div>
      <div class="brand-sub">Management System</div>
    </div>
  </div>

  <div class="nav-links">
    <a href="dashboard.php">Dashboard</a>
    <a href="add_transaction.php">Add Trasactions</a>
    <a href="transactions.php">History</a>
    <a href="inventory.php">Inventory</a>

    <!-- ✅ HAMBURGER MENU -->
    <div class="menu-wrapper">
      <button type="button" class="hamburger-btn" onclick="toggleMenu()">☰</button>

      <div class="dropdown-menu" id="dropdownMenu">
  <a href="account_settings.php">Account Settings</a>
  <div class="menu-divider"></div>
  <a class="menu-logout" href="../auth/logout.php">Logout</a>
</div>
    </div>
  </div>
</div>

<script>
function toggleMenu(){
  document.getElementById("dropdownMenu").classList.toggle("show");
}

document.addEventListener("click", function(e){
  const menu = document.getElementById("dropdownMenu");
  const btn  = document.querySelector(".hamburger-btn");
  if(!menu || !btn) return;

  if(!menu.contains(e.target) && !btn.contains(e.target)){
    menu.classList.remove("show");
  }
});
</script>
