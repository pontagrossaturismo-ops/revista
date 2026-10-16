<?php
require_once __DIR__.'/cms/core.php';
header('Cache-Control: no-store'); header('X-Frame-Options: DENY');
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','samesite'=>'Strict']); session_start();
if(!revista_setting('RSS_ADMIN_PASSWORD_HASH') || empty($_SESSION['rss_admin'])) { http_response_code(403); exit('Entre em admin.php antes de abrir o diagnóstico.'); }
$_SESSION['csrf']=$_SESSION['csrf'] ?? bin2hex(random_bytes(32));
if($_SERVER['REQUEST_METHOD']==='POST') {
    if(!hash_equals($_SESSION['csrf'],(string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Formulário expirado.'); }
    session_write_close(); @set_time_limit(180);
    $report=['version'=>1,'created_at'=>gmdate('c'),'files'=>[]];
    $collect=function($url) use (&$report) {
        try { [$body,$final]=rss_fetch($url,2097152); $report['files'][]=['url'=>$url,'final_url'=>$final,'body'=>$body]; return $body; }
        catch(Throwable $e) { $report['files'][]=['url'=>$url,'error'=>$e->getMessage()]; return ''; }
    };
    $base='https://operarioferroviario.com.br';
    $html=$collect($base.'/noticias');
    preg_match_all('~<script\b[^>]*\bsrc=["\x27]([^"\x27]+)["\x27]~i',$html,$scripts);
    $main='';
    foreach($scripts[1] as $src) {
        try { $url=rss_resolve($base.'/noticias',$src); } catch(Throwable $e) { continue; }
        if(parse_url($url,PHP_URL_HOST)==='operarioferroviario.com.br' && preg_match('~^/assets/index-[A-Za-z0-9_-]+\.js$~',parse_url($url,PHP_URL_PATH) ?? '')) { $main=$collect($url); break; }
    }
    if($main==='') $main=$collect($base.'/assets/index-B55aO247.js');
    preg_match_all('~assets/((?:article|ArticleView|ArticleList|ArticleListView|index)-[A-Za-z0-9_-]+\.js)~',$main,$modules);
    $paths=array_unique($modules[1]);
    if(!$paths) $paths=['article-DAqZ6hmN.js','ArticleView-9OPYkJGl.js','ArticleList-B0tN1uN5.js','ArticleListView-BFffxgag.js','index-gjiZV_1e.js'];
    foreach(array_slice(array_values($paths),0,8) as $path) $collect($base.'/assets/'.$path);
    $collect('https://config.fanbase.com.br/sys/fanhub/templates/operarioferroviario.com.br.json');
    header('Content-Type: application/json; charset=utf-8'); header('Content-Disposition: attachment; filename="ofec-diagnostico.json"'); header('X-Content-Type-Options: nosniff');
    echo json_encode($report,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_PRETTY_PRINT); exit;
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Diagnóstico OFEC</title><link rel="stylesheet" href="rss-admin.css"></head><body><main class="rss-panel"><header class="panel-heading"><h1>Diagnóstico da fonte OFEC</h1><a href="admin.php?area=settings">Voltar ao painel</a></header><section class="panel-card"><p>Este diagnóstico reúne a página pública de notícias, os scripts que a carregam e sua configuração pública. O arquivo permite identificar os endereços das notícias, textos e fotos.</p><p>O diagnóstico não usa as senhas do seu banco nem executa os scripts coletados. Pode levar alguns minutos. Ao concluir, o navegador baixará <strong>ofec-diagnostico.json</strong>.</p><form method="post"><input type="hidden" name="csrf" value="<?=rss_escape($_SESSION['csrf'])?>"><button>Gerar e baixar diagnóstico OFEC</button></form></section></main></body></html>
