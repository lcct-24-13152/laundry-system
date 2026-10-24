<?php
session_start();
include "../config/db.php";

/* DELETE SELECTED */
if (isset($_POST['delete_selected'])) {
    if (!empty($_POST['delete_ids'])) {
        foreach ($_POST['delete_ids'] as $id) {
            $id = (int)$id;
            $conn->query("DELETE FROM laundry_types WHERE id=$id");
        }

        header("Location: laundry_types.php");
        exit();
    }
}

/* ADD TYPE */
if (isset($_POST['add_type'])) {
    $type  = mysqli_real_escape_string($conn, $_POST['type']);
    $min   = (float)$_POST['min_kg'];
    $max   = (float)$_POST['max_kg'];
    $full_price = (float)$_POST['price_per_load'];
    $min_price  = (float)$_POST['min_load_price'];

    $conn->query("
       INSERT INTO laundry_types
       (type_name, min_kg, max_kg, price_per_load, min_load_price)
       VALUES
       ('$type', '$min', '$max', '$full_price', '$min_price')
    ");

    header("Location: laundry_types.php");
    exit();
}

/* SAVE ALL */
if (isset($_POST['save_all'])) {
    foreach ($_POST['min_kg'] as $id => $min) {
        $id         = (int)$id;
        $min        = (float)$min;
        $max        = (float)$_POST['max_kg'][$id];
        $price      = (float)$_POST['price_per_load'][$id];
        $min_price  = (float)$_POST['min_load_price'][$id];

        $conn->query("
            UPDATE laundry_types 
            SET min_kg=$min, max_kg=$max, price_per_load=$price, min_load_price=$min_price
            WHERE id=$id
        ");
    }
    header("Location: laundry_types.php");
    exit();
}

include "navbar.php";
$res = $conn->query("SELECT * FROM laundry_types ORDER BY type_name ASC");
?>

<div class="page-container">

    <div class="page-header">
        <div class="page-title">
            Laundry Types
            <p>Manage weight ranges and pricing per load.</p>
        </div>

        <div class="header-actions">
            <button type="button" class="btn-add-modal" onclick="openTypeModal()">+ Add Laundry Type</button>
            <a href="inventory.php" class="btn-back">← Back</a>
        </div>
    </div>

    <div class="card full-card">
        <div class="card-header-row">
            <h3>Laundry Type List</h3>
            <span class="card-subtitle">Edit prices and weight range directly in the table.</span>
        </div>

        <form method="POST" onsubmit="return confirmDelete(event)">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 50px;"><input type="checkbox" id="checkAll"></th>
                            <th>Laundry Type</th>
                            <th style="text-align: center;">Min KG</th>
                            <th style="text-align: center;">Max KG</th>
                            <th style="text-align: center;">Min Load Price</th>
                            <th style="text-align: center;">Price per Load</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $res->fetch_assoc()): ?>
                            <tr>
                                <td style="text-align: center;">
                                    <input type="checkbox" name="delete_ids[]" value="<?= $row['id'] ?>" class="row-check">
                                </td>
                                <td class="type-name"><?= htmlspecialchars($row['type_name']) ?></td>
                                <td style="text-align: center;">
                                    <input type="number" step="0.1" name="min_kg[<?= $row['id'] ?>]" value="<?= $row['min_kg'] ?>" class="price-input">
                                </td>
                                <td style="text-align: center;">
                                    <input type="number" step="0.1" name="max_kg[<?= $row['id'] ?>]" value="<?= $row['max_kg'] ?>" class="price-input">
                                </td>
                                <td style="text-align: center;">
                                    <input type="number" step="0.01" name="min_load_price[<?= $row['id'] ?>]" value="<?= $row['min_load_price'] ?>" class="price-input">
                                </td>
                                <td style="text-align: center;">
                                    <input type="number" step="0.01" name="price_per_load[<?= $row['id'] ?>]" value="<?= $row['price_per_load'] ?>" class="price-input">
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <div class="button-container-centered">
                <button type="submit" name="delete_selected" class="btn-delete">Delete Selected</button>
                <button type="submit" name="save_all" class="btn-update">Save All Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ADD LAUNDRY TYPE MODAL -->
<div id="typeModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <div>
                <h3>Add Laundry Type</h3>
                <p>Enter the laundry type name, kilogram range, and pricing.</p>
            </div>
            <button type="button" class="modal-close" onclick="closeTypeModal()">×</button>
        </div>

        <form method="POST" class="modal-form">
            <div class="modal-field modal-wide">
                <label>Laundry Type Name</label>
                <input type="text" name="type" placeholder="e.g. Comforter" required>
            </div>

            <div class="modal-field">
                <label>Min KG</label>
                <input type="number" step="0.1" name="min_kg" placeholder="e.g. 1" required>
            </div>

            <div class="modal-field">
                <label>Max KG</label>
                <input type="number" step="0.1" name="max_kg" placeholder="e.g. 7" required>
            </div>

            <div class="modal-field">
                <label>Min Load Price</label>
                <input type="number" step="0.01" name="min_load_price" placeholder="0.00" required>
            </div>

            <div class="modal-field">
                <label>Price per Load</label>
                <input type="number" step="0.01" name="price_per_load" placeholder="0.00" required>
            </div>

            <div class="modal-actions">
                <button type="button" class="modal-cancel" onclick="closeTypeModal()">Cancel</button>
                <button type="submit" name="add_type" class="modal-save">Save Laundry Type</button>
            </div>
        </form>
    </div>
</div>

<style>
:root{
    --primary:#2563eb;
    --primary-dark:#1d4ed8;
    --success:#16a34a;
    --danger:#dc2626;
    --text:#0f172a;
    --muted:#64748b;
    --border:#e5e7eb;
    --bg:#f4f7fb;
    --card:#ffffff;
    --shadow:0 8px 22px rgba(15,23,42,.06);
}

*{
    box-sizing:border-box;
}

body{
    background:var(--bg);
    color:var(--text);
}

/* FULL WIDTH LAYOUT */
.page-container {
    max-width: 1350px;
    margin: auto;
    padding: 16px;
}

/* PAGE HEADER WITH BACK BUTTON */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 18px;
    margin-bottom: 18px;
    background: linear-gradient(135deg, #ffffff, #eff6ff);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 18px 22px;
    box-shadow: var(--shadow);
}

.page-title {
    font-size: 24px;
    font-weight: 800;
    color: var(--text);
}

.page-title p {
    font-size: 12px;
    font-weight: 600;
    color: var(--muted);
    margin-top: 5px;
}

.header-actions{
    display:flex;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
}

/* BACK BUTTON */
.btn-back,
.btn-add-modal {
    color: white;
    text-decoration: none;
    padding: 9px 15px;
    border-radius: 10px;
    font-weight: 800;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 120px;
    height: 38px;
    border:none;
    cursor:pointer;
    transition: 0.2s ease;
}

.btn-back {
    background: var(--primary);
    box-shadow: 0 6px 14px rgba(37, 99, 235, 0.16);
}

.btn-add-modal{
    background:#0f172a;
    box-shadow:0 6px 14px rgba(15,23,42,.14);
}

.btn-back:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
}

.btn-add-modal:hover{
    background:#020617;
    transform:translateY(-1px);
}

.card.full-card {
    width: 100%;
    background: white;
    padding: 16px;
    border-radius: 16px;
    box-shadow: var(--shadow);
    border:1px solid var(--border);
    margin-bottom: 16px;
}

.card-header-row{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    margin-bottom:14px;
}

.card h3 {
    margin:0;
    font-size: 17px;
    font-weight:800;
    color:var(--text);
}

.card-subtitle{
    color:var(--muted);
    font-size:12px;
    font-weight:600;
}

/* TABLE STYLES */
.table-responsive {
    width: 100%;
    overflow-x: auto;
}

.table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 8px;
}

.table th {
    background: #f8fafc;
    padding: 10px;
    text-align: left;
    font-size: 11px;
    font-weight:900;
    color: #334155;
    border-bottom:1px solid var(--border);
    white-space:nowrap;
}

.table td {
    padding: 8px 10px;
    border-top: 1px solid #eef2f7;
    font-size:12px;
    vertical-align:middle;
}

.table tr:hover td{
    background:#f9fbff;
}

.price-input {
    width: 100%;
    max-width: 95px;
    padding: 7px 8px;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    text-align: center;
    font-size:12px;
    outline:none;
}

.price-input:focus{
    border-color:var(--primary);
    box-shadow:0 0 0 3px rgba(37,99,235,.09);
}

/* BUTTONS */
.btn-update {
    background: #22c55e;
    color: white;
    border: none;
    padding: 9px 15px;
    border-radius: 9px;
    cursor: pointer;
    font-size:12px;
    font-weight: 800;
    box-shadow:0 6px 14px rgba(34,197,94,.14);
    transition:.2s ease;
}

.btn-update:hover {
    background: #16a34a;
    transform:translateY(-1px);
}

.btn-delete {
    background: #ef4444;
    color: white;
    border: none;
    padding: 9px 15px;
    border-radius: 9px;
    font-size:12px;
    font-weight: 800;
    cursor: pointer;
    transition:.2s ease;
}

.btn-delete:hover {
    background: #dc2626;
    transform:translateY(-1px);
}

/* CENTERED BUTTONS CONTAINER */
.button-container-centered {
    display: flex;
    justify-content: center;
    gap: 12px;
    padding: 22px 0 6px 0;
    flex-wrap:wrap;
}

.type-name {
    font-weight: 800;
    color: #1e293b;
}

input[type="checkbox"] {
    width: 17px;
    height: 17px;
    cursor: pointer;
}

/* MODAL */
.modal-overlay{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(15,23,42,.55);
    z-index:99999;
    align-items:center;
    justify-content:center;
    padding:20px;
}

.modal-overlay.show{
    display:flex;
}

.modal-box{
    width:620px;
    max-width:100%;
    background:#fff;
    border-radius:18px;
    box-shadow:0 24px 70px rgba(0,0,0,.25);
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

.modal-header{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:15px;
    padding:18px 20px;
    background:linear-gradient(135deg,#eff6ff,#ffffff);
    border-bottom:1px solid var(--border);
}

.modal-header h3{
    margin:0;
    font-size:19px;
    font-weight:900;
    color:var(--text);
}

.modal-header p{
    margin:4px 0 0;
    font-size:12px;
    color:var(--muted);
    line-height:1.4;
}

.modal-close{
    width:32px;
    height:32px;
    border:none;
    border-radius:50%;
    background:#e5e7eb;
    color:#334155;
    font-size:22px;
    font-weight:800;
    cursor:pointer;
}

.modal-close:hover{
    background:#d1d5db;
}

.modal-form{
    padding:18px 20px 20px;
    display:grid;
    grid-template-columns:repeat(2, minmax(0,1fr));
    gap:12px;
}

.modal-wide{
    grid-column:span 2;
}

.modal-field{
    display:flex;
    flex-direction:column;
}

.modal-field label{
    font-size:11px;
    font-weight:800;
    color:#334155;
    margin-bottom:5px;
}

.modal-field input{
    width:100%;
    height:38px;
    padding:8px 10px;
    font-size:12px;
    border-radius:9px;
    border:1px solid #cbd5e1;
    outline:none;
}

.modal-field input:focus{
    border-color:var(--primary);
    box-shadow:0 0 0 3px rgba(37,99,235,.10);
}

.modal-actions{
    grid-column:1 / -1;
    display:flex;
    justify-content:flex-end;
    gap:10px;
    margin-top:4px;
}

.modal-cancel{
    background:#e5e7eb;
    color:#111827;
    border:none;
    padding:9px 15px;
    border-radius:9px;
    font-size:12px;
    font-weight:800;
    cursor:pointer;
}

.modal-save{
    background:var(--primary);
    color:white;
    border:none;
    padding:9px 15px;
    border-radius:9px;
    font-size:12px;
    font-weight:800;
    cursor:pointer;
}

.modal-save:hover{
    background:var(--primary-dark);
}

/* RESPONSIVE */
@media (max-width: 900px) {
    .page-header {
        flex-direction: column;
    }

    .header-actions{
        width:100%;
    }

    .btn-back,
    .btn-add-modal{
        flex:1;
    }

    .card-header-row{
        flex-direction:column;
        align-items:flex-start;
    }
}

@media (max-width: 700px) {
    .page-container{
        padding:14px;
    }

    .page-title{
        font-size:22px;
    }

    .modal-form{
        grid-template-columns:1fr;
    }

    .modal-wide{
        grid-column:span 1;
    }

    .modal-actions{
        flex-direction:column;
    }

    .modal-cancel,
    .modal-save{
        width:100%;
    }

    .button-container-centered{
        flex-direction:column;
    }

    .btn-delete,
    .btn-update{
        width:100%;
    }
}
</style>

<script>
const checkAll = document.getElementById("checkAll");

if(checkAll){
    checkAll.addEventListener("change", function () {
        let checks = document.querySelectorAll(".row-check");
        checks.forEach(cb => cb.checked = this.checked);
    });
}

function confirmDelete(event) {
    if (event.submitter && event.submitter.name === 'delete_selected') {
        let checked = document.querySelectorAll('.row-check:checked');
        if (checked.length === 0) {
            alert("Please select at least one item to delete.");
            return false;
        }
        return confirm("Are you sure you want to delete selected items?");
    }
    return true;
}

function openTypeModal(){
    document.getElementById("typeModal").classList.add("show");
}

function closeTypeModal(){
    document.getElementById("typeModal").classList.remove("show");
}

window.addEventListener("click", function(e){
    const modal = document.getElementById("typeModal");

    if(e.target === modal){
        closeTypeModal();
    }
});

document.addEventListener("keydown", function(e){
    if(e.key === "Escape"){
        closeTypeModal();
    }
});
</script>