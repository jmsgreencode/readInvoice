<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\EmailNotificationRepository;
use App\Logging\StructuredLogger;

class EmailNotificationService
{
    private EmailNotificationRepository $repo;
    private StructuredLogger $logger;

    public function __construct(EmailNotificationRepository $repo, StructuredLogger $logger)
    {
        $this->repo = $repo;
        $this->logger = $logger;
    }

    public function queueNotification(string $type, string $recipientEmail, ?int $userId, string $subject, string $bodyHtml, ?string $bodyText = null, ?string $entityType = null, ?int $entityId = null): int
    {
        return $this->repo->create([
            'notification_type' => $type,
            'recipient_email' => $recipientEmail,
            'recipient_user_id' => $userId,
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);
    }

    public function processQueue(int $batchSize = 50): int
    {
        $notifications = $this->repo->findQueued($batchSize);
        $sent = 0;

        $smtpHost = getenv('SMTP_HOST') ?: 'mailhog';
        $smtpPort = (int)(getenv('SMTP_PORT') ?: 1025);

        foreach ($notifications as $notif) {
            try {
                $this->sendEmail($notif, $smtpHost, $smtpPort);
                $this->repo->markSent((int)$notif['id']);
                $sent++;
            } catch (\Exception $e) {
                $this->repo->markFailed((int)$notif['id'], $e->getMessage());
                $this->logger->error('Email send failed', ['id' => $notif['id'], 'error' => $e->getMessage()]);
            }
        }

        return $sent;
    }

    private function sendEmail(array $notif, string $smtpHost, int $smtpPort): void
    {
        // Use PHPMailer if available, otherwise basic SMTP
        if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->Port = $smtpPort;
            $mail->SMTPAuth = false;

            $smtpUser = getenv('SMTP_USER');
            if ($smtpUser) {
                $mail->SMTPAuth = true;
                $mail->Username = $smtpUser;
                $mail->Password = getenv('SMTP_PASS') ?: '';
            }

            $encryption = getenv('SMTP_ENCRYPTION');
            if ($encryption) {
                $mail->SMTPSecure = $encryption;
            }

            $mail->setFrom(
                getenv('SMTP_FROM_EMAIL') ?: 'noreply@procom.local',
                getenv('SMTP_FROM_NAME') ?: 'proCom System'
            );
            $mail->addAddress($notif['recipient_email']);
            $mail->isHTML(true);
            $mail->Subject = $notif['subject'];
            $mail->Body = $notif['body_html'];
            if ($notif['body_text']) {
                $mail->AltBody = $notif['body_text'];
            }

            $mail->send();
        } else {
            // Fallback: log only
            $this->logger->info('Email would be sent (PHPMailer not installed)', [
                'to' => $notif['recipient_email'],
                'subject' => $notif['subject'],
            ]);
        }
    }
}
