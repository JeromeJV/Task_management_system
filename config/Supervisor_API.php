<?php
//Insert
include('config/connection.php');

$message = "";
$route_err = '';
$pieces_err = '';
$stock_err = '';

if (isset($_POST['submit'])) {
    $route = $_POST['route'];
    $pieces    = $_POST['pieces'];
    $stock  = $_POST['stock'];

     $isValid = true;

    if (!preg_match("/^[a-zA-Z0-9 ]*$/", $route)) {
        $route_err = "Please use only letters and spaces for your Address.";
        $isValid = false;
    }

    if (!preg_match("/^[0-9 ]*$/", $pieces)) {
        $pieces_err = "Please use only number for your Pieces.";
        $isValid = false;
    }

    if (!preg_match("/^[0-9 ]*$/", $stock)) {
        $stock_err = "Please use only number for your Stock.";
        $isValid = false;
    }

     if ($isValid) {
        $safe_route     = mysqli_real_escape_string($conn, $route);
        $safe_pieces    = mysqli_real_escape_string($conn, $pieces);
        $safe_stock     = mysqli_real_escape_string($conn, $stock);

        $sql   = "INSERT INTO delivery (id, route, pieces, stock) VALUES ('', '$safe_route', '$safe_pieces', '$safe_stock')";
        $query = mysqli_query($conn, $sql);

        if ($query) {
            $message = "New Task send</a>";
        } elseif (isset($_POST['records'])) {
        header("Location: supervisor.php");
        exit();
        }
     }
}


//Edit

$passid = $_POST['idno'] ?? null;
$view_data = null;
$delete_message = "";

if (isset($_POST['del'])) {
    // Backend Logic for Delete
    $sql    = "DELETE FROM delivery WHERE id = '$passid'";
    $result = mysqli_query($conn, $sql);
    $delete_message = "Record Deleted Successfully. <br><a href='supervisor_view.php'>View Records</a>";

} elseif (isset($_POST['upd'])) {
    //Dto nag fe-fetch para sa single Record to Update
    $sql    = "SELECT * FROM delivery WHERE id = '$passid'";
    $result = mysqli_query($conn, $sql);
    $row    = mysqli_fetch_assoc($result);

    $view_data = [
        'id'      => $passid,
        'route' => $row['route'],
        'pieces'    => $row['pieces'],
        'stock'    => $row['stock']
    ];
}

//Update
$status_message = "";

if (isset($_POST['submit'])) {
    $route = $_POST['route'];
    $pieces    = $_POST['pieces'];
    $stock    = $_POST['stock'];

    $sql   = "UPDATE delivery SET  pieces = '$pieces', stock = '$stock' WHERE route = '$route' ";
    $query = mysqli_query($conn, $sql);

    if ($query) {
        $status_message = "<br>Update Successful<br><br><a href='supervisor_view.php'><input type='button' name='back' value='View Records'></a>";
    }
} elseif (isset($_POST['can'])) {
    header("Location: supervisor_view.php");
    exit();
}

//View
$sql    = "SELECT * FROM delivery ORDER BY route ASC";
$result = mysqli_query($conn, $sql);
$count  = mysqli_num_rows($result);

$records = [];
if ($count > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $records[] = $row;
    }
}
?>