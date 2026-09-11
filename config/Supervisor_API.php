<?php
// -----------------------------------------------------
// 1. Connection & Initialization
// -----------------------------------------------------
include('config/connection.php');

$module = $_REQUEST['module'] ?? 'delivery'; 
$action = $_POST['action'] ?? '';

$message          = "";
$status_message   = "";
$delete_message   = "";
$errors           = [];
$view_data        = null;

// Hinihiwalay na mga array para sa Delivery at Factory Modules
$pending_records  = []; 
$history_records  = []; 

// Para sa Production Items / Dropdown selection
$records          = []; 
$production_items = []; 

// -----------------------------------------------------
// 2. Module: DELIVERY
// -----------------------------------------------------
if ($module === 'delivery') {

    // --- INSERT ---
    if (isset($_POST['submit']) && $action === 'insert') {
        $production_id = $_POST['production_id'] ?? '';
        $route         = trim($_POST['route'] ?? '');
        $delivery_date = trim($_POST['delivery_date'] ?? '');

        // Validation
        if (empty($production_id)) {
            $errors['production_id'] = "Please select a product.";
        }
        if (!preg_match("/^[a-zA-Z0-9 ]*$/", $route) || empty($route)) {
            $errors['route'] = "Please enter a valid route address (letters and numbers only).";
        }
        if (empty($delivery_date)) {
            $errors['delivery_date'] = "Delivery date is required.";
        }

        if (empty($errors)) {
            // Fetch production item specs
            $get_prod = $conn->prepare("SELECT Stock_number, quantity FROM production WHERE production_id = ?");
            $get_prod->bind_param("s", $production_id);
            $get_prod->execute();
            $prod_res  = $get_prod->get_result();
            $prod_data = $prod_res->fetch_assoc();
            $get_prod->close();

            if ($prod_data) {
                $stock  = $prod_data['Stock_number'];
                $pieces = $prod_data['quantity']; 

                $stmt = $conn->prepare("INSERT INTO delivery (route, pieces, stock, delivery_date) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $route, $pieces, $stock, $delivery_date);
                
                if ($stmt->execute()) {
                    $message = "New Delivery Task sent successfully.";
                }
                $stmt->close();
            } else {
                $errors['production_id'] = "Selected product not found in production records.";
            }
        }
    }

    // --- EDIT & DELETE ---
    $passid = $_POST['idno'] ?? null;

    if (isset($_POST['del']) && $passid) {
        $stmt = $conn->prepare("DELETE FROM delivery WHERE delivery_id = ?");
        $stmt->bind_param("s", $passid);
        $stmt->execute();
        $stmt->close();
        $delete_message = "Record Deleted Successfully. <br><a href='delivery_main.php'>View Records</a>";

    } elseif (isset($_POST['upd']) && $passid) {
        $stmt = $conn->prepare("SELECT * FROM delivery WHERE delivery_id = ?");
        $stmt->bind_param("s", $passid);
        $stmt->execute();
        $result = $stmt->get_result();
        $view_data = $result->fetch_assoc();
        $stmt->close();
    }

    // --- UPDATE ---
    if (isset($_POST['submit']) && $action === 'update') {
        $route         = $_POST['route'] ?? '';
        $pieces        = $_POST['pieces'] ?? '';
        $stock         = $_POST['stock'] ?? '';
        $delivery_date = $_POST['delivery_date'] ?? '';

        $stmt = $conn->prepare("UPDATE delivery SET pieces = ?, stock = ?, delivery_date = ? WHERE route = ?");
        $stmt->bind_param("ssss", $pieces, $stock, $delivery_date, $route);

        if ($stmt->execute()) {
            $status_message = "<br>Update Successful<br><br><a href='delivery_main.php'><input type='button' value='View Records'></a>";
        }
        $stmt->close();
    }

    // --- UPDATE STATUS TO DELIVERED ---
    if (isset($_POST['mark_delivered'])) {
        $delivery_id = $_POST['idno'] ?? '';

        if (!empty($delivery_id)) {
            $stmt = $conn->prepare("UPDATE delivery SET status = 'Delivered' WHERE delivery_id = ?");
            $stmt->bind_param("s", $delivery_id);
            $stmt->execute();
            $stmt->close();
        }
        
        $redirect_page = ($_SESSION['role'] === 'log') ? 'logistic.php' : 'delivery_main.php';
        header("Location: $redirect_page");
        exit();
    }

    // --- VIEW / FETCH RECORDS (DELIVERY) ---

    // 1. Fetch PENDING Deliveries
    $sql_pending = "SELECT d.delivery_id, d.route, d.pieces, d.stock, d.delivery_date, d.status, 
                       p.product_name 
                FROM delivery d 
                LEFT JOIN production p ON d.stock = p.Stock_number 
                WHERE d.status != 'Delivered' OR d.status IS NULL
                GROUP BY d.delivery_id
                ORDER BY d.delivery_id DESC";

    $res_pending = mysqli_query($conn, $sql_pending);
    if ($res_pending && mysqli_num_rows($res_pending) > 0) {
        while ($row = mysqli_fetch_assoc($res_pending)) {
            $pending_records[] = $row;
        }
    }

    // 2. Fetch DELIVERED Deliveries (History)
    $sql_history = "SELECT d.delivery_id, d.route, d.pieces, d.stock, d.delivery_date, d.status, 
                       p.product_name 
                FROM delivery d 
                LEFT JOIN production p ON d.stock = p.Stock_number 
                WHERE d.status = 'Delivered' 
                GROUP BY d.delivery_id
                ORDER BY d.delivery_id DESC";

    $res_history = mysqli_query($conn, $sql_history);
    if ($res_history && mysqli_num_rows($res_history) > 0) {
        while ($row = mysqli_fetch_assoc($res_history)) {
            $history_records[] = $row;
        }
    }

    // Fetch products para sa Delivery Dropdown selection
    $prod_query = "SELECT production_id, product_name, Stock_number, quantity, product_status FROM production";
    $prod_result = mysqli_query($conn, $prod_query);
    if ($prod_result) {
        $production_items = mysqli_fetch_all($prod_result, MYSQLI_ASSOC);
    }
} 

// -----------------------------------------------------
// 3. Module: FACTORY (PRODUCTION)
// -----------------------------------------------------
elseif ($module === 'factory') {

    // --- INSERT ---
    if (isset($_POST['submit']) && $action === 'insert') {
        $product_name = trim($_POST['product_name'] ?? '');
        $target_pcs   = trim($_POST['target_pcs'] ?? '');
        $due_date     = trim($_POST['due_date'] ?? '');
        $Stock_number = trim($_POST['Stock_number'] ?? '');
        $quantity     = trim($_POST['quantity'] ?? '');

        if (!preg_match("/^[a-zA-Z0-9 ]*$/", $product_name)) {
            $errors['product_name'] = "Please use only letters and spaces for your Product Name.";
        }
        if (!preg_match("/^[0-9 ]*$/", $target_pcs)) {
            $errors['target_pcs'] = "Please use only numbers for your Target PCS.";
        }
        if (!preg_match("/^[0-9 ]*$/", $quantity)) {
            $errors['quantity'] = "Please use only numbers for your Quantity.";
        }
        if (!preg_match("/^[0-9 ]*$/", $Stock_number)) {
            $errors['Stock_number'] = "Please use only numbers for your Stock Number.";
        }
        if (!empty($Stock_number) && mb_strlen($Stock_number) != 4) {
            $errors['Stock_number'] = "Your Stock Number must be exactly 4 characters long.";
        }

        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO production (product_name, target_pcs, due_date, Stock_number, quantity) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $product_name, $target_pcs, $due_date, $Stock_number, $quantity);
            
            if ($stmt->execute()) {
                $message = "New Production Task sent successfully.";
            }
            $stmt->close();
        }
    }

    // --- EDIT & DELETE ---
    $passid = $_POST['idno'] ?? null;

    if (isset($_POST['del']) && $passid) {
        $stmt = $conn->prepare("DELETE FROM production WHERE production_id = ?");
        $stmt->bind_param("s", $passid);
        $stmt->execute();
        $stmt->close();
        $delete_message = "Record Deleted Successfully. <br><a href='factory_main.php'>View Records</a>";

    } elseif (isset($_POST['upd']) && $passid) {
        $stmt = $conn->prepare("SELECT * FROM production WHERE production_id = ?");
        $stmt->bind_param("s", $passid);
        $stmt->execute();
        $result = $stmt->get_result();
        $view_data = $result->fetch_assoc();
        $stmt->close();
    }

    // --- UPDATE ---
    if (isset($_POST['submit']) && $action === 'update') {
        $production_id = $_POST['production_id'] ?? '';
        $product_name  = $_POST['product_name'] ?? '';
        $target_pcs    = $_POST['target_pcs'] ?? '';
        $due_date      = $_POST['due_date'] ?? '';
        $Stock_number  = $_POST['Stock_number'] ?? '';
        $quantity      = $_POST['quantity'] ?? '';

        $stmt = $conn->prepare("UPDATE production SET product_name=?, target_pcs=?, due_date=?, Stock_number=?, quantity=? WHERE production_id=?");
        $stmt->bind_param("ssssss", $product_name, $target_pcs, $due_date, $Stock_number, $quantity, $production_id);

        if ($stmt->execute()) {
            $status_message = "<br>Update Successful<br><br><a href='factory_main.php'><input type='button' value='View Records'></a>";
        }
        $stmt->close();
    }

    // --- UPDATE STATUS TO DONE ---
    if (isset($_POST['mark_done'])) {
        $production_id = $_POST['idno'] ?? '';

        if (!empty($production_id)) {
            $stmt = $conn->prepare("UPDATE production SET product_status = 'product done' WHERE production_id = ?");
            $stmt->bind_param("s", $production_id);
            $stmt->execute();
            $stmt->close();
        }
        
        $redirect_page = ($_SESSION['role'] === 'pro') ? 'production.php' : 'factory_main.php';
        header("Location: $redirect_page");
        exit();
    }

    // --- VIEW / FETCH RECORDS (FACTORY: HIWALAY NA ANG PENDING AT HISTORY) ---

    // 1. Fetch PENDING Factory / Production Tasks (Hindi pa 'product done')
    $sql_pending = "SELECT production_id, product_name, target_pcs, Stock_number, quantity, due_date, product_status 
                    FROM production 
                    WHERE product_status != 'product done' OR product_status IS NULL
                    GROUP BY production_id
                    ORDER BY production_id DESC";

    $res_pending = mysqli_query($conn, $sql_pending);
    if ($res_pending && mysqli_num_rows($res_pending) > 0) {
        while ($row = mysqli_fetch_assoc($res_pending)) {
            $pending_records[] = $row;
        }
    }

    // 2. Fetch DONE Factory / Production Tasks (History / 'product done')
    $sql_history = "SELECT production_id, product_name, target_pcs, Stock_number, quantity, due_date, product_status 
                    FROM production 
                    WHERE product_status = 'product done'
                    GROUP BY production_id 
                    ORDER BY production_id DESC";

    $res_history = mysqli_query($conn, $sql_history);
    if ($res_history && mysqli_num_rows($res_history) > 0) {
        while ($row = mysqli_fetch_assoc($res_history)) {
            $history_records[] = $row;
        }
    }

    $count = count($pending_records);
}

// -----------------------------------------------------
// 4. Cancel Redirection
// -----------------------------------------------------
if (isset($_POST['can'])) {
    $redirect_page = ($module === 'factory') ? 'factory_main.php' : 'delivery_main.php';
    header("Location: $redirect_page");
    exit();
}
?>
