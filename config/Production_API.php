<!-- CRUD PART -->
<?php
//Insert
include('config/connection.php');

$message = "";

if (isset($_POST['submit'])) {
    $production_id = $_POST['production_id'];
    $task_name = $_POST['task_name'];
    $item_name    = $_POST['item_name'];
    $quantity  = $_POST['quantity'];

    $sql   = "INSERT INTO production (id, production_id, task_name, item_name, quantity) VALUES ('', '$production_id', '$task_name', '$item_name', '$quantity')";
    $query = mysqli_query($conn, $sql);

    if ($query) {
        $message = "New Record is Saved <br><br><a href='supervisor_view.php'><input type='button' value='View Records'></a>";
    }
} elseif (isset($_POST['records'])) {
    header("Location: supervisor_view.php"); //File ng production
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
        'production_id' => $row['production_id'],
        'task_name' => $row['task_name'],
        'item_name'    => $row['item_name'],
        'quantity'    => $row['quantity']
    ];
}
?>

<?php
include('config/connection.php');
//Update
$status_message = "";

if (isset($_POST['submit'])) {
    $production_id = $_POST['production_id'];
    $task_name = $_POST['task_name'];
    $item_name    = $_POST['item_name'];
    $quantity    = $_POST['quantity'];

    $sql   = "UPDATE production SET  task_name = '$task_name', item_name = '$item_name', quantity = '$quantity' WHERE production_id = 'production_id' ";
    $query = mysqli_query($conn, $sql);

    if ($query) {
        $status_message = "<br>Update Successful<br><br><a href='supervisor_view.php'><input type='button' name='back' value='View Records'></a>";
    }
} elseif (isset($_POST['can'])) {
    header("Location: supervisor_view.php"); //frontend file
    exit();
}
?>

<?php
include 'config/connection.php';
//View
$sql    = "SELECT * FROM production ORDER BY route ASC";
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