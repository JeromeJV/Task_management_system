<!-- CRUD PART -->
<?php
//Insert
include('config/connection.php');

$message = "";
$route_err = '';
$peaces_err = '';
$stock_err = '';

if (isset($_POST['submit'])) {
    $route = $_POST['route'];
    $peaces    = $_POST['peaces'];
    $stock  = $_POST['stock'];

     $isValid = true;

    if (!preg_match("/^[a-zA-Z0-9 ]*$/", $route)) {
        $route_err = "Please use only letters and spaces for your Address.";
        $isValid = false;
    }

    if (!preg_match("/^[0-9 ]*$/", $peaces)) {
        $peaces_err = "Please use only number for your Peaces.";
        $isValid = false;
    }

    if (!preg_match("/^[0-9 ]*$/", $stock)) {
        $stock_err = "Please use only number for your Stock.";
        $isValid = false;
    }

     if ($isValid) {
        $safe_route     = mysqli_real_escape_string($conn, $route);
        $safe_peaces    = mysqli_real_escape_string($conn, $peaces);
        $safe_stock     = mysqli_real_escape_string($conn, $stock);

        $sql   = "INSERT INTO delivery (id, route, peaces, stock) VALUES ('', '$safe_route', '$safe_peaces', '$safe_stock')";
        $query = mysqli_query($conn, $sql);

        if ($query) {
            $message = "New Task send</a>";
        } elseif (isset($_POST['records'])) {
        header("Location: supervisor.php");
        exit();
        }
     }
}

?>

<?php
//Edit
include('config/connection.php');

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
        'peaces'    => $row['peaces'],
        'stock'    => $row['stock']
    ];
}
?>

<?php
include('config/connection.php');
//Update
$status_message = "";
$message = "";
$route_err = '';
$peaces_err = '';
$stock_err = '';

if (isset($_POST['submit'])) {
    $route = $_POST['route'];
    $peaces    = $_POST['peaces'];
    $stock    = $_POST['stock'];

    if (!preg_match("/^[a-zA-Z0-9 ]*$/", $route)) {
        $route_err = "Please use only letters and spaces for your Address.";
        $isValid = false;
    }

    if (!preg_match("/^[0-9 ]*$/", $peaces)) {
        $peaces_err = "Please use only number for your Peaces.";
        $isValid = false;
    }

    if (!preg_match("/^[0-9 ]*$/", $stock)) {
        $stock_err = "Please use only number for your Stock.";
        $isValid = false;
    }
    if ($isValid) {
        $safes_route     = mysqli_real_escape_string($conn, $route);
        $safes_peaces    = mysqli_real_escape_string($conn, $peaces);
        $safes_stock     = mysqli_real_escape_string($conn, $stock);

    
        $sql   = "UPDATE delivery SET  peaces = '$safes_peaces', stock = '$safes_stock' WHERE route = '$safes_route' ";
        $query = mysqli_query($conn, $sql);

        if ($query) {
            $status_message = "<br>Update Successful<br><br><a href='supervisor.php'><input type='button' name='back' value='View Records'></a>";
        } elseif (isset($_POST['can'])) {
        header("Location: supervisor.php");
        exit();
        }
    }

}
?>

<?php
include 'config/connection.php';
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
<!-- CRUD PART -->