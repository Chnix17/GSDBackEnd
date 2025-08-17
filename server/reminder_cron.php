<?php
// reminder_cron.php
// Server-side script to send automatic reminders every minute based on fetchAssignedRelease
// Configure Windows Task Scheduler to run this script every 1 minute:
// Program/script: C:\xampp\php\php.exe
// Arguments: c:\xampp\htdocs\coc\gsd\reminder_cron.php

// Ensure consistent timezone
date_default_timezone_set('Asia/Manila');

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Load DB connection
require_once __DIR__ . '/../connection-pdo.php';
require_once __DIR__ . '/../personnel.php';
// Quick runner guard: allow CLI and HTTP
$isCli = (php_sapi_name() === 'cli');

function log_line($msg) {
    $ts = date('Y-m-d H:i:s');
    $line = "[$ts] $msg\n";
    if (php_sapi_name() === 'cli') {
        echo $line;
    } else {
        echo nl2br(htmlentities($line));
    }
}

try {
    // 1) Find personnel by user level (user_level_id = 2)
    $sql = "SELECT users_id FROM tbl_users WHERE users_user_level_id = 2 AND is_active = 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $subs = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!$subs) {
        log_line('No active subscriptions found.');
        exit(0);
    }

    $user = new User();

    $sentCount = 0;
    foreach ($subs as $personnelId) {
        $personnelId = (int)$personnelId;

        // Ensure this personnel has an active push subscription before proceeding
        $chk = $conn->prepare('SELECT 1 FROM tbl_push_subscriptions WHERE user_id = :uid AND is_active = 1 LIMIT 1');
        $chk->execute([':uid' => $personnelId]);
        if (!$chk->fetchColumn()) {
            log_line("No active subscription for personnel_id=$personnelId");
            continue;
        }

        // 2) Use existing fetchAssignedRelease to see if personnel has available tasks
        $json = $user->fetchAssignedRelease($personnelId);
        $res = json_decode($json, true);
        if (!is_array($res) || ($res['status'] ?? 'error') !== 'success') {
            log_line("fetchAssignedRelease failed for personnel_id=$personnelId: " . substr($json, 0, 200));
            continue;
        }

        $data = $res['data'] ?? [];
        if (empty($data)) {
            log_line("No tasks for personnel_id=$personnelId");
            continue;
        }

        // Notify at thresholds before start (with ±1 minute tolerance):
        // 1 day (1440), 5 hours (300), 2 hours (120), 1 hour (60)
        $thresholds = [1440 => '1 day', 300 => '5 hours', 120 => '2 hours', 60 => '1 hour'];

        // Build absolute URL to sender once
        if ($isCli) {
            $base = 'http://localhost/coc/gsd';
        } else {
            $scheme = isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http');
            $base = $scheme . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['REQUEST_URI']), '/');
        }
        $url = $base . '/send-push-notification.php';

        $now = time();
        foreach ($data as $reservation) {
            if (empty($reservation['reservation_start_date'])) { continue; }
            $startTs = strtotime($reservation['reservation_start_date']);
            if ($startTs === false) { continue; }

            $minsUntilStart = (int) floor(($startTs - $now) / 60);
            // Match within ±1 minute tolerance
            $matchedThreshold = null;
            $whenLabel = null;
            foreach ($thresholds as $t => $label) {
                if (abs($minsUntilStart - $t) <= 1) { $matchedThreshold = $t; $whenLabel = $label; break; }
            }
            if ($matchedThreshold === null) { continue; }
            $title = 'GSD Task Reminder';
            $resTitle = $reservation['reservation_title'] ?? 'Reservation';
            $startFmt = date('M d, Y h:i A', $startTs);
            $startTimeOnly = date('g:i A', $startTs);
            $todayYmd = date('Y-m-d', $now);
            $startYmd = date('Y-m-d', $startTs);
            $isToday = ($startYmd === $todayYmd);
            $isTomorrow = ($startYmd === date('Y-m-d', strtotime('+1 day', $now)));
            // All reminders are about the reservation start time (task/checklist are the same)
            if ($matchedThreshold === 1440 && $isTomorrow) {
                $body = "$resTitle Checklist starts tomorrow at $startTimeOnly.";
            } elseif (in_array($matchedThreshold, [300, 120, 60], true) && $isToday) {
                // Hour-based reminders on the same day
                if ($matchedThreshold === 300) {
                    $body = "$resTitle Checklist starts today at $startTimeOnly (in 5 hours).";
                } elseif ($matchedThreshold === 120) {
                    $body = "$resTitle Checklist starts today at $startTimeOnly (in 2 hours).";
                } else { // 60
                    $body = "$resTitle Checklist starts today at $startTimeOnly (in 1 hour).";
                }
            } else {
                // Fallback phrasing if date math doesn't align as expected
                $body = "$resTitle Checklist starts at $startFmt ($whenLabel from now).";
            }

            $payload = [
                'operation' => 'send',
                'user_id'   => $personnelId,
                'title'     => $title,
                'body'      => $body,
                'data'      => [ 'url' => '/ViewRequest' ],
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($resp === false) {
                log_line("Push send cURL error for personnel_id=$personnelId: " . curl_error($ch));
            } else {
                log_line("Push sent to personnel_id=$personnelId for reservation '$resTitle' ($whenLabel), HTTP=$code, resp=" . substr($resp, 0, 200));
                if ($code >= 200 && $code < 300) { $sentCount++; }
            }
            curl_close($ch);
        }
    }

    log_line("Done. Notifications sent: $sentCount");
    exit(0);
} catch (Throwable $e) {
    log_line('Fatal error: ' . $e->getMessage());
    exit(1);
}
