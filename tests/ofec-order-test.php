<?php
$dir=sys_get_temp_dir().'/revista-order-'.bin2hex(random_bytes(6)); putenv('RSS_DATA_DIR='.$dir); putenv('REVISTA_CONFIG_DIR='.$dir.'/config');
require dirname(__DIR__).'/cms/core.php';
$n=0; function check($yes,$label) { global $n; if(!$yes) throw new RuntimeException($label); $n++; }
try {
    rss_save_source(0,'OFEC','https://operarioferroviario.com.br/noticias','esporte'); rss_save_source(0,'Outra fonte','https://example.com/feed','esporte');
    $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jX1sAAAAASUVORK5CYII='); $key=rss_cache_image('https://example.com/photo.png',fn($u,$limit)=>[$png,$u]);
    $item=fn($name,$date)=>['identity'=>$name,'title'=>$name,'url'=>'https://example.com/'.$name,'summary'=>'Resumo','body'=>'Texto','image'=>'https://example.com/photo.png','image_file'=>$key,'published_at'=>$date];
    // Ordem de chegada propositalmente diferente das datas originais da OFEC.
    rss_store(rss_db(),1,[$item('OFEC recente','2026-10-10 12:00:00')]);
    rss_store(rss_db(),1,[$item('OFEC antiga','2026-10-08 12:00:00')]);
    rss_store(rss_db(),1,[$item('OFEC intermediaria','2026-10-09 12:00:00')]);
    rss_store(rss_db(),2,[$item('Outra nova','2020-01-01 00:00:00')]); rss_store(rss_db(),2,[$item('Outra antiga','2025-01-01 00:00:00')]);
    rss_db()->exec("UPDATE content_order SET registered_at='2026-10-16 12:00:00' WHERE kind='rss'");
    $titles=array_column(cms_news_entries('esporte'),'title'); $ofec=array_values(array_filter($titles,fn($v)=>str_starts_with($v,'OFEC')));
    check($ofec===['OFEC recente','OFEC intermediaria','OFEC antiga'],'OFEC usa publicação original, não chegada');
    check(array_search('Outra antiga',$titles)<array_search('Outra nova',$titles),'Outras fontes preservam a ordem de cadastro');
    $before=rss_db()->query("SELECT registered_at FROM content_order WHERE item_key='1' AND kind='rss'")->fetchColumn();
    rss_store(rss_db(),1,[$item('OFEC recente','2026-10-11 12:00:00')]);
    check(rss_db()->query("SELECT published_at FROM articles WHERE identity='OFEC recente'")->fetchColumn()==='2026-10-11 12:00:00','Reimportação corrige data original armazenada');
    check(rss_db()->query("SELECT registered_at FROM content_order WHERE item_key='1' AND kind='rss'")->fetchColumn()===$before,'Reimportação não altera cadastro');
    rss_db()->exec("UPDATE sources SET type='rss',url='https://operarioferroviario.com.br/feed/' WHERE id=1");
    check(array_values(array_filter(array_column(cms_news_entries(),'title'),fn($v)=>str_starts_with($v,'OFEC')))===$ofec,'Cadastro legado também usa cronologia');
    check(cms_news_entries('viagem')===[],'Filtro de categoria preservado');
    echo "$n verificações de ordenação OFEC passaram.\n";
} finally { if(is_dir($dir)) { $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST); foreach($it as $f) $f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname()); rmdir($dir); } }
