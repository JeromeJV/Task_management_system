<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/config/connection.php';
require_once __DIR__ . '/config/autoLog.php';
require_once __DIR__ . '/config/system_audit.php';
require_once __DIR__ . '/config/mailer.php';
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['id'], $_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Administrator access is required.');
}

$currentAdminId = (int) $_SESSION['id'];
$adminCheckStmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'admin' AND is_active = 1 LIMIT 1");
if (!$adminCheckStmt) {
    throw new RuntimeException('Unable to verify administrator access: ' . $conn->error);
}
$adminCheckStmt->bind_param('i', $currentAdminId);
if (!$adminCheckStmt->execute()) {
    $error = $adminCheckStmt->error;
    $adminCheckStmt->close();
    throw new RuntimeException('Unable to verify administrator access: ' . $error);
}
$isStillAdmin = $adminCheckStmt->get_result()->num_rows === 1;
$adminCheckStmt->close();
if (!$isStillAdmin) {
    session_unset();
    session_destroy();
    http_response_code(403);
    exit('Administrator access is no longer active. Please sign in again.');
}

$roles = [
    'admin' => 'Administrator',
    'HR' => 'Human Resources',
    'payroll' => 'Payroll',
    'super' => 'Supervisor',
    'log' => 'Logistics',
    'pro' => 'Legacy Production',
    'cooking' => 'Cooking',
    'packaging' => 'Packaging',
    'employee' => 'Employee',
];
$section = (string) ($_GET['section'] ?? 'dashboard');
if (!in_array($section, ['dashboard', 'accounts', 'audit', 'reports'], true)) {
    $section = 'dashboard';
}
$errorMessage = '';
$flashMessage = (string) ($_SESSION['admin_flash'] ?? '');
$flashType = (string) ($_SESSION['admin_flash_type'] ?? 'success');
unset($_SESSION['admin_flash']);
unset($_SESSION['admin_flash_type']);

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$profilePictureDirectory = __DIR__ . '/uploads/profile-pictures';
$deleteStoredProfilePicture = static function (?string $filename) use ($profilePictureDirectory): void {
    if ($filename === null || !preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $filename)) {
        return;
    }

    $filePath = $profilePictureDirectory . DIRECTORY_SEPARATOR . $filename;
    if (is_file($filePath) && !unlink($filePath)) {
        error_log('TaskTrack could not remove an old profile picture.');
    }
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) $_SESSION['admin_csrf'], $postedToken)) {
        $errorMessage = 'Your session token expired. Refresh the page and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        if (in_array($action, ['update_profile_picture', 'remove_profile_picture'], true)) {
            $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$userId || $userId < 1) {
                $errorMessage = 'Choose a valid account.';
            } else {
                $userStmt = $conn->prepare('SELECT id, name, profile_picture FROM users WHERE id = ? LIMIT 1');
                if (!$userStmt) {
                    throw new RuntimeException('Unable to prepare the profile picture account lookup: ' . $conn->error);
                }
                $userStmt->bind_param('i', $userId);
                if (!$userStmt->execute()) {
                    $error = $userStmt->error;
                    $userStmt->close();
                    throw new RuntimeException('Unable to load the profile picture account: ' . $error);
                }
                $pictureAccount = $userStmt->get_result()->fetch_assoc();
                $userStmt->close();

                if (!$pictureAccount) {
                    $errorMessage = 'The selected account no longer exists.';
                } else {
                    $newFilename = null;
                    if ($action === 'update_profile_picture') {
                        $upload = $_FILES['profile_picture'] ?? null;
                        if (
                            !is_array($upload)
                            || !isset($upload['error'], $upload['tmp_name'], $upload['size'])
                            || !is_string($upload['tmp_name'])
                            || !is_numeric($upload['error'])
                            || !is_numeric($upload['size'])
                        ) {
                            $errorMessage = 'Choose a profile picture to upload.';
                        } elseif ((int) $upload['error'] !== UPLOAD_ERR_OK) {
                            $errorMessage = match ((int) $upload['error']) {
                                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The profile picture must be no larger than 2 MB.',
                                UPLOAD_ERR_NO_FILE => 'Choose a profile picture to upload.',
                                default => 'The profile picture could not be uploaded. Please try again.',
                            };
                        } elseif ((int) $upload['size'] < 1 || (int) $upload['size'] > 2 * 1024 * 1024) {
                            $errorMessage = 'The profile picture must be no larger than 2 MB.';
                        } else {
                            $fileInfo = new finfo(FILEINFO_MIME_TYPE);
                            $mimeType = $fileInfo->file($upload['tmp_name']);
                            $imageInfo = @getimagesize($upload['tmp_name']);
                            $allowedImageTypes = [
                                'image/jpeg' => 'jpg',
                                'image/png' => 'png',
                                'image/webp' => 'webp',
                            ];
                            if (
                                !is_string($mimeType)
                                || !isset($allowedImageTypes[$mimeType])
                                || !is_array($imageInfo)
                                || ($imageInfo['mime'] ?? null) !== $mimeType
                                || (int) ($imageInfo[0] ?? 0) < 1
                                || (int) ($imageInfo[1] ?? 0) < 1
                                || (int) $imageInfo[0] > 5000
                                || (int) $imageInfo[1] > 5000
                            ) {
                                $errorMessage = 'Upload a valid JPG, PNG, or WebP image no larger than 5000 × 5000 pixels.';
                            } elseif (!is_uploaded_file($upload['tmp_name'])) {
                                $errorMessage = 'The profile picture upload could not be verified. Please try again.';
                            } else {
                                if (!is_dir($profilePictureDirectory) && !mkdir($profilePictureDirectory, 0755, true) && !is_dir($profilePictureDirectory)) {
                                    throw new RuntimeException('Unable to create the profile picture upload directory.');
                                }
                                $newFilename = bin2hex(random_bytes(16)) . '.' . $allowedImageTypes[$mimeType];
                                if (!move_uploaded_file(
                                    $upload['tmp_name'],
                                    $profilePictureDirectory . DIRECTORY_SEPARATOR . $newFilename
                                )) {
                                    throw new RuntimeException('Unable to save the uploaded profile picture.');
                                }
                            }
                        }
                    }

                    if ($errorMessage === '') {
                        $storedFilename = $action === 'remove_profile_picture' ? null : $newFilename;
                        if ($storedFilename === null && $action === 'update_profile_picture') {
                            $errorMessage = 'Choose a valid profile picture before uploading.';
                        } else {
                            $pictureStmt = $conn->prepare('UPDATE users SET profile_picture = ? WHERE id = ?');
                            if (!$pictureStmt) {
                                if ($newFilename !== null) {
                                    $deleteStoredProfilePicture($newFilename);
                                }
                                throw new RuntimeException('Unable to prepare the profile picture update: ' . $conn->error);
                            }
                            $pictureStmt->bind_param('si', $storedFilename, $userId);
                            if (!$pictureStmt->execute()) {
                                $error = $pictureStmt->error;
                                $pictureStmt->close();
                                if ($newFilename !== null) {
                                    $deleteStoredProfilePicture($newFilename);
                                }
                                throw new RuntimeException('Unable to save the profile picture: ' . $error);
                            }
                            $pictureStmt->close();

                            recordSystemAudit(
                                $conn,
                                $currentAdminId,
                                (string) $_SESSION['name'],
                                $action === 'remove_profile_picture' ? 'profile_picture_removed' : 'profile_picture_updated',
                                'user',
                                $userId,
                                ($action === 'remove_profile_picture' ? 'Removed the profile picture for ' : 'Updated the profile picture for ')
                                    . (string) $pictureAccount['name'] . '.'
                            );
                            if ($userId === $currentAdminId) {
                                if ($storedFilename === null) {
                                    unset($_SESSION['profile_picture']);
                                } else {
                                    $_SESSION['profile_picture'] = $storedFilename;
                                }
                            }
                            $deleteStoredProfilePicture(
                                isset($pictureAccount['profile_picture']) ? (string) $pictureAccount['profile_picture'] : null
                            );
                            $_SESSION['admin_flash'] = $action === 'remove_profile_picture'
                                ? 'Profile picture removed.'
                                : 'Profile picture updated for ' . (string) $pictureAccount['name'] . '.';
                            header('Location: admin.php?section=accounts');
                            exit();
                        }
                    }
                }
            }
        } elseif ($action === 'link_employee') {
            $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
            $employeeId = filter_var($_POST['employee_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$userId || $userId < 1 || $employeeId === false || $employeeId < 0) {
                $errorMessage = 'Choose a valid account and employee profile.';
            } else {
                if (!$conn->begin_transaction()) {
                    throw new RuntimeException('Unable to start the employee account-link update: ' . $conn->error);
                }
                $userStmt = $conn->prepare('SELECT id FROM users WHERE id = ? LIMIT 1 FOR UPDATE');
                if (!$userStmt) {
                    throw new RuntimeException('Unable to prepare the account-link lookup: ' . $conn->error);
                }
                $userStmt->bind_param('i', $userId);
                if (!$userStmt->execute()) {
                    $error = $userStmt->error;
                    $userStmt->close();
                    throw new RuntimeException('Unable to load the account for linking: ' . $error);
                }
                $userExists = $userStmt->get_result()->num_rows === 1;
                $userStmt->close();

                if (!$userExists) {
                    $conn->rollback();
                    $errorMessage = 'The selected account no longer exists.';
                } else {
                    $linkedEmployeeId = $employeeId === 0 ? null : $employeeId;
                    if ($linkedEmployeeId !== null) {
                        $employeeStmt = $conn->prepare('SELECT employee_id FROM employee WHERE employee_id = ? LIMIT 1');
                        if (!$employeeStmt) {
                            throw new RuntimeException('Unable to prepare the employee profile lookup: ' . $conn->error);
                        }
                        $employeeStmt->bind_param('i', $linkedEmployeeId);
                        if (!$employeeStmt->execute()) {
                            $error = $employeeStmt->error;
                            $employeeStmt->close();
                            throw new RuntimeException('Unable to verify the employee profile: ' . $error);
                        }
                        $employeeExists = $employeeStmt->get_result()->num_rows === 1;
                        $employeeStmt->close();

                        $linkedUserStmt = $conn->prepare('SELECT id FROM users WHERE employee_id = ? AND id <> ? LIMIT 1 FOR UPDATE');
                        if (!$linkedUserStmt) {
                            throw new RuntimeException('Unable to prepare the duplicate profile-link check: ' . $conn->error);
                        }
                        $linkedUserStmt->bind_param('ii', $linkedEmployeeId, $userId);
                        if (!$linkedUserStmt->execute()) {
                            $error = $linkedUserStmt->error;
                            $linkedUserStmt->close();
                            throw new RuntimeException('Unable to check for an existing profile link: ' . $error);
                        }
                        $profileAlreadyLinked = $linkedUserStmt->get_result()->num_rows > 0;
                        $linkedUserStmt->close();

                        if (!$employeeExists || $profileAlreadyLinked) {
                            $conn->rollback();
                            $errorMessage = !$employeeExists
                                ? 'The selected employee profile no longer exists.'
                                : 'That employee profile is already linked to another account.';
                        }
                    }

                    if ($errorMessage === '') {
                        $linkStmt = $conn->prepare('UPDATE users SET employee_id = ? WHERE id = ?');
                        if (!$linkStmt) {
                            throw new RuntimeException('Unable to prepare the employee account-link update: ' . $conn->error);
                        }
                        $linkStmt->bind_param('ii', $linkedEmployeeId, $userId);
                        if (!$linkStmt->execute()) {
                            $error = $linkStmt->error;
                            $linkStmt->close();
                            throw new RuntimeException('Unable to update the employee account link: ' . $error);
                        }
                        $linkStmt->close();
                        recordSystemAudit(
                            $conn,
                            $currentAdminId,
                            (string) $_SESSION['name'],
                            'employee_account_link_updated',
                            'user',
                            $userId,
                            $linkedEmployeeId === null
                                ? 'Removed the employee profile link from account #' . $userId . '.'
                                : 'Linked account #' . $userId . ' to employee profile #' . $linkedEmployeeId . '.'
                        );
                        if (!$conn->commit()) {
                            throw new RuntimeException('Unable to save the employee account link: ' . $conn->error);
                        }
                        $_SESSION['admin_flash'] = 'Employee profile link updated.';
                        header('Location: admin.php?section=accounts');
                        exit();
                    }
                }
            }
        } elseif ($action === 'create_account') {
            $employeeId = filter_var($_POST['employee_id'] ?? null, FILTER_VALIDATE_INT);
            $role = (string) ($_POST['role'] ?? '');
            $password = (string) ($_POST['password'] ?? '');
            if (!$employeeId || $employeeId < 1) {
                $errorMessage = 'Choose a valid employee profile.';
            } elseif (!isset($roles[$role])) {
                $errorMessage = 'Choose a valid account role.';
            } elseif (strlen($password) < 8 || !preg_match('/[0-9]/', $password)) {
                $errorMessage = 'Password must be at least 8 characters and include a number.';
            } else {
                $employeeStmt = $conn->prepare(
                    'SELECT username, email FROM employee WHERE employee_id = ? LIMIT 1'
                );
                if (!$employeeStmt) {
                    throw new RuntimeException('Unable to prepare the employee lookup: ' . $conn->error);
                }
                $employeeStmt->bind_param('i', $employeeId);
                if (!$employeeStmt->execute()) {
                    $error = $employeeStmt->error;
                    $employeeStmt->close();
                    throw new RuntimeException('Unable to load the employee profile: ' . $error);
                }
                $employee = $employeeStmt->get_result()->fetch_assoc();
                $employeeStmt->close();

                if (!$employee || trim((string) $employee['email']) === '') {
                    $errorMessage = 'That employee profile has no email address for an account.';
                } else {
                    if (!$conn->begin_transaction()) {
                        throw new RuntimeException('Unable to start account creation: ' . $conn->error);
                    }
                    $existingStmt = $conn->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1 FOR UPDATE');
                    if (!$existingStmt) {
                        throw new RuntimeException('Unable to prepare the duplicate account check: ' . $conn->error);
                    }
                    $employeeEmail = (string) $employee['email'];
                    $existingStmt->bind_param('s', $employeeEmail);
                    if (!$existingStmt->execute()) {
                        $error = $existingStmt->error;
                        $existingStmt->close();
                        throw new RuntimeException('Unable to check for an existing account: ' . $error);
                    }
                    $hasAccount = $existingStmt->get_result()->num_rows > 0;
                    $existingStmt->close();

                    if ($hasAccount) {
                        $conn->rollback();
                        $errorMessage = 'An account already exists for this employee email.';
                    } else {
                        $name = (string) $employee['username'];
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $insertStmt = $conn->prepare(
                            'INSERT INTO users (name, email, employee_id, password, role) VALUES (?, ?, ?, ?, ?)'
                        );
                        if (!$insertStmt) {
                            throw new RuntimeException('Unable to prepare the account creation: ' . $conn->error);
                        }
                        $insertStmt->bind_param('sisss', $name, $employeeEmail, $employeeId, $hashedPassword, $role);
                        if (!$insertStmt->execute()) {
                            $error = $insertStmt->error;
                            $insertStmt->close();
                            $conn->rollback();
                            throw new RuntimeException('Unable to create the account: ' . $error);
                        }
                        $newUserId = (int) $conn->insert_id;
                        $insertStmt->close();
                        recordSystemAudit(
                            $conn,
                            $currentAdminId,
                            (string) $_SESSION['name'],
                            'account_created',
                            'user',
                            $newUserId,
                            'Created ' . $role . ' account for ' . $name . ' (' . $employeeEmail . ').'
                        );
                        if (!$conn->commit()) {
                            throw new RuntimeException('Unable to save the new account: ' . $conn->error);
                        }
                        try {
                            sendTaskTrackAccountPassword(
                                $employeeEmail,
                                $name,
                                $roles[$role],
                                $password
                            );
                            $_SESSION['admin_flash'] = 'Account created and password email sent to ' . $employeeEmail . '.';
                        } catch (RuntimeException $mailError) {
                            $_SESSION['admin_flash_type'] = 'warning';
                            $_SESSION['admin_flash'] = 'Account created, but the password email was not sent. '
                                . $mailError->getMessage()
                                . ' Use Reset password below to set a new password and try again.';
                        }
                        header('Location: admin.php?section=accounts');
                        exit();
                    }
                }
            }
        } elseif ($action === 'update_role') {
            $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
            $role = (string) ($_POST['role'] ?? '');
            if (!$userId || $userId < 1 || !isset($roles[$role])) {
                $errorMessage = 'Choose a valid account and role.';
            } elseif ($userId === $currentAdminId) {
                $errorMessage = 'You cannot change your own administrator role.';
            } else {
                if (!$conn->begin_transaction()) {
                    throw new RuntimeException('Unable to start the role update: ' . $conn->error);
                }
                $userStmt = $conn->prepare('SELECT id, name, email, role FROM users WHERE id = ? LIMIT 1 FOR UPDATE');
                if (!$userStmt) {
                    throw new RuntimeException('Unable to prepare the account lookup: ' . $conn->error);
                }
                $userStmt->bind_param('i', $userId);
                if (!$userStmt->execute()) {
                    $error = $userStmt->error;
                    $userStmt->close();
                    throw new RuntimeException('Unable to load the account: ' . $error);
                }
                $user = $userStmt->get_result()->fetch_assoc();
                $userStmt->close();

                if (!$user) {
                    $conn->rollback();
                    $errorMessage = 'The selected account no longer exists.';
                } elseif ($user['role'] === 'admin' && $role !== 'admin') {
                    $adminsStmt = $conn->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1 FOR UPDATE");
                    if (!$adminsStmt) {
                        throw new RuntimeException('Unable to verify administrator access: ' . $conn->error);
                    }
                    $hasAnotherAdmin = $adminsStmt->num_rows > 1;
                    $adminsStmt->free();
                    if (!$hasAnotherAdmin) {
                        $conn->rollback();
                        $errorMessage = 'The last administrator account cannot be demoted.';
                    } else {
                        $updateStmt = $conn->prepare('UPDATE users SET role = ? WHERE id = ?');
                        if (!$updateStmt) {
                            throw new RuntimeException('Unable to prepare the role update: ' . $conn->error);
                        }
                        $updateStmt->bind_param('si', $role, $userId);
                        if (!$updateStmt->execute()) {
                            $error = $updateStmt->error;
                            $updateStmt->close();
                            throw new RuntimeException('Unable to update the account role: ' . $error);
                        }
                        $updateStmt->close();
                        recordSystemAudit($conn, $currentAdminId, (string) $_SESSION['name'], 'role_changed', 'user', $userId, 'Changed ' . $user['email'] . ' role from ' . $user['role'] . ' to ' . $role . '.');
                        if (!$conn->commit()) {
                            throw new RuntimeException('Unable to save the account role: ' . $conn->error);
                        }
                        $_SESSION['admin_flash'] = 'Account role updated successfully.';
                        header('Location: admin.php?section=accounts');
                        exit();
                    }
                } elseif ($user['role'] === $role) {
                    $conn->rollback();
                    $errorMessage = 'The account already has that role.';
                } else {
                    $updateStmt = $conn->prepare('UPDATE users SET role = ? WHERE id = ?');
                    if (!$updateStmt) {
                        throw new RuntimeException('Unable to prepare the role update: ' . $conn->error);
                    }
                    $updateStmt->bind_param('si', $role, $userId);
                    if (!$updateStmt->execute()) {
                        $error = $updateStmt->error;
                        $updateStmt->close();
                        throw new RuntimeException('Unable to update the account role: ' . $error);
                    }
                    $updateStmt->close();
                    recordSystemAudit($conn, $currentAdminId, (string) $_SESSION['name'], 'role_changed', 'user', $userId, 'Changed ' . $user['email'] . ' role from ' . $user['role'] . ' to ' . $role . '.');
                    if (!$conn->commit()) {
                        throw new RuntimeException('Unable to save the account role: ' . $conn->error);
                    }
                    $_SESSION['admin_flash'] = 'Account role updated successfully.';
                    header('Location: admin.php?section=accounts');
                    exit();
                }
            }
        } elseif ($action === 'toggle_account') {
            $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
            $isActive = filter_var($_POST['is_active'] ?? null, FILTER_VALIDATE_INT);
            if (!$userId || $userId < 1 || !in_array($isActive, [0, 1], true)) {
                $errorMessage = 'Choose a valid account status.';
            } elseif ($userId === $currentAdminId && $isActive === 0) {
                $errorMessage = 'You cannot disable your own administrator account.';
            } else {
                if (!$conn->begin_transaction()) {
                    throw new RuntimeException('Unable to start the account status update: ' . $conn->error);
                }
                $userStmt = $conn->prepare('SELECT id, name, email, role, is_active FROM users WHERE id = ? LIMIT 1 FOR UPDATE');
                if (!$userStmt) {
                    throw new RuntimeException('Unable to prepare the account status lookup: ' . $conn->error);
                }
                $userStmt->bind_param('i', $userId);
                if (!$userStmt->execute()) {
                    $error = $userStmt->error;
                    $userStmt->close();
                    throw new RuntimeException('Unable to load the account status: ' . $error);
                }
                $user = $userStmt->get_result()->fetch_assoc();
                $userStmt->close();
                if (!$user) {
                    $conn->rollback();
                    $errorMessage = 'The selected account no longer exists.';
                } elseif ((int) $user['is_active'] === $isActive) {
                    $conn->rollback();
                    $errorMessage = 'The account already has that status.';
                } elseif ($user['role'] === 'admin' && $isActive === 0) {
                    $adminsStmt = $conn->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1 FOR UPDATE");
                    if (!$adminsStmt) {
                        throw new RuntimeException('Unable to verify active administrator access: ' . $conn->error);
                    }
                    $hasAnotherActiveAdmin = $adminsStmt->num_rows > 1;
                    $adminsStmt->free();
                    if (!$hasAnotherActiveAdmin) {
                        $conn->rollback();
                        $errorMessage = 'The last active administrator account cannot be disabled.';
                    } else {
                        $updateStmt = $conn->prepare('UPDATE users SET is_active = ? WHERE id = ?');
                        if (!$updateStmt) {
                            throw new RuntimeException('Unable to prepare the account status update: ' . $conn->error);
                        }
                        $updateStmt->bind_param('ii', $isActive, $userId);
                        if (!$updateStmt->execute()) {
                            $error = $updateStmt->error;
                            $updateStmt->close();
                            throw new RuntimeException('Unable to update account status: ' . $error);
                        }
                        $updateStmt->close();
                        recordSystemAudit($conn, $currentAdminId, (string) $_SESSION['name'], 'account_disabled', 'user', $userId, 'Disabled account ' . $user['email'] . '.');
                        if (!$conn->commit()) {
                            throw new RuntimeException('Unable to save the account status: ' . $conn->error);
                        }
                        $_SESSION['admin_flash'] = 'Account disabled. Any active session has been blocked.';
                        header('Location: admin.php?section=accounts');
                        exit();
                    }
                } else {
                    $updateStmt = $conn->prepare('UPDATE users SET is_active = ? WHERE id = ?');
                    if (!$updateStmt) {
                        throw new RuntimeException('Unable to prepare the account status update: ' . $conn->error);
                    }
                    $updateStmt->bind_param('ii', $isActive, $userId);
                    if (!$updateStmt->execute()) {
                        $error = $updateStmt->error;
                        $updateStmt->close();
                        throw new RuntimeException('Unable to update account status: ' . $error);
                    }
                    $updateStmt->close();
                    $auditAction = $isActive === 1 ? 'account_enabled' : 'account_disabled';
                    $auditDetails = ($isActive === 1 ? 'Enabled account ' : 'Disabled account ') . $user['email'] . '.';
                    recordSystemAudit($conn, $currentAdminId, (string) $_SESSION['name'], $auditAction, 'user', $userId, $auditDetails);
                    if (!$conn->commit()) {
                        throw new RuntimeException('Unable to save the account status: ' . $conn->error);
                    }
                    $_SESSION['admin_flash'] = $isActive === 1 ? 'Account enabled successfully.' : 'Account disabled. Any active session has been blocked.';
                    header('Location: admin.php?section=accounts');
                    exit();
                }
            }
        } elseif ($action === 'reset_password') {
            $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
            $password = (string) ($_POST['password'] ?? '');
            if (!$userId || $userId < 1 || $userId === $currentAdminId) {
                $errorMessage = 'Choose another valid account to reset.';
            } elseif (strlen($password) < 8 || !preg_match('/[0-9]/', $password)) {
                $errorMessage = 'Password must be at least 8 characters and include a number.';
            } else {
                if (!$conn->begin_transaction()) {
                    throw new RuntimeException('Unable to start the password reset: ' . $conn->error);
                }
                $targetStmt = $conn->prepare('SELECT name, email, role FROM users WHERE id = ? LIMIT 1');
                if (!$targetStmt) {
                    throw new RuntimeException('Unable to prepare the account email lookup: ' . $conn->error);
                }
                $targetStmt->bind_param('i', $userId);
                if (!$targetStmt->execute()) {
                    $error = $targetStmt->error;
                    $targetStmt->close();
                    throw new RuntimeException('Unable to load the account email: ' . $error);
                }
                $targetAccount = $targetStmt->get_result()->fetch_assoc();
                $targetStmt->close();
                if (!$targetAccount) {
                    $conn->rollback();
                    $errorMessage = 'The selected account no longer exists.';
                    $targetAccount = null;
                }
                if ($targetAccount) {
                    $resetStmt = $conn->prepare('UPDATE users SET password = ?, login_attempts = 0, lockout_time = NULL WHERE id = ?');
                    if (!$resetStmt) {
                        throw new RuntimeException('Unable to prepare the password reset: ' . $conn->error);
                    }
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $resetStmt->bind_param('si', $hashedPassword, $userId);
                    if (!$resetStmt->execute() || $resetStmt->affected_rows < 1) {
                        $error = $resetStmt->error;
                        $resetStmt->close();
                        $conn->rollback();
                        throw new RuntimeException('Unable to reset the account password: ' . $error);
                    }
                    $resetStmt->close();
                    recordSystemAudit($conn, $currentAdminId, (string) $_SESSION['name'], 'password_reset', 'user', $userId, 'Reset the password and cleared login lockout for user #' . $userId . '.');
                    if (!$conn->commit()) {
                        throw new RuntimeException('Unable to save the password reset: ' . $conn->error);
                    }
                    try {
                        sendTaskTrackAccountPassword(
                            (string) $targetAccount['email'],
                            (string) $targetAccount['name'],
                            $roles[(string) $targetAccount['role']] ?? (string) $targetAccount['role'],
                            $password
                        );
                        $_SESSION['admin_flash'] = 'Password updated and email sent to ' . $targetAccount['email'] . '.';
                    } catch (RuntimeException $mailError) {
                        $_SESSION['admin_flash_type'] = 'warning';
                        $_SESSION['admin_flash'] = 'Password updated, but the email was not sent. '
                            . $mailError->getMessage()
                            . ' Set a new password and try again.';
                    }
                    header('Location: admin.php?section=accounts');
                    exit();
                }
            }
        } else {
            $errorMessage = 'Unsupported administrator action.';
        }
    }
}

$employees = [];
$employeeResult = $conn->query(
    "SELECT e.employee_id, e.username, e.email, e.position
     FROM employee e
     WHERE e.email IS NOT NULL AND TRIM(e.email) <> ''
       AND NOT EXISTS (
           SELECT 1 FROM users u
           WHERE u.employee_id = e.employee_id
              OR LOWER(TRIM(u.email)) = LOWER(TRIM(e.email))
       )
     ORDER BY e.username"
);
if (!$employeeResult) {
    throw new RuntimeException('Unable to load employees without accounts: ' . $conn->error);
}
while ($employee = $employeeResult->fetch_assoc()) {
    $employees[] = $employee;
}
$employeeResult->free();

$employeeProfiles = [];
$profileResult = $conn->query('SELECT employee_id, username, email FROM employee ORDER BY username, employee_id');
if (!$profileResult) {
    throw new RuntimeException('Unable to load employee profiles for account linking: ' . $conn->error);
}
while ($profile = $profileResult->fetch_assoc()) {
    $employeeProfiles[] = $profile;
}
$profileResult->free();

$accounts = [];
$accountResult = $conn->query(
    'SELECT u.id, u.name, u.email, u.role, u.is_active, u.employee_id, u.profile_picture, e.username AS employee_username
     FROM users u
     LEFT JOIN employee e ON e.employee_id = u.employee_id
     ORDER BY u.name, u.id'
);
if (!$accountResult) {
    throw new RuntimeException('Unable to load system accounts: ' . $conn->error);
}
while ($account = $accountResult->fetch_assoc()) {
    $accounts[] = $account;
}
$accountResult->free();

$roleCounts = [];
$roleResult = $conn->query('SELECT role, COUNT(*) AS item_count FROM users GROUP BY role ORDER BY role');
if (!$roleResult) {
    throw new RuntimeException('Unable to load account role summary: ' . $conn->error);
}
while ($row = $roleResult->fetch_assoc()) {
    $roleCounts[(string) $row['role']] = (int) $row['item_count'];
}
$roleResult->free();

$reportToday = new DateTimeImmutable('today');
$reportRange = (string) ($_GET['range'] ?? 'month');
if (!in_array($reportRange, ['today', 'week', '7days', 'month', 'year', 'custom'], true)) {
    $reportRange = 'month';
}
$reportStart = $reportToday->modify('first day of this month');
$reportEnd = $reportToday->modify('last day of this month');
if ($reportRange === 'today') {
    $reportStart = $reportToday;
    $reportEnd = $reportToday;
} elseif ($reportRange === 'week') {
    $reportStart = $reportToday->modify('monday this week');
    $reportEnd = $reportToday;
} elseif ($reportRange === '7days') {
    $reportStart = $reportToday->modify('-6 days');
    $reportEnd = $reportToday;
} elseif ($reportRange === 'year') {
    $reportStart = $reportToday->setDate((int) $reportToday->format('Y'), 1, 1);
    $reportEnd = $reportToday->setDate((int) $reportToday->format('Y'), 12, 31);
} elseif ($reportRange === 'custom') {
    $parseReportDate = static function ($value): ?DateTimeImmutable {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $dateErrors = DateTimeImmutable::getLastErrors();
        if (
            !$parsed
            || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
            || $parsed->format('Y-m-d') !== $value
        ) {
            return null;
        }
        return $parsed;
    };
    $customStart = $parseReportDate($_GET['start_date'] ?? null);
    $customEnd = $parseReportDate($_GET['end_date'] ?? null);
    if (!$customStart || !$customEnd || $customStart > $customEnd) {
        $errorMessage = 'Choose a valid start and end date for the report.';
        $reportRange = 'month';
        $reportStart = $reportToday->modify('first day of this month');
        $reportEnd = $reportToday->modify('last day of this month');
    } else {
        $reportStart = $customStart;
        $reportEnd = $customEnd;
    }
}
$reportStartDate = $reportStart->format('Y-m-d');
$reportEndDate = $reportEnd->format('Y-m-d');
$reportStartDateTime = $reportStart->format('Y-m-d') . ' 00:00:00';
$reportEndExclusive = $reportEnd->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
$reportDays = (int) $reportStart->diff($reportEnd)->days + 1;
$reportBucketFormat = $reportDays <= 31 ? '%Y-%m-%d' : '%Y-%m';

$reportAttendanceCounts = [];
$reportPayrollCounts = [];
$reportProductionCounts = [];
$reportDeliveryCounts = [];
$reportTaskCounts = [];
$attendanceTrend = [];
$payrollTrend = [];
$reportQueries = [
    'attendance' => [
        'SELECT COALESCE(status, \'Unknown\') AS item_status, COUNT(*) AS item_count
         FROM attendance WHERE attendance_date BETWEEN ? AND ? GROUP BY item_status',
        'ss',
        [$reportStartDate, $reportEndDate],
    ],
    'payroll' => [
        'SELECT COALESCE(status, \'Unknown\') AS item_status, COUNT(*) AS item_count,
                COALESCE(SUM(total_credited), 0) AS total_amount
         FROM payroll WHERE pay_date BETWEEN ? AND ? GROUP BY item_status',
        'ss',
        [$reportStartDate, $reportEndDate],
    ],
    'production' => [
        'SELECT work_type, work_status, COUNT(*) AS item_count
         FROM production_assignments WHERE assigned_at >= ? AND assigned_at < ?
         GROUP BY work_type, work_status ORDER BY work_type, work_status',
        'ss',
        [$reportStartDateTime, $reportEndExclusive],
    ],
    'delivery' => [
        'SELECT COALESCE(status, \'Pending\') AS item_status, COUNT(*) AS item_count
         FROM delivery
         WHERE (CASE WHEN delivered_at IS NOT NULL THEN DATE(delivered_at) ELSE delivery_date END) BETWEEN ? AND ?
         GROUP BY item_status',
        'ss',
        [$reportStartDate, $reportEndDate],
    ],
    'tasks' => [
        "SELECT CASE WHEN completion_review_status = 'pending_review'
                         THEN 'Awaiting review'
                         ELSE COALESCE(status, 'pending')
                    END AS item_status,
                COUNT(*) AS item_count
         FROM office WHERE submission_date >= ? AND submission_date < ?
         GROUP BY item_status",
        'ss',
        [$reportStartDateTime, $reportEndExclusive],
    ],
    'attendance_trend' => [
        "SELECT DATE_FORMAT(attendance_date, '{$reportBucketFormat}') AS period_label, COUNT(*) AS item_count
         FROM attendance WHERE attendance_date BETWEEN ? AND ?
         GROUP BY period_label ORDER BY period_label",
        'ss',
        [$reportStartDate, $reportEndDate],
    ],
    'payroll_trend' => [
        "SELECT DATE_FORMAT(pay_date, '{$reportBucketFormat}') AS period_label,
                COALESCE(SUM(total_credited), 0) AS total_amount
         FROM payroll WHERE pay_date BETWEEN ? AND ?
         GROUP BY period_label ORDER BY period_label",
        'ss',
        [$reportStartDate, $reportEndDate],
    ],
];
if (in_array($section, ['dashboard', 'reports'], true)) {
    foreach ($reportQueries as $queryName => [$querySql, $queryTypes, $queryValues]) {
        $statement = $conn->prepare($querySql);
        if (!$statement) {
            throw new RuntimeException('Unable to prepare the filtered administrator report: ' . $conn->error);
        }
        $statement->bind_param($queryTypes, ...$queryValues);
        if (!$statement->execute()) {
            $error = $statement->error;
            $statement->close();
            throw new RuntimeException('Unable to load the filtered administrator report: ' . $error);
        }
        $result = $statement->get_result();
        while ($row = $result->fetch_assoc()) {
            if ($queryName === 'attendance') {
                $reportAttendanceCounts[(string) $row['item_status']] = (int) $row['item_count'];
            } elseif ($queryName === 'payroll') {
                $reportPayrollCounts[(string) $row['item_status']] = [
                    'count' => (int) $row['item_count'],
                    'amount' => (float) $row['total_amount'],
                ];
            } elseif ($queryName === 'production') {
                $reportProductionCounts[] = $row;
            } elseif ($queryName === 'delivery') {
                $reportDeliveryCounts[(string) $row['item_status']] = (int) $row['item_count'];
            } elseif ($queryName === 'tasks') {
                $reportTaskCounts[(string) $row['item_status']] = (int) $row['item_count'];
            } elseif ($queryName === 'attendance_trend') {
                $attendanceTrend[] = [
                    'label' => (string) $row['period_label'],
                    'value' => (int) $row['item_count'],
                ];
            } elseif ($queryName === 'payroll_trend') {
                $payrollTrend[] = [
                    'label' => (string) $row['period_label'],
                    'value' => (float) $row['total_amount'],
                ];
            }
        }
        $statement->close();
    }
}
$reportAttendanceTotal = array_sum($reportAttendanceCounts);
$reportPayrollTotal = array_sum(array_column($reportPayrollCounts, 'amount'));
$reportDeliveryTotal = array_sum($reportDeliveryCounts);
$maxAttendanceTrend = max([1, ...array_column($attendanceTrend, 'value')]);
$maxPayrollTrend = max([1, ...array_column($payrollTrend, 'value')]);
$maxDeliveryCount = max([1, ...array_values($reportDeliveryCounts)]);
$dashboardAttendanceColors = ['#21764c', '#e3a72f', '#4389a8', '#b9554c', '#7b6ca8', '#8a9d62', '#6c7b75'];
$dashboardAttendancePie = [];
$attendancePieTotal = array_sum($reportAttendanceCounts);
$attendancePieAngle = -90.0;
if ($attendancePieTotal > 0) {
    $colorIndex = 0;
    foreach ($reportAttendanceCounts as $status => $count) {
        $sliceAngle = ((float) $count / $attendancePieTotal) * 360;
        $startRadians = deg2rad($attendancePieAngle);
        $endRadians = deg2rad($attendancePieAngle + $sliceAngle);
        $startX = 110 + (82 * cos($startRadians));
        $startY = 110 + (82 * sin($startRadians));
        $endX = 110 + (82 * cos($endRadians));
        $endY = 110 + (82 * sin($endRadians));
        $color = $dashboardAttendanceColors[$colorIndex % count($dashboardAttendanceColors)];
        if ($count === $attendancePieTotal) {
            $path = '';
        } else {
            $largeArc = $sliceAngle > 180 ? 1 : 0;
            $path = sprintf(
                'M 110 110 L %.2f %.2f A 82 82 0 %d 1 %.2f %.2f Z',
                $startX,
                $startY,
                $largeArc,
                $endX,
                $endY
            );
        }
        $dashboardAttendancePie[] = [
            'status' => $status,
            'count' => $count,
            'percentage' => round(($count / $attendancePieTotal) * 100),
            'color' => $color,
            'path' => $path,
        ];
        $attendancePieAngle += $sliceAngle;
        $colorIndex++;
    }
}
$dashboardPayrollPoints = [];
$payrollPointCount = count($payrollTrend);
foreach ($payrollTrend as $index => $point) {
    $x = $payrollPointCount < 2
        ? 310.0
        : 44 + ($index * (532 / ($payrollPointCount - 1)));
    $y = 174 - (((float) $point['value'] / $maxPayrollTrend) * 136);
    $dashboardPayrollPoints[] = [
        'x' => round($x, 2),
        'y' => round($y, 2),
        'label' => $point['label'],
        'value' => (float) $point['value'],
    ];
}
$dashboardPayrollPolyline = implode(' ', array_map(
    static fn (array $point): string => $point['x'] . ',' . $point['y'],
    $dashboardPayrollPoints
));
$dashboardPayrollLabelIndexes = [];
if ($payrollPointCount > 0) {
    $labelCount = min(5, $payrollPointCount);
    for ($labelIndex = 0; $labelIndex < $labelCount; $labelIndex++) {
        $dashboardPayrollLabelIndexes[] = $labelCount === 1
            ? 0
            : (int) round($labelIndex * ($payrollPointCount - 1) / ($labelCount - 1));
    }
}
$dashboardOperations = [
    ['label' => 'Office tasks', 'value' => array_sum($reportTaskCounts), 'color' => '#21764c'],
    ['label' => 'Production', 'value' => array_sum(array_map(
        static fn (array $row): int => (int) $row['item_count'],
        $reportProductionCounts
    )), 'color' => '#4389a8'],
    ['label' => 'Deliveries', 'value' => $reportDeliveryTotal, 'color' => '#e3a72f'],
];
$maxDashboardOperations = max([1, ...array_column($dashboardOperations, 'value')]);

if ($section === 'reports' && ($_GET['download'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="tasktrack-system-report-' . $reportStartDate . '-to-' . $reportEndDate . '.csv"');
    $output = fopen('php://output', 'w');
    if ($output === false) {
        throw new RuntimeException('Unable to create the CSV report.');
    }
    fputcsv($output, ['Area', 'Status / Role', 'Count', 'Amount']);
    fputcsv($output, ['Report period', $reportStartDate . ' to ' . $reportEndDate, '', '']);
    foreach ($roleCounts as $role => $count) {
        fputcsv($output, ['Accounts by role (current snapshot)', $role, $count, '']);
    }
    foreach ($reportAttendanceCounts as $status => $count) {
        fputcsv($output, ['Attendance', $status, $count, '']);
    }
    foreach ($reportTaskCounts as $status => $count) {
        fputcsv($output, ['Office tasks submitted in period', $status, $count, '']);
    }
    foreach ($reportPayrollCounts as $status => $totals) {
        fputcsv($output, ['Payroll', $status, $totals['count'], number_format($totals['amount'], 2, '.', '')]);
    }
    foreach ($reportProductionCounts as $row) {
        fputcsv($output, ['Production - ' . $row['work_type'], $row['work_status'], $row['item_count'], '']);
    }
    foreach ($reportDeliveryCounts as $status => $count) {
        fputcsv($output, ['Logistics', $status, $count, '']);
    }
    fclose($output);
    exit();
}

$auditEntries = [];
$auditSources = [
    'SELECT occurred_at, actor_name, action_type, subject_id, details FROM system_audit_log',
];
foreach ([
    'production_activity' => "SELECT occurred_at, actor_name, event_type AS action_type, NULL AS subject_id,
                                     CONCAT('Production #', production_id, COALESCE(CONCAT(' · ', work_type), ''), ' activity') AS details
                              FROM production_activity",
    'delivery_activity' => "SELECT occurred_at, actor_name, event_type AS action_type, NULL AS subject_id,
                                   CONCAT('Delivery #', delivery_id, ' activity') AS details
                            FROM delivery_activity",
] as $tableName => $activityQuery) {
    $tableCheck = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tableName) . "'");
    if (!$tableCheck) {
        throw new RuntimeException('Unable to inspect system activity history: ' . $conn->error);
    }
    if ($tableCheck->num_rows > 0) {
        $auditSources[] = $activityQuery;
    }
    $tableCheck->free();
}
$auditResult = $conn->query(implode(' UNION ALL ', $auditSources) . ' ORDER BY occurred_at DESC LIMIT 100');
if (!$auditResult) {
    throw new RuntimeException('Unable to load administrator audit history: ' . $conn->error);
}
while ($audit = $auditResult->fetch_assoc()) {
    $auditEntries[] = $audit;
}
$auditResult->free();

$employeeCountResult = $conn->query('SELECT COUNT(*) AS total FROM employee');
if (!$employeeCountResult) {
    throw new RuntimeException('Unable to load employee profile count: ' . $conn->error);
}
$employeeCount = (int) ($employeeCountResult->fetch_assoc()['total'] ?? 0);
$employeeCountResult->free();
$userCount = count($accounts);
$activeTaskCount = 0;
foreach ($reportTaskCounts as $status => $count) {
    if (!in_array(strtolower(trim($status)), ['completed', 'complete', 'done'], true)) {
        $activeTaskCount += $count;
    }
}
$selectedPeriodAttendanceTotal = $reportAttendanceTotal;
$selectedPeriodPaidPayroll = $reportPayrollCounts['Paid']['amount'] ?? 0.0;
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$sectionTitles = [
    'dashboard' => 'System overview',
    'accounts' => 'Account management',
    'audit' => 'System audit log',
    'reports' => 'System reports',
];
$tasktrackActive = $section === 'dashboard' ? 'dashboard' : $section;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $escape($sectionTitles[$section]); ?> | TaskTrack Admin</title>
    <link rel="stylesheet" href="css/tasktrack_sidebar.css?v=<?= filemtime(__DIR__ . '/css/tasktrack_sidebar.css'); ?>">
    <link rel="stylesheet" href="css/admin.css?v=<?= filemtime(__DIR__ . '/css/admin.css'); ?>">
    <link rel="stylesheet" href="css/role-form-controls.css?v=<?= filemtime(__DIR__ . '/css/role-form-controls.css'); ?>">
    <link rel="stylesheet" href="css/select-control.css?v=<?= filemtime(__DIR__ . '/css/select-control.css'); ?>">
</head>
<body>
<?php include __DIR__ . '/config/tasktrack_sidebar.php'; ?>
<main class="admin-main">
    <header class="admin-header">
        <div><p class="admin-eyebrow">TASKTRACK ADMINISTRATION</p><h1><?= $escape($sectionTitles[$section]); ?></h1><p>System-wide visibility and account access management.</p></div>
        <div class="admin-user"><strong><?= $escape($_SESSION['name'] ?? 'Administrator'); ?></strong><span><?= $escape(date('M j, Y')); ?></span></div>
    </header>
    <?php if ($flashMessage !== ''): ?><div class="admin-notice is-<?= $escape($flashType); ?>" role="status"><?= $escape($flashMessage); ?></div><?php endif; ?>
    <?php if ($errorMessage !== ''): ?><div class="admin-notice is-error" role="alert"><?= $escape($errorMessage); ?></div><?php endif; ?>

    <?php if ($section === 'dashboard'): ?>
        <section class="admin-stat-grid" aria-label="System summary">
            <article class="admin-stat" title="Current total; employee profiles do not have a reliable creation date."><span>Employee profiles · current total</span><strong><?= $employeeCount; ?></strong></article>
            <article class="admin-stat" title="Current total; system accounts do not have a reliable creation date."><span>System accounts · current total</span><strong><?= $userCount; ?></strong></article>
            <article class="admin-stat"><span>Active office tasks · selected period</span><strong><?= $activeTaskCount; ?></strong></article>
            <article class="admin-stat"><span>Attendance records · selected period</span><strong><?= $selectedPeriodAttendanceTotal; ?></strong></article>
            <article class="admin-stat"><span>Paid payroll · selected period</span><strong>₱<?= number_format((float) $selectedPeriodPaidPayroll, 2); ?></strong></article>
        </section>
        <section class="admin-panel admin-dashboard-analytics">
            <div class="admin-panel-heading">
                <div><p class="admin-eyebrow">DATE-FILTERED OVERVIEW</p><h2>Operational trends</h2></div>
                <a href="admin.php?section=reports&amp;range=<?= $escape(rawurlencode($reportRange)); ?>&amp;start_date=<?= $escape(rawurlencode($reportStartDate)); ?>&amp;end_date=<?= $escape(rawurlencode($reportEndDate)); ?>">Open detailed reports</a>
            </div>
            <p class="admin-report-period">Showing <?= $escape($reportStart->format('M j, Y')); ?> to <?= $escape($reportEnd->format('M j, Y')); ?>. Operational data follows this period; employee and account totals are current snapshots.</p>
            <form method="get" class="admin-report-filter">
                <input type="hidden" name="section" value="dashboard">
                <input type="hidden" name="range" value="custom">
                <label>From
                    <input type="date" name="start_date" value="<?= $escape($reportStartDate); ?>" required>
                </label>
                <label>To
                    <input type="date" name="end_date" value="<?= $escape($reportEndDate); ?>" required>
                </label>
                <button type="submit" class="admin-secondary-button">Apply dates</button>
            </form>
            <nav class="admin-report-presets" aria-label="Quick dashboard date ranges">
                <a class="<?= $reportRange === 'today' ? 'is-active' : ''; ?>" href="admin.php?section=dashboard&amp;range=today">Today</a>
                <a class="<?= $reportRange === 'week' ? 'is-active' : ''; ?>" href="admin.php?section=dashboard&amp;range=week">This week</a>
                <a class="<?= $reportRange === '7days' ? 'is-active' : ''; ?>" href="admin.php?section=dashboard&amp;range=7days">Last 7 days</a>
                <a class="<?= $reportRange === 'month' ? 'is-active' : ''; ?>" href="admin.php?section=dashboard&amp;range=month">This month</a>
                <a class="<?= $reportRange === 'year' ? 'is-active' : ''; ?>" href="admin.php?section=dashboard&amp;range=year">This year</a>
            </nav>
            <h3 class="admin-report-subheading">Dashboard charts</h3>
            <div class="admin-report-charts">
                <article class="admin-report-chart admin-dashboard-chart">
                    <h3>Attendance by status</h3>
                    <p>Distribution during the selected period</p>
                    <?php if ($attendancePieTotal > 0): ?>
                        <div class="admin-pie-layout">
                            <svg class="admin-pie-chart" viewBox="0 0 220 220" role="img" aria-label="Attendance status distribution pie chart">
                                <?php foreach ($dashboardAttendancePie as $slice): ?>
                                    <?php if ($slice['path'] === ''): ?>
                                        <circle cx="110" cy="110" r="82" fill="<?= $escape($slice['color']); ?>"><title><?= $escape($slice['status']); ?>: <?= $slice['count']; ?> records (100%)</title></circle>
                                    <?php else: ?>
                                        <path d="<?= $escape($slice['path']); ?>" fill="<?= $escape($slice['color']); ?>"><title><?= $escape($slice['status']); ?>: <?= $slice['count']; ?> records (<?= $slice['percentage']; ?>%)</title></path>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <circle class="admin-pie-center" cx="110" cy="110" r="46" fill="#fff"></circle>
                                <text x="110" y="106" text-anchor="middle" class="admin-pie-total"><?= $attendancePieTotal; ?></text>
                                <text x="110" y="124" text-anchor="middle" class="admin-pie-caption">records</text>
                            </svg>
                            <ul class="admin-pie-legend">
                                <?php foreach ($dashboardAttendancePie as $slice): ?>
                                    <li><span><i style="--legend-color: <?= $escape($slice['color']); ?>"></i><?= $escape($slice['status']); ?></span><strong><?= $slice['count']; ?> · <?= $slice['percentage']; ?>%</strong></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php else: ?>
                        <p class="admin-chart-empty">No attendance records in this period.</p>
                    <?php endif; ?>
                </article>
                <article class="admin-report-chart admin-dashboard-chart admin-dashboard-chart-wide">
                    <h3>Operational activity</h3>
                    <p>Tasks submitted, production assignments, and deliveries during the selected period</p>
                    <svg class="admin-column-chart" viewBox="0 0 540 250" role="img" aria-label="Operational activity bar chart">
                        <?php foreach ([0, 1, 2, 3] as $gridLine): ?>
                            <?php $gridY = 28 + ($gridLine * 48); ?>
                            <line x1="42" y1="<?= $gridY; ?>" x2="512" y2="<?= $gridY; ?>" class="admin-line-grid"></line>
                        <?php endforeach; ?>
                        <line x1="42" y1="172" x2="512" y2="172" class="admin-line-axis"></line>
                        <?php foreach ($dashboardOperations as $index => $operation): ?>
                            <?php
                            $barX = 105 + ($index * 145);
                            $barHeight = $operation['value'] > 0
                                ? max(3, ((int) $operation['value'] / $maxDashboardOperations) * 138)
                                : 0;
                            $barY = 172 - $barHeight;
                            ?>
                            <rect x="<?= $barX; ?>" y="<?= number_format($barY, 2, '.', ''); ?>" width="66" height="<?= number_format($barHeight, 2, '.', ''); ?>" rx="7" fill="<?= $escape($operation['color']); ?>">
                                <title><?= $escape($operation['label']); ?>: <?= $operation['value']; ?></title>
                            </rect>
                            <text x="<?= $barX + 33; ?>" y="<?= number_format(max(18, $barY - 8), 2, '.', ''); ?>" text-anchor="middle" class="admin-column-value"><?= $operation['value']; ?></text>
                            <text x="<?= $barX + 33; ?>" y="198" text-anchor="middle" class="admin-column-label"><?= $escape($operation['label']); ?></text>
                        <?php endforeach; ?>
                    </svg>
                    <ul class="admin-column-legend">
                        <?php foreach ($dashboardOperations as $operation): ?>
                            <li><i style="--legend-color: <?= $escape($operation['color']); ?>"></i><?= $escape($operation['label']); ?> <strong><?= $operation['value']; ?></strong></li>
                        <?php endforeach; ?>
                    </ul>
                </article>
            </div>
        </section>
        <section class="admin-panel-grid">
            <article class="admin-panel">
                <div class="admin-panel-heading"><div><p class="admin-eyebrow">CURRENT SNAPSHOT</p><h2>Current accounts by role</h2></div><a href="admin.php?section=accounts">Manage accounts</a></div>
                <div class="admin-table-wrap"><table><thead><tr><th>Role</th><th>Accounts</th></tr></thead><tbody>
                <?php foreach ($roleCounts as $role => $count): ?><tr><td><?= $escape($roles[$role] ?? ucfirst($role)); ?></td><td><?= $count; ?></td></tr><?php endforeach; ?>
                </tbody></table></div>
            </article>
            <article class="admin-panel">
                <div class="admin-panel-heading"><div><p class="admin-eyebrow">SELECTED PERIOD</p><h2>Attendance status</h2></div><a href="admin.php?section=reports&amp;range=<?= $escape(rawurlencode($reportRange)); ?>&amp;start_date=<?= $escape(rawurlencode($reportStartDate)); ?>&amp;end_date=<?= $escape(rawurlencode($reportEndDate)); ?>">All reports</a></div>
                <div class="admin-table-wrap"><table><thead><tr><th>Status</th><th>Records</th></tr></thead><tbody>
                <?php foreach ($reportAttendanceCounts as $status => $count): ?><tr><td><?= $escape(ucfirst($status)); ?></td><td><?= $count; ?></td></tr><?php endforeach; ?>
                <?php if ($reportAttendanceCounts === []): ?><tr><td colspan="2">No attendance records in this period.</td></tr><?php endif; ?>
                </tbody></table></div>
            </article>
            <article class="admin-panel">
                <div class="admin-panel-heading"><div><p class="admin-eyebrow">SELECTED PERIOD</p><h2>Production</h2></div></div>
                <div class="admin-table-wrap"><table><thead><tr><th>Stage</th><th>Status</th><th>Assignments</th></tr></thead><tbody>
                <?php foreach ($reportProductionCounts as $row): ?><tr><td><?= $escape($row['work_type']); ?></td><td><?= $escape($row['work_status']); ?></td><td><?= (int) $row['item_count']; ?></td></tr><?php endforeach; ?>
                <?php if ($reportProductionCounts === []): ?><tr><td colspan="3">No production assignments in this period.</td></tr><?php endif; ?>
                </tbody></table></div>
            </article>
            <article class="admin-panel">
                <div class="admin-panel-heading"><div><p class="admin-eyebrow">SELECTED PERIOD</p><h2>Logistics</h2></div></div>
                <div class="admin-table-wrap"><table><thead><tr><th>Status</th><th>Deliveries</th></tr></thead><tbody>
                <?php foreach ($reportDeliveryCounts as $status => $count): ?><tr><td><?= $escape($status); ?></td><td><?= $count; ?></td></tr><?php endforeach; ?>
                <?php if ($reportDeliveryCounts === []): ?><tr><td colspan="2">No delivery records in this period.</td></tr><?php endif; ?>
                </tbody></table></div>
            </article>
            <article class="admin-panel">
                <div class="admin-panel-heading"><div><p class="admin-eyebrow">SELECTED PERIOD</p><h2>Payroll status</h2></div></div>
                <div class="admin-table-wrap"><table><thead><tr><th>Status</th><th>Records</th><th>Total</th></tr></thead><tbody>
                <?php foreach ($reportPayrollCounts as $status => $totals): ?><tr><td><?= $escape($status); ?></td><td><?= $totals['count']; ?></td><td>₱<?= number_format($totals['amount'], 2); ?></td></tr><?php endforeach; ?>
                <?php if ($reportPayrollCounts === []): ?><tr><td colspan="3">No payroll records in this period.</td></tr><?php endif; ?>
                </tbody></table></div>
            </article>
        </section>
    <?php elseif ($section === 'accounts'): ?>
        <section class="admin-panel admin-account-create">
            <div class="admin-panel-heading"><div><p class="admin-eyebrow">NEW ACCOUNT</p><h2>Create a system account</h2></div></div>
            <form method="post" class="admin-form">
                <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['admin_csrf']); ?>">
                <input type="hidden" name="action" value="create_account">
                <label>Employee profile
                    <select name="employee_id" required>
                        <option value="">Select employee</option>
                        <?php foreach ($employees as $employee): ?>
                            <option value="<?= (int) $employee['employee_id']; ?>"><?= $escape($employee['username'] . ' · ' . $employee['email']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>System role
                    <select name="role" required>
                        <?php foreach ($roles as $role => $label): ?><option value="<?= $escape($role); ?>"><?= $escape($label); ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label>Password (sent to the employee's email)
                    <input type="password" name="password" minlength="8" required autocomplete="new-password">
                    <small>At least 8 characters and one number. The employee can change it later in account settings.</small>
                </label>
                <button type="submit">Create account &amp; email password</button>
            </form>
            <?php if ($employees === []): ?><p class="admin-muted">All employee profiles with email addresses already have accounts.</p><?php endif; ?>
        </section>
        <section class="admin-panel">
            <div class="admin-panel-heading"><div><p class="admin-eyebrow">SYSTEM ACCESS</p><h2>Registered accounts</h2></div><span><?= $userCount; ?> accounts</span></div>
            <div class="admin-table-wrap"><table><thead><tr><th>Name</th><th>Profile picture</th><th>Email</th><th>Employee profile</th><th>Role</th><th>Account status</th><th>Reset password</th></tr></thead><tbody>
            <?php foreach ($accounts as $account): ?>
                <tr>
                    <td><?= $escape($account['name']); ?><?= (int) $account['id'] === $currentAdminId ? ' (you)' : ''; ?></td>
                    <td class="admin-profile-picture-cell">
                        <form method="post" enctype="multipart/form-data" class="admin-profile-picture-form">
                            <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['admin_csrf']); ?>">
                            <input type="hidden" name="action" value="update_profile_picture">
                            <input type="hidden" name="user_id" value="<?= (int) $account['id']; ?>">
                            <label class="admin-profile-picture-picker">
                                <input type="file" name="profile_picture" accept="image/jpeg,image/png,image/webp" required data-profile-picture-input aria-label="Choose profile picture for <?= $escape($account['name']); ?>">
                                <span class="admin-profile-picture-preview">
                                    <?php if (!empty($account['profile_picture']) && preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', (string) $account['profile_picture'])): ?>
                                        <img src="uploads/profile-pictures/<?= $escape($account['profile_picture']); ?>" alt="">
                                    <?php else: ?>
                                        <?= $escape(mb_strtoupper(mb_substr(trim((string) $account['name']), 0, 1)) ?: 'U'); ?>
                                    <?php endif; ?>
                                    <span class="admin-profile-picture-picker-icon" aria-hidden="true">+</span>
                                </span>
                                <span class="admin-profile-picture-picker-hint">Change photo</span>
                            </label>
                            <?php if (!empty($account['profile_picture']) && preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', (string) $account['profile_picture'])): ?>
                                <button type="button" class="admin-profile-picture-edit-button" data-profile-picture-edit>
                                    Edit current crop
                                </button>
                            <?php endif; ?>
                            <button type="submit" class="admin-secondary-button" data-profile-picture-submit hidden>Save photo</button>
                        </form>
                        <?php if (!empty($account['profile_picture'])): ?>
                            <form method="post" class="admin-profile-picture-remove" onsubmit="return confirm('Remove this profile picture?');">
                                <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['admin_csrf']); ?>">
                                <input type="hidden" name="action" value="remove_profile_picture">
                                <input type="hidden" name="user_id" value="<?= (int) $account['id']; ?>">
                                <button type="submit" class="admin-profile-picture-remove-button">Remove</button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td><?= $escape($account['email']); ?></td>
                    <td>
                        <form method="post" class="admin-inline-form">
                            <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['admin_csrf']); ?>">
                            <input type="hidden" name="action" value="link_employee">
                            <input type="hidden" name="user_id" value="<?= (int) $account['id']; ?>">
                            <select name="employee_id" aria-label="Employee profile for <?= $escape($account['name']); ?>">
                                <option value="0">Not linked</option>
                                <?php foreach ($employeeProfiles as $profile): ?>
                                    <option value="<?= (int) $profile['employee_id']; ?>" <?= (int) ($account['employee_id'] ?? 0) === (int) $profile['employee_id'] ? 'selected' : ''; ?>>
                                        <?= $escape($profile['username'] . ' · ' . $profile['email']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="admin-secondary-button">Link</button>
                        </form>
                    </td>
                    <td>
                        <?php if ((int) $account['id'] === $currentAdminId): ?>
                            <?= $escape($roles[$account['role']] ?? ucfirst($account['role'])); ?>
                        <?php else: ?>
                            <form method="post" class="admin-inline-form">
                                <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['admin_csrf']); ?>">
                                <input type="hidden" name="action" value="update_role">
                                <input type="hidden" name="user_id" value="<?= (int) $account['id']; ?>">
                                <select name="role" aria-label="Role for <?= $escape($account['name']); ?>">
                                    <?php foreach ($roles as $role => $label): ?><option value="<?= $escape($role); ?>" <?= $account['role'] === $role ? 'selected' : ''; ?>><?= $escape($label); ?></option><?php endforeach; ?>
                                </select>
                                <button type="submit" class="admin-secondary-button">Save</button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ((int) $account['id'] === $currentAdminId): ?>
                            <span class="admin-status is-active">Active</span>
                        <?php else: ?>
                            <form method="post" class="admin-inline-form" <?= (int) $account['is_active'] === 1 ? 'onsubmit="return confirm(\'Disable this account? It will be logged out and unable to sign in.\');"' : ''; ?>>
                                <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['admin_csrf']); ?>">
                                <input type="hidden" name="action" value="toggle_account">
                                <input type="hidden" name="user_id" value="<?= (int) $account['id']; ?>">
                                <input type="hidden" name="is_active" value="<?= (int) $account['is_active'] === 1 ? 0 : 1; ?>">
                                <span class="admin-status <?= (int) $account['is_active'] === 1 ? 'is-active' : 'is-disabled'; ?>"><?= (int) $account['is_active'] === 1 ? 'Active' : 'Disabled'; ?></span>
                                <button type="submit" class="admin-secondary-button"><?= (int) $account['is_active'] === 1 ? 'Disable' : 'Enable'; ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ((int) $account['id'] !== $currentAdminId): ?>
                            <form method="post" class="admin-inline-form">
                                <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['admin_csrf']); ?>">
                                <input type="hidden" name="action" value="reset_password">
                                <input type="hidden" name="user_id" value="<?= (int) $account['id']; ?>">
                                <input type="password" name="password" minlength="8" placeholder="Set new password" required autocomplete="new-password" aria-label="New password for <?= $escape($account['name']); ?>">
                                <button type="submit" class="admin-secondary-button">Set &amp; email</button>
                            </form>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($accounts === []): ?><tr><td colspan="7">No system accounts.</td></tr><?php endif; ?>
            </tbody></table></div>
        </section>
    <?php elseif ($section === 'audit'): ?>
        <section class="admin-panel">
            <div class="admin-panel-heading"><div><p class="admin-eyebrow">SYSTEM ACTIVITY</p><h2>Recent audit events</h2></div><span>Latest 100</span></div>
            <p class="admin-muted">Includes account, role, task, attendance, payroll, production, and logistics activity.</p>
            <div class="admin-table-wrap"><table><thead><tr><th>Date</th><th>Actor</th><th>Action</th><th>Record ID</th><th>Details</th></tr></thead><tbody>
            <?php foreach ($auditEntries as $entry): ?>
                <tr><td><?= $escape($entry['occurred_at']); ?></td><td><?= $escape($entry['actor_name']); ?></td><td><?= $escape(str_replace('_', ' ', ucfirst($entry['action_type']))); ?></td><td><?= $escape($entry['subject_id'] ?? '—'); ?></td><td><?= $escape($entry['details']); ?></td></tr>
            <?php endforeach; ?>
            <?php if ($auditEntries === []): ?><tr><td colspan="5">No system activity has been recorded.</td></tr><?php endif; ?>
            </tbody></table></div>
        </section>
    <?php else: ?>
        <section class="admin-panel">
            <div class="admin-panel-heading">
                <div><p class="admin-eyebrow">CONSOLIDATED REPORT</p><h2>System-wide operational summary</h2></div>
                <a class="admin-primary-link" href="admin.php?<?= $escape(http_build_query([
                    'section' => 'reports',
                    'range' => $reportRange,
                    'start_date' => $reportStartDate,
                    'end_date' => $reportEndDate,
                    'download' => 'csv',
                ])); ?>" title="Download the selected reporting period as a CSV file">Download CSV report</a>
            </div>
            <p class="admin-report-period">Showing <?= $escape($reportStart->format('M j, Y')); ?> to <?= $escape($reportEnd->format('M j, Y')); ?>.</p>
            <form method="get" class="admin-report-filter">
                <input type="hidden" name="section" value="reports">
                <input type="hidden" name="range" value="custom">
                <label>From
                    <input type="date" name="start_date" value="<?= $escape($reportStartDate); ?>" required>
                </label>
                <label>To
                    <input type="date" name="end_date" value="<?= $escape($reportEndDate); ?>" required>
                </label>
                <button type="submit" class="admin-secondary-button">Apply dates</button>
            </form>
            <nav class="admin-report-presets" aria-label="Quick report date ranges">
                <a class="<?= $reportRange === 'today' ? 'is-active' : ''; ?>" href="admin.php?section=reports&amp;range=today">Today</a>
                <a class="<?= $reportRange === 'week' ? 'is-active' : ''; ?>" href="admin.php?section=reports&amp;range=week">This week</a>
                <a class="<?= $reportRange === '7days' ? 'is-active' : ''; ?>" href="admin.php?section=reports&amp;range=7days">Last 7 days</a>
                <a class="<?= $reportRange === 'month' ? 'is-active' : ''; ?>" href="admin.php?section=reports&amp;range=month">This month</a>
                <a class="<?= $reportRange === 'year' ? 'is-active' : ''; ?>" href="admin.php?section=reports&amp;range=year">This year</a>
            </nav>
            <section class="admin-report-stat-grid" aria-label="Selected period totals">
                <article><span>Attendance records</span><strong><?= $reportAttendanceTotal; ?></strong></article>
                <article><span>Payroll credited</span><strong>₱<?= number_format($reportPayrollTotal, 2); ?></strong></article>
                <article><span>Office tasks submitted</span><strong><?= array_sum($reportTaskCounts); ?></strong></article>
                <article><span>Deliveries</span><strong><?= $reportDeliveryTotal; ?></strong></article>
            </section>
            <h3 class="admin-report-subheading">Trends and status graphs</h3>
            <div class="admin-report-charts">
                <article class="admin-report-chart">
                    <h3>Attendance volume</h3>
                    <p><?= $reportDays <= 31 ? 'Records per day' : 'Records per month'; ?></p>
                    <div class="admin-chart-bars" role="list" aria-label="Attendance record totals by period">
                        <?php foreach ($attendanceTrend as $point): ?>
                            <div class="admin-chart-row" role="listitem" aria-label="<?= $escape($point['label']); ?>: <?= (int) $point['value']; ?> records">
                                <span><?= $escape($point['label']); ?></span><div class="admin-chart-track" aria-hidden="true"><i style="width: <?= number_format(($point['value'] / $maxAttendanceTrend) * 100, 2, '.', ''); ?>%"></i></div><strong><?= (int) $point['value']; ?></strong>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($attendanceTrend === []): ?><p class="admin-chart-empty">No attendance records in this period.</p><?php endif; ?>
                    </div>
                </article>
                <article class="admin-report-chart">
                    <h3>Payroll credited</h3>
                    <p><?= $reportDays <= 31 ? 'Amount per day' : 'Amount per month'; ?></p>
                    <div class="admin-chart-bars" role="list" aria-label="Payroll credited totals by period">
                        <?php foreach ($payrollTrend as $point): ?>
                            <div class="admin-chart-row" role="listitem" aria-label="<?= $escape($point['label']); ?>: ₱<?= number_format($point['value'], 2); ?>">
                                <span><?= $escape($point['label']); ?></span><div class="admin-chart-track is-gold" aria-hidden="true"><i style="width: <?= number_format(($point['value'] / $maxPayrollTrend) * 100, 2, '.', ''); ?>%"></i></div><strong>₱<?= number_format($point['value'], 0); ?></strong>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($payrollTrend === []): ?><p class="admin-chart-empty">No payroll records in this period.</p><?php endif; ?>
                    </div>
                </article>
                <article class="admin-report-chart">
                    <h3>Delivery status</h3>
                    <p>Completion date, or scheduled date if not completed</p>
                    <div class="admin-chart-bars" role="list" aria-label="Delivery totals by status">
                        <?php foreach ($reportDeliveryCounts as $status => $count): ?>
                            <div class="admin-chart-row" role="listitem" aria-label="<?= $escape($status); ?>: <?= $count; ?> deliveries">
                                <span><?= $escape($status); ?></span><div class="admin-chart-track is-blue" aria-hidden="true"><i style="width: <?= number_format(($count / $maxDeliveryCount) * 100, 2, '.', ''); ?>%"></i></div><strong><?= $count; ?></strong>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($reportDeliveryCounts === []): ?><p class="admin-chart-empty">No deliveries in this period.</p><?php endif; ?>
                    </div>
                </article>
            </div>
            <div class="admin-report-grid">
                <article><h3>Accounts by role</h3><?php foreach ($roleCounts as $role => $count): ?><p><span><?= $escape($roles[$role] ?? ucfirst($role)); ?></span><strong><?= $count; ?></strong></p><?php endforeach; ?></article>
                <article><h3>Attendance · selected period</h3><?php foreach ($reportAttendanceCounts as $status => $count): ?><p><span><?= $escape(ucfirst($status)); ?></span><strong><?= $count; ?></strong></p><?php endforeach; ?><?php if ($reportAttendanceCounts === []): ?><p><span>No records</span><strong>0</strong></p><?php endif; ?></article>
                <article><h3>Office tasks · submitted in period</h3><?php foreach ($reportTaskCounts as $status => $count): ?><p><span><?= $escape(ucfirst($status)); ?></span><strong><?= $count; ?></strong></p><?php endforeach; ?><?php if ($reportTaskCounts === []): ?><p><span>No records</span><strong>0</strong></p><?php endif; ?></article>
                <article><h3>Payroll · selected period</h3><?php foreach ($reportPayrollCounts as $status => $totals): ?><p><span><?= $escape($status); ?></span><strong><?= $totals['count']; ?> · ₱<?= number_format($totals['amount'], 2); ?></strong></p><?php endforeach; ?><?php if ($reportPayrollCounts === []): ?><p><span>No records</span><strong>0</strong></p><?php endif; ?></article>
                <article><h3>Production stages · assigned in period</h3><?php foreach ($reportProductionCounts as $row): ?><p><span><?= $escape($row['work_type'] . ' · ' . $row['work_status']); ?></span><strong><?= (int) $row['item_count']; ?></strong></p><?php endforeach; ?><?php if ($reportProductionCounts === []): ?><p><span>No records</span><strong>0</strong></p><?php endif; ?></article>
                <article><h3>Deliveries · selected period</h3><?php foreach ($reportDeliveryCounts as $status => $count): ?><p><span><?= $escape($status); ?></span><strong><?= $count; ?></strong></p><?php endforeach; ?><?php if ($reportDeliveryCounts === []): ?><p><span>No records</span><strong>0</strong></p><?php endif; ?></article>
            </div>
        </section>
    <?php endif; ?>
</main>
<dialog class="admin-profile-crop-dialog" data-profile-crop-dialog aria-labelledby="admin-profile-crop-title">
    <div class="admin-profile-crop-content">
        <div class="admin-profile-crop-heading">
            <div>
                <p class="admin-eyebrow">PROFILE PHOTO</p>
                <h2 id="admin-profile-crop-title">Crop your photo</h2>
            </div>
            <button type="button" class="admin-profile-crop-close" data-profile-crop-cancel aria-label="Cancel crop">×</button>
        </div>
        <p class="admin-profile-crop-help">Drag the image to reposition it. Use the slider to zoom in or out.</p>
        <div class="admin-profile-crop-stage">
            <canvas data-profile-crop-canvas width="512" height="512" tabindex="0" aria-label="Square photo crop area. Use arrow keys to reposition the image."></canvas>
            <span class="admin-profile-crop-loading" data-profile-crop-loading>Loading image…</span>
        </div>
        <label class="admin-profile-crop-zoom" for="admin-profile-crop-zoom">
            <span>Zoom</span>
            <input id="admin-profile-crop-zoom" type="range" min="1" max="3" step="0.01" value="1" data-profile-crop-zoom disabled>
        </label>
        <p class="admin-profile-crop-error" data-profile-crop-error role="alert" hidden></p>
        <div class="admin-profile-crop-actions">
            <button type="button" class="admin-profile-crop-cancel-button" data-profile-crop-cancel>Cancel</button>
            <button type="button" class="admin-secondary-button" data-profile-crop-apply disabled>Use crop</button>
        </div>
    </div>
</dialog>
<script src="js/select-control.js?v=<?= filemtime(__DIR__ . '/js/select-control.js'); ?>"></script>
<script src="js/admin-profile-picture.js?v=<?= filemtime(__DIR__ . '/js/admin-profile-picture.js'); ?>"></script>
</body>
</html>
