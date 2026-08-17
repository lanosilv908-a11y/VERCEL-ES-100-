<?php
require_once __DIR__ . '/app_storage.php';
$raw = app_storage_get('stats.json');
$stats = ['index_clicks2'=>0,'pix_generated'=>0];
if ($raw !== null) {
    $prev = json_decode($raw, true);
    if (is_array($prev)) { $stats = array_merge($stats, $prev); }
}
$stats['index_clicks2'] = (int)($stats['index_clicks2'] ?? 0) + 1;
app_storage_put('stats.json', json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['status'=>'ok','index_clicks2'=>$stats['index_clicks2']]);
