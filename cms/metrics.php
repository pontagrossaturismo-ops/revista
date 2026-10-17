<?php
require_once __DIR__.'/core.php';
function cms_metrics_db() { return cms_db(); }

function cms_metric($kind,$item) {
    $key=$kind.':'.$item;
    if(time()-(int)($_SESSION['metrics'][$key] ?? 0)<1800) return;
    $day=(new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
    rss_sqlite_upsert(cms_metrics_db(),'INSERT INTO daily_metrics(day,kind,item,total) VALUES(?,?,?,1)',[$day,$kind,$item],'UPDATE daily_metrics SET total=total+1 WHERE day=? AND kind=? AND item=?',[$day,$kind,$item]);
    $_SESSION['metrics'][$key]=time();
}
function cms_clicks($key) { $stmt=cms_metrics_db()->prepare("SELECT COALESCE(SUM(total),0) FROM daily_metrics WHERE kind='whatsapp' AND item=?"); $stmt->execute([$key]); return (int)$stmt->fetchColumn(); }
function cms_dashboard($period,$month) {
    $zone=new DateTimeZone('America/Sao_Paulo');
    if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$month)) $month=(new DateTimeImmutable('now',$zone))->format('Y-m');
    $start=new DateTimeImmutable($month.'-01',$zone);
    if($period==='months') { $start=$start->setDate((int)$start->format('Y'),1,1); $end=$start->modify('+1 year'); }
    else $end=$start->modify('+1 month');
    $stmt=cms_metrics_db()->prepare('SELECT * FROM daily_metrics WHERE day>=? AND day<?'); $stmt->execute([$start->format('Y-m-d'),$end->format('Y-m-d')]); $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $buckets=[]; for($d=$start;$d<$end;$d=$d->modify('+1 day')) { $key=$period==='months'?$d->format('m'):($period==='weeks'?(string)(intdiv((int)$d->format('j')-1,7)+1):$d->format('d')); $buckets[$key]=0; }
    $top=[]; $visits=0; $pages=0;
    foreach($rows as $row) { if($row['kind']==='site') { $visits+=(int)$row['total']; $d=new DateTimeImmutable($row['day'],$zone); $key=$period==='months'?$d->format('m'):($period==='weeks'?(string)(intdiv((int)$d->format('j')-1,7)+1):$d->format('d')); $buckets[$key]+=(int)$row['total']; } if($row['kind']==='page') $pages+=(int)$row['total']; if($row['kind']==='article') $top[$row['item']]=($top[$row['item']] ?? 0)+(int)$row['total']; }
    arsort($top); $entries=[]; foreach(cms_news_entries() as $entry) $entries[$entry['key']]=$entry;
    $ranking=[]; foreach($top as $key=>$total) if(isset($entries[$key])) { $ranking[]=$entries[$key]+['visits'=>$total]; if(count($ranking)===5) break; }
    return compact('buckets','visits','pages','ranking','month');
}
