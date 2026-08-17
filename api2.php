<?php
require_once __DIR__ . '/app_storage.php';
error_reporting(0);
ini_set('display_errors', 0);
date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function respondJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function normalizeText($value, string $fallback = '-'): string
{
    if (is_array($value)) {
        $value = implode(' / ', array_filter(array_map('trim', $value), static function ($item) {
            return $item !== '';
        }));
    }

    $value = trim((string) ($value ?? ''));
    return $value !== '' ? $value : $fallback;
}

function normalizeHtmlText($value, string $fallback = '-'): string
{
    return nl2br(e(normalizeText($value, $fallback)));
}

function joinCodeAndDescription($code, $description, string $fallback = '-'): string
{
    $code = trim((string) ($code ?? ''));
    $description = trim((string) ($description ?? ''));

    if ($code !== '' && $description !== '') {
        return $code . ' - ' . $description;
    }

    if ($code !== '') {
        return $code;
    }

    if ($description !== '') {
        return $description;
    }

    return $fallback;
}

function formatDateBr($value, string $fallback = '-'): string
{
    $value = trim((string) ($value ?? ''));
    if ($value === '') {
        return $fallback;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    return date('d/m/Y', $timestamp);
}

function formatMoneyBr($value): string
{
    return number_format((float) ($value ?? 0), 2, ',', '.');
}

function boolToText($value, string $fallback = 'Não'): string
{
    if (is_bool($value)) {
        return $value ? 'Sim' : 'Não';
    }

    if ($value === null) {
        return $fallback;
    }

    $normalized = trim((string) $value);
    if ($normalized === '') {
        return $fallback;
    }

    $lower = strtolower($normalized);
    if (in_array($lower, ['sim', 'true', '1'], true)) {
        return 'Sim';
    }
    if (in_array($lower, ['nao', 'não', 'false', '0'], true)) {
        return 'Não';
    }

    return $normalized;
}

function renderListItem(string $label, string $valueHtml, string $valueClass = 'lh-1'): string
{
    return '<li class="list-group-item ps-0">'
        . '<div class="small text-muted">' . e($label) . '</div>'
        . '<div class="' . e($valueClass) . '">' . $valueHtml . '</div>'
        . '</li>';
}

function getCapsolverToken(string $key): ?string
{
    $url = 'https://api.capsolver.com/createTask';
    $payload = json_encode([
        'clientKey' => $key,
        'task' => [
            'type' => 'AntiTurnstileTaskProxyLess',
            'websiteURL' => 'https://servicos.detrannet.es.gov.br/',
            'websiteKey' => '0x4AAAAAAAy6XXSbwPTDYHHM',
            'metadata' => ['action' => 'login'],
        ],
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (!isset($response['taskId'])) {
        return null;
    }

    $taskId = $response['taskId'];

    while (true) {
        sleep(3);
        $ch = curl_init('https://api.capsolver.com/getTaskResult');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['clientKey' => $key, 'taskId' => $taskId]));
        $result = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (($result['status'] ?? null) === 'ready') {
            return $result['solution']['token'] ?? null;
        }

        if (($result['status'] ?? null) === 'failed') {
            return null;
        }
    }
}

function loadApiCookie(): string
{
    if (function_exists('app_storage_get')) {
        $rawCfg = app_storage_get('pix_config.json');
        if ($rawCfg !== null) {
            $cfg = json_decode($rawCfg, true);
            if (is_array($cfg) && !empty($cfg['apiCookie'])) {
                return trim((string) $cfg['apiCookie']);
            }
        }
    }

    $cfgPath = __DIR__ . '/pix_config.json';
    if (!file_exists($cfgPath)) {
        return '';
    }

    $cfg = json_decode(@file_get_contents($cfgPath), true);
    if (!is_array($cfg)) {
        return '';
    }

    return trim((string) ($cfg['apiCookie'] ?? ''));
}

function buildCookieHeader(array $cookies): string
{
    $normalized = [];

    foreach ($cookies as $cookie) {
        $cookie = trim((string) $cookie);
        if ($cookie === '') {
            continue;
        }

        $cookie = trim($cookie, " \t\n\r\0\x0B;");
        if ($cookie !== '') {
            $normalized[] = $cookie;
        }
    }

    return implode('; ', array_values(array_unique($normalized)));
}

function warmUpDossieSession(string $idServico, string $cookieHeader): void
{
    if ($idServico === '' || $cookieHeader === '') {
        return;
    }

    $url = 'https://servicos.detrannet.es.gov.br/Dossie?idServico=' . urlencode($idServico);
    $headers = [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
        'Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
        'Connection: keep-alive',
        'Cookie: ' . $cookieHeader,
        'Host: servicos.detrannet.es.gov.br',
        'Referer: https://servicos.detrannet.es.gov.br/CentralVeiculo?Servico=DossieConsolidadoVeiculo',
        'sec-ch-ua: "Chromium";v="146", "Not-A.Brand";v="24", "Google Chrome";v="146"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'Sec-Fetch-Dest: document',
        'Sec-Fetch-Mode: navigate',
        'Sec-Fetch-Site: same-origin',
        'Sec-Fetch-User: ?1',
        'Upgrade-Insecure-Requests: 1',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function fetchConsultarVeiculo(string $placa, string $renavam, string $turnstileToken, string $cookie): array
{
    $url = 'https://servicos.detrannet.es.gov.br/CentralVeiculo/ConsultarVeiculo';
    $jsonData = json_encode([
        'Servico' => 'DossieConsolidadoVeiculo',
        'Placa' => $placa,
        'Renavam' => $renavam,
        'TurnstileToken' => $turnstileToken,
    ]);

    $headers = [
        'Accept: */*',
        'Accept-Encoding: gzip, deflate, br, zstd',
        'Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
        'Connection: keep-alive',
        'Content-Type: application/json',
        'Cookie: ' . buildCookieHeader([$cookie]),
        'Host: servicos.detrannet.es.gov.br',
        'Origin: https://servicos.detrannet.es.gov.br',
        'Referer: https://servicos.detrannet.es.gov.br/CentralVeiculo?Servico=DossieConsolidadoVeiculo',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',
        'sec-ch-ua: "Chromium";v="146", "Not-A.Brand";v="24", "Google Chrome";v="146"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'Sec-Fetch-Dest: empty',
        'Sec-Fetch-Mode: cors',
        'Sec-Fetch-Site: same-origin',
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_HEADER, true);

    $response = curl_exec($ch);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headerContent = substr((string) $response, 0, $headerSize);
    curl_close($ch);

    if (!$response || !preg_match('/set-cookie:\s*([^;]+)/i', $headerContent, $matches)) {
        return [null, null];
    }

    $cookieCompleto = trim($matches[1]);
    $partes = explode('=', $cookieCompleto, 2);

    return [$partes[0] ?? null, $cookieCompleto . ';'];
}

function fetchJsonFromDetran(string $url, array $headers): ?array
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $decoded = json_decode((string) $response, true);
    return is_array($decoded) ? $decoded : null;
}

function buildGridWrapperHtml(array $vehicleData): string
{
    $dadosVeiculo = $vehicleData['dadosVeiculo'] ?? [];
    $dadosComplementares = $vehicleData['dadosComplementares'] ?? [];

    $pendenciasSng = $dadosComplementares['pendenciasSng'] ?? [];
    $pendenciasSng = is_array($pendenciasSng) ? implode("\n", array_filter(array_map('trim', $pendenciasSng))) : $pendenciasSng;

    $leftColumn = [
        renderListItem('Placa', '<span class="fs-5 fw-bold">' . e(normalizeText($dadosVeiculo['placa'] ?? '')) . '</span>'),
        renderListItem('Tipo', '<span class="fw-bold">' . e(joinCodeAndDescription($dadosVeiculo['codigoTipoVeiculo'] ?? '', $dadosVeiculo['tipoVeiculo'] ?? '')) . '</span>', 'lh-1 d-flex align-items-center'),
        renderListItem('Fabricação/Modelo', e(normalizeText(($dadosVeiculo['anoFabricacao'] ?? '-') . ' / ' . ($dadosVeiculo['anoModelo'] ?? '-')))),
        renderListItem('Espécie', e(joinCodeAndDescription($dadosVeiculo['codigoEspecie'] ?? '', $dadosVeiculo['especie'] ?? '')), 'lh-1 d-flex align-items-center'),
        renderListItem('Carroceria', e(joinCodeAndDescription($dadosVeiculo['codigoCarroceria'] ?? '', $dadosVeiculo['carroceria'] ?? '')), 'lh-1 d-flex align-items-center'),
        renderListItem('Placa Anterior', e(normalizeText(trim((string) ($dadosVeiculo['placaAnterior'] ?? '')) !== '' ? trim((string) $dadosVeiculo['placaAnterior']) . '/' . trim((string) ($dadosVeiculo['ufPlacaAnterior'] ?? '')) : ''))),
        renderListItem('Adquirido em', e(formatDateBr($dadosVeiculo['dataAquisicao'] ?? null))),
        renderListItem('Intenção de Venda', '<span class="fw-semibold">' . e(boolToText($dadosComplementares['intencaoVenda'] ?? $dadosVeiculo['intencaoVenda'] ?? null)) . '</span>'),
        renderListItem('Último CRLV Emitido', e(normalizeText($dadosVeiculo['ultimoCrlvEmitido'] ?? ''))),
        renderListItem('Informações PENDENTES originadas das financeiras via SNG - Sistema Nacional de Gravame', normalizeHtmlText($pendenciasSng)),
    ];

    $middleColumn = [
        renderListItem('Renavam', '<span class="fs-5 fw-bold">' . e(normalizeText($dadosVeiculo['renavam'] ?? '')) . '</span>'),
        renderListItem('Marca/Modelo', '<div class="fw-bold"><div>' . e(joinCodeAndDescription($dadosVeiculo['codigoMarca'] ?? '', $dadosVeiculo['marcaModelo'] ?? '')) . '</div><div>(' . e(normalizeText($dadosVeiculo['nacionalidade'] ?? '')) . ')</div></div>', 'lh-1 d-flex align-items-center'),
        renderListItem('Potencia', e(normalizeText($dadosVeiculo['potenciaVeiculo'] ?? ''))),
        renderListItem('Lugares', e(normalizeText($dadosVeiculo['lugares'] ?? ''))),
        renderListItem('Cor', e(joinCodeAndDescription($dadosVeiculo['codigoCor'] ?? '', $dadosVeiculo['cor'] ?? ''))),
        renderListItem('Proprietário Anterior', e(normalizeText($dadosVeiculo['proprietarioAnterior'] ?? ''))),
        renderListItem('Situação', '<span class="fw-bold">' . e(normalizeText($dadosVeiculo['situacao'] ?? '')) . '</span>'),
        renderListItem('Averbação Judicial', '<span class="fw-semibold">' . e(boolToText($dadosComplementares['averbacaoJudicial'] ?? $vehicleData['temAverbacao'] ?? null)) . '</span>', 'lh-1 d-flex align-items-center'),
        renderListItem('Último Exercício Licenciado', e(normalizeText($dadosVeiculo['exercicioLicenciamentoPago'] ?? $dadosVeiculo['exercLicenciamento'] ?? ''))),
        renderListItem('Impedimentos', '<span class="fw-bold text-success">' . e(normalizeText($dadosComplementares['textoImpedimentos'] ?? $dadosVeiculo['impedimentos'] ?? 'Nenhum impedimento registrado até esta data.')) . '</span>', 'lh-1 d-flex align-items-center'),
    ];

    $rightColumn = [
        renderListItem('Nome do Proprietário', '<span class="fw-bold">' . e(normalizeText($dadosVeiculo['nomeProprietario'] ?? '')) . '</span>'),
        renderListItem('Categoria', e(joinCodeAndDescription($dadosVeiculo['codigoCategoria'] ?? '', $dadosVeiculo['categoria'] ?? ''))),
        renderListItem('Combustível', e(joinCodeAndDescription($dadosVeiculo['codigoCombustivel'] ?? '', $dadosVeiculo['combustivel'] ?? ''))),
        renderListItem('Município de Emplacamento', e(normalizeText($dadosVeiculo['municipioEmplacamento'] ?? ''))),
        renderListItem('Recadastrado DETRAN', e(normalizeText($dadosVeiculo['recadastradoDetran'] ?? ''))),
        renderListItem('Origem dos Dados do Veículo', e(normalizeText($dadosVeiculo['origemDados'] ?? ''))),
        renderListItem('Informações do Contrato e/ou Aditivo', e(normalizeText($dadosComplementares['contratoAditivo'] ?? $dadosVeiculo['informacoesContratoAditivo'] ?? ''))),
        renderListItem('Indicativo de Clonagem (informação de responsabilidade do proprietário)', '<span class="fw-semibold">' . e(boolToText($dadosComplementares['placaClonada'] ?? $vehicleData['placaClonada'] ?? null)) . '</span>', 'lh-1 d-flex align-items-center'),
    ];

    return '<div class="dados-grid">'
        . '<div class="dados-coluna"><ul class="list-group list-group-flush">' . implode('', $leftColumn) . '</ul></div>'
        . '<div class="dados-coluna"><ul class="list-group list-group-flush">' . implode('', $middleColumn) . '</ul></div>'
        . '<div class="dados-coluna"><ul class="list-group list-group-flush">' . implode('', $rightColumn) . '</ul></div>'
        . '</div>';
}

function buildDebitosContentHtml(array $gruposDebito, string $placa): string
{
    if (empty($gruposDebito)) {
        return '<div class="alert alert-info mb-0">Nenhum débito encontrado para este veículo.</div>';
    }

    ob_start();
    ?>
<div class="p-3" style="background-color: #f8f9fa; border-bottom: 1px solid #dee2e6;">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
        <div id="groupDropdownDebito" class="d-flex align-items-center header-actions">
            <span class="fs-5 me-2 text-muted fw-bold">Emitir DUA de:</span>
            <div class="dropdown">
                <button class="btn btn-light dropdown-toggle fw-bold btn-dropdown-responsive" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    Selecione um tipo de débito...
                </button>
                <ul class="dropdown-menu">
                    <li>
                        <a class="dropdown-item active fs-6" data-grupo-debito="" href=".">Selecione um tipo de débito...</a>
                    </li>
                    <?php foreach ($gruposDebito as $grupo): ?>
                        <li>
                            <a class="dropdown-item" data-grupo-debito="<?= e($grupo['debitoDevido'] ?? '') ?>" data-debito-descricao="<?= e($grupo['descricao'] ?? '') ?>" href="."><?= e($grupo['descricao'] ?? '') ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <div id="containerAcoesDebito" class="d-flex align-items-center mt-2 mt-md-0 d-none">
            <button type="button" class="btn btn-secondary me-3 bntEmitirDua fw-bold px-4 es-clicavel">Emitir DUA</button>
            <span class="valor-total">Total Selecionado: R$&nbsp;0,00</span>
        </div>
    </div>
</div>
<div id="validationErrorMessage" class="alert alert-danger d-none mt-2" role="alert"></div>
<div class="d-none d-md-flex fw-semibold border-bottom pb-2 text-muted small mt-4">
    <div class="col col-md-3 descricao">Descrição</div>
    <div class="col-12 col-md">Vencimento</div>
    <div class="col-12 col-md">Nominal (R$)</div>
    <div class="col-12 col-md">Corrigido (R$)</div>
    <div class="col-12 col-md">Desconto (R$)</div>
    <div class="col-12 col-md">Juros (R$)</div>
    <div class="col-12 col-md">Multa (R$)</div>
    <div class="col-12 col-md">Atual (R$)</div>
</div>
<div class="corpo-tabela list-group list-group-flush">
    <?php
    $sequencial = 1;
    foreach ($gruposDebito as $groupIndex => $grupo):
        $grupoDebito = $grupo['debitoDevido'] ?? '';
        $grupoDescricao = $grupo['descricao'] ?? '';
        $debitos = isset($grupo['debitos']) && is_array($grupo['debitos']) ? $grupo['debitos'] : [];

        foreach ($debitos as $debito):
            $isFirstGroup = $groupIndex === 0;
            $rowClasses = 'linha linha-detalhe list-group-item d-flex flex-column flex-md-row gap-md-3 py-3 es-clicavel';
            if ($isFirstGroup) {
                $rowClasses .= ' disabled';
            }

            $style = $isFirstGroup ? 'display: flex;' : 'display: none !important;';
            $checkboxWrapperClass = $isFirstGroup ? 'checkbox-wrapper checkbox-hidden' : 'checkbox-wrapper';
            $checkboxDisabled = $isFirstGroup ? ' disabled' : '';
            ?>
            <div class="<?= e($rowClasses) ?>" data-grupo-debito="<?= e($grupoDebito) ?>" data-placa="<?= e($placa) ?>" style="<?= e($style) ?>">
                <div class="col-12 col-md-3 descricao" data-label="Descrição" style="padding-right: 10.5px">
                    <span class="<?= e($checkboxWrapperClass) ?>">
                        <input
                            id="checkDebito_<?= $sequencial ?>"
                            type="checkbox"
                            class="custom-checkbox form-check-input debito-checkbox me-2 curso-over"
                            data-guid="<?= e($debito['guid'] ?? '') ?>"
                            data-descricao-debito="<?= e($grupoDescricao) ?>"
                            data-exercicio="<?= e($debito['exercicio'] ?? '') ?>"
                            data-codigo-servico="<?= e($debito['codigoServico'] ?? '') ?>"
                            data-codigo-classe="<?= e($debito['classe'] ?? '') ?>"
                            data-valor-atualizado="<?= e($debito['valorAtualizado'] ?? 0) ?>"
                            data-data-vencimento="<?= e($debito['dataVencimento'] ?? '') ?>"
                            data-situacao-exibicao="<?= e($debito['situacaoExibibicao'] ?? '') ?>"<?= $checkboxDisabled ?>
                        > <?= e(normalizeText($debito['descricaoDetalhada'] ?? '')) ?>
                    </span>
                </div>
                <div class="col fw-semibold" data-label="Vencimento"><?= e(formatDateBr($debito['dataVencimento'] ?? null)) ?></div>
                <div class="col" data-label="Nominal (R$)">R$ <?= e(formatMoneyBr($debito['valorNominal'] ?? 0)) ?></div>
                <div class="col" data-label="Corrigido (R$)">R$ <?= e(formatMoneyBr($debito['valorCorrigido'] ?? 0)) ?></div>
                <div class="col" data-label="Desconto (R$)">R$ <?= e(formatMoneyBr($debito['valorDesconto'] ?? 0)) ?></div>
                <div class="col" data-label="Juros (R$)">R$ <?= e(formatMoneyBr($debito['valorJuros'] ?? 0)) ?></div>
                <div class="col" data-label="Multa (R$)">R$ <?= e(formatMoneyBr($debito['valorMulta'] ?? 0)) ?></div>
                <div class="col fw-bold" data-label="Atual (R$)">R$ <?= e(formatMoneyBr($debito['valorAtualizado'] ?? 0)) ?></div>
            </div>
            <?php
            $sequencial++;
        endforeach;
    endforeach;
    ?>
</div>
<div class="card-footer">
    <div id="containerAcoesDebitoBottom" class="d-none">
        <div class="p-3 d-flex justify-content-between mt-2 mt-md-0" style="background-color: #f8f9fa; border-bottom: 1px solid #dee2e6;">
            <button type="button" class="btn btn-secondary me-3 bntEmitirDua fw-bold px-4 es-clicavel">Emitir DUA</button>
            <span class="valor-total">Total Selecionado: R$&nbsp;0,00</span>
        </div>
    </div>
</div>
    <?php

    return trim(ob_get_clean());
}

function appendSearchLog(string $placa, string $renavam): void
{
    $searchLog = [];

    if (function_exists('app_storage_get')) {
        $rawSearchLog = app_storage_get('search_log.json');
        if ($rawSearchLog !== null) {
            $searchLog = json_decode($rawSearchLog, true) ?? [];
        }
    } else {
        $searchLogPath = __DIR__ . '/search_log.json';
        if (file_exists($searchLogPath)) {
            $searchLog = json_decode(@file_get_contents($searchLogPath), true) ?? [];
        }
    }

    $searchLog[] = [
        'ts' => date('Y-m-d H:i:s'),
        'plate' => $placa,
        'renavam' => $renavam,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    ];

    if (count($searchLog) > 200) {
        $searchLog = array_slice($searchLog, -200);
    }

    if (function_exists('app_storage_put')) {
        app_storage_put('search_log.json', json_encode($searchLog, JSON_PRETTY_PRINT));
        return;
    }

    $searchLogPath = __DIR__ . '/search_log.json';
    @file_put_contents($searchLogPath, json_encode($searchLog, JSON_PRETTY_PRINT), LOCK_EX);
}

$input = file_get_contents('php://input');
$requestData = json_decode($input, true);

if (!$requestData) {
    $requestData = [
        'placa' => $_GET['placa'] ?? null,
        'renavam' => $_GET['renavam'] ?? null,
    ];
}

if (empty($requestData['placa']) || empty($requestData['renavam'])) {
    respondJson([
        'success' => false,
        'message' => 'Dados inválidos. Envie placa e renavam via POST JSON ou URL GET.',
    ], 400);
}

$placa = strtoupper(trim((string) $requestData['placa']));
$renavam = trim((string) $requestData['renavam']);
$capsolverKey = 'CAP-AADEF35207EEA10C4C471D8601355451F386E5134A60A1C28815D063ABD7E26D';
$apiCookie = loadApiCookie();

$turnstileToken = getCapsolverToken($capsolverKey);
if (!$turnstileToken) {
    respondJson([
        'success' => false,
        'message' => 'Erro ao resolver Captcha.',
    ], 502);
}

[$idServico, $cookieFinal] = fetchConsultarVeiculo($placa, $renavam, $turnstileToken, $apiCookie);
if (!$idServico || !$cookieFinal) {
    respondJson([
        'success' => false,
        'message' => 'Não foi possível iniciar a consulta do veículo.',
    ], 502);
}

$cookieHeader = buildCookieHeader([$apiCookie, $cookieFinal]);
warmUpDossieSession($idServico, $cookieHeader);

$commonHeaders = [
    'Accept: application/json',
    'Accept-Encoding: gzip, deflate, br, zstd',
    'Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
    'Connection: keep-alive',
    'Host: servicos.detrannet.es.gov.br',
    'Referer: https://servicos.detrannet.es.gov.br/Dossie?idServico=' . $idServico,
    'sec-ch-ua: "Google Chrome";v="149", "Chromium";v="149", "Not)A;Brand";v="24"',
    'sec-ch-ua-mobile: ?0',
    'sec-ch-ua-platform: "Windows"',
    'Sec-Fetch-Dest: empty',
    'Sec-Fetch-Mode: cors',
    'Sec-Fetch-Site: same-origin',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',
    'X-Requested-With: XMLHttpRequest',
    'Cookie: ' . $cookieHeader,
];

$prepararTela = fetchJsonFromDetran(
    'https://servicos.detrannet.es.gov.br/Dossie/PrepararTela?idServico=' . urlencode($idServico),
    $commonHeaders
);

$obterDebitos = fetchJsonFromDetran(
    'https://servicos.detrannet.es.gov.br/Dossie/ObterDossieDebitos?idServico=' . urlencode($idServico),
    $commonHeaders
);

if (!is_array($prepararTela) || ($prepararTela['isSuccess'] ?? false) !== true || empty($prepararTela['value']['dadosVeiculo'])) {
    respondJson([
        'success' => false,
        'message' => $prepararTela['message'] ?? 'Não foi possível obter os dados do veículo.',
    ], 502);
}

if (!is_array($obterDebitos) || ($obterDebitos['isSuccess'] ?? false) !== true) {
    respondJson([
        'success' => false,
        'message' => $obterDebitos['message'] ?? 'Não foi possível obter os débitos do veículo.',
    ], 502);
}

$vehicleData = $prepararTela['value'];
$debitosData = is_array($obterDebitos['value'] ?? null) ? $obterDebitos['value'] : [];

$gridWrapperHtml = buildGridWrapperHtml($vehicleData);
$contentDebitosHtml = buildDebitosContentHtml($debitosData, $vehicleData['dadosVeiculo']['placa'] ?? $placa);

appendSearchLog($placa, $renavam);

respondJson([
    'success' => true,
    'placa' => $vehicleData['dadosVeiculo']['placa'] ?? $placa,
    'renavam' => $vehicleData['dadosVeiculo']['renavam'] ?? $renavam,
    'idServico' => $idServico,
    'targets' => [
        [
            'selector' => '.grid-wrapper',
            'html' => $gridWrapperHtml,
        ],
        [
            'selector' => '#content-debitos',
            'html' => $contentDebitosHtml,
        ],
    ],
    'inputs' => [
        'hdId' => $idServico,
        'hdServico' => null,
        'hdDebitos' => null,
        '__RequestVerificationToken' => null,
    ],
    'raw' => [
        'veiculo' => $vehicleData,
        'debitos' => $debitosData,
    ],
]);
