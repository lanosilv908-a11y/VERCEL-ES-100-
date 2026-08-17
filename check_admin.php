<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/app_storage.php';

$currentIp = $_SERVER['REMOTE_ADDR'];
$raw = app_storage_get('admin_ips.json');
$isAdmin = false;

if ($raw !== null) {
    $adminIps = json_decode($raw, true) ?: [];
    if (in_array($currentIp, $adminIps)) {
        $isAdmin = true;
    }
}

echo json_encode(['isAdmin' => $isAdmin]);
exit;
