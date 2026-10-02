<?php
session_start();

if(!isset($_SESSION['user_id'])){
    header("Location: ../login.php");
    exit();
}

include "../includes/header.php";
include "../includes/sidebar.php";
?>

<div class="content">

<h2>Welcome, <?php echo $_SESSION['fullname']; ?></h2>

<div class="row mt-4">

<div class="col-md-3">
<div class="card">
<div class="card-body">
<h5>Today's Consumption</h5>
<h2>4.8 kWh</h2>
</div>
</div>
</div>

<div class="col-md-3">
<div class="card">
<div class="card-body">
<h5>Solar Generated</h5>
<h2>6.1 kWh</h2>
</div>
</div>
</div>

<div class="col-md-3">
<div class="card">
<div class="card-body">
<h5>Battery</h5>
<h2>84%</h2>
</div>
</div>
</div>

<div class="col-md-3">
<div class="card">
<div class="card-body">
<h5>Grid Usage</h5>
<h2>1.2 kWh</h2>
</div>
</div>
</div>

</div>

</div>

<?php
include "../includes/footer.php";
?>