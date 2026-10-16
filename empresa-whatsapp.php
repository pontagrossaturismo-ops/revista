<?php
require_once __DIR__.'/cms/metrics.php';
$slug=is_string($_GET['id'] ?? '')?$_GET['id']:'';
foreach(cms_companies()['rows'] as $store) if($store['url']===$slug) {
    try { $url=cms_whatsapp($store['whatsapp'] ?? $store['whats'] ?? ''); } catch(InvalidArgumentException $e) { $url=''; }
    if(!$url) break;
    session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','samesite'=>'Lax']); session_start();
    if(empty($_SESSION['rss_admin'])) cms_metric('whatsapp',$store['_key']);
    header('Cache-Control: no-store'); header('Location: '.$url, true,302); exit;
}
http_response_code(404); echo 'WhatsApp não disponível.';
