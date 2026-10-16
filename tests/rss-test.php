<?php
putenv('RSS_DATA_DIR='.sys_get_temp_dir().'/revista-rss-test-'.bin2hex(random_bytes(6)));
putenv('REVISTA_CONFIG_DIR='.getenv('RSS_DATA_DIR'));
require dirname(__DIR__).'/rss/core.php';
$count=0;
function check($condition,$message) { global $count; if (!$condition) throw new RuntimeException($message); $count++; }
function rejects($fn,$message) { $failed=false; try { $fn(); } catch(Throwable $e) { $failed=true; } check($failed,$message); }
$xml='<rss version="2.0" xmlns:media="http://search.yahoo.com/mrss/"><channel><item><guid>x</guid><title>Turismo &amp; lazer</title><link>/noticia</link><description><![CDATA[<p>Resumo <script>alert(1)</script></p>]]></description><media:thumbnail url="https://example.com/foto.jpg"/><pubDate>Wed, 14 Oct 2026 10:00:00 GMT</pubDate></item></channel></rss>';
$fixture=$xml;
$items=rss_parse($xml,'https://example.com/rss');
check(count($items)===1,'RSS deve retornar um item');
check($items[0]['url']==='https://example.com/noticia','Link relativo');
check($items[0]['title']==='Turismo & lazer','Título e entidades');
check(!str_contains($items[0]['summary'],'<script>'),'HTML removido');
check($items[0]['image']==='https://example.com/foto.jpg','Imagem media');
$png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jX1sAAAAASUVORK5CYII=');
$items[0]['image_file']=rss_cache_image($items[0]['image'],fn($url,$limit)=>[$png,$url]);
$atom='<feed xmlns="http://www.w3.org/2005/Atom"><entry><id>atom1</id><title>Atom</title><link rel="self" href="https://example.com/feed"/><link rel="alternate" href="https://example.com/atom"/><summary>Teste</summary><updated>2026-01-01T00:00:00Z</updated></entry></feed>';
$atoms=rss_parse($atom,'https://example.com/'); check(count($atoms)===1 && $atoms[0]['url']==='https://example.com/atom','Atom com namespace');
rejects(fn()=>rss_parse('<!DOCTYPE rss [<!ENTITY x SYSTEM "file:///etc/passwd">]><rss/>','https://example.com/'),'XXE bloqueado');
$externalLoads=0; $oldLoader=libxml_get_external_entity_loader(); libxml_set_external_entity_loader(function() use (&$externalLoads) { $externalLoads++; return null; });
check(count(rss_parse('<!DOCTYPE rss SYSTEM "https://example.invalid/external.dtd">'.$fixture,'https://example.com/'))===1,'DOCTYPE externo ignorado sem carregar DTD');
check(count(rss_parse('<!DOCTYPE rss PUBLIC "-//Example//DTD RSS//EN" "file:///not-readable.dtd">'.$fixture,'https://example.com/'))===1,'DOCTYPE PUBLIC ignorado sem ler arquivo');
libxml_set_external_entity_loader($oldLoader); check($externalLoads===0,'Nenhuma DTD externa foi carregada');
check(count(rss_parse('<!DOCTYPE rss>'.$fixture,'https://example.com/'))===1,'DOCTYPE simples aceito');
check(count(rss_parse(str_replace('Resumo','Texto <!DOCTYPE html> <!ENTITY exemplo>',$fixture),'https://example.com/'))===1,'Texto em CDATA não bloqueia feed');
rejects(fn()=>rss_parse('<!DOCTYPE rss [<!ENTITY % remote SYSTEM "https://example.invalid/xxe">%remote;]>'.$fixture,'https://example.com/'),'Entidade externa de parâmetro bloqueada');
rejects(fn()=>rss_parse('<!DOCTYPE rss [<!ENTITY x "expansion">]>'.$fixture,'https://example.com/'),'Expansão de entidade interna bloqueada');
rejects(fn()=>rss_parse("\xff\xfe".iconv('UTF-8','UTF-16LE',$fixture),'https://example.com/'),'Codificação com bytes nulos bloqueada');
try { rss_parse('<!DOCTYPE html><html><body>Access denied</body></html>','https://example.com/'); check(false,'HTML deve falhar'); }
catch(RuntimeException $e) { check(str_contains($e->getMessage(),'página HTML'),'Erro de HTML explica URL ou bloqueio'); }
rejects(fn()=>rss_parse('<html/>','https://example.com/'),'HTML não aceito');
rejects(fn()=>rss_parse('<rss>','https://example.com/'),'XML inválido');
rejects(fn()=>rss_url('file:///etc/passwd'),'Esquema inválido');
rejects(fn()=>rss_url('https://user:pass@example.com'),'Credencial em URL');
rejects(fn()=>rss_public_ips('127.0.0.1'),'Loopback');
rejects(fn()=>rss_public_ips('10.0.0.1'),'IP privado');
$db=rss_db(); $db->exec("INSERT INTO sources(name,url,category) VALUES('Fonte','https://example.com/rss','viagem')");
rss_store($db,1,$items); rss_store($db,1,$items);
check((int)$db->query('SELECT count(*) FROM articles')->fetchColumn()===1,'Não duplicar');
$items[0]['title']='Título atualizado'; rss_store($db,1,$items);
check($db->query('SELECT title FROM articles')->fetchColumn()==='Título atualizado','Atualização de item');
ob_start(); rss_cards('viagem'); $html=ob_get_clean(); check(str_contains($html,'Título atualizado') && str_contains($html,'noticia-card'),'Card integrado');
ob_start(); rss_cards('lazer'); check(ob_get_clean()==='','Filtro categoria');
$db->exec('UPDATE sources SET active=0'); ob_start(); rss_cards(); check(ob_get_clean()==='','Fonte pausada oculta');
$db->exec('UPDATE sources SET active=1');
ob_start(); include dirname(__DIR__).'/feed.php'; $feed=ob_get_clean(); check(simplexml_load_string($feed)!==false && str_contains($feed,'Título atualizado'),'RSS exportado válido');
$db->exec("INSERT INTO sources(name,url,category) VALUES('Fonte inválida','https://example.com/broken','lazer')");
$results=rss_refresh(function($url) use ($fixture) { return [str_ends_with($url,'broken') ? '<html/>' : $fixture, $url]; },fn($url)=>'');
check(count($results)===2,'Todas as fontes processadas');
check($db->query('SELECT error FROM sources WHERE id=1')->fetchColumn()===null,'Sucesso registrado');
check((bool)$db->query('SELECT error FROM sources WHERE id=2')->fetchColumn(),'Falha registrada separadamente');
check((int)$db->query('SELECT count(*) FROM articles')->fetchColumn()===1,'Falha não remove notícias');
$db->exec('DELETE FROM sources WHERE id=1'); check((int)$db->query('SELECT count(*) FROM articles')->fetchColumn()===0,'Excluir itens com fonte');
$dir=getenv('RSS_DATA_DIR'); foreach(glob($dir.'/images/*') as $f) unlink($f); rmdir($dir.'/images'); foreach(glob($dir.'/*') as $f) unlink($f); rmdir($dir);
echo "$count verificações passaram.\n";
