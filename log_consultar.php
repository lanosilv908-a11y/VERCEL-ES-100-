<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/app_storage.php';

$raw = @app_storage_get('click_stats.json');
$clickStats = ['consultar_clicks' => 0, 'enter_clicks' => 0];

if ($raw !== null && $raw !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $clickStats['consultar_clicks'] = isset($decoded['consultar_clicks']) ? (int)$decoded['consultar_clicks'] : 0;
        $clickStats['enter_clicks']     = isset($decoded['enter_clicks'])     ? (int)$decoded['enter_clicks']     : 0;
    }
}

$clickStats['consultar_clicks']++;

@app_storage_put('click_stats.json', json_encode($clickStats, JSON_PRETTY_PRINT));

echo json_encode(['success' => true]);
