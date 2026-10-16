<?php
$dir=sys_get_temp_dir().'/revista-publication-'.bin2hex(random_bytes(6)); putenv('RSS_DATA_DIR='.$dir); putenv('REVISTA_CONFIG_DIR='.$dir.'/config');
require dirname(__DIR__).'/rss/core.php';
$n=0; function check($ok,$message) { global $n; if(!$ok) throw new RuntimeException($message); $n++; }
try {
    rss_save_source(0,'OFEC','https://operarioferroviario.com.br/noticias','esporte');
    rss_save_source(0,'Outras notícias','https://example.com/feed','esporte');
    $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jX1sAAAAASUVORK5CYII=');
    $key=rss_cache_image('https://example.com/old.jpg',fn($u,$limit)=>[$png,$u]);
    $make=fn($id,$photo,$date)=>['identity'=>$id,'title'=>$id,'url'=>'https://example.com/news/'.$id,'summary'=>'Resumo','body'=>'Texto','image'=>'https://example.com/old.jpg','image_file'=>$photo,'published_at'=>$date];
    $db=rss_db(); rss_store($db,1,[$make('OFEC preservada',$key,'2025-01-01')]);
    $hidden=[]; for($i=0;$i<100;$i++) $hidden[]=$make('pendente-'.$i,'','2026-10-16'); rss_store($db,2,$hidden);
    check(rss_prune()===10,'Limitar a reserva de pendências sem apagar a notícia publicada');
    check((int)$db->query('SELECT COUNT(*) FROM articles WHERE source_id=1')->fetchColumn()===1 && count(rss_public_articles('esporte',null))===1,'Notícia da OFEC continua armazenada e visível após entradas sem foto');
    check((int)$db->query('SELECT COUNT(*) FROM articles WHERE source_id=2')->fetchColumn()===90,'Guardar pendências para tentar novamente');
    $valid=[]; for($i=0;$i<90;$i++) $valid[]=$make('publicada-'.$i,$key,'2026-10-16'); rss_store($db,2,$valid); rss_prune();
    check(count(rss_public_articles('esporte',null))===90,'Manter máximo de 90 notícias publicadas por categoria');
    rss_store($db,1,[$make('OFEC recém cadastrada',$key,'2025-01-01')]); rss_prune();
    check((int)$db->query("SELECT COUNT(*) FROM articles WHERE title='OFEC recém cadastrada'")->fetchColumn()===1,'Data original antiga não elimina uma matéria recém cadastrada');
    check(count(rss_public_articles('esporte',null))===90,'Não criar sétima página ao cadastrar notícia antiga');
    $db->exec('UPDATE sources SET active=0 WHERE id=2'); rss_prune();
    check((int)$db->query('SELECT COUNT(*) FROM articles WHERE source_id=1')->fetchColumn()===1,'Fonte pausada não desloca matéria ativa');
    $id=1556475; $article=['id'=>$id,'title'=>'Foto preservada','articleBody'=>'<p>Texto inicial</p>','mainImage'=>'https://example.com/old.jpg','createdAt'=>'2026-10-16'];
    $fetch=function($url) use (&$article,$id) { return [json_encode(['data'=>str_contains($url,'?')?['items'=>[['id'=>$id]]]:$article]),$url]; };
    rss_refresh($fetch,fn($u)=>rss_cache_image($u,fn($url,$limit)=>[$png,$url]));
    $article['mainImage']='https://example.com/rotated.jpg'; $article['articleBody']='<p>Texto atualizado</p>';
    rss_refresh($fetch,fn($u)=>throw new RuntimeException('Foto temporariamente indisponível'));
    $row=$db->query("SELECT * FROM articles WHERE title='Foto preservada'")->fetch(PDO::FETCH_ASSOC);
    check(rss_has_image($row) && $row['image']==='https://example.com/old.jpg','Nova URL com falha mantém foto local já validada');
    check(str_contains($row['body'],'Texto atualizado') && $row['image_error']==='Foto temporariamente indisponível','Atualizar texto e registrar falha da foto sem esconder a matéria');
    check(count(rss_public_articles('esporte',null))===2,'Matéria publicada permanece visível após falha temporária');
    echo "$n verificações de preservação de notícias passaram.\n";
} finally { if(is_dir($dir)) { $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST); foreach($it as $f) $f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname()); rmdir($dir); } }
