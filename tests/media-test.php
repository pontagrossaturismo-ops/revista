<?php
$dir=sys_get_temp_dir().'/revista-media-'.bin2hex(random_bytes(6)); mkdir($dir,0700);
putenv('RSS_DATA_DIR='.$dir); putenv('REVISTA_CONFIG_DIR='.$dir);
// Reproduz uma base criada pela versão anterior.
$old=new PDO('sqlite:'.$dir.'/rss.sqlite');
$old->exec('CREATE TABLE sources(id INTEGER PRIMARY KEY,name TEXT,url TEXT UNIQUE,category TEXT,active INTEGER DEFAULT 1,checked_at TEXT,error TEXT)');
$old->exec('CREATE TABLE articles(id INTEGER PRIMARY KEY,source_id INTEGER,identity TEXT,title TEXT,url TEXT,summary TEXT,image TEXT,published_at TEXT,UNIQUE(source_id,identity))');
$old->exec("INSERT INTO sources(id,name,url,category) VALUES(1,'Fonte','https://example.com/feed','viagem'); INSERT INTO articles(source_id,identity,title,url,summary,image,published_at) VALUES(1,'old','Antiga','https://example.com/old','Resumo antigo','','2026-01-01')");
require dirname(__DIR__).'/rss/core.php';
$count=0;
function media_check($ok,$message) { global $count; if (!$ok) throw new RuntimeException($message); $count++; }
function media_reject($fn,$message) { $error=false; try {$fn();} catch(Throwable $e) {$error=true;} media_check($error,$message); }
$db=rss_db(); media_check($db->query('SELECT title FROM articles WHERE identity="old"')->fetchColumn()==='Antiga','Migração preserva matérias');
media_check(in_array('image_file',array_column($db->query('PRAGMA table_info(articles)')->fetchAll(PDO::FETCH_ASSOC),'name'),true),'Colunas novas disponíveis');
$xml='<rss xmlns:content="http://purl.org/rss/1.0/modules/content/"><channel><item><guid>full</guid><title>Completa</title><link>https://example.com/news/article</link><description>Resumo</description><content:encoded><![CDATA[<p>Parágrafo um.</p><p>Parágrafo dois.</p><script>unsafe()</script><img data-src="/foto.png" src="data:image/png;base64,x">]]></content:encoded></item></channel></rss>';
$item=rss_parse($xml,'https://example.com/feed')[0];
media_check($item['image']==='https://example.com/foto.png','Imagem apenas no conteúdo completo');
media_check($item['summary']==='Resumo' && str_contains($item['body'],'Parágrafo dois.'),'Corpo completo separado do resumo');
media_check(!str_contains($item['body'],'unsafe()') && str_contains($item['body'],"\n\n"),'Remover scripts e preservar parágrafos');
$group='<rss xmlns:media="http://search.yahoo.com/mrss/"><channel><item><title>Grupo</title><link>https://example.com/news</link><media:group><media:content medium="image" url="https://example.com/group.png"/></media:group></item></channel></rss>';
media_check(rss_parse($group,'https://example.com/')[0]['image']==='https://example.com/group.png','Imagem em media group');
$png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jX1sAAAAASUVORK5CYII=');
$key=rss_cache_image($item['image'],fn($url,$limit)=>[$png,$url]);
media_check(is_file($dir.'/images/'.$key) && file_get_contents($dir.'/images/'.$key)===$png,'Imagem copiada para armazenamento privado');
media_check(rss_cache_image($item['image'],fn()=>throw new RuntimeException('Não deve baixar de novo'))===$key,'Cache reutilizado');
media_reject(fn()=>rss_cache_image('https://example.com/evil.svg',fn()=>['<svg onload="alert(1)"/>','']),'SVG/HTML não aceito como imagem');
media_reject(fn()=>rss_cache_image('https://example.com/big.png',fn()=>[str_repeat('x',5*1024*1024+1),'']),'Limite de tamanho');
$item['image_file']=$key; rss_store($db,1,[$item]);
$row=$db->query('SELECT * FROM articles WHERE identity<>"old"')->fetch(PDO::FETCH_ASSOC);
media_check($row['body']===$item['body'] && $row['image_file']===$key,'Conteúdo e imagem persistidos');
media_check(rss_image_src($row)==='rss-imagem.php?id='.$row['id'],'URL local da imagem');
ob_start(); rss_cards('viagem'); $cards=ob_get_clean();
media_check(str_contains($cards,'href="rss-materia.php?id='.$row['id'].'"') && !str_contains($cards,'href="https://example.com/news/article"'),'Cards abrem a matéria interna');
$items=rss_parse($xml,'https://example.com/feed'); $items[0]['image_error']='Falha temporária'; rss_store($db,1,$items);
media_check($db->query('SELECT image_file FROM articles WHERE identity<>"old"')->fetchColumn()===$key,'Falha temporária preserva imagem já baixada');
$results=rss_refresh(fn($url)=>[$xml,$url],fn()=>throw new RuntimeException('HTTP 403'));
media_check(str_contains($results[0],'1 itens processados') && $db->query('SELECT image_error FROM articles WHERE identity<>"old"')->fetchColumn()==='HTTP 403','Falha da imagem não impede importar texto');
foreach(glob($dir.'/images/*') as $f) unlink($f); rmdir($dir.'/images');
$db=null; $old=null; foreach(glob($dir.'/*') as $f) unlink($f); rmdir($dir);
echo "$count verificações de conteúdo e imagens passaram.\n";
