<?php
//Insert
include('config/connection.php');

$message = "";

if (isset($_POST['submit'])) {
    $route = $_POST['route'];
    $pieces    = $_POST['pieces'];
    $stock  = $_POST['stock'];

    $sql   = "INSERT INTO delivery (id, route, pieces, stock) VALUES ('', '$route', '$pieces', '$stock')";
    $query = mysqli_query($conn, $sql);

    if ($query) {
        $message = "New Record is Saved <br><br><a href='supervisor_view.php'><input type='button' value='View Records'></a>";
    }
} elseif (isset($_POST['records'])) {
    header("Location: supervisor_view.php");
    exit();
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
        'pieces'    => $row['pieces'],
        'stock'    => $row['stock']
    ];
}
?>

<?php
include('config/connection.php');
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