<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Execute pelo cron/terminal.'); }
require_once __DIR__.'/rss/core.php';
try {
    $db=rss_db(); $dir=rss_data_dir();
    if (in_array('--diagnostico',$argv,true)) {
        $status=rss_status();
        echo 'Configuração: '.revista_config_dir().PHP_EOL;
        echo 'Armazenamento RSS: '.$status['file'].PHP_EOL;
        echo 'Fontes: '.$status['sources'].' | Notícias armazenadas: '.$status['articles'].' | Visíveis: '.$status['visible'].PHP_EOL;
        foreach ($db->query('SELECT s.name,s.active,count(a.id) AS total FROM sources s LEFT JOIN articles a ON a.source_id=s.id GROUP BY s.id ORDER BY s.name') as $source) echo $source['name'].': '.$source['total'].' notícias | '.($source['active'] ? 'Ativa' : 'Pausada').PHP_EOL;
        exit(0);
    }
    $lock=fopen($dir.'/import.lock','c');
    if (!$lock || !flock($lock,LOCK_EX | LOCK_NB)) exit("Outra importação está em andamento.\n");
    $stamp=$dir.'/last-import';
    $interval=max(5,min(1440,(int)revista_setting('RSS_INTERVAL_MINUTES',15)))*60;
    if (!in_array('--force',$argv,true) && is_file($stamp) && time()-filemtime($stamp)<$interval) exit("Aguardando intervalo configurado.\n");
    $results=rss_refresh(); touch($stamp); echo implode("\n",$results)."\n";
    exit(count(array_filter($results,fn($s)=>str_contains($s,': ERRO'))) ? 1 : 0);
} catch (Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
