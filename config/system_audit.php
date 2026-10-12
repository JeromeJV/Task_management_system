<?php

function recordSystemAudit(
    mysqli $conn,
    ?int $actorUserId,
    string $actorName,
    string $actionType,
    string $subjectType,
    ?int $subjectId,
    string $details
): void {
    $stmt = $conn->prepare(
        'INSERT INTO system_audit_log
            (actor_user_id, actor_name, action_type, subject_type, subject_id, details)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare system audit history: ' . $conn->error);
    }
    $stmt->bind_param('isssis', $actorUserId, $actorName, $actionType, $subjectType, $subjectId, $details);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Unable to record system audit history: ' . $error);
    }
    $stmt->close();
}
