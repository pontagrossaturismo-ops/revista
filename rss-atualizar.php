<?php
require_once __DIR__.'/rss/scheduler.php';
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
if($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); exit; }
if(($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')==='cross-site') { http_response_code(403); exit; }
$force=($_POST['manual'] ?? '')==='1';
if($force) {
    session_start();
    if(!revista_setting('RSS_ADMIN_PASSWORD_HASH') || empty($_SESSION['rss_admin']) || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'],(string)($_POST['csrf'] ?? ''))) { http_response_code(403); echo json_encode(['error'=>'Entre novamente no painel.']); exit; }
    session_write_close();
}
ignore_user_abort(true); @set_time_limit(0);
try { echo json_encode(rss_scheduled_refresh($force),JSON_UNESCAPED_UNICODE); }
catch(Throwable $e) { error_log('Atualização RSS: '.$e->getMessage()); http_response_code(500); echo json_encode(['error'=>'Falha na atualização. Consulte o histórico das fontes RSS.']); }
