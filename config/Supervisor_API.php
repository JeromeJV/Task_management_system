<?php
include('config/connection.php');
require_once __DIR__ . '/account_access.php';
require_once __DIR__ . '/leave_helpers.php';
require_once __DIR__ . '/task_notifications.php';

// -----------------------------------------------------
// 1. Connection & Initialization
// -----------------------------------------------------

$module = $_REQUEST['module'] ?? 'delivery';

// Authorization Check
if (!isset($_SESSION['email'])) {
    header("Location: index.php");
    exit();
}
requireActiveAccount($conn);
ensureLeaveSchema($conn);
ensureTaskNotificationSchema($conn);
if (empty($_SESSION['task_review_csrf'])) {
    $_SESSION['task_review_csrf'] = bin2hex(random_bytes(32));
}

$allowed_roles = ($module === 'factory') ? ['super', 'pro', 'cooking', 'packaging'] : ['super', 'log'];
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
$allowed_products = require __DIR__ . '/ginga_products.php';

// -----------------------------------------------------
// 2. Module: DELIVERY
// -----------------------------------------------------
if ($module === 'delivery') {
    require_once __DIR__ . '/delivery_activity_schema.php';
    ensureDeliveryActivitySchema($conn);
    $current_role = (string) ($_SESSION['role'] ?? '');
    $current_user_id = (int) ($_SESSION['id'] ?? $_SESSION['user_id'] ?? 0);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['review_delivery'])) {
        $posted_token = (string) ($_POST['task_review_csrf'] ?? '');
        $delivery_id = filter_var($_POST['delivery_id'] ?? null, FILTER_VALIDATE_INT);
        $decision = (string) $_POST['review_delivery'];
        $review_note = trim((string) ($_POST['review_note'] ?? ''));

        if ($current_role !== 'super') {
            http_response_code(403);
            exit('Only a supervisor can review delivery completion.');
        }
        if (!hash_equals((string) $_SESSION['task_review_csrf'], $posted_token)) {
            $_SESSION['delivery_error'] = 'Your session token expired. Refresh the page and try again.';
        } elseif (!$delivery_id || $delivery_id < 1 || !in_array($decision, ['approve', 'reject'], true)) {
            $_SESSION['delivery_error'] = 'Choose a valid delivery and review decision.';
        } elseif ($decision === 'reject' && $review_note === '') {
            $_SESSION['delivery_error'] = 'Add an explanation when requesting delivery changes.';
        } elseif (mb_strlen($review_note) > 1000) {
            $_SESSION['delivery_error'] = 'The review note must be no more than 1000 characters.';
        } else {
            if (!$conn->begin_transaction()) {
                throw new RuntimeException('Unable to start the delivery review: ' . $conn->error);
            }
            $review_stmt = $conn->prepare(
                "SELECT route, driver_id, delivery_proof_image
                 FROM delivery
                 WHERE delivery_id = ? AND (assigned_by = ? OR assigned_by IS NULL)
                   AND status = 'Awaiting Approval'
                   AND completion_review_status = 'pending_review'
                 LIMIT 1 FOR UPDATE"
            );
            if (!$review_stmt) {
                throw new RuntimeException('Unable to prepare the delivery review lookup: ' . $conn->error);
            }
            $review_stmt->bind_param('ii', $delivery_id, $current_user_id);
            if (!$review_stmt->execute()) {
                $error = $review_stmt->error;
                $review_stmt->close();
                throw new RuntimeException('Unable to load the delivery for review: ' . $error);
            }
            $delivery_to_review = $review_stmt->get_result()->fetch_assoc();
            $review_stmt->close();

            if (!$delivery_to_review) {
                $conn->rollback();
                $_SESSION['delivery_error'] = 'This delivery is no longer waiting for your review.';
            } else {
                $approved = $decision === 'approve';
                $review_status = $approved ? 'approved' : 'rejected';
                $delivery_status = $approved ? 'Delivered' : 'Pending';
                $review_note_value = $review_note !== '' ? $review_note : null;
                $update_stmt = $conn->prepare(
                    "UPDATE delivery
                     SET status = ?, completion_review_status = ?, completion_review_note = ?,
                         completion_reviewed_by = ?, completion_reviewed_at = NOW(),
                         delivered_at = IF(? = 1, delivered_at, NULL)
                     WHERE delivery_id = ?
                       AND status = 'Awaiting Approval'
                       AND completion_review_status = 'pending_review'
                       AND (assigned_by = ? OR assigned_by IS NULL)"
                );
                if (!$update_stmt) {
                    throw new RuntimeException('Unable to prepare the delivery review update: ' . $conn->error);
                }
                $update_stmt->bind_param(
                    'sssiiii',
                    $delivery_status,
                    $review_status,
                    $review_note_value,
                    $current_user_id,
                    $approved,
                    $delivery_id,
                    $current_user_id
                );
                if (!$update_stmt->execute() || $update_stmt->affected_rows !== 1) {
                    $error = $update_stmt->error ?: 'The delivery review could not be saved.';
                    $update_stmt->close();
                    $conn->rollback();
                    throw new RuntimeException('Unable to save the delivery review: ' . $error);
                }
                $update_stmt->close();
                $actor_name = (string) ($_SESSION['name'] ?? 'Supervisor');
                recordDeliveryActivity(
                    $conn,
                    $delivery_id,
                    $current_user_id,
                    $actor_name,
                    $approved ? 'delivery_approved' : 'delivery_changes_requested'
                );
                createTaskNotification(
                    $conn,
                    (int) $delivery_to_review['driver_id'],
                    $current_user_id,
                    $approved ? 'delivery_approved' : 'delivery_changes_requested',
                    $approved ? 'Delivery approved' : 'Delivery changes requested',
                    $approved
                        ? 'Your delivery to ' . $delivery_to_review['route'] . ' was reviewed and approved.'
                        : 'Your delivery to ' . $delivery_to_review['route'] . ' needs changes: ' . $review_note,
                    'logistic.php',
                    'delivery',
                    (string) $delivery_id
                );
                if (!$conn->commit()) {
                    throw new RuntimeException('Unable to commit the delivery review: ' . $conn->error);
                }
                $_SESSION['delivery_success'] = $approved
                    ? 'Delivery approved and marked completed.'
                    : 'Delivery changes requested and sent to the driver.';
            }
        }
        $reviewReturn = (string) ($_POST['review_return'] ?? '');
        header('Location: ' . ($reviewReturn === 'delivery_main.php'
            ? 'delivery_main.php'
            : 'supervisor.php#page-dashboard'));
        exit();
    }

    if (isset($_GET['activity_log'])) {
        header('Content-Type: application/json; charset=utf-8');
        $delivery_id = filter_var($_GET['activity_log'], FILTER_VALIDATE_INT);
        if (!$delivery_id) {
            http_response_code(400);
            echo json_encode(['error' => 'A valid delivery ID is required.']);
            exit();
        }

        $activity_stmt = $conn->prepare(
            "SELECT actor_name, event_type, from_driver_name, to_driver_name, occurred_at
             FROM delivery_activity
             WHERE delivery_id = ?
             ORDER BY occurred_at DESC, activity_id DESC"
        );
        if (!$activity_stmt) {
            throw new RuntimeException('Unable to prepare delivery activity history: ' . $conn->error);
        }
        $activity_stmt->bind_param('i', $delivery_id);
        if (!$activity_stmt->execute()) {
            throw new RuntimeException('Unable to load delivery activity history: ' . $activity_stmt->error);
        }
        $activity_result = $activity_stmt->get_result();
        $activity_rows = [];
        while ($activity_row = $activity_result->fetch_assoc()) {
            $activity_rows[] = $activity_row;
        }
        $activity_stmt->close();

        if ($current_role === 'log') {
            $ownership_stmt = $conn->prepare("SELECT delivery_id FROM delivery WHERE delivery_id = ? AND driver_id = ? LIMIT 1");
            $ownership_stmt->bind_param('ii', $delivery_id, $current_user_id);
            if (!$ownership_stmt->execute() || $ownership_stmt->get_result()->num_rows === 0) {
                http_response_code(403);
                echo json_encode(['error' => 'You can only view activity for your assigned deliveries.']);
                exit();
            }
            $ownership_stmt->close();
        }
        echo json_encode(['activities' => $activity_rows], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // --- FETCH PRESENT DRIVERS TODAY ---
    $present_drivers = [];
    $sql_drivers = "SELECT u.id, u.name
                    FROM users u
                    WHERE u.role = 'log'
                      AND EXISTS (
                          SELECT 1
                          FROM attendance a
                          LEFT JOIN employee e ON e.employee_id = a.employee_id
                          WHERE a.attendance_date = CURDATE()
                            AND LOWER(TRIM(a.status)) IN ('present', 'early', 'late', 'overtime', 'undertime', 'half day')
                            AND a.time_in IS NOT NULL
                            AND a.time_in != '00:00:00'
                            AND (
                                (
                                    (a.time_out IS NULL OR a.time_out = '00:00:00')
                                    AND (a.time_in_2 IS NULL OR a.time_in_2 = '00:00:00')
                                )
                                OR (
                                    a.time_in_2 IS NOT NULL
                                    AND a.time_in_2 != '00:00:00'
                                    AND (a.time_out_2 IS NULL OR a.time_out_2 = '00:00:00')
                                )
                            )
                            AND (
                                a.user_id = u.id
                                OR e.username = u.name
                                OR (e.email IS NOT NULL AND e.email != '' AND e.email = u.email)
                                OR EXISTS (
                                    SELECT 1
                                    FROM attendance driver_user_link
                                    WHERE driver_user_link.employee_id = e.employee_id
                                      AND driver_user_link.user_id = u.id
                                )
                            )
                      )
                    ORDER BY u.name";

    $res_drivers = mysqli_query($conn, $sql_drivers);
    if ($res_drivers && mysqli_num_rows($res_drivers) > 0) {
        while ($d = mysqli_fetch_assoc($res_drivers)) {
            $present_drivers[] = $d;
        }
    }

    function isDriverPresentToday($conn, $driver_id) {
        $check_sql = "SELECT a.attendance_id
                      FROM attendance a
                      LEFT JOIN employee e ON e.employee_id = a.employee_id
                      INNER JOIN users u ON u.id = ?
                      WHERE u.role = 'log'
                        AND a.attendance_date = CURDATE()
                        AND LOWER(TRIM(a.status)) IN ('present', 'early', 'late', 'overtime', 'undertime', 'half day')
                        AND a.time_in IS NOT NULL
                        AND a.time_in != '00:00:00'
                        AND (
                            (
                                (a.time_out IS NULL OR a.time_out = '00:00:00')
                                AND (a.time_in_2 IS NULL OR a.time_in_2 = '00:00:00')
                            )
                            OR (
                                a.time_in_2 IS NOT NULL
                                AND a.time_in_2 != '00:00:00'
                                AND (a.time_out_2 IS NULL OR a.time_out_2 = '00:00:00')
                            )
                        )
                        AND (
                            a.user_id = u.id
                            OR e.username = u.name
                            OR (e.email IS NOT NULL AND e.email != '' AND e.email = u.email)
                            OR EXISTS (
                                SELECT 1
                                FROM attendance driver_user_link
                                WHERE driver_user_link.employee_id = e.employee_id
                                  AND driver_user_link.user_id = u.id
                            )
                        )";
        
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("i", $driver_id);
        $stmt->execute();
        $stmt->store_result();
        $is_present = ($stmt->num_rows > 0);
        $stmt->close();
        
        return $is_present;
    }

    // --- INSERT DELIVERY ---
    if (isset($_POST['submit']) && $action === 'insert' && $current_role === 'super') {
        $submitted_groups = $_POST['assignments'] ?? [];
        if (!is_array($submitted_groups)) {
            $submitted_groups = [];
        }
        if (empty($submitted_groups) && isset($_POST['production_ids'])) {
            $legacy_ids = $_POST['production_ids'];
            $submitted_groups = [[
                'driver_id' => $_POST['driver_id'] ?? '',
                'products' => [],
            ]];
            if (is_array($legacy_ids)) {
                foreach ($legacy_ids as $legacy_id) {
                    if (is_string($legacy_id) || is_int($legacy_id)) {
                        $submitted_groups[0]['products'][(string) $legacy_id] = [
                            'route' => $_POST['route'] ?? '',
                            'delivery_date' => $_POST['delivery_date'] ?? '',
                        ];
                    }
                }
            }
        }

        $deliveries_to_create = [];
        $seen_production_ids = [];
        foreach ($submitted_groups as $group) {
            if (!is_array($group)) {
                $errors['assignments'] = 'Invalid delivery group submitted.';
                continue;
            }
            $driver_id = filter_var($group['driver_id'] ?? '', FILTER_VALIDATE_INT);
            if (!$driver_id || $driver_id < 1) {
                $errors['assignments'] = 'Select an active driver for each delivery group.';
            } elseif (!isDriverPresentToday($conn, $driver_id)) {
                $errors['assignments'] = 'A selected driver is not timed in / present today or has already timed out.';
            }

            $group_products = $group['products'] ?? [];
            if (!is_array($group_products) || empty($group_products)) {
                $errors['assignments'] = 'Choose at least one product for each delivery group.';
                continue;
            }

            foreach ($group_products as $group_product_id => $product_details) {
                if (
                    (!is_string($group_product_id) && !is_int($group_product_id))
                    || !is_array($product_details)
                ) {
                    $errors['assignments'] = 'Invalid product selection submitted.';
                    continue;
                }
                $production_id = $product_details['production_id'] ?? $group_product_id;
                if (!is_string($production_id) && !is_int($production_id)) {
                    $errors['assignments'] = 'Choose a valid product.';
                    continue;
                }
                $production_id = trim((string) $production_id);
                if ($production_id === '' || strlen($production_id) > 64) {
                    $errors['assignments'] = 'Choose a valid product.';
                    continue;
                }
                if (isset($seen_production_ids[$production_id])) {
                    $errors['assignments'] = 'A product can only be assigned once.';
                    continue;
                }
                $seen_production_ids[$production_id] = true;

                $route = $product_details['route'] ?? null;
                $delivery_date = $product_details['delivery_date'] ?? null;
                $route = is_string($route) ? trim($route) : '';
                $delivery_date = is_string($delivery_date) ? trim($delivery_date) : '';
                $parsed_delivery_date = DateTimeImmutable::createFromFormat('!Y-m-d', $delivery_date);
                if ($route === '' || !preg_match("/^[a-zA-Z0-9 ]+$/", $route)) {
                    $errors['assignments'] = 'Enter a valid destination for every selected product.';
                }
                if (
                    !$parsed_delivery_date
                    || $parsed_delivery_date->format('Y-m-d') !== $delivery_date
                ) {
                    $errors['assignments'] = 'Select a valid delivery date for every selected product.';
                } elseif (userHasLeaveOnDate($conn, (int) $driver_id, $delivery_date)) {
                    $errors['assignments'] = 'A selected driver has recorded leave on the delivery date.';
                }
                $deliveries_to_create[] = [
                    'production_id' => $production_id,
                    'driver_id' => $driver_id,
                    'route' => $route,
                    'delivery_date' => $delivery_date,
                ];
            }
        }
        if (empty($deliveries_to_create)) {
            $errors['assignments'] = $errors['assignments'] ?? 'Select at least one product before creating deliveries.';
        }

        if (empty($errors)) {
            $conn->begin_transaction();
            $get_prod = null;
            $check_delivery = null;
            $insert_delivery = null;
            try {
                $get_prod = $conn->prepare(
                    "SELECT Stock_number, quantity
                     FROM production
                     WHERE production_id = ?
                       AND LOWER(TRIM(COALESCE(product_status, ''))) = 'product done'
                     FOR UPDATE"
                );
                if (!$get_prod) {
                    throw new RuntimeException('Unable to prepare the completed-product lookup: ' . $conn->error);
                }
                $check_delivery = $conn->prepare("SELECT delivery_id FROM delivery WHERE production_id = ? LIMIT 1");
                if (!$check_delivery) {
                    throw new RuntimeException('Unable to prepare the existing-delivery check: ' . $conn->error);
                }
                $insert_delivery = $conn->prepare(
                    "INSERT INTO delivery (production_id, route, pieces, stock, driver_id, assigned_by, delivery_date)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                if (!$insert_delivery) {
                    throw new RuntimeException('Unable to prepare delivery creation: ' . $conn->error);
                }

                $actor_name = trim((string) ($_SESSION['name'] ?? ''));
                if ($actor_name === '') {
                    $actor_name = 'User #' . $current_user_id;
                }
                $created_count = 0;

                foreach ($deliveries_to_create as $delivery_details) {
                    $production_id = $delivery_details['production_id'];
                    $driver_id = $delivery_details['driver_id'];
                    $route = $delivery_details['route'];
                    $delivery_date = $delivery_details['delivery_date'];
                    $get_prod->bind_param('s', $production_id);
                    if (!$get_prod->execute()) {
                        throw new RuntimeException('Unable to verify a selected product: ' . $get_prod->error);
                    }
                    $prod_result = $get_prod->get_result();
                    $prod_data = $prod_result->fetch_assoc();
                    $prod_result->free();
                    if (!$prod_data) {
                        throw new RuntimeException("A selected product is no longer available or is not marked 'Product Done'.");
                    }

                    $check_delivery->bind_param('s', $production_id);
                    if (!$check_delivery->execute()) {
                        throw new RuntimeException('Unable to verify whether a product is already assigned: ' . $check_delivery->error);
                    }
                    $existing_delivery = $check_delivery->get_result();
                    $already_assigned = $existing_delivery->num_rows > 0;
                    $existing_delivery->free();
                    if ($already_assigned) {
                        throw new RuntimeException('A selected product has already been assigned to a delivery.');
                    }
                    $pieces = $prod_data['quantity'];
                    $stock = $prod_data['Stock_number'];
                    $stock = $prod_data['Stock_number'];
                    $insert_delivery->bind_param(
                        'ssiiiis',
                        $production_id,
                        $route,
                        $pieces,
                        $stock,
                        $driver_id,
                        $current_user_id,
                        $delivery_date
                    );
                    if (!$insert_delivery->execute()) {
                        throw new RuntimeException('Unable to create a delivery task: ' . $insert_delivery->error);
                    }
                    $delivery_id = (int) $conn->insert_id;
                    $driver_name = getDeliveryDriverName($conn, $driver_id);
                    recordDeliveryActivity(
                        $conn,
                        $delivery_id,
                        $current_user_id,
                        $actor_name,
                        'delivery_assigned',
                        null,
                        null,
                        $driver_id,
                        $driver_name
                    );
                    createTaskNotification(
                        $conn,
                        (int) $driver_id,
                        (int) $current_user_id,
                        'delivery_assigned',
                        'New delivery assigned',
                        'A delivery to ' . $route . ' is assigned to you for ' . date('M j, Y', strtotime($delivery_date)) . '.',
                        'logistic.php',
                        'delivery',
                        (string) $delivery_id
                    );
                    $created_count++;
                }

                if (!$conn->commit()) {
                    throw new RuntimeException('Unable to commit the delivery assignments.');
                }
                $message = $created_count . ' delivery task' . ($created_count === 1 ? '' : 's') . ' assigned and created successfully.';
            } catch (RuntimeException $exception) {
                $conn->rollback();
                $errors['production_id'] = $exception->getMessage();
            } finally {
                if ($get_prod) {
                    $get_prod->close();
                }
                if ($check_delivery) {
                    $check_delivery->close();
                }
                if ($insert_delivery) {
                    $insert_delivery->close();
                }
            }
        }
    }

    // --- UPDATE DELIVERY ---
    if (isset($_POST['submit']) && $action === 'update' && $current_role === 'super') {
        $delivery_id   = filter_var($_POST['delivery_id'] ?? '', FILTER_VALIDATE_INT);
        $driver_id     = filter_var($_POST['driver_id'] ?? '', FILTER_VALIDATE_INT);
        $route         = trim($_POST['route'] ?? '');
        $pieces        = trim($_POST['pieces'] ?? '');
        $stock         = trim($_POST['stock'] ?? '');
        $delivery_date = trim($_POST['delivery_date'] ?? '');
        $pieces_is_integer = ctype_digit($pieces);
        $stock_is_integer = ctype_digit($stock);
        $pieces_value = $pieces_is_integer ? (int) $pieces : 0;
        $stock_value = $stock_is_integer ? (int) $stock : 0;

        if (!$delivery_id || $delivery_id < 1) {
            $errors['delivery_id'] = 'Choose a valid delivery record to update.';
        }
        if (!$driver_id || $driver_id < 1) {
            $errors['driver_id'] = "Please select a driver.";
        }
        if ($route === '' || strlen($route) > 255) {
            $errors['route'] = 'Enter a destination with no more than 255 characters.';
        }
        if (!$pieces_is_integer || $pieces_value < 1 || $pieces_value > 2147483647) {
            $errors['pieces'] = 'Enter a quantity of at least one piece.';
        }
        if (!$stock_is_integer || $stock_value < 1 || $stock_value > 2147483647) {
            $errors['stock'] = 'Enter a valid stock number.';
        }
        $parsed_delivery_date = DateTimeImmutable::createFromFormat('!Y-m-d', $delivery_date);
        if (!$parsed_delivery_date || $parsed_delivery_date->format('Y-m-d') !== $delivery_date) {
            $errors['delivery_date'] = 'Select a valid delivery date.';
        }

        $old_delivery = null;
        if ($delivery_id && $delivery_id > 0) {
            $old_delivery_stmt = $conn->prepare(
                "SELECT driver_id, delivery_date
                 FROM delivery
                 WHERE delivery_id = ? AND status != 'Delivered'
                   AND completion_review_status <> 'pending_review'
                 LIMIT 1"
            );
            if (!$old_delivery_stmt) {
                throw new RuntimeException('Unable to prepare delivery update lookup: ' . $conn->error);
            }
            $old_delivery_stmt->bind_param('i', $delivery_id);
            if (!$old_delivery_stmt->execute()) {
                throw new RuntimeException('Unable to load delivery before update: ' . $old_delivery_stmt->error);
            }
            $old_delivery = $old_delivery_stmt->get_result()->fetch_assoc();
            $old_delivery_stmt->close();
            if (!$old_delivery) {
                $errors['delivery_id'] = 'The delivery could not be found, is already completed, or is awaiting review.';
            }
        }

        $driver_changed = $old_delivery && (int) ($old_delivery['driver_id'] ?? 0) !== (int) $driver_id;
        $date_changed = $old_delivery && (string) ($old_delivery['delivery_date'] ?? '') !== $delivery_date;
        if ($driver_id && $driver_id > 0 && $driver_changed && !isDriverPresentToday($conn, $driver_id)) {
            $errors['driver_id'] = "A new driver must be timed in and present today.";
        }
        if (
            $driver_id && $driver_id > 0
            && empty($errors['delivery_date'])
            && ($driver_changed || $date_changed)
            && userHasLeaveOnDate($conn, $driver_id, $delivery_date)
        ) {
            $errors['driver_id'] = 'The selected driver has recorded leave on the delivery date.';
        }

        if ($delivery_id && $delivery_id > 0 && empty($errors)) {
            $stmt = $conn->prepare(
                "UPDATE delivery
                 SET route = ?, pieces = ?, stock = ?, driver_id = ?, delivery_date = ?,
                    assigned_by = ?, completion_review_status = 'none',
                    completion_review_note = NULL, completion_reviewed_by = NULL,
                    completion_reviewed_at = NULL
                 WHERE delivery_id = ? AND status != 'Delivered'
                   AND completion_review_status <> 'pending_review'"
            );
            if (!$stmt) {
                throw new RuntimeException('Unable to prepare delivery update: ' . $conn->error);
            }
            $stmt->bind_param(
                'siiisii',
                $route,
                $pieces_value,
                $stock_value,
                $driver_id,
                $delivery_date,
                $current_user_id,
                $delivery_id
            );

            if (!$conn->begin_transaction()) {
                $stmt->close();
                throw new RuntimeException('Unable to start delivery update transaction: ' . $conn->error);
            }
            if ($stmt->execute()) {
                $actor_name = trim((string) ($_SESSION['name'] ?? ''));
                if ($actor_name === '') {
                    $actor_name = 'User #' . $current_user_id;
                }
                $previous_driver_id = (int) ($old_delivery['driver_id'] ?? 0);
                try {
                    if ($previous_driver_id !== (int) $driver_id) {
                        recordDeliveryActivity(
                            $conn,
                            (int) $delivery_id,
                            $current_user_id,
                            $actor_name,
                            'driver_reassigned',
                            $previous_driver_id > 0 ? $previous_driver_id : null,
                            $previous_driver_id > 0 ? getDeliveryDriverName($conn, $previous_driver_id) : null,
                            (int) $driver_id,
                            getDeliveryDriverName($conn, (int) $driver_id)
                        );
                        if ($previous_driver_id > 0) {
                            createTaskNotification(
                                $conn,
                                $previous_driver_id,
                                (int) $current_user_id,
                                'delivery_reassigned',
                                'Delivery reassigned',
                                'A delivery previously assigned to you has been reassigned to another driver.',
                                'logistic.php',
                                'delivery',
                                (string) $delivery_id
                            );
                        }
                        createTaskNotification(
                            $conn,
                            (int) $driver_id,
                            (int) $current_user_id,
                            'delivery_reassigned',
                            'Delivery assigned to you',
                            'A delivery to ' . $route . ' is assigned to you for ' . date('M j, Y', strtotime($delivery_date)) . '.',
                            'logistic.php',
                            'delivery',
                            (string) $delivery_id
                        );
                    }
                    recordDeliveryActivity($conn, (int) $delivery_id, $current_user_id, $actor_name, 'delivery_updated');
                    if (!$conn->commit()) {
                        throw new RuntimeException('Unable to commit the delivery update.');
                    }
                    $message = "Delivery record updated successfully.";
                } catch (RuntimeException $exception) {
                    $conn->rollback();
                    $errors['delivery_id'] = "The delivery update and activity history could not be saved: " . $exception->getMessage();
                }
            } else {
                $conn->rollback();
                $errors['delivery_id'] = "Unable to update the delivery record.";
            }
            $stmt->close();
        } elseif (empty($errors)) {
            $errors['delivery_id'] = 'The delivery could not be updated.';
        }
    }

    // --- CONFIRM DELIVERY ---
    if (($_POST['mark_delivered'] ?? '') === '1' && $current_role === 'log') {
        $delivery_id = filter_var($_POST['idno'] ?? '', FILTER_VALIDATE_INT);
        $current_user_id = (int) ($_SESSION['id'] ?? $_SESSION['user_id'] ?? 0);

        if ($delivery_id && $current_user_id > 0) {
            $delivery_check = $conn->prepare(
                "SELECT delivery_proof_image
                 FROM delivery
                 WHERE delivery_id = ? AND driver_id = ?
                   AND status <> 'Delivered'
                   AND completion_review_status <> 'pending_review'
                 LIMIT 1"
            );
            if (!$delivery_check) {
                throw new RuntimeException('Unable to prepare delivery ownership check: ' . $conn->error);
            }
            $delivery_check->bind_param('ii', $delivery_id, $current_user_id);
            if (!$delivery_check->execute()) {
                $error = $delivery_check->error;
                $delivery_check->close();
                throw new RuntimeException('Unable to verify delivery assignment: ' . $error);
            }
            $eligible_delivery = $delivery_check->get_result()->fetch_assoc();
            $delivery_check->close();

            if (!$eligible_delivery) {
                $_SESSION['delivery_error'] = 'This delivery is unavailable, already completed, or assigned to another driver.';
            } else {
                $upload = $_FILES['delivery_proof'] ?? null;
                $proof_error = '';
                $proof_filename = null;
                $proof_path = null;
                if (
                    !is_array($upload)
                    || !isset($upload['error'], $upload['tmp_name'], $upload['size'])
                    || !is_string($upload['tmp_name'])
                    || !is_numeric($upload['error'])
                    || !is_numeric($upload['size'])
                ) {
                    $proof_error = 'Choose a delivery proof photo before submitting.';
                } elseif ((int) $upload['error'] !== UPLOAD_ERR_OK) {
                    $proof_error = match ((int) $upload['error']) {
                        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The delivery proof photo must be no larger than 5 MB.',
                        UPLOAD_ERR_NO_FILE => 'Choose a delivery proof photo before submitting.',
                        default => 'The delivery proof photo could not be uploaded. Please try again.',
                    };
                } elseif ((int) $upload['size'] < 1 || (int) $upload['size'] > 5 * 1024 * 1024) {
                    $proof_error = 'The delivery proof photo must be no larger than 5 MB.';
                } else {
                    $file_info = new finfo(FILEINFO_MIME_TYPE);
                    $mime_type = $file_info->file($upload['tmp_name']);
                    $image_info = @getimagesize($upload['tmp_name']);
                    $allowed_image_types = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                    ];
                    if (
                        !is_string($mime_type)
                        || !isset($allowed_image_types[$mime_type])
                        || !is_array($image_info)
                        || ($image_info['mime'] ?? null) !== $mime_type
                        || (int) ($image_info[0] ?? 0) < 1
                        || (int) ($image_info[1] ?? 0) < 1
                        || (int) $image_info[0] > 6000
                        || (int) $image_info[1] > 6000
                    ) {
                        $proof_error = 'Upload a valid JPG, PNG, or WebP photo no larger than 6000 × 6000 pixels.';
                    } elseif (!is_uploaded_file($upload['tmp_name'])) {
                        $proof_error = 'The delivery proof upload could not be verified. Please try again.';
                    } else {
                        $proof_directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'delivery-proofs';
                        if (!is_dir($proof_directory) && !mkdir($proof_directory, 0755, true) && !is_dir($proof_directory)) {
                            throw new RuntimeException('Unable to create the delivery proof upload directory.');
                        }
                        $proof_filename = bin2hex(random_bytes(16)) . '.' . $allowed_image_types[$mime_type];
                        $proof_path = $proof_directory . DIRECTORY_SEPARATOR . $proof_filename;
                        if (!move_uploaded_file($upload['tmp_name'], $proof_path)) {
                            throw new RuntimeException('Unable to save the delivery proof photo.');
                        }
                    }
                }

                if ($proof_error !== '') {
                    $_SESSION['delivery_error'] = $proof_error;
                } else {
                    $stmt = $conn->prepare(
                        "UPDATE delivery
                         SET status = 'Awaiting Approval', delivered_at = NOW(),
                             completion_review_status = 'pending_review',
                             completion_review_note = NULL, completion_reviewed_by = NULL,
                             completion_reviewed_at = NULL, delivery_proof_image = ?
                         WHERE delivery_id = ? AND driver_id = ?
                           AND status <> 'Delivered'
                           AND completion_review_status <> 'pending_review'"
                    );
                    if (!$stmt) {
                        if ($proof_path !== null && is_file($proof_path)) {
                            unlink($proof_path);
                        }
                        throw new RuntimeException('Unable to prepare delivery completion: ' . $conn->error);
                    }
                    $stmt->bind_param('sii', $proof_filename, $delivery_id, $current_user_id);
                    if (!$conn->begin_transaction()) {
                        $stmt->close();
                        if ($proof_path !== null && is_file($proof_path)) {
                            unlink($proof_path);
                        }
                        throw new RuntimeException('Unable to start delivery completion: ' . $conn->error);
                    }
                    if (!$stmt->execute()) {
                        $error = $stmt->error;
                        $conn->rollback();
                        $stmt->close();
                        if ($proof_path !== null && is_file($proof_path)) {
                            unlink($proof_path);
                        }
                        throw new RuntimeException('Unable to save delivery completion: ' . $error);
                    }
                    if ($stmt->affected_rows === 1) {
                        $actor_name = trim((string) ($_SESSION['name'] ?? ''));
                        if ($actor_name === '') {
                            $actor_name = 'User #' . $current_user_id;
                        }
                        try {
                            recordDeliveryActivity($conn, $delivery_id, $current_user_id, $actor_name, 'delivery_submitted_for_review');
                            $assignerStmt = $conn->prepare('SELECT assigned_by FROM delivery WHERE delivery_id = ?');
                            if (!$assignerStmt) {
                                throw new RuntimeException('Unable to prepare delivery assigner lookup: ' . $conn->error);
                            }
                            $assignerStmt->bind_param('i', $delivery_id);
                            if (!$assignerStmt->execute()) {
                                $error = $assignerStmt->error;
                                $assignerStmt->close();
                                throw new RuntimeException('Unable to load delivery assigner: ' . $error);
                            }
                            $assignerId = (int) ($assignerStmt->get_result()->fetch_assoc()['assigned_by'] ?? 0);
                            $assignerStmt->close();
                            if ($assignerId > 0) {
                                createTaskNotification(
                                    $conn,
                                    $assignerId,
                                    $current_user_id,
                                    'delivery_review_requested',
                                    'Delivery submitted for review',
                                    $actor_name . ' submitted the delivery to ' . $delivery_id . ' for your approval.',
                                    'supervisor.php#page-dashboard',
                                    'delivery',
                                    (string) $delivery_id
                                );
                            } else {
                                $supervisor_stmt = $conn->prepare("SELECT id FROM users WHERE role = 'super' AND is_active = 1");
                                if (!$supervisor_stmt) {
                                    throw new RuntimeException('Unable to prepare supervisor review notifications: ' . $conn->error);
                                }
                                if (!$supervisor_stmt->execute()) {
                                    $error = $supervisor_stmt->error;
                                    $supervisor_stmt->close();
                                    throw new RuntimeException('Unable to load supervisors for delivery review: ' . $error);
                                }
                                $supervisor_result = $supervisor_stmt->get_result();
                                while ($supervisor_row = $supervisor_result->fetch_assoc()) {
                                    createTaskNotification(
                                        $conn,
                                        (int) $supervisor_row['id'],
                                        $current_user_id,
                                        'delivery_review_requested',
                                        'Delivery submitted for review',
                                        $actor_name . ' submitted delivery #' . $delivery_id . ' for supervisor approval.',
                                        'supervisor.php#page-dashboard',
                                        'delivery',
                                        (string) $delivery_id
                                    );
                                }
                                $supervisor_stmt->close();
                            }
                            if (!$conn->commit()) {
                                throw new RuntimeException('Unable to commit delivery completion.');
                            }
                            $previous_proof = (string) ($eligible_delivery['delivery_proof_image'] ?? '');
                            if ($previous_proof !== '' && preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $previous_proof)) {
                                $previous_path = $proof_directory . DIRECTORY_SEPARATOR . $previous_proof;
                                if (is_file($previous_path) && !unlink($previous_path)) {
                                    error_log('TaskTrack could not remove a replaced delivery proof photo.');
                                }
                            }
                            $_SESSION['delivery_success'] = 'Delivery submitted for supervisor approval.';
                        } catch (RuntimeException $exception) {
                            $conn->rollback();
                            if ($proof_path !== null && is_file($proof_path)) {
                                unlink($proof_path);
                            }
                            $_SESSION['delivery_error'] = 'Delivery completion could not be saved with its activity history: ' . $exception->getMessage();
                        }
                    } else {
                        $conn->rollback();
                        if ($proof_path !== null && is_file($proof_path)) {
                            unlink($proof_path);
                        }
                        $_SESSION['delivery_error'] = 'This delivery is unavailable, already completed, or assigned to another driver.';
                    }
                    $stmt->close();
                }
            }
        }
    }

    // -----------------------------------------------------------------
    // ACCOUNT-LEVEL DATA ISOLATION (FETCH PENDING & HISTORY DELIVERIES)
    // -----------------------------------------------------------------
    
    // Kunin ang Role at User ID mula sa active session
    $current_role = $_SESSION['role'] ?? '';
    $current_user_id = $_SESSION['id'] ?? $_SESSION['user_id'] ?? null;

    // --- FETCH PENDING DELIVERIES ---
    if ($current_role === 'super') {
        // PAG SUPERVISOR: Nakikita lahat ng delivery ng kahit sinong driver
        $sql_pending = "SELECT d.delivery_id, d.production_id, d.route, d.pieces, d.stock, d.driver_id, d.assigned_by, d.delivery_date, d.status, d.delivered_at, d.delivery_proof_image,
                               d.completion_review_status, d.completion_review_note, d.completion_reviewed_by, d.completion_reviewed_at,
                               assigner.name AS assigned_by_name, reviewer.name AS completion_reviewed_by_name,
                               p.product_name, p.product_status,
                               u.name AS driver_name
                        FROM delivery d 
                        LEFT JOIN production p ON d.production_id = p.production_id 
                        LEFT JOIN users u ON d.driver_id = u.id
                        LEFT JOIN users assigner ON d.assigned_by = assigner.id
                        LEFT JOIN users reviewer ON d.completion_reviewed_by = reviewer.id
                        WHERE d.status != 'Delivered' OR d.status IS NULL
                        GROUP BY d.delivery_id
                        ORDER BY d.delivery_id DESC";
        $stmt_pending = $conn->prepare($sql_pending);
    } else {
        // PAG DRIVER / LOGISTICS ('log'): ACCOUNT-LEVEL FILTERING
        // I-filter gamit ang d.driver_id = ? para KANYANG ACCOUNT LANG ANG LUMABAS
        $sql_pending = "SELECT d.delivery_id, d.production_id, d.route, d.pieces, d.stock, d.driver_id, d.assigned_by, d.delivery_date, d.status, d.delivered_at, d.delivery_proof_image,
                               d.completion_review_status, d.completion_review_note, d.completion_reviewed_by, d.completion_reviewed_at,
                               assigner.name AS assigned_by_name, reviewer.name AS completion_reviewed_by_name,
                               p.product_name, p.product_status,
                               u.name AS driver_name
                        FROM delivery d 
                        LEFT JOIN production p ON d.production_id = p.production_id 
                        LEFT JOIN users u ON d.driver_id = u.id
                        LEFT JOIN users assigner ON d.assigned_by = assigner.id
                        LEFT JOIN users reviewer ON d.completion_reviewed_by = reviewer.id
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
        $sql_history = "SELECT d.delivery_id, d.production_id, d.route, d.pieces, d.stock, d.driver_id, d.assigned_by, d.delivery_date, d.status, d.delivered_at, d.delivery_proof_image,
                               d.completion_review_status, d.completion_review_note, d.completion_reviewed_by, d.completion_reviewed_at,
                               assigner.name AS assigned_by_name, reviewer.name AS completion_reviewed_by_name,
                               p.product_name, p.product_status,
                               u.name AS driver_name 
                        FROM delivery d 
                        LEFT JOIN production p ON d.production_id = p.production_id 
                        LEFT JOIN users u ON d.driver_id = u.id
                        LEFT JOIN users assigner ON d.assigned_by = assigner.id
                        LEFT JOIN users reviewer ON d.completion_reviewed_by = reviewer.id
                        WHERE d.status = 'Delivered' 
                        GROUP BY d.delivery_id
                        ORDER BY d.delivery_id DESC";
        $stmt_history = $conn->prepare($sql_history);
    } else {
        // PAG DRIVER / LOGISTICS ('log'): ACCOUNT-LEVEL FILTERING
        $sql_history = "SELECT d.delivery_id, d.production_id, d.route, d.pieces, d.stock, d.driver_id, d.assigned_by, d.delivery_date, d.status, d.delivered_at, d.delivery_proof_image,
                               d.completion_review_status, d.completion_review_note, d.completion_reviewed_by, d.completion_reviewed_at,
                               assigner.name AS assigned_by_name, reviewer.name AS completion_reviewed_by_name,
                               p.product_name, p.product_status,
                               u.name AS driver_name 
                        FROM delivery d 
                        LEFT JOIN production p ON d.production_id = p.production_id 
                        LEFT JOIN users u ON d.driver_id = u.id
                        LEFT JOIN users assigner ON d.assigned_by = assigner.id
                        LEFT JOIN users reviewer ON d.completion_reviewed_by = reviewer.id
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
                   WHERE LOWER(TRIM(COALESCE(p.product_status, ''))) = 'product done'
                     AND d.delivery_id IS NULL";

    $prod_result = mysqli_query($conn, $prod_query);
    if ($prod_result) {
        $production_items = mysqli_fetch_all($prod_result, MYSQLI_ASSOC);
    }

    $delivery_report_period = $_GET['report_period'] ?? 'month';
    $delivery_report_error = '';
    if (!in_array($delivery_report_period, ['month', 'year'], true)) {
        $delivery_report_period = 'month';
        $delivery_report_error = 'Choose a valid reporting period.';
    }
    $delivery_report_month = $_GET['report_month'] ?? date('Y-m');
    $valid_report_month = is_string($delivery_report_month)
        && preg_match('/^\d{4}-\d{2}$/', $delivery_report_month)
        && ($report_month_date = DateTimeImmutable::createFromFormat('!Y-m', $delivery_report_month))
        && $report_month_date->format('Y-m') === $delivery_report_month;
    if (!$valid_report_month) {
        if ($delivery_report_period === 'month') {
            $delivery_report_error = 'Choose a valid month for the delivery report.';
        }
        $delivery_report_month = date('Y-m');
        $report_month_date = DateTimeImmutable::createFromFormat('!Y-m', $delivery_report_month);
    }
    $delivery_report_year = $_GET['report_year'] ?? date('Y');
    $valid_report_year = is_string($delivery_report_year)
        && preg_match('/^\d{4}$/', $delivery_report_year)
        && (int) $delivery_report_year >= 1000
        && ($report_year_date = DateTimeImmutable::createFromFormat('!Y', $delivery_report_year))
        && $report_year_date->format('Y') === $delivery_report_year;
    if (!$valid_report_year) {
        if ($delivery_report_period === 'year') {
            $delivery_report_error = 'Choose a valid year for the delivery report.';
        }
        $delivery_report_year = date('Y');
        $report_year_date = DateTimeImmutable::createFromFormat('!Y', $delivery_report_year);
    }
    if ($delivery_report_period === 'year') {
        $delivery_report_start = $report_year_date->format('Y-01-01');
        $delivery_report_end = $report_year_date->format('Y-12-31');
        $delivery_report_label = $report_year_date->format('Y');
    } else {
        $delivery_report_start = $report_month_date->format('Y-m-01');
        $delivery_report_end = $report_month_date->modify('last day of this month')->format('Y-m-d');
        $delivery_report_label = $report_month_date->format('F Y');
    }
    $delivery_report = ['total' => 0, 'completed' => 0, 'pending' => 0, 'overdue' => 0];
    $delivery_report_scope = $current_role === 'log' ? ' AND driver_id = ?' : '';
    $delivery_report_sql = "SELECT COUNT(*) AS total_count,
                                   SUM(CASE WHEN status = 'Delivered' THEN 1 ELSE 0 END) AS completed_count,
                                   SUM(CASE WHEN (status != 'Delivered' OR status IS NULL)
                                             AND (status IS NULL OR LOWER(status) NOT IN ('cancelled', 'canceled'))
                                            THEN 1 ELSE 0 END) AS pending_count,
                                   SUM(CASE WHEN (status != 'Delivered' OR status IS NULL)
                                             AND (status IS NULL OR LOWER(status) NOT IN ('cancelled', 'canceled'))
                                             AND delivery_date < CURDATE()
                                            THEN 1 ELSE 0 END) AS overdue_count
                            FROM delivery
                            WHERE delivery_date BETWEEN ? AND ?$delivery_report_scope";
    $delivery_report_stmt = $conn->prepare($delivery_report_sql);
    if (!$delivery_report_stmt) {
        throw new RuntimeException('Unable to prepare monthly delivery report: ' . $conn->error);
    }
    if ($current_role === 'log') {
        $delivery_report_stmt->bind_param('ssi', $delivery_report_start, $delivery_report_end, $current_user_id);
    } else {
        $delivery_report_stmt->bind_param('ss', $delivery_report_start, $delivery_report_end);
    }
    if (!$delivery_report_stmt->execute()) {
        throw new RuntimeException('Unable to load monthly delivery report: ' . $delivery_report_stmt->error);
    }
    $delivery_report_row = $delivery_report_stmt->get_result()->fetch_assoc();
    if ($delivery_report_row) {
        $delivery_report = [
            'total' => (int) $delivery_report_row['total_count'],
            'completed' => (int) $delivery_report_row['completed_count'],
            'pending' => (int) $delivery_report_row['pending_count'],
            'overdue' => (int) $delivery_report_row['overdue_count'],
        ];
    }
    $delivery_report_stmt->close();
}

// -----------------------------------------------------
// 3. Module: FACTORY (PRODUCTION)
// -----------------------------------------------------
elseif ($module === 'factory') {

    require_once __DIR__ . '/production_assignment_schema.php';
    ensureProductionAssignmentSchema($conn);
    ensureProductionActivitySchema($conn);

    $current_role = (string) ($_SESSION['role'] ?? '');
    $current_user_id = (int) ($_SESSION['id'] ?? $_SESSION['user_id'] ?? 0);
    $is_production_worker = in_array($current_role, ['pro', 'cooking', 'packaging'], true);
    $is_production_supervisor = $current_role === 'super';
    $current_work_type = [
        'cooking' => 'Cooking',
        'packaging' => 'Packaging',
    ][$current_role] ?? null;
    $current_employee_id = null;

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['review_production_stage'])) {
        $posted_token = (string) ($_POST['task_review_csrf'] ?? '');
        $production_id = trim((string) ($_POST['production_id'] ?? ''));
        $work_type = (string) ($_POST['work_type'] ?? '');
        $decision = (string) $_POST['review_production_stage'];
        $review_note = trim((string) ($_POST['review_note'] ?? ''));

        if (!$is_production_supervisor) {
            http_response_code(403);
            exit('Only a supervisor can review production completion.');
        }
        if (!hash_equals((string) $_SESSION['task_review_csrf'], $posted_token)) {
            $_SESSION['production_error'] = 'Your session token expired. Refresh the page and try again.';
        } elseif ($production_id === '' || strlen($production_id) > 64
            || !in_array($work_type, ['Cooking', 'Packaging'], true)
            || !in_array($decision, ['approve', 'reject'], true)) {
            $_SESSION['production_error'] = 'Choose a valid production stage and review decision.';
        } elseif ($decision === 'reject' && $review_note === '') {
            $_SESSION['production_error'] = 'Add an explanation when requesting production changes.';
        } elseif (mb_strlen($review_note) > 1000) {
            $_SESSION['production_error'] = 'The review note must be no more than 1000 characters.';
        } else {
            if (!$conn->begin_transaction()) {
                throw new RuntimeException('Unable to start the production review: ' . $conn->error);
            }
            $review_stmt = $conn->prepare(
                "SELECT pa.employee_id, pa.assigned_by, p.product_name
                 FROM production_assignments pa
                 INNER JOIN production p ON p.production_id = pa.production_id
                 WHERE pa.production_id = ? AND pa.work_type = ?
                   AND pa.work_status = 'Pending'
                   AND pa.completion_review_status = 'pending_review'
                   AND (pa.assigned_by = ? OR pa.assigned_by IS NULL)
                 LIMIT 1 FOR UPDATE"
            );
            if (!$review_stmt) {
                throw new RuntimeException('Unable to prepare the production review lookup: ' . $conn->error);
            }
            $review_stmt->bind_param('ssi', $production_id, $work_type, $current_user_id);
            if (!$review_stmt->execute()) {
                $error = $review_stmt->error;
                $review_stmt->close();
                throw new RuntimeException('Unable to load the production stage for review: ' . $error);
            }
            $stage_to_review = $review_stmt->get_result()->fetch_assoc();
            $review_stmt->close();

            if (!$stage_to_review) {
                $conn->rollback();
                $_SESSION['production_error'] = 'This production stage is no longer waiting for your review.';
            } else {
                $approved = $decision === 'approve';
                $review_status = $approved ? 'approved' : 'rejected';
                $review_note_value = $review_note !== '' ? $review_note : null;
                $update_stmt = $conn->prepare(
                    "UPDATE production_assignments
                     SET work_status = IF(? = 1, 'Done', 'Pending'),
                         completed_at = IF(? = 1, NOW(), NULL),
                         completion_review_status = ?, completion_review_note = ?,
                         completion_reviewed_by = ?, completion_reviewed_at = NOW()
                     WHERE production_id = ? AND work_type = ?
                       AND (assigned_by = ? OR assigned_by IS NULL)
                       AND work_status = 'Pending'
                       AND completion_review_status = 'pending_review'"
                );
                if (!$update_stmt) {
                    throw new RuntimeException('Unable to prepare the production review update: ' . $conn->error);
                }
                $update_stmt->bind_param(
                    'iississi',
                    $approved,
                    $approved,
                    $review_status,
                    $review_note_value,
                    $current_user_id,
                    $production_id,
                    $work_type,
                    $current_user_id
                );
                if (!$update_stmt->execute() || $update_stmt->affected_rows !== 1) {
                    $error = $update_stmt->error ?: 'The production review could not be saved.';
                    $update_stmt->close();
                    $conn->rollback();
                    throw new RuntimeException('Unable to save the production review: ' . $error);
                }
                $update_stmt->close();

                if ($approved && $work_type === 'Packaging') {
                    $production_stmt = $conn->prepare(
                        "UPDATE production SET product_status = 'product done'
                         WHERE production_id = ?
                           AND LOWER(TRIM(COALESCE(product_status, ''))) <> 'product done'"
                    );
                    if (!$production_stmt) {
                        throw new RuntimeException('Unable to prepare final production approval: ' . $conn->error);
                    }
                    $production_stmt->bind_param('s', $production_id);
                    if (!$production_stmt->execute() || $production_stmt->affected_rows !== 1) {
                        $error = $production_stmt->error ?: 'The final production status could not be updated.';
                        $production_stmt->close();
                        $conn->rollback();
                        throw new RuntimeException('Unable to complete the production item: ' . $error);
                    }
                    $production_stmt->close();
                }

                $actor_name = (string) ($_SESSION['name'] ?? 'Supervisor');
                $stage_employee_id = (int) $stage_to_review['employee_id'];
                recordProductionActivity(
                    $conn,
                    $production_id,
                    $current_user_id,
                    $actor_name,
                    $approved ? 'stage_approved' : 'stage_changes_requested',
                    $work_type,
                    null,
                    null,
                    $stage_employee_id,
                    getProductionEmployeeName($conn, $stage_employee_id)
                );
                $employee_user_stmt = $conn->prepare('SELECT id FROM users WHERE employee_id = ?');
                if (!$employee_user_stmt) {
                    throw new RuntimeException('Unable to prepare the production employee notification lookup: ' . $conn->error);
                }
                $employee_user_stmt->bind_param('i', $stage_employee_id);
                if (!$employee_user_stmt->execute()) {
                    $error = $employee_user_stmt->error;
                    $employee_user_stmt->close();
                    throw new RuntimeException('Unable to load the production employee notification recipient: ' . $error);
                }
                $employee_users = $employee_user_stmt->get_result();
                while ($employee_user = $employee_users->fetch_assoc()) {
                    createTaskNotification(
                        $conn,
                        (int) $employee_user['id'],
                        $current_user_id,
                        $approved ? 'production_stage_approved' : 'production_stage_changes_requested',
                        $approved ? 'Production stage approved' : 'Production changes requested',
                        $approved
                            ? 'Your ' . $work_type . ' stage for ' . $stage_to_review['product_name'] . ' was approved.'
                            : 'Your ' . $work_type . ' stage for ' . $stage_to_review['product_name'] . ' needs changes: ' . $review_note,
                        'Production.php',
                        'production',
                        $production_id
                    );
                }
                $employee_user_stmt->close();
                if (!$conn->commit()) {
                    throw new RuntimeException('Unable to commit the production review: ' . $conn->error);
                }
                $_SESSION['production_success'] = $approved
                    ? 'Production stage approved.'
                    : 'Production changes requested and sent to the employee.';
            }
        }
        header('Location: factory_main.php');
        exit();
    }

    if ($is_production_worker) {
        $current_user_id = (int) ($_SESSION['id'] ?? $_SESSION['user_id'] ?? 0);
        $employee_stmt = $conn->prepare(
            "SELECT e.employee_id
             FROM users u
             INNER JOIN employee e
                 ON (e.username = u.name
                     OR (e.email IS NOT NULL AND e.email = u.email)
                     OR EXISTS (
                         SELECT 1
                         FROM attendance employee_user_link
                         WHERE employee_user_link.employee_id = e.employee_id
                           AND employee_user_link.user_id = u.id
                     ))
             WHERE u.id = ?
               AND u.role IN ('pro', 'cooking', 'packaging')
             ORDER BY (e.email = u.email) DESC, e.employee_id ASC
             LIMIT 1"
        );
        $employee_stmt->bind_param("i", $current_user_id);
        $employee_stmt->execute();
        $employee_result = $employee_stmt->get_result();
        if ($employee_row = $employee_result->fetch_assoc()) {
            $current_employee_id = (int) $employee_row['employee_id'];
        }
        $employee_stmt->close();
    }

    $present_production_employees = [];
    $present_employees_sql = "SELECT DISTINCT e.employee_id, e.username, e.position, u.role AS production_role
                              FROM employee e
                              INNER JOIN users u
                                  ON u.role IN ('cooking', 'packaging')
                                 AND (
                                     u.name = e.username
                                     OR (e.email IS NOT NULL AND e.email != '' AND u.email = e.email)
                                     OR EXISTS (
                                         SELECT 1
                                         FROM attendance employee_user_link
                                         WHERE employee_user_link.employee_id = e.employee_id
                                           AND employee_user_link.user_id = u.id
                                     )
                                 )
                              WHERE EXISTS (
                                  SELECT 1
                                  FROM attendance a
                                  WHERE (a.user_id = u.id OR a.employee_id = e.employee_id)
                                    AND DATE(a.attendance_date) = CURDATE()
                                    AND LOWER(TRIM(a.status)) IN ('present', 'early', 'late', 'overtime', 'undertime', 'half day')
                              )
                              ORDER BY e.position, e.username";
    $present_employees_result = mysqli_query($conn, $present_employees_sql);
    if (!$present_employees_result) {
        throw new RuntimeException("Unable to load production accounts with active attendance.");
    }
    while ($employee = mysqli_fetch_assoc($present_employees_result)) {
        $present_production_employees[] = $employee;
    }

    $is_present_production_employee = static function (int $employee_id, string $work_type) use ($conn): bool {
        $required_role = $work_type === 'Cooking' ? 'cooking' : 'packaging';
        $check_employee = $conn->prepare(
            "SELECT e.employee_id
             FROM employee e
             INNER JOIN users u
                 ON (u.name = e.username
                     OR (e.email IS NOT NULL AND e.email != '' AND u.email = e.email)
                     OR EXISTS (
                         SELECT 1
                         FROM attendance employee_user_link
                         WHERE employee_user_link.employee_id = e.employee_id
                           AND employee_user_link.user_id = u.id
                     ))
             WHERE e.employee_id = ?
               AND u.role = ?
               AND EXISTS (
                   SELECT 1
                   FROM attendance a
                   WHERE (a.user_id = u.id OR a.employee_id = e.employee_id)
                     AND DATE(a.attendance_date) = CURDATE()
                     AND LOWER(TRIM(a.status)) IN ('present', 'early', 'late', 'overtime', 'undertime', 'half day')
               )
             LIMIT 1"
        );
        $check_employee->bind_param("is", $employee_id, $required_role);
        $check_employee->execute();
        $is_eligible = ($check_employee->get_result()->num_rows > 0);
        $check_employee->close();

        return $is_eligible;
    };

    // --- REASSIGN PRODUCTION TASK ---
    if ($is_production_supervisor && isset($_POST['submit']) && $action === 'reassign') {
        $production_id = trim($_POST['production_id'] ?? '');
        $employee_id = filter_var($_POST['employee_id'] ?? '', FILTER_VALIDATE_INT);
        $work_type = trim($_POST['work_type'] ?? '');

        if ($production_id === '') {
            $errors['assignment'] = "Please select a production task.";
        }
        if (!$employee_id || !in_array($work_type, ['Cooking', 'Packaging'], true)) {
            $errors['assignment_target'] = "Please select a present employee for the selected work area.";
        } elseif (!$is_present_production_employee((int) $employee_id, $work_type)) {
            $errors['assignment_target'] = "Select a present account whose role matches the selected work area.";
        }

        if (empty($errors)) {
            $check_task = $conn->prepare(
                "SELECT p.production_id, p.due_date
                 FROM production p
                 WHERE p.production_id = ?
                   AND (p.product_status != 'product done' OR p.product_status IS NULL)
                 LIMIT 1"
            );
            $check_task->bind_param("s", $production_id);
            $check_task->execute();
            $task_row = $check_task->get_result()->fetch_assoc();
            $task_is_pending = $task_row !== null;
            $check_task->close();

            if (!$task_is_pending) {
                $errors['assignment'] = "The selected task is unavailable or already completed.";
            } elseif (!empty($task_row['due_date']) && employeeHasLeaveOnDate($conn, (int) $employee_id, (string) $task_row['due_date'])) {
                $errors['assignment_target'] = "The selected employee has recorded leave on the production due date.";
            }
        }

        if (empty($errors) && $work_type === 'Packaging') {
            $cooking_check = $conn->prepare(
                "SELECT work_status
                 FROM production_assignments
                 WHERE production_id = ? AND work_type = 'Cooking'
                 LIMIT 1"
            );
            $cooking_check->bind_param("s", $production_id);
            $cooking_check->execute();
            $cooking_assignment = $cooking_check->get_result()->fetch_assoc();
            $cooking_check->close();

            if (!$cooking_assignment || $cooking_assignment['work_status'] !== 'Done') {
                $errors['assignment'] = "Packaging can only be assigned after Cooking is marked done.";
            }
        }

        if (empty($errors)) {
            $opposite_work_type = $work_type === 'Cooking' ? 'Packaging' : 'Cooking';
            $opposite_assignment = $conn->prepare(
                "SELECT employee_id
                 FROM production_assignments
                 WHERE production_id = ? AND work_type = ?
                 LIMIT 1"
            );
            $opposite_assignment->bind_param("ss", $production_id, $opposite_work_type);
            $opposite_assignment->execute();
            $opposite_row = $opposite_assignment->get_result()->fetch_assoc();
            $opposite_assignment->close();

            if ($opposite_row && (int) $opposite_row['employee_id'] === (int) $employee_id) {
                $errors['assignment_target'] = "Cooking and Packaging must be assigned to different employees.";
            }
        }

        if (empty($errors)) {
            $check_stage = $conn->prepare(
                "SELECT work_status, employee_id, completion_review_status
                 FROM production_assignments
                 WHERE production_id = ? AND work_type = ?
                 LIMIT 1"
            );
            $check_stage->bind_param("ss", $production_id, $work_type);
            $check_stage->execute();
            $stage_row = $check_stage->get_result()->fetch_assoc();
            $check_stage->close();

            if ($stage_row && $stage_row['work_status'] === 'Done') {
                $errors['assignment'] = "A completed work area cannot be reassigned.";
            } elseif ($stage_row && $stage_row['completion_review_status'] === 'pending_review') {
                $errors['assignment'] = "A work area awaiting supervisor approval cannot be reassigned.";
            }
        }

        if (empty($errors)) {
            $assigned_by = (int) ($_SESSION['id'] ?? $_SESSION['user_id'] ?? 0);
            $actor_name = trim((string) ($_SESSION['name'] ?? ''));
            if ($actor_name === '') {
                $actor_name = 'User #' . $assigned_by;
            }
            $stmt = $conn->prepare(
                "INSERT INTO production_assignments (production_id, employee_id, work_type, assigned_by)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    employee_id = VALUES(employee_id),
                    assigned_by = VALUES(assigned_by),
                    assigned_at = CURRENT_TIMESTAMP,
                    completion_review_status = 'none',
                    completion_review_note = NULL,
                    completion_reviewed_by = NULL,
                    completion_reviewed_at = NULL,
                    completed_at = NULL"
            );
            $stmt->bind_param("sisi", $production_id, $employee_id, $work_type, $assigned_by);
            $conn->begin_transaction();
            if ($stmt->execute()) {
                $previous_employee_id = (int) ($stage_row['employee_id'] ?? 0);
                try {
                    if (!$stage_row || $previous_employee_id !== (int) $employee_id) {
                        recordProductionActivity(
                            $conn,
                            $production_id,
                            $assigned_by,
                            $actor_name,
                            $stage_row ? 'stage_reassigned' : 'stage_assigned',
                            $work_type,
                            $previous_employee_id > 0 ? $previous_employee_id : null,
                            $previous_employee_id > 0 ? getProductionEmployeeName($conn, $previous_employee_id) : null,
                            (int) $employee_id,
                            getProductionEmployeeName($conn, (int) $employee_id)
                        );
                        if ($previous_employee_id > 0) {
                            notifyEmployeeTaskAssignee(
                                $conn,
                                $previous_employee_id,
                                $assigned_by,
                                'production_reassigned',
                                'Production stage reassigned',
                                'The ' . $work_type . ' stage for production item #' . $production_id . ' has been assigned to another employee.',
                                'Production.php',
                                'production',
                                $production_id
                            );
                        }
                        notifyEmployeeTaskAssignee(
                            $conn,
                            (int) $employee_id,
                            $assigned_by,
                            'production_assigned',
                            'Production stage assigned',
                            'The ' . $work_type . ' stage for production item #' . $production_id . ' is assigned to you.',
                            'Production.php',
                            'production',
                            $production_id
                        );
                    }
                    if (!$conn->commit()) {
                        throw new RuntimeException('The database could not commit the production assignment.');
                    }
                    $message = $stage_row
                        ? "Production stage reassigned successfully."
                        : "Production stage assigned successfully.";
                } catch (RuntimeException $exception) {
                    $conn->rollback();
                    $errors['assignment'] = "Unable to save the assignment and its activity history: " . $exception->getMessage();
                }
            } else {
                $conn->rollback();
                $errors['assignment'] = "Unable to reassign the selected production task.";
            }
            $stmt->close();
        }
    }

    // --- INSERT PRODUCTION ---
    if ($is_production_supervisor && isset($_POST['submit']) && $action === 'insert') {
        $submitted_assignments = $_POST['assignments'] ?? null;
        if (!is_array($submitted_assignments) && isset($_POST['product_name'])) {
            $submitted_assignments = [[
                'product_name' => $_POST['product_name'] ?? '',
                'cooking_employee_id' => $_POST['cooking_employee_id'] ?? '',
                'target_pcs' => $_POST['target_pcs'] ?? '',
                'Stock_number' => $_POST['Stock_number'] ?? '',
                'quantity' => $_POST['quantity'] ?? '',
                'due_date' => $_POST['due_date'] ?? '',
            ]];
        }
        $submitted_assignments = is_array($submitted_assignments) ? array_values($submitted_assignments) : [];
        $validated_assignments = [];

        if ($submitted_assignments === []) {
            $errors['assignments'] = "Add at least one production product before confirming.";
        }

        foreach ($submitted_assignments as $index => $assignment) {
            $row_number = $index + 1;
            if (!is_array($assignment)) {
                $errors['assignments'] = "Production item {$row_number} is invalid.";
                continue;
            }

            $product_name = trim((string) ($assignment['product_name'] ?? ''));
            $target_pcs = trim((string) ($assignment['target_pcs'] ?? ''));
            $due_date = trim((string) ($assignment['due_date'] ?? ''));
            $stock_number = trim((string) ($assignment['Stock_number'] ?? ''));
            $quantity = trim((string) ($assignment['quantity'] ?? ''));
            $cooking_employee_id = filter_var(
                $assignment['cooking_employee_id'] ?? '',
                FILTER_VALIDATE_INT
            );

            if (!in_array($product_name, $allowed_products, true)) {
                $errors['assignments'] = "Select a valid product for production item {$row_number}.";
            }
            if ($target_pcs === '' || !preg_match('/^[0-9]+$/', $target_pcs)) {
                $errors['assignments'] = "Enter a valid Target PCS for production item {$row_number}.";
            }
            if ($quantity === '' || !preg_match('/^[0-9]+$/', $quantity)) {
                $errors['assignments'] = "Enter a valid Quantity for production item {$row_number}.";
            }
            if (!preg_match('/^[0-9]{4}$/', $stock_number)) {
                $errors['assignments'] = "Stock Number for production item {$row_number} must be exactly 4 digits.";
            }
            $date_parts = explode('-', $due_date);
            if (
                !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date)
                || count($date_parts) !== 3
                || !checkdate((int) ($date_parts[1] ?? 0), (int) ($date_parts[2] ?? 0), (int) ($date_parts[0] ?? 0))
            ) {
                $errors['assignments'] = "Select a valid due date for production item {$row_number}.";
            }
            if (!$cooking_employee_id) {
                $errors['assignments'] = "Assign a present Cooking employee to production item {$row_number}.";
            } elseif (!$is_present_production_employee((int) $cooking_employee_id, 'Cooking')) {
                $errors['assignments'] = "Select a present Cooking employee for production item {$row_number}.";
            } elseif (
                $due_date !== ''
                && preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date)
                && employeeHasLeaveOnDate($conn, (int) $cooking_employee_id, $due_date)
            ) {
                $errors['assignments'] = "Cooking employee for production item {$row_number} has recorded leave on the due date.";
            }

            $validated_assignments[] = [
                'product_name' => $product_name,
                'target_pcs' => $target_pcs,
                'due_date' => $due_date,
                'stock_number' => $stock_number,
                'quantity' => $quantity,
                'cooking_employee_id' => (int) ($cooking_employee_id ?: 0),
            ];
        }

        if (empty($errors)) {
            $assigned_by = (int) ($_SESSION['id'] ?? $_SESSION['user_id'] ?? 0);
            $production_stmt = null;
            $assignment_stmt = null;
            $transaction_started = false;
            $work_type = 'Cooking';
            $actor_name = trim((string) ($_SESSION['name'] ?? ''));
            if ($actor_name === '') {
                $actor_name = 'User #' . $assigned_by;
            }

            try {
                $conn->begin_transaction();
                $transaction_started = true;
                $production_stmt = $conn->prepare(
                    "INSERT INTO production (product_name, target_pcs, due_date, Stock_number, quantity)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $assignment_stmt = $conn->prepare(
                    "INSERT INTO production_assignments (production_id, employee_id, work_type, assigned_by)
                     VALUES (?, ?, ?, ?)"
                );
                if (!$production_stmt || !$assignment_stmt) {
                    throw new RuntimeException('Unable to prepare production assignment statements.');
                }

                foreach ($validated_assignments as $assignment) {
                    $production_stmt->bind_param(
                        "sssss",
                        $assignment['product_name'],
                        $assignment['target_pcs'],
                        $assignment['due_date'],
                        $assignment['stock_number'],
                        $assignment['quantity']
                    );
                    if (!$production_stmt->execute()) {
                        throw new RuntimeException('Unable to create a production product.');
                    }

                    $production_id = (string) $conn->insert_id;
                    $employee_id = $assignment['cooking_employee_id'];
                    $assignment_stmt->bind_param("sisi", $production_id, $employee_id, $work_type, $assigned_by);
                    if (!$assignment_stmt->execute()) {
                        throw new RuntimeException('Unable to assign a production product to Cooking.');
                    }

                    recordProductionActivity(
                        $conn,
                        $production_id,
                        $assigned_by,
                        $actor_name,
                        'product_created',
                        'Cooking',
                        null,
                        null,
                        $employee_id,
                        getProductionEmployeeName($conn, $employee_id)
                    );
                    notifyEmployeeTaskAssignee(
                        $conn,
                        $employee_id,
                        $assigned_by,
                        'production_assigned',
                        'Production task assigned',
                        'A new Cooking task for production item #' . $production_id . ' is assigned to you.',
                        'Production.php',
                        'production',
                        $production_id
                    );
                }

                if (!$conn->commit()) {
                    throw new RuntimeException('The database could not commit the production assignments.');
                }
                $transaction_started = false;
                $message = count($validated_assignments) === 1
                    ? "New production task created and assigned to Cooking."
                    : count($validated_assignments) . " production tasks created and assigned successfully.";
            } catch (RuntimeException $exception) {
                if ($transaction_started) {
                    $conn->rollback();
                }
                $errors['assignments'] = "No production tasks were saved. " . $exception->getMessage();
            } finally {
                if ($production_stmt) {
                    $production_stmt->close();
                }
                if ($assignment_stmt) {
                    $assignment_stmt->close();
                }
            }
        }
    }

    // --- DELETE PRODUCTION ---
    $passid = $_POST['idno'] ?? null;

    if ($is_production_supervisor && isset($_POST['del']) && $passid) {
        $delete_assignment = $conn->prepare("DELETE FROM production_assignments WHERE production_id = ?");
        $delete_assignment->bind_param("s", $passid);
        $delete_assignment->execute();
        $delete_assignment->close();

        $stmt = $conn->prepare("DELETE FROM production WHERE production_id = ?");
        $stmt->bind_param("s", $passid);
        $stmt->execute();
        $stmt->close();
        $message = "Record deleted successfully.";
    }

    // --- UPDATE PRODUCTION ---
    if ($is_production_supervisor && isset($_POST['submit']) && $action === 'update') {
        $production_id = filter_var($_POST['production_id'] ?? '', FILTER_VALIDATE_INT);
        $product_name  = trim((string) ($_POST['product_name'] ?? ''));
        $target_pcs    = trim((string) ($_POST['target_pcs'] ?? ''));
        $due_date      = trim((string) ($_POST['due_date'] ?? ''));
        $stock_number  = trim((string) ($_POST['Stock_number'] ?? ''));
        $quantity      = trim((string) ($_POST['quantity'] ?? ''));

        if (!$production_id || $production_id < 1) {
            $errors['update'] = 'Choose a valid production task to update.';
        }
        if (!in_array($product_name, $allowed_products, true)) {
            $errors['update'] = 'Please select a valid product from the list.';
        }
        $target_pcs_is_integer = ctype_digit($target_pcs);
        $quantity_is_integer = ctype_digit($quantity);
        $target_pcs_value = $target_pcs_is_integer ? (int) $target_pcs : 0;
        $quantity_value = $quantity_is_integer ? (int) $quantity : 0;
        if (!$target_pcs_is_integer || $target_pcs_value < 0 || $target_pcs_value > 2147483647) {
            $errors['update'] = 'Enter a valid Target PCS.';
        }
        if (!$quantity_is_integer || $quantity_value < 0 || $quantity_value > 2147483647) {
            $errors['update'] = 'Enter a valid Quantity.';
        }
        if (!preg_match('/^[0-9]{4}$/', $stock_number)) {
            $errors['update'] = 'Stock Number must be exactly 4 digits.';
        }
        $date_parts = explode('-', $due_date);
        if (
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date)
            || count($date_parts) !== 3
            || !checkdate((int) ($date_parts[1] ?? 0), (int) ($date_parts[2] ?? 0), (int) ($date_parts[0] ?? 0))
        ) {
            $errors['update'] = 'Select a valid due date.';
        }

        if (empty($errors)) {
            $stmt = $conn->prepare(
                "UPDATE production
                 SET product_name = ?, target_pcs = ?, due_date = ?, Stock_number = ?, quantity = ?
                 WHERE production_id = ?"
            );
            if (!$stmt) {
                throw new RuntimeException('Unable to prepare production update: ' . $conn->error);
            }
            $stock_number_value = (int) $stock_number;
            $stmt->bind_param("sisiii", $product_name, $target_pcs_value, $due_date, $stock_number_value, $quantity_value, $production_id);
            if (!$conn->begin_transaction()) {
                $stmt->close();
                throw new RuntimeException('Unable to start production update transaction: ' . $conn->error);
            }
            if ($stmt->execute()) {
                $actor_user_id = (int) ($_SESSION['id'] ?? $_SESSION['user_id'] ?? 0);
                $actor_name = trim((string) ($_SESSION['name'] ?? ''));
                if ($actor_name === '') {
                    $actor_name = 'User #' . $actor_user_id;
                }
                try {
                    recordProductionActivity(
                        $conn,
                        $production_id,
                        $actor_user_id,
                        $actor_name,
                        'product_updated'
                    );
                    if (!$conn->commit()) {
                        throw new RuntimeException('The database could not commit the product update.');
                    }
                    $message = "Production Task updated successfully.";
                } catch (RuntimeException $exception) {
                    $conn->rollback();
                    $errors['update'] = "The product update and activity history could not be saved: " . $exception->getMessage();
                }
            } else {
                $conn->rollback();
                $errors['update'] = "Failed to update Production Task: " . $stmt->error;
            }
            $stmt->close();
        }
    }

    // --- UPDATE STATUS TO DONE ---
    if (isset($_POST['mark_done'])) {
        $production_id = $_POST['idno'] ?? '';
        if ($is_production_worker && $current_employee_id !== null && !empty($production_id)) {
            $conn->begin_transaction();
            $completion_error = null;
            $stage_completed = false;
            $assignment_work_type_filter = $current_work_type === null ? '' : " AND work_type = '$current_work_type'";
            $assignment_check = $conn->prepare(
                "SELECT work_type, work_status, assigned_by, completion_review_status
                 FROM production_assignments
                 WHERE production_id = ? AND employee_id = ?$assignment_work_type_filter
                 LIMIT 1
                 FOR UPDATE"
            );
            $assignment_check->bind_param("si", $production_id, $current_employee_id);
            $assignment_check->execute();
            $worker_assignment = $assignment_check->get_result()->fetch_assoc();
            $assignment_check->close();

            $can_complete_stage = $worker_assignment
                && $worker_assignment['work_status'] === 'Pending'
                && $worker_assignment['completion_review_status'] !== 'pending_review';
            if ($can_complete_stage && $worker_assignment['work_type'] === 'Packaging') {
                $cooking_check = $conn->prepare(
                    "SELECT work_status
                     FROM production_assignments
                     WHERE production_id = ? AND work_type = 'Cooking'
                     LIMIT 1
                     FOR UPDATE"
                );
                $cooking_check->bind_param("s", $production_id);
                $cooking_check->execute();
                $cooking_assignment = $cooking_check->get_result()->fetch_assoc();
                $cooking_check->close();
                $can_complete_stage = !$cooking_assignment || $cooking_assignment['work_status'] === 'Done';
            }

            if ($can_complete_stage) {
                $complete_stage = $conn->prepare(
                    "UPDATE production_assignments
                     SET completion_review_status = 'pending_review',
                         completion_review_note = NULL,
                         completion_reviewed_by = NULL,
                         completion_reviewed_at = NULL
                     WHERE production_id = ? AND employee_id = ? AND work_type = ?
                       AND work_status = 'Pending'
                       AND completion_review_status <> 'pending_review'"
                );
                $complete_stage->bind_param("sis", $production_id, $current_employee_id, $worker_assignment['work_type']);
                $stage_completed = $complete_stage->execute() && $complete_stage->affected_rows === 1;
                $complete_stage->close();

                if ($stage_completed) {
                    $actor_user_id = (int) ($_SESSION['id'] ?? $_SESSION['user_id'] ?? 0);
                    $actor_name = trim((string) ($_SESSION['name'] ?? ''));
                    if ($actor_name === '') {
                        $actor_name = getProductionEmployeeName($conn, $current_employee_id) ?? ('User #' . $actor_user_id);
                    }
                    try {
                        recordProductionActivity(
                            $conn,
                            (string) $production_id,
                            $actor_user_id,
                            $actor_name,
                            'stage_submitted_for_review',
                            $worker_assignment['work_type'],
                            null,
                            null,
                            $current_employee_id,
                            getProductionEmployeeName($conn, $current_employee_id)
                        );
                        $assigner_id = (int) ($worker_assignment['assigned_by'] ?? 0);
                        if ($assigner_id > 0) {
                            createTaskNotification(
                                $conn,
                                $assigner_id,
                                $actor_user_id,
                                'production_stage_review_requested',
                                'Production stage submitted for review',
                                $actor_name . ' submitted the ' . $worker_assignment['work_type'] . ' stage for item #' . $production_id . ' for your approval.',
                                'factory_main.php',
                                'production',
                                (string) $production_id
                            );
                        }
                    } catch (RuntimeException $exception) {
                        $completion_error = "This stage could not be completed with its activity history: " . $exception->getMessage();
                    }
                }
            } else {
                $completion_error = $worker_assignment
                    && $worker_assignment['work_type'] === 'Packaging'
                    && $worker_assignment['completion_review_status'] !== 'pending_review'
                    ? "Packaging can only be submitted after Cooking is approved."
                    : "This task is not assigned to your account or is already completed.";
            }

            if ($can_complete_stage && !$stage_completed) {
                $completion_error = "This task could not be completed. It may already be done or unavailable.";
            }

            if ($completion_error === null) {
                if (!$conn->commit()) {
                    $conn->rollback();
                    $_SESSION['production_error'] = "The production stage submission could not be saved.";
                } else {
                    $_SESSION['production_success'] = "Production stage submitted for supervisor review.";
                }
            } else {
                $conn->rollback();
                $_SESSION['production_error'] = $completion_error;
            }
        } elseif ($is_production_worker) {
            $_SESSION['production_error'] = "This task is not assigned to your account.";
        } elseif ($is_production_supervisor && !empty($production_id)) {
            $_SESSION['production_error'] = "Production stages can only be completed by the assigned Cooking and Packaging workers.";
        }
        
        $redirect_page = $is_production_worker ? 'Production.php' : 'factory_main.php';
        header("Location: $redirect_page");
        exit();
    }

    // --- FETCH PRODUCTION RECORDS ---
    if (!$is_production_worker || $current_employee_id !== null) {
        if ($is_production_worker) {
            $assignment_employee_filter = " AND pa.employee_id = " . $current_employee_id;
            $role_stage_filter = $current_work_type === null
                ? ''
                : " AND pa.work_type = '" . $current_work_type . "'";
            $stage_visibility_filter = " AND pa.work_status = 'Pending'
                                        AND (pa.work_type = 'Cooking'
                                             OR NOT EXISTS (
                                                 SELECT 1
                                                 FROM production_assignments legacy_cooking
                                                 WHERE legacy_cooking.production_id = pa.production_id
                                                   AND legacy_cooking.work_type = 'Cooking'
                                             )
                                             OR EXISTS (
                                            SELECT 1
                                            FROM production_assignments cooking
                                            WHERE cooking.production_id = pa.production_id
                                              AND cooking.work_type = 'Cooking'
                                              AND cooking.work_status = 'Done'
                                        ))";
            $pending_assignment_source = "INNER JOIN production_assignments pa
                                            ON pa.production_id = CAST(p.production_id AS CHAR)
                                           AND pa.work_type IN ('Cooking', 'Packaging')
                                         LEFT JOIN employee e ON e.employee_id = pa.employee_id
                                         LEFT JOIN users assignment_user ON assignment_user.id = pa.assigned_by
                                         LEFT JOIN users review_user ON review_user.id = pa.completion_reviewed_by";
            $history_assignment_source = $pending_assignment_source;
            $pending_stage_visibility_filter = $assignment_employee_filter . $role_stage_filter . $stage_visibility_filter;
            $history_assignment_filter = $assignment_employee_filter . $role_stage_filter;
        } else {
            $pending_assignment_source = "CROSS JOIN (
                                             SELECT 'Cooking' AS work_type
                                             UNION ALL SELECT 'Packaging'
                                         ) stages
                                         LEFT JOIN production_assignments pa
                                           ON pa.production_id = CAST(p.production_id AS CHAR)
                                          AND pa.work_type = stages.work_type
                                         LEFT JOIN employee e ON e.employee_id = pa.employee_id
                                         LEFT JOIN users assignment_user ON assignment_user.id = pa.assigned_by
                                         LEFT JOIN users review_user ON review_user.id = pa.completion_reviewed_by";
            $history_assignment_source = $pending_assignment_source;
            $pending_stage_visibility_filter = " AND NOT (
                                                stages.work_type = 'Cooking'
                                                AND COALESCE(pa.work_status, '') = 'Done'
                                            )";
            $history_assignment_filter = "";
        }
        $assigned_status_expression = $is_production_worker
            ? "CASE
                   WHEN pa.completion_review_status = 'pending_review' THEN 'Awaiting Approval'
                   ELSE COALESCE(pa.work_status, 'Pending')
               END"
            : "CASE
                   WHEN pa.completion_review_status = 'pending_review' THEN 'Awaiting Approval'
                   WHEN stages.work_type = 'Packaging'
                     AND pa.employee_id IS NULL
                     AND EXISTS (
                         SELECT 1
                         FROM production_assignments cooking_ready
                         WHERE cooking_ready.production_id = CAST(p.production_id AS CHAR)
                           AND cooking_ready.work_type = 'Cooking'
                           AND cooking_ready.work_status = 'Done'
                     )
                   THEN 'Waiting for Packaging'
                   WHEN stages.work_type = 'Packaging'
                     AND COALESCE(pa.work_status, 'Pending') = 'Pending'
                     AND EXISTS (
                         SELECT 1
                         FROM production_assignments cooking_stage
                         WHERE cooking_stage.production_id = CAST(p.production_id AS CHAR)
                           AND cooking_stage.work_type = 'Cooking'
                     )
                     AND NOT EXISTS (
                         SELECT 1
                         FROM production_assignments cooking
                         WHERE cooking.production_id = CAST(p.production_id AS CHAR)
                           AND cooking.work_type = 'Cooking'
                           AND cooking.work_status = 'Done'
                     )
                   THEN 'Waiting for Cooking'
                   ELSE COALESCE(pa.work_status, 'Pending')
               END";
        $history_status_expression = $is_production_worker
            ? "COALESCE(pa.work_status, 'Pending')"
            : "CASE
                   WHEN pa.work_status IS NULL AND p.product_status = 'product done' THEN 'Done'
                   ELSE COALESCE(pa.work_status, 'Pending')
               END";

        $sql_pending = "SELECT p.production_id, p.product_name, p.target_pcs, p.Stock_number, p.quantity, p.due_date, p.product_status,
                               pa.employee_id AS assigned_employee_id, e.username AS assigned_employee_name,
                               e.position AS assigned_position,
                               pa.assigned_by, assignment_user.name AS assigned_by_name,
                               pa.completion_review_status, pa.completion_review_note,
                               pa.completion_reviewed_by, review_user.name AS completion_reviewed_by_name,
                               pa.completion_reviewed_at,
                               " . ($is_production_worker ? "pa.work_type" : "stages.work_type") . " AS assigned_work_type,
                               $assigned_status_expression AS assigned_work_status
                        FROM production p
                        $pending_assignment_source
                        WHERE (p.product_status != 'product done' OR p.product_status IS NULL)"
                        . $pending_stage_visibility_filter . "
                        ORDER BY p.production_id DESC, assigned_work_type";

        $res_pending = mysqli_query($conn, $sql_pending);
        if (!$res_pending) {
            throw new RuntimeException("Unable to load production tasks: " . mysqli_error($conn));
        }
        while ($row = mysqli_fetch_assoc($res_pending)) {
            $pending_records[] = $row;
        }

        $sql_history = "SELECT p.production_id, p.product_name, p.target_pcs, p.Stock_number, p.quantity, p.due_date, p.product_status,
                               pa.employee_id AS assigned_employee_id, e.username AS assigned_employee_name,
                               e.position AS assigned_position,
                               pa.assigned_by, assignment_user.name AS assigned_by_name,
                               pa.completion_review_status, pa.completion_review_note,
                               pa.completion_reviewed_by, review_user.name AS completion_reviewed_by_name,
                               pa.completion_reviewed_at,
                               " . ($is_production_worker ? "pa.work_type" : "stages.work_type") . " AS assigned_work_type,
                               $history_status_expression AS assigned_work_status
                        FROM production p
                        $history_assignment_source
                        WHERE p.product_status = 'product done'"
                        . $history_assignment_filter . "
                        ORDER BY p.production_id DESC, assigned_work_type";

        $res_history = mysqli_query($conn, $sql_history);
        if (!$res_history) {
            throw new RuntimeException("Unable to load production task history: " . mysqli_error($conn));
        }
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