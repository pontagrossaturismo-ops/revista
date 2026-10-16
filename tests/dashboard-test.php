<?php
$dir=sys_get_temp_dir().'/revista-dashboard-'.bin2hex(random_bytes(6)); mkdir($dir,0700,true); putenv('RSS_DATA_DIR='.$dir); putenv('REVISTA_CONFIG_DIR='.$dir.'/config');
require dirname(__DIR__).'/cms/metrics.php'; require dirname(__DIR__).'/rss/scheduler.php';
$n=0; function check($value,$label) { global $n; if(!$value) throw new RuntimeException($label); $n++; }
try {
    $_SESSION=[]; cms_metric('site','all'); cms_metric('site','all'); check((int)cms_metrics_db()->query("SELECT SUM(total) FROM daily_metrics WHERE kind='site'")->fetchColumn()===1,'Deduplicar visita por sessão');
    $_SESSION['metrics']['site:all']=time()-1801; cms_metric('site','all'); check((int)cms_metrics_db()->query("SELECT SUM(total) FROM daily_metrics WHERE kind='site'")->fetchColumn()===2,'Nova visita após 30 minutos');
    cms_metric('whatsapp','cms:2'); cms_metric('whatsapp','cms:2'); check(cms_clicks('cms:2')===1 && cms_clicks('cms:3')===0,'Separar cliques por empresa');
    $db=cms_metrics_db(); $db->exec('DELETE FROM daily_metrics');
    cms_save_news(0,0,['title'=>'Matéria popular','category'=>'viagem','image'=>'foto.png','body'=>'Conteúdo']);
    $insert=$db->prepare('INSERT INTO daily_metrics(day,kind,item,total) VALUES(?,?,?,?)');
    foreach([['2026-02-01','site','all',2],['2026-02-08','site','all',3],['2026-02-28','site','all',5],['2026-01-31','site','all',7],['2025-02-01','site','all',99],['2026-02-02','article','cms:1',9],['2026-02-03','article','cms:999',100]] as $row) $insert->execute($row);
    $days=cms_dashboard('days','2026-02'); check(count($days['buckets'])===28 && $days['visits']===10,'Mês de 28 dias, sem dados fora do período');
    check($days['buckets']['01']===2 && $days['buckets']['08']===3,'Agrupamento diário');
    $weeks=cms_dashboard('weeks','2026-02'); check(count($weeks['buckets'])===4 && $weeks['buckets'][1]===2 && $weeks['buckets'][2]===3 && $weeks['buckets'][4]===5,'Agrupamento semanal');
    $months=cms_dashboard('months','2026-02'); check(count($months['buckets'])===12 && $months['visits']===17 && $months['buckets']['01']===7 && $months['buckets']['02']===10,'Agrupamento anual');
    check(count($days['ranking'])===1 && $days['ranking'][0]['visits']===9,'Ranking exclui matérias removidas');
    check(cms_dashboard('days','2026-13')['month']!=='2026-13','Validar filtro de mês');
    check(rss_scheduled_refresh()['state']==='done','Primeira atualização automática');
    check(rss_scheduled_refresh()['state']==='waiting','Intervalo de 12 horas');
    touch($dir.'/last-import',time()-43199); check(rss_scheduled_refresh()['state']==='waiting','Não antecipar intervalo');
    touch($dir.'/last-import',time()-43201); check(rss_scheduled_refresh()['state']==='done','Executar após 12 horas');
    check(rss_scheduled_refresh(true)['state']==='done','Atualização manual antecipa intervalo');
    $lock=fopen($dir.'/import.lock','c'); flock($lock,LOCK_EX); check(rss_scheduled_refresh(true)['state']==='busy','Impedir execução simultânea'); flock($lock,LOCK_UN); fclose($lock);
    echo "$n verificações de métricas e agendamento passaram.\n";
} finally { $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST); foreach($it as $file) $file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname()); rmdir($dir); }
