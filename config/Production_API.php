<?php
// -----------------------------------------------------
//                          Insert
// -----------------------------------------------------
include('config/connection.php');

$message = "";
$product_name_err = '';
$target_pcs_err = '';
$due_date_err = '';
$Stock_number_err = '';
$quantity_err = '';

if (isset($_POST['submit'])) { 
    $production_id = $_POST['production_id'];
    $product_name    = $_POST['product_name'];
    $target_pcs = $_POST['target_pcs'];
    $due_date = $_POST['due_date'];
    $Stock_number = $_POST['Stock_number'];
    $quantity = $_POST['quantity'];

     $isValid = true;

    if (!preg_match("/^[a-zA-Z0-9 ]*$/", $product_name)) {
        $product_name_err = "Please use only letters and spaces for your Product Name.";
        $isValid = false;
    }

    if (!preg_match("/^[0-9 ]*$/", $target_pcs)) {
        $target_pcs_err = "Please use only numbers for your Target PCS.";    
        $isValid = false;
    }

    if (!preg_match("/^[0-9 ]*$/", $quantity)) {
        $quantity_err = "Please use only numbers for your Quantity.";    
        $isValid = false;
    }

    if (!preg_match("/^[0-9 ]*$/", $Stock_number)) {
        $Stock_number_err = "Please use only numbers for your Stock Number.";
        $isValid = false;
    }

    if (mb_strlen($Stock_number) < 8) {
        $Stock_number_err = "Your Stock Number must be at least 8 characters long.";
        $isValid = false;
    }

     if ($isValid) {
        $safe_product_name     = mysqli_real_escape_string($conn, $product_name);
        $safe_target_pcs     = mysqli_real_escape_string($conn, $target_pcs);
        $safe_due_date     = mysqli_real_escape_string($conn, $due_date);
        $safe_Stock_number     = mysqli_real_escape_string($conn, $Stock_number);
        $safe_quantity     = mysqli_real_escape_string($conn, $quantity);
       
        $sql   = "INSERT INTO production (production_id, product_name, target_pcs, due_date, Stock_number, quantity) VALUES ('', '$safe_product_name', '$safe_target_pcs', '$safe_due_date', '$safe_Stock_number', '$safe_quantity')";
        $query = mysqli_query($conn, $sql);

        if ($query) {
            $message = "New Task sent successfully.";
        } elseif (isset($_POST['records'])) {
        header("Location: production.php");
        exit();
        }
     }
}

// -----------------------------------------------------
//                       Edit
// -----------------------------------------------------

$passid = $_POST['idno'] ?? null;
$view_data = null;
$delete_message = "";

if (isset($_POST['del'])) {
    // Backend Logic for Delete
    $sql    = "DELETE FROM production WHERE production_id = '$passid'";
    $result = mysqli_query($conn, $sql);
    $delete_message = "Record Deleted Successfully. <br><a href='production.php'>View Records</a>";

} elseif (isset($_POST['upd'])) {
    //Dto nag fe-fetch para sa single Record to Update
    $sql    = "SELECT * FROM production WHERE production_id = '$passid'";
    $result = mysqli_query($conn, $sql);
    $row    = mysqli_fetch_assoc($result);

    $view_data = [
        'production_id'      => $passid,
        'product_name' => $row['product_name'],
        'target_pcs'    => $row['target_pcs'],
        'due_date'    => $row['due_date'],
        'Stock_number' => $row['Stock_number'],
        'quantity' => $row['quantity'] 
    ];
}

// -----------------------------------------------------
//                      Update
// -----------------------------------------------------

$status_message = "";

if (isset($_POST['submit'])) {
    $production_id = $_POST['production_id'];
    $product_name    = $_POST['product_name'];
    $target_pcs = $_POST['target_pcs'];
    $due_date = $_POST['due_date'];
    $Stock_number = $_POST['Stock_number'];
    $quantity = $_POST['quantity'];

    $sql   = "UPDATE production SET  product_name = '$product_name', target_pcs = '$target_pcs', due_date = '$due_date', Stock_number = '$Stock_number', quantity = '$quantity' WHERE production_id = '$production_id' ";
    $query = mysqli_query($conn, $sql);

    if ($query) {
        $status_message = "<br>Update Successful<br><br><a href='production.php'><input type='button' name='back' value='View Records'></a>";
    }
} elseif (isset($_POST['can'])) {
    header("Location: production.php");
    exit();
}
// -----------------------------------------------------
//                      View
// -----------------------------------------------------
$sql    = "SELECT * FROM production ORDER BY production_id ASC";
$result = mysqli_query($conn, $sql);
$count  = mysqli_num_rows($result);

$records = [];
if ($count > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $records[] = $row;
    }
}
?>