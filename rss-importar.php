<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Execute pelo cron/terminal.'); }
require_once __DIR__.'/rss/core.php';
try {
    $db=rss_db(); $dir=dirname((revista_setting('RSS_DATA_DIR') ?: dirname(__DIR__).'/revista-rss-data').'/rss.sqlite');
    $lock=fopen($dir.'/import.lock','c');
    if (!$lock || !flock($lock,LOCK_EX | LOCK_NB)) exit("Outra importação está em andamento.\n");
    $stamp=$dir.'/last-import';
    $interval=max(5,min(1440,(int)revista_setting('RSS_INTERVAL_MINUTES',15)))*60;
    if (!in_array('--force',$argv,true) && is_file($stamp) && time()-filemtime($stamp)<$interval) exit("Aguardando intervalo configurado.\n");
    $results=rss_refresh(); touch($stamp); echo implode("\n",$results)."\n";
    exit(count(array_filter($results,fn($s)=>str_contains($s,': ERRO'))) ? 1 : 0);
} catch (Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
