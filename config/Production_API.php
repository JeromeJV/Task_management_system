 <?php
// -----------------------------------------------------
//                      Insert
// -----------------------------------------------------
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/production_stock_numbers.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/account_access.php';
if (!isset($_SESSION['id'], $_SESSION['email'], $_SESSION['role']) || $_SESSION['role'] !== 'super') {
    http_response_code(403);
    exit('Supervisor access is required.');
}
requireActiveAccount($conn);
if (empty($_SESSION['production_legacy_csrf'])) {
    $_SESSION['production_legacy_csrf'] = bin2hex(random_bytes(32));
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && !hash_equals((string) $_SESSION['production_legacy_csrf'], (string) ($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    exit('Your session token expired. Refresh and try again.');
}

$message = "";
$product_name_err = '';
$target_pcs_err = '';
$due_date_err = '';
$Stock_number_err = '';
$quantity_err = '';

// Ginawang 'insert_submit' ang name upang hindi magbanggaan sa Update
if (isset($_POST['submit']) && !isset($_POST['is_update'])) { 
    $production_id = $_POST['production_id'] ?? '';
    $product_name    = $_POST['product_name'] ?? '';
    $target_pcs = $_POST['target_pcs'] ?? '';
    $due_date = $_POST['due_date'] ?? '';
    $Stock_number = $_POST['Stock_number'] ?? '';
    $quantity = $_POST['quantity'] ?? '';

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

    if (!preg_match('/^[0-9]{4}$/', (string) $Stock_number)) {
        $Stock_number_err = 'Stock Number must be exactly 4 digits.';
        $isValid = false;
    }

    if ($isValid) {
        $stockLockAcquired = false;
        try {
            acquireProductionStockNumberLock($conn);
            $stockLockAcquired = true;
            $stockNumberValue = (int) $Stock_number;
            if (productionStockNumberExists($conn, $stockNumberValue)) {
                $Stock_number_err = 'That stock number is already assigned to another product.';
            } else {
                $insertStatement = $conn->prepare(
                    "INSERT INTO production (product_name, target_pcs, department, due_date, Stock_number, quantity)
                     VALUES (?, ?, 'Production', ?, ?, ?)"
                );
                if (!$insertStatement) {
                    throw new RuntimeException('Unable to prepare production product creation: ' . $conn->error);
                }
                $insertStatement->bind_param('sissi', $product_name, $target_pcs, $due_date, $stockNumberValue, $quantity);
                if ($insertStatement->execute()) {
                    $message = 'New Task sent successfully.';
                } else {
                    $message = 'Unable to save the production task.';
                }
                $insertStatement->close();
            }
        } catch (RuntimeException $error) {
            $Stock_number_err = $error->getMessage();
        } finally {
            if ($stockLockAcquired) {
                releaseProductionStockNumberLock($conn);
            }
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
    $productionId = filter_var($passid, FILTER_VALIDATE_INT);
    $deleteStatement = $productionId ? $conn->prepare('DELETE FROM production WHERE production_id = ?') : false;
    if ($deleteStatement) {
        $deleteStatement->bind_param('i', $productionId);
        $delete_message = $deleteStatement->execute()
            ? 'Record Deleted Successfully.'
            : 'Unable to delete the production record.';
        $deleteStatement->close();
    } else {
        $delete_message = 'Choose a valid production record.';
    }

} elseif (isset($_POST['upd'])) {
    $productionId = filter_var($passid, FILTER_VALIDATE_INT);
    $lookupStatement = $productionId ? $conn->prepare('SELECT * FROM production WHERE production_id = ?') : false;
    if ($lookupStatement) {
        $lookupStatement->bind_param('i', $productionId);
        $lookupStatement->execute();
        $row = $lookupStatement->get_result()->fetch_assoc();
        $lookupStatement->close();
    } else {
        $row = null;
    }

    if ($row) {
        $view_data = [
            'production_id' => $passid,
            'product_name'  => $row['product_name'],
            'target_pcs'    => $row['target_pcs'],
            'due_date'      => $row['due_date'],
            'Stock_number'  => $row['Stock_number'],
            'quantity'      => $row['quantity'] 
        ];
    }
}

// -----------------------------------------------------
//                      Update
// -----------------------------------------------------

$status_message = "";

// Ginamitan ng 'is_update' check para sa Update form lang mag-trigger
if (isset($_POST['submit']) && isset($_POST['is_update'])) {
    $production_id = $_POST['production_id'] ?? '';
    $product_name  = $_POST['product_name'] ?? '';
    $target_pcs    = $_POST['target_pcs'] ?? '';
    $due_date      = $_POST['due_date'] ?? '';
    $Stock_number  = $_POST['Stock_number'] ?? '';
    $quantity      = $_POST['quantity'] ?? '';

    $updateId = filter_var($production_id, FILTER_VALIDATE_INT);
    $stockNumberValue = preg_match('/^[0-9]{4}$/', $Stock_number) ? (int) $Stock_number : 0;
    if (!$updateId || $updateId < 1 || $stockNumberValue < 1000) {
        $status_message = 'Choose a valid production record and 4-digit stock number.';
    } else {
        $stockLockAcquired = false;
        try {
            acquireProductionStockNumberLock($conn);
            $stockLockAcquired = true;
            $currentStockNumber = getProductionStockNumber($conn, (int) $updateId);
            if ($currentStockNumber === null) {
                throw new RuntimeException('The selected production record no longer exists.');
            }
            if ($stockNumberValue !== $currentStockNumber && productionStockNumberExists($conn, $stockNumberValue, (int) $updateId)) {
                $status_message = 'That stock number is already assigned to another product.';
            } else {
                $updateStatement = $conn->prepare(
                    'UPDATE production SET product_name = ?, target_pcs = ?, due_date = ?, Stock_number = ?, quantity = ? WHERE production_id = ?'
                );
                if (!$updateStatement) {
                    throw new RuntimeException('Unable to prepare production update: ' . $conn->error);
                }
                $updateStatement->bind_param('sisiii', $product_name, $target_pcs, $due_date, $stockNumberValue, $quantity, $updateId);
                $status_message = $updateStatement->execute()
                    ? 'Update Successful.'
                    : 'Unable to update the production record.';
                $updateStatement->close();
            }
        } catch (RuntimeException $error) {
            $status_message = $error->getMessage();
        } finally {
            if ($stockLockAcquired) {
                releaseProductionStockNumberLock($conn);
            }
        }
    }
} elseif (isset($_POST['can'])) {
    header("Location: factory_main.php");
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