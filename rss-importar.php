<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Execute pelo cron/terminal.'); }
require_once __DIR__.'/rss/core.php';
try {
    $db=rss_db(); $dir=dirname((getenv('RSS_DATA_DIR') ?: dirname(__DIR__).'/revista-rss-data').'/rss.sqlite');
    $lock=fopen($dir.'/import.lock','c');
    if (!$lock || !flock($lock,LOCK_EX | LOCK_NB)) exit("Outra importação está em andamento.\n");
    $results=rss_refresh(); echo implode("\n",$results)."\n";
    exit(count(array_filter($results,fn($s)=>str_contains($s,': ERRO'))) ? 1 : 0);
} catch (Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
