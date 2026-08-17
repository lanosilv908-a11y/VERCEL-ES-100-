<?php

/**
 * Hybrid Storage — Upstash Redis (Vercel) + Arquivos Locais (XAMPP)
 *
 * Variáveis de ambiente para produção (Vercel):
 *   UPSTASH_REDIS_REST_URL   ou  KV_URL
 *   UPSTASH_REDIS_REST_TOKEN ou  KV_REST_API_TOKEN
 *
 * Sem essas variáveis, usa arquivos locais (desenvolvimento XAMPP).
 *
 * Crie uma conta gratuita em https://upstash.com → Redis → REST API
 * Copie a URL e o token para as env vars do Vercel.
 */

function _s_is_redis() {
    static $checked = null;
    if ($checked !== null) return $checked;
    $url  = trim(getenv('UPSTASH_REDIS_REST_URL') ?: getenv('KV_URL') ?: '');
    $tok  = trim(getenv('UPSTASH_REDIS_REST_TOKEN') ?: getenv('KV_REST_API_TOKEN') ?: '');
    $checked = ($url !== '' && $tok !== '');
    return $checked;
}

function _s_redis_call($op, $key, $value = null) {
    $base = rtrim(getenv('UPSTASH_REDIS_REST_URL') ?: getenv('KV_URL'), '/');
    $tok  = getenv('UPSTASH_REDIS_REST_TOKEN') ?: getenv('KV_REST_API_TOKEN');
    $url  = $base . '/' . $op . '/' . urlencode('app:' . $key);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $tok]);

    if ($op === 'set' && $value !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, (string)$value);
    } elseif ($op === 'del') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }

    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return ['code' => $code, 'body' => $body];
}

// =====================================================================
// API PÚBLICA — use estas funções em vez de file_get/put_contents
// =====================================================================

function app_storage_get($file) {
    $key = basename($file);
    if (_s_is_redis()) {
        $res = _s_redis_call('get', $key);
        if ($res['code'] === 200) {
            $d = json_decode($res['body'], true);
            if (isset($d['result']) && $d['result'] !== null) {
                return $d['result'];
            }
        }
        return null;
    }
    $path = _s_local_dir() . DIRECTORY_SEPARATOR . $key;
    if (is_file($path)) {
        $c = @file_get_contents($path);
        return $c !== false ? $c : null;
    }
    return null;
}

function app_storage_put($file, $value) {
    $key = basename($file);
    if (_s_is_redis()) {
        _s_redis_call('set', $key, $value);
        return true;
    }
    $path = _s_local_dir() . DIRECTORY_SEPARATOR . $key;
    $dir  = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    return @file_put_contents($path, (string)$value, LOCK_EX) !== false;
}

function app_storage_exists($file) {
    $key = basename($file);
    if (_s_is_redis()) {
        $res = _s_redis_call('exists', $key);
        if ($res['code'] === 200) {
            $d = json_decode($res['body'], true);
            return (int)($d['result'] ?? 0) > 0;
        }
        return false;
    }
    return is_file(_s_local_dir() . DIRECTORY_SEPARATOR . $key);
}

function app_storage_del($file) {
    $key = basename($file);
    if (_s_is_redis()) {
        _s_redis_call('del', $key);
        return true;
    }
    $path = _s_local_dir() . DIRECTORY_SEPARATOR . $key;
    if (is_file($path)) @unlink($path);
    return true;
}

// =====================================================================
// Retorna o conteúdo diretamente (para storage.php / readfile)
// =====================================================================

function app_storage_output($file) {
    $raw = app_storage_get($file);
    if ($raw !== null) {
        echo $raw;
        return true;
    }
    return false;
}

// =====================================================================
// Funções auxiliares mantidas para compatibilidade
// =====================================================================

function _s_local_dir() {
    static $dir = null;
    if ($dir !== null) return $dir;

    $candidates = array_values(array_filter([
        getenv('TMPDIR') ?: null,
        getenv('TEMP')   ?: null,
        getenv('TMP')    ?: null,
        sys_get_temp_dir(),
        __DIR__ . DIRECTORY_SEPARATOR . '.data',
    ]));

    foreach ($candidates as $c) {
        $base = rtrim($c, DIRECTORY_SEPARATOR);
        if ($base === '') continue;
        $dir = $base . DIRECTORY_SEPARATOR . 'vercel-php-app' . DIRECTORY_SEPARATOR . sha1(__DIR__);
        if (is_dir($dir)) return $dir;
        if (@mkdir($dir, 0777, true) || is_dir($dir)) return $dir;
    }
    return __DIR__;
}

function app_storage_root_dir() {
    return _s_local_dir();
}

function app_storage_seed_map() {
    return [
        'admin_ips.json'        => "[]\n",
        'click_stats.json'      => "{\n    \"consultar_clicks\": 0,\n    \"enter_clicks\": 0\n}\n",
        'consultados_log.json'  => "[]\n",
        'pix_config.json'       => "{}\n",
        'pix_config_admin.json' => "{}\n",
        'pix_last.json'         => "[]\n",
        'pix_log.json'          => "[]\n",
        'pix_log_oculto.json'   => "[]\n",
        'pix_mode.txt'          => "desativo\n",
        'search_log.json'       => "[]\n",
        'stats.json'            => "{\"index_clicks2\":0,\"pix_generated\":0}\n",
    ];
}

/**
 * Retorna caminho do arquivo local (apenas para dev).
 * No Vercel com Redis, NÃO use esta função.
 * Use app_storage_get / app_storage_put.
 */
function app_storage_path($file) {
    $safeFile = basename($file);
    $path = _s_local_dir() . DIRECTORY_SEPARATOR . $safeFile;

    if (!is_file($path)) {
        $seedMap = app_storage_seed_map();
        $defaultContent = $seedMap[$safeFile] ?? '';
        @file_put_contents($path, $defaultContent, LOCK_EX);
    }
    return $path;
}
