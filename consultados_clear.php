<?php
require_once __DIR__ . '/app_storage.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    app_storage_put('consultados_log.json', json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    echo json_encode(['success'=>true]);
} else {
    http_response_code(405);
}
?>
