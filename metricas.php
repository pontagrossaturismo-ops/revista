<?php
require_once __DIR__.'/cms/metrics.php';
header('Cache-Control: no-store');
if($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); exit; }
if(isset($_SERVER['HTTP_SEC_FETCH_SITE']) && $_SERVER['HTTP_SEC_FETCH_SITE']==='cross-site') { http_response_code(403); exit; }
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','samesite'=>'Lax']); session_start();
try {
    if(!empty($_SESSION['rss_admin'])) { http_response_code(204); exit; }
    $page=basename((string)($_POST['page'] ?? ''));
    if(!preg_match('/^[a-z0-9-]+\.php$/',$page) || !is_file(__DIR__.'/'.$page) || preg_match('/admin|instalar|token|metricas|atualizar/',$page)) { http_response_code(400); exit; }
    cms_metric('site','all'); cms_metric('page',$page);
    $kind=$page==='rss-materia.php'?'rss':($page==='noticia.php'?'cms':($page==='materia.php'?'sql':'')); $id=(int)($_POST['id'] ?? 0);
    if($kind && $id>0) foreach(cms_news_entries() as $entry) if($entry['key']===$kind.':'.$id) { cms_metric('article',$entry['key']); break; }
    http_response_code(204);
} catch(Throwable $e) { error_log('Métricas: '.$e->getMessage()); http_response_code(503); }
