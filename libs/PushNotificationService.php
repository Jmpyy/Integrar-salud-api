<?php
// backend/libs/PushNotificationService.php

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class PushNotificationService {
    private $db;
    private $webPush = null;

    public function __construct($db) {
        $this->db = $db;
        $this->initWebPush();
    }

    private function initWebPush() {
        if (!class_exists('Minishlink\WebPush\WebPush')) {
            // Autoloader via composer
            $autoloadPath = __DIR__ . '/../vendor/autoload.php';
            if (file_exists($autoloadPath)) {
                require_once $autoloadPath;
            } else {
                error_log("PushNotificationService: No se encontró vendor/autoload.php. Composer no instalado.");
                return;
            }
        }

        try {
            $stmt = $this->db->query("SELECT public_key, private_key FROM vapid_keys ORDER BY id DESC LIMIT 1");
            $keys = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($keys) {
                $auth = [
                    'VAPID' => [
                        'subject' => 'mailto:contacto@integrarsalud.com',
                        'publicKey' => $keys['public_key'],
                        'privateKey' => $keys['private_key'],
                    ],
                ];
                $this->webPush = new WebPush($auth);
            }
        } catch (Exception $e) {
            error_log("PushNotificationService Error: " . $e->getMessage());
        }
    }

    /**
     * Send notification to a specific user
     */
    public function notifyUser($userId, $title, $body, $url = '/dashboard') {
        if (!$this->webPush) return false;

        try {
            $stmt = $this->db->prepare("SELECT id, endpoint, p256dh, auth FROM push_subscriptions WHERE user_id = ?");
            $stmt->execute([$userId]);
            $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($subs)) return false;

            $payload = json_encode([
                'title' => $title,
                'body' => $body,
                'url' => $url,
                'icon' => '/pwa-192x192.png'
            ]);

            $sent = 0;
            foreach ($subs as $subData) {
                $subscription = Subscription::create([
                    'endpoint' => $subData['endpoint'],
                    'keys' => [
                        'p256dh' => $subData['p256dh'],
                        'auth' => $subData['auth']
                    ]
                ]);

                // Queue the notification
                $this->webPush->queueNotification($subscription, $payload);
            }

            // Flush (send) queued notifications
            foreach ($this->webPush->flush() as $report) {
                $endpoint = $report->getRequest()->getUri()->__toString();

                if ($report->isSuccess()) {
                    $sent++;
                } else {
                    error_log("Push Failed for {$endpoint}: {$report->getReason()}");
                    
                    // If the subscription is expired or invalid, remove it from the DB
                    if ($report->isSubscriptionExpired()) {
                        $del = $this->db->prepare("DELETE FROM push_subscriptions WHERE endpoint = ?");
                        $del->execute([$endpoint]);
                    }
                }
            }

            return $sent > 0;
        } catch (Exception $e) {
            error_log("PushNotificationService Notify Error: " . $e->getMessage());
            return false;
        }
    }
}
