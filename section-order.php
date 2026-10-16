<?php
require_once __DIR__.'/cms/core.php';
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','samesite'=>'Strict']); session_start();
if($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); header('Allow: POST'); echo json_encode(['error'=>'Use POST.']); exit; }
if(!revista_setting('RSS_ADMIN_PASSWORD_HASH') || empty($_SESSION['rss_admin']) || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'],(string)($_POST['csrf'] ?? ''))) { http_response_code(403); echo json_encode(['error'=>'Acesso negado. Entre novamente no painel.']); exit; }
try { $ids=json_decode((string)($_POST['order'] ?? ''),true,512,JSON_THROW_ON_ERROR); cms_reorder_sections($ids); echo json_encode(['saved'=>true]); }
catch(InvalidArgumentException | JsonException $e) { http_response_code(422); echo json_encode(['error'=>$e->getMessage()]); }
catch(Throwable $e) { error_log('Ordem das seções: '.$e->getMessage()); http_response_code(500); echo json_encode(['error'=>'Não foi possível salvar a ordem. Tente novamente.']); }
