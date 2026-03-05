<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Connection;

class EmailNotificationRepository
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function findQueued(int $limit = 50): array
    {
        $stmt = $this->db->execute(
            'SELECT * FROM email_notifications WHERE status = ? AND attempts < 3 ORDER BY created_at ASC LIMIT ?',
            ['queued', $limit]
        );
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $this->db->execute(
            'INSERT INTO email_notifications (notification_type, recipient_email, recipient_user_id, subject, body_html, body_text, entity_type, entity_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['notification_type'], $data['recipient_email'], $data['recipient_user_id'] ?? null,
                $data['subject'], $data['body_html'], $data['body_text'] ?? null,
                $data['entity_type'] ?? null, $data['entity_id'] ?? null
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    public function markSent(int $id): void
    {
        $this->db->execute(
            'UPDATE email_notifications SET status = ?, sent_at = NOW(), attempts = attempts + 1 WHERE id = ?',
            ['sent', $id]
        );
    }

    public function markFailed(int $id, string $error): void
    {
        $this->db->execute(
            'UPDATE email_notifications SET status = CASE WHEN attempts >= 2 THEN ? ELSE ? END, last_error = ?, attempts = attempts + 1 WHERE id = ?',
            ['failed', 'queued', $error, $id]
        );
    }
}
