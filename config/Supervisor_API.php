<?php
include('config/connection.php');

// -----------------------------------------------------
// 1. Connection & Initialization
// -----------------------------------------------------

$module = $_REQUEST['module'] ?? 'delivery';

// Authorization Check
if (!isset($_SESSION['email'])) {
    header("Location: index.php");
    exit();
}

$allowed_roles = ($module === 'factory') ? ['super', 'pro'] : ['super', 'log'];
if (!in_array($_SESSION['role'], $allowed_roles, true)) {
    header("Location: index.php");
    exit();
}

$action = $_POST['action'] ?? '';

$message          = "";
$status_message   = "";
$delete_message   = "";
$errors           = [];
$view_data        = null;

// Arrays para sa data handling
$pending_records  = []; 
$history_records  = []; 
$records          = []; 
$production_items = []; 

// Trusted list ng products mula sa ENUM schema
$allowed_products = [
    "Ginga Turmeric Brew",
    "Ginga Turmeric w/ Guyabano",
    "Ginga Turmeric w/ Lemon",
    "Ginga Ginger - Regural Pouch",
    "Ginga Ginger Brew with Turmeric And Lemon",
    "Ginga Ginger - Strong",
    "Ginga Ginger - Regural",
    "Ginga Ginger Pure Tea",
    "Ginga Turmeric Pure Tea",
    "Ginga Mangosteen Pure Tea",
    "Ginga Guyabano Pure Tea",
    "Ginga Butterfly Pea Tea",
    "Herbal Green Tea"
];

// -----------------------------------------------------
// 2. Module: DELIVERY
// -----------------------------------------------------
if ($module === 'delivery') {

    // --- FETCH PRESENT DRIVERS TODAY ---
    $present_drivers = [];
    $sql_drivers = "SELECT DISTINCT u.id, u.name 
                    FROM users u
                    INNER JOIN employee e ON (e.username = u.name OR e.email = u.email)
                    INNER JOIN attendance a ON a.employee_id = e.employee_id
                    WHERE u.role = 'log' 
                    AND DATE(a.attendance_date) = CURDATE()
                    AND LOWER(a.status) = 'present'
                    AND a.time_in IS NOT NULL 
                    AND a.time_in != '00:00:00'";

    $res_drivers = mysqli_query($conn, $sql_drivers);
    if ($res_drivers && mysqli_num_rows($res_drivers) > 0) {
        while ($d = mysqli_fetch_assoc($res_drivers)) {
            $present_drivers[] = $d;
        }
    }

    function isDriverPresentToday($conn, $driver_id) {
        $check_sql = "SELECT a.attendance_id 
                    FROM attendance a
                    INNER JOIN employee e ON a.employee_id = e.employee_id
                    INNER JOIN users u ON (u.name = e.username OR u.email = e.email)
                    WHERE u.id = ? 
                        AND u.role = 'log'
                        AND DATE(a.attendance_date) = CURDATE()
                        AND LOWER(a.status) = 'present'
                        AND a.time_in IS NOT NULL 
                        AND a.time_in != '00:00:00'";
        
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("i", $driver_id);
        $stmt->execute();
        $stmt->store_result();
        $is_present = ($stmt->num_rows > 0);
        $stmt->close();
        
        return $is_present;
    }

    // --- INSERT DELIVERY ---
    if (isset($_POST['submit']) && $action === 'insert') {
        $production_id = $_POST['production_id'] ?? '';
        $driver_id     = $_POST['driver_id'] ?? '';
        $route         = trim($_POST['route'] ?? '');
        $delivery_date = trim($_POST['delivery_date'] ?? '');

        if (empty($production_id)) {
            $errors['production_id'] = "Please select a product.";
        }
        
        if (empty($driver_id)) {
            $errors['driver_id'] = "Please assign a present driver.";
        } else if (!isDriverPresentToday($conn, $driver_id)) {
            $errors['driver_id'] = "Selected driver is not timed in / present today or has already timed out.";
        }

        if (!preg_match("/^[a-zA-Z0-9 ]*$/", $route) || empty($route)) {
            $errors['route'] = "Please enter a valid route address.";
        }
        if (empty($delivery_date)) {
            $errors['delivery_date'] = "Delivery date is required.";
        }

        if (empty($errors)) {
            // Kunin lang ang data ng product kung ito ay 'product done'
            $get_prod = $conn->prepare("SELECT Stock_number, quantity FROM production WHERE production_id = ? AND product_status = 'product done'");
            $get_prod->bind_param("s", $production_id);
            $get_prod->execute();
            $prod_res  = $get_prod->get_result();
            $prod_data = $prod_res->fetch_assoc();
            $get_prod->close();

            if ($prod_data) {
                $stock  = $prod_data['Stock_number'];
                $pieces = $prod_data['quantity']; 

                $stmt = $conn->prepare("INSERT INTO delivery (production_id, route, pieces, stock, driver_id, delivery_date) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssss", $production_id, $route, $pieces, $stock, $driver_id, $delivery_date);
                
                if ($stmt->execute()) {
                    $message = "New Delivery Task assigned and created successfully.";
                }
                $stmt->close();
            } else {
                $errors['production_id'] = "Selected product is either not found or not yet completed ('product done').";
            }
        }
    }

    // --- UPDATE DELIVERY ---
    if (isset($_POST['submit']) && $action === 'update') {
        $delivery_id   = $_POST['delivery_id'] ?? '';
        $driver_id     = $_POST['driver_id'] ?? '';
        $route         = trim($_POST['route'] ?? '');
        $pieces        = trim($_POST['pieces'] ?? '');
        $stock         = trim($_POST['stock'] ?? '');
        $delivery_date = trim($_POST['delivery_date'] ?? '');

        if (empty($driver_id)) {
            $errors['driver_id'] = "Please select a driver.";
        } else if (!isDriverPresentToday($conn, $driver_id)) {
            $errors['driver_id'] = "Selected driver is not timed in / present today or has already timed out.";
        }

        if (!empty($delivery_id) && empty($errors)) {
            $stmt = $conn->prepare("UPDATE delivery SET route = ?, pieces = ?, stock = ?, driver_id = ?, delivery_date = ? WHERE delivery_id = ?");
            $stmt->bind_param("ssssss", $route, $pieces, $stock, $driver_id, $delivery_date, $delivery_id);

            if ($stmt->execute()) {
                $message = "Delivery record updated successfully.";
            }
            $stmt->close();
        }
    }

    // --- CONFIRM DELIVERY ---
    if (($_POST['mark_delivered'] ?? '') === '1') {
        $delivery_id = filter_var($_POST['idno'] ?? '', FILTER_VALIDATE_INT);
        $current_user_id = $_SESSION['id'] ?? $_SESSION['user_id'] ?? null;

        if ($delivery_id && $current_user_id !== null) {
            $stmt = $conn->prepare("UPDATE delivery SET status = 'Delivered' WHERE delivery_id = ? AND driver_id = ? AND (status != 'Delivered' OR status IS NULL)");
            $stmt->bind_param("ii", $delivery_id, $current_user_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    // -----------------------------------------------------------------
    // ACCOUNT-LEVEL DATA ISOLATION (FETCH PENDING & HISTORY DELIVERIES)
    // -----------------------------------------------------------------
    
    // Kunin ang Role at User ID mula sa active session
    $current_role    = $_SESSION['role'] ?? '';
    $current_user_id = $_SESSION['id'] ?? $_SESSION['user_id'] ?? null; 

    // --- FETCH PENDING DELIVERIES ---
    if ($current_role === 'super') {
        // PAG SUPERVISOR: Nakikita lahat ng delivery ng kahit sinong driver
        $sql_pending = "SELECT d.delivery_id, d.production_id, d.route, d.pieces, d.stock, d.driver_id, d.delivery_date, d.status, 
                               p.product_name, p.product_status,
                               u.name AS driver_name
                        FROM delivery d 
                        LEFT JOIN production p ON d.production_id = p.production_id 
                        LEFT JOIN users u ON d.driver_id = u.id
                        WHERE d.status != 'Delivered' OR d.status IS NULL
                        GROUP BY d.delivery_id
                        ORDER BY d.delivery_id DESC";
        $stmt_pending = $conn->prepare($sql_pending);
    } else {
        // PAG DRIVER / LOGISTICS ('log'): ACCOUNT-LEVEL FILTERING
        // I-filter gamit ang d.driver_id = ? para KANYANG ACCOUNT LANG ANG LUMABAS
        $sql_pending = "SELECT d.delivery_id, d.production_id, d.route, d.pieces, d.stock, d.driver_id, d.delivery_date, d.status, 
                               p.product_name, p.product_status,
                               u.name AS driver_name
                        FROM delivery d 
                        LEFT JOIN production p ON d.production_id = p.production_id 
                        LEFT JOIN users u ON d.driver_id = u.id
                        WHERE (d.status != 'Delivered' OR d.status IS NULL)
                          AND d.driver_id = ?
                        GROUP BY d.delivery_id
                        ORDER BY d.delivery_id DESC";
        $stmt_pending = $conn->prepare($sql_pending);
        $stmt_pending->bind_param("i", $current_user_id);
    }

    if ($stmt_pending->execute()) {
        $res_pending = $stmt_pending->get_result();
        while ($row = $res_pending->fetch_assoc()) {
            $pending_records[] = $row;
        }
    }
    $stmt_pending->close();


    // --- FETCH DELIVERY HISTORY ---
    if ($current_role === 'super') {
        // PAG SUPERVISOR: Nakikita ang buong kasaysayan ng delivery ng lahat
        $sql_history = "SELECT d.delivery_id, d.production_id, d.route, d.pieces, d.stock, d.driver_id, d.delivery_date, d.status, 
                               p.product_name, p.product_status,
                               u.name AS driver_name 
                        FROM delivery d 
                        LEFT JOIN production p ON d.production_id = p.production_id 
                        LEFT JOIN users u ON d.driver_id = u.id
                        WHERE d.status = 'Delivered' 
                        GROUP BY d.delivery_id
                        ORDER BY d.delivery_id DESC";
        $stmt_history = $conn->prepare($sql_history);
    } else {
        // PAG DRIVER / LOGISTICS ('log'): ACCOUNT-LEVEL FILTERING
        $sql_history = "SELECT d.delivery_id, d.production_id, d.route, d.pieces, d.stock, d.driver_id, d.delivery_date, d.status, 
                               p.product_name, p.product_status,
                               u.name AS driver_name 
                        FROM delivery d 
                        LEFT JOIN production p ON d.production_id = p.production_id 
                        LEFT JOIN users u ON d.driver_id = u.id
                        WHERE d.status = 'Delivered'
                          AND d.driver_id = ?
                        GROUP BY d.delivery_id
                        ORDER BY d.delivery_id DESC";
        $stmt_history = $conn->prepare($sql_history);
        $stmt_history->bind_param("i", $current_user_id);
    }

    if ($stmt_history->execute()) {
        $res_history = $stmt_history->get_result();
        while ($row = $res_history->fetch_assoc()) {
            $history_records[] = $row;
        }
    }
    $stmt_history->close();

    
    // --- FETCH COMPLETED PRODUCTS ONLY ('product done') ---
    $prod_query = "SELECT p.production_id, p.product_name, p.Stock_number, p.quantity, p.product_status   
                   FROM production p
                   LEFT JOIN delivery d ON p.production_id = d.production_id
                   WHERE p.product_status = 'product done' 
                     AND d.delivery_id IS NULL";

    $prod_result = mysqli_query($conn, $prod_query);
    if ($prod_result) {
        $production_items = mysqli_fetch_all($prod_result, MYSQLI_ASSOC);
    }
}

// -----------------------------------------------------
// 3. Module: FACTORY (PRODUCTION)
// -----------------------------------------------------
elseif ($module === 'factory') {

    // --- INSERT PRODUCTION ---
    if (isset($_POST['submit']) && $action === 'insert') {
        $product_name = trim($_POST['product_name'] ?? '');
        $target_pcs   = trim($_POST['target_pcs'] ?? '');
        $due_date     = trim($_POST['due_date'] ?? '');
        $Stock_number = trim($_POST['Stock_number'] ?? '');
        $quantity     = trim($_POST['quantity'] ?? '');

        if (!in_array($product_name, $allowed_products)) {
            $errors['product_name'] = "Please select a valid product from the list.";
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

    // --- DELETE PRODUCTION ---
    $passid = $_POST['idno'] ?? null;

    if (isset($_POST['del']) && $passid) {
        $stmt = $conn->prepare("DELETE FROM production WHERE production_id = ?");
        $stmt->bind_param("s", $passid);
        $stmt->execute();
        $stmt->close();
        $message = "Record deleted successfully.";
    }

    // --- UPDATE PRODUCTION ---
    if (isset($_POST['submit']) && $action === 'update') {
        $production_id = trim($_POST['production_id'] ?? '');
        $product_name  = trim($_POST['product_name'] ?? '');
        $target_pcs    = trim($_POST['target_pcs'] ?? '');
        $due_date      = trim($_POST['due_date'] ?? '');
        $Stock_number  = trim($_POST['Stock_number'] ?? '');
        $quantity      = trim($_POST['quantity'] ?? '');

        if (!in_array($product_name, $allowed_products)) {
            $errors['product_name'] = "Please select a valid product from the list.";
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

        if (empty($errors) && !empty($production_id)) {
            $stmt = $conn->prepare("UPDATE production SET product_name=?, target_pcs=?, due_date=?, Stock_number=?, quantity=? WHERE production_id=?");
            $stmt->bind_param("ssssss", $product_name, $target_pcs, $due_date, $Stock_number, $quantity, $production_id);

            if ($stmt->execute()) {
                $message = "Production Task updated successfully.";
            } else {
                $message = "Failed to update Production Task.";
            }
            $stmt->close();
        }
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

    // --- FETCH PRODUCTION RECORDS ---
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
}

// -----------------------------------------------------
// 4. Cancel Redirection
// -----------------------------------------------------
if (isset($_POST['can'])) {
    $redirect_page = ($module === 'factory') ? 'factory_main.php' : 'delivery_main.php';
    header("Location: $redirect_page");
    exit();
}