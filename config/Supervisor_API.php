<?php
// CRUD PART
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include('config/connection.php');

$message = "";
$status_message = "";
$delete_message = "";
$route_err = '';
$pieces_err = '';
$stock_err = '';
$view_data = null;

// ==========================================
// 1. INSERT TASK (Add Form)
// ==========================================
if (isset($_POST['submit'])) {
    $route  = $_POST['route'] ?? '';
    $pieces = $_POST['pieces'] ?? ''; // Input name sa HTML mo ay 'peaces'
    $stock  = $_POST['stock'] ?? '';

    $isValid = true;

    if (!preg_match("/^[a-zA-Z0-9 ]*$/", $route)) {
        $route_err = "Please use only letters and spaces for your Address.";
        $isValid = false;
    }

    if (!preg_match("/^[0-9]*$/", $pieces)) {
        $pieces_err = "Please use only numbers for Pieces.";
        $isValid = false;
    }

    if (!preg_match("/^[0-9]*$/", $stock)) {
        $stock_err = "Please use only numbers for Stock.";
        $isValid = false;
    }

    if ($isValid) {
        $safe_route  = mysqli_real_escape_string($conn, $route);
        $safe_pieces = mysqli_real_escape_string($conn, $pieces);
        $safe_stock  = mysqli_real_escape_string($conn, $stock);

        // Tugma sa DB columns: route, pieces, stock
        $sql   = "INSERT INTO delivery (route, pieces, stock) VALUES ('$safe_route', '$safe_pieces', '$safe_stock')";
        $query = mysqli_query($conn, $sql);

        if ($query) {
            $message = "New Task added successfully!";
        } else {
            $message = "Error adding record: " . mysqli_error($conn);
        }
    }
}

// ==========================================
// 2. DELETE & EDIT FETCH
// ==========================================
$passid = $_POST['idno'] ?? null;

if (isset($_POST['del']) && !empty($passid)) {
    // Tama ang delivery_id base sa screenshot mo!
    $safe_id = mysqli_real_escape_string($conn, $passid);
    $sql     = "DELETE FROM delivery WHERE delivery_id = '$safe_id'";
    
    if (mysqli_query($conn, $sql)) {
        header("Location: supervisor.php");
        exit();
    } else {
        $delete_message = "Error deleting record: " . mysqli_error($conn);
    }

} elseif (isset($_POST['upd']) && !empty($passid)) {
    $safe_id = mysqli_real_escape_string($conn, $passid);
    $sql     = "SELECT * FROM delivery WHERE delivery_id = '$safe_id'";
    $result  = mysqli_query($conn, $sql);
    $row     = mysqli_fetch_assoc($result);

    if ($row) {
        $view_data = [
            'delivery_id' => $row['delivery_id'],
            'route'       => $row['route'],
            'pieces'      => $row['pieces'],
            'stock'       => $row['stock']
        ];
    }
}

// ==========================================
// 3. UPDATE TASK
// ==========================================
if (isset($_POST['update_submit'])) {
    $id     = $_POST['idno'] ?? '';
    $route  = $_POST['route'] ?? '';
    $pieces = $_POST['pieces'] ?? '';
    $stock  = $_POST['stock'] ?? '';

    $isValid = true;

    if (!preg_match("/^[a-zA-Z0-9 ]*$/", $route)) {
        $route_err = "Please use only letters and spaces for your Address.";
        $isValid = false;
    }

    if (!preg_match("/^[0-9]*$/", $pieces)) {
        $pieces_err = "Please use only numbers for Pieces.";
        $isValid = false;
    }

    if (!preg_match("/^[0-9]*$/", $stock)) {
        $stock_err = "Please use only numbers for Stock.";
        $isValid = false;
    }

    if ($isValid) {
        $safe_id     = mysqli_real_escape_string($conn, $id);
        $safes_route  = mysqli_real_escape_string($conn, $route);
        $safes_pieces = mysqli_real_escape_string($conn, $pieces);
        $safes_stock  = mysqli_real_escape_string($conn, $stock);

        $sql   = "UPDATE delivery SET route = '$safes_route', pieces = '$safes_pieces', stock = '$safes_stock' WHERE delivery_id = '$safe_id'";
        $query = mysqli_query($conn, $sql);

        if ($query) {
            $status_message = "Update Successful!";
            header("Location: supervisor.php");
            exit();
        }
    }
}

// ==========================================
// 4. VIEW ALL RECORDS
// ==========================================
$sql     = "SELECT * FROM delivery ORDER BY route ASC";
$result  = mysqli_query($conn, $sql);
$count   = ($result) ? mysqli_num_rows($result) : 0;

$records = [];
if ($count > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        // Ginawang 'id' alias para basahin sa HTML hidden input
        $row['id'] = $row['delivery_id']; 
        $records[] = $row;
    }
}
?>