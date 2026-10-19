<?php
$hrSidebarName = trim((string) ($_SESSION['name'] ?? ''));
if ($hrSidebarName === '') {
    $hrSidebarName = trim((string) ($_SESSION['email'] ?? ''));
}
if ($hrSidebarName === '') {
    $hrSidebarName = 'HR User';
}

$hrSidebarProfilePicture = null;
if (isset($conn, $_SESSION['id']) && $conn instanceof mysqli) {
    $hrSidebarProfileStmt = $conn->prepare('SELECT profile_picture FROM users WHERE id = ? LIMIT 1');
    if (!$hrSidebarProfileStmt) {
        throw new RuntimeException('Unable to prepare the HR sidebar profile picture lookup: ' . $conn->error);
    }
    $hrSidebarUserId = (int) $_SESSION['id'];
    $hrSidebarProfileStmt->bind_param('i', $hrSidebarUserId);
    if (!$hrSidebarProfileStmt->execute()) {
        $error = $hrSidebarProfileStmt->error;
        $hrSidebarProfileStmt->close();
        throw new RuntimeException('Unable to load the HR sidebar profile picture: ' . $error);
    }
    $hrSidebarProfilePicture = $hrSidebarProfileStmt->get_result()->fetch_assoc()['profile_picture'] ?? null;
    $hrSidebarProfileStmt->close();
    if (
        !is_string($hrSidebarProfilePicture)
        || !preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $hrSidebarProfilePicture)
    ) {
        $hrSidebarProfilePicture = null;
    }
}

$hrSidebarInitial = mb_strtoupper(mb_substr($hrSidebarName, 0, 1));
?>
<a class="hr-brand" href="HR.php" aria-label="HR home">
    <span class="hr-brand-mark" aria-hidden="true">
        <?php if ($hrSidebarProfilePicture !== null): ?>
            <img src="uploads/profile-pictures/<?= htmlspecialchars($hrSidebarProfilePicture, ENT_QUOTES, 'UTF-8'); ?>" alt="">
        <?php else: ?>
            <?= htmlspecialchars($hrSidebarInitial !== '' ? $hrSidebarInitial : 'U', ENT_QUOTES, 'UTF-8'); ?>
        <?php endif; ?>
    </span>
</a>
