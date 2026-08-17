<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/app_storage.php';
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$rawMode = app_storage_get('pix_mode.txt');
$isModeActive = false;
if ($rawMode !== null) {
    $modeContent = trim(strtolower($rawMode));
    if ($modeContent === 'ativo' || $modeContent === '1' || $modeContent === 'true') {
        $isModeActive = true;
    }
}

if ($isModeActive) {
    $rawCfg = app_storage_get('pix_config_admin.json');
} else {
    $rawCfg = app_storage_get('pix_config.json');
}

$pixKey = '';
if ($rawCfg !== null) {
    $cfg = json_decode($rawCfg, true);
    if (is_array($cfg) && isset($cfg['pixKey']) && is_string($cfg['pixKey']) && $cfg['pixKey']!=='') {
        $pixKey = $cfg['pixKey'];
    }
}

// Fallback logic if needed, similar to api_new.php or debitos.php
if ($pixKey === '') {
    // Default fallback or check other config
    // For now, let's just return what we found or empty
}

echo json_encode(['key' => $pixKey, 'mode' => $isModeActive ? 'active' : 'inactive']);
