<?php
require_once __DIR__ . '/app_storage.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$raw = @app_storage_get('click_stats.json');
$clickStats = ['consultar_clicks' => 0, 'enter_clicks' => 0];
if ($raw !== null && $raw !== '') {
    $current = json_decode($raw, true);
    if (is_array($current)) {
        $clickStats['consultar_clicks'] = isset($current['consultar_clicks']) ? (int)$current['consultar_clicks'] : 0;
        $clickStats['enter_clicks']     = isset($current['enter_clicks'])     ? (int)$current['enter_clicks']     : 0;
    }
}

$clickStats['enter_clicks']++;
@app_storage_put('click_stats.json', json_encode($clickStats, JSON_PRETTY_PRINT));

echo json_encode(['success' => true, 'enter_clicks' => $clickStats['enter_clicks']]);
