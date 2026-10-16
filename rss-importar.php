<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Execute pelo cron/terminal.'); }
require_once __DIR__.'/rss/core.php';
try {
    if (!in_array('--diagnostico',$argv,true)) { echo "Iniciando importação RSS. As mensagens abaixo mostram o progresso.\n"; fflush(STDOUT); }
    $db=rss_db(); $dir=rss_data_dir();
    if (in_array('--diagnostico',$argv,true)) {
        $status=rss_status();
        echo 'Configuração: '.revista_config_dir().PHP_EOL;
        echo 'Armazenamento RSS: '.$status['file'].PHP_EOL;
        echo 'Fontes: '.$status['sources'].' | Notícias armazenadas: '.$status['articles'].' | Visíveis: '.$status['visible'].PHP_EOL;
        foreach ($db->query('SELECT s.name,s.active,count(a.id) AS total FROM sources s LEFT JOIN articles a ON a.source_id=s.id GROUP BY s.id ORDER BY s.name') as $source) echo $source['name'].': '.$source['total'].' notícias | '.($source['active'] ? 'Ativa' : 'Pausada').PHP_EOL;
        exit(0);
    }
    require_once __DIR__.'/rss/scheduler.php';
    $started=microtime(true);
    $progress=function($message) use ($started) {
        $message=preg_replace('/[\x00-\x1F\x7F]/u',' ',(string)$message);
        echo '[+'.(int)(microtime(true)-$started).'s] '.$message.PHP_EOL;
        if (ob_get_level()>0) ob_flush(); fflush(STDOUT); flush();
    };
    $run=rss_scheduled_refresh(in_array('--force',$argv,true),$progress);
    if($run['state']!=='done') exit($run['state']==='busy'?"Outra importação está em andamento.\n":"Aguardando intervalo de 12 horas.\n");
    $results=$run['results'];
    $status=rss_status(); echo 'Finalizado. Notícias armazenadas: '.$status['articles'].' | Visíveis com foto: '.$status['visible'].PHP_EOL;
    exit(count(array_filter($results,fn($s)=>str_contains($s,': ERRO'))) ? 1 : 0);
} catch (Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
