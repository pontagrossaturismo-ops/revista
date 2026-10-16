<?php
$dir=sys_get_temp_dir().'/revista-ofec-'.bin2hex(random_bytes(6)); putenv('RSS_DATA_DIR='.$dir); putenv('REVISTA_CONFIG_DIR='.$dir.'/config');
require dirname(__DIR__).'/cms/core.php';
$n=0; function check($yes,$msg) { global $n; if(!$yes) throw new RuntimeException($msg); $n++; } function rejects($fn,$msg) { try { $fn(); } catch(Throwable $e) { check(true,$msg); return; } check(false,$msg); }
$api='https://fancontentapi.fanbase.com.br/api/owner/195/newsArticles';
$article=['id'=>1556475,'title'=>'Fantasma avança &amp; comemora','description'=>'Resumo','articleBody'=>'<p>O Operário venceu.</p><p>Próxima fase.<script>alert(1)</script></p>','mainImage'=>'https://cdn.example.com/foto.jpeg','createdAt'=>'2026-10-08T15:00:00-03:00','photographer'=>'Foto OFEC','isExternal'=>false];
try {
    rss_save_source(0,'OFEC','https://operarioferroviario.com.br/feed/','esporte'); $source=cms_db()->query('SELECT * FROM sources')->fetch(PDO::FETCH_ASSOC);
    check($source['type']==='ofec' && $source['url']==='https://operarioferroviario.com.br/noticias','Reconhecer fonte antiga e normalizar URL');
    check(rss_source_type(['url'=>'https://operarioferroviario.com.br/feed/'])==='ofec','Reconhecer fonte legada sem tipo');
    check(!rss_ofec_url('https://operarioferroviario.com.br.evil.example/noticias'),'Não confundir domínio semelhante');
    rejects(fn()=>rss_save_source(0,'Falsa','https://example.com/noticias','esporte','ofec'),'Tipo OFEC exige endereço do clube');
    $calls=[]; $fetch=function($url) use (&$calls,$api,$article) { $calls[]=$url; return [json_encode(['header'=>['codigo'=>1],'data'=>str_contains($url,'?')?['items'=>[['id'=>1556475]],'totalPages'=>1]:$article]),$url]; };
    $batch=rss_ofec_items($fetch); check(count($batch['items'])===1 && count($calls)===2,'Usar lista e detalhe da API observada nos scripts');
    $item=$batch['items'][0]; check($item['url']==='https://operarioferroviario.com.br/noticias/1556475' && $item['title']==='Fantasma avança & comemora','Link original e título');
    check(str_contains($item['body'],"\n") && !str_contains($item['body'],'alert(1)'),'Parágrafos e remoção de scripts');
    check($item['credit']==='Foto OFEC' && $item['published_at']==='2026-10-08 18:00:00','Créditos e fuso horário');
    check(rss_ofec_article(array_merge($article,['isExternal'=>true]),1556475)===null,'Ignorar iframe de notícia externa');
    rejects(fn()=>rss_ofec_article(array_merge($article,['id'=>999]),1556475),'Validar ID do detalhe');
    rejects(fn()=>rss_ofec_article(array_merge($article,['articleBody'=>'']),1556475),'Não publicar chamada sem texto');
    rejects(fn()=>rss_ofec_json(fn($u)=>['<html>bloqueado</html>',$u],$api),'Explicar resposta não JSON');
    rejects(fn()=>rss_ofec_json(fn($u)=>['{"header":{"codigo":0},"data":{}}',$u],$api),'Tratar falha informada pela API');
    rejects(fn()=>rss_ofec_items(fn($u)=>['{"data":{"unexpected":[]}}',$u]),'Detectar mudança na estrutura da lista');
    $duplicates=function($u) use ($article) { return [json_encode(['data'=>str_contains($u,'?')?['items'=>[['id'=>1556475],['id'=>1556475],['id'=>'../private']]]:$article]),$u]; };
    $dedup=rss_ofec_items($duplicates); check(count($dedup['items'])===1 && count($dedup['errors'])===1,'Deduplicar IDs da lista e rejeitar caminhos inválidos');
    $missingPhoto=rss_ofec_article(array_merge($article,['mainImage'=>'javascript:alert(1)']),1556475); check($missingPhoto['image']==='','Foto inválida não impede armazenar texto');
    $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jX1sAAAAASUVORK5CYII=');
    $cache=fn($url)=>rss_cache_image($url,fn($u,$limit)=>[$png,$u]);
    rss_refresh($fetch,$cache); $row=cms_db()->query('SELECT * FROM articles')->fetch(PDO::FETCH_ASSOC);
    check(rss_has_image($row) && $row['credit']==='Foto OFEC' && count(rss_public_articles('esporte'))===1,'Importar e publicar com foto local na categoria');
    rss_refresh($fetch,$cache); check((int)cms_db()->query('SELECT COUNT(*) FROM articles')->fetchColumn()===1,'Reimportação não duplica matéria');
    $changed=$article; $changed['title']='Título atualizado'; $changed['photographer']='Novo crédito';
    $updated=fn($url)=>[json_encode(['data'=>str_contains($url,'?')?['items'=>[['id'=>1556475]]]:$changed]),$url];
    rss_refresh($updated,$cache); $row=cms_db()->query('SELECT * FROM articles')->fetch(PDO::FETCH_ASSOC); check($row['title']==='Título atualizado' && $row['credit']==='Novo crédito','Atualizar texto e crédito sem duplicar');
    $other=$article; $other['id']=1556476; $other['mainImage']='';
    $partial=fn($url)=>[json_encode(['data'=>str_contains($url,'?')?['items'=>[['id'=>1556475],['id'=>1556476],['id'=>1556477]]]: (str_ends_with($url,'1556476')?$other:(str_ends_with($url,'1556477')?['id'=>1556477,'title'=>'Sem texto']:$article))]),$url];
    rss_refresh($partial,$cache); $run=cms_db()->query('SELECT * FROM import_runs ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    check($run['status']==='partial' && $run['content_errors']==1 && $run['image_errors']==1,'Distinguir falhas de conteúdo e de foto');
    check((int)cms_db()->query('SELECT COUNT(*) FROM articles')->fetchColumn()===2 && count(rss_public_articles('esporte'))===1,'Armazenar sem foto sem publicar; demais matérias continuam');
    rss_refresh(fn($u)=>throw new RuntimeException('API indisponível'),$cache); check(count(rss_public_articles('esporte'))===1,'Preservar notícias durante indisponibilidade da API');
    $calls=[]; $paged=function($url) use (&$calls,$article) { $calls[]=$url; if(str_contains($url,'?')) { parse_str(parse_url($url,PHP_URL_QUERY),$q); $rows=[]; for($i=1;$i<=30;$i++) $rows[]=['id'=>(int)$q['page']*100+$i]; return [json_encode(['data'=>['items'=>$rows,'totalPages'=>10]]),$url]; } $id=basename(parse_url($url,PHP_URL_PATH)); return [json_encode(['data'=>array_merge($article,['id'=>(int)$id])]),$url]; };
    check(count(rss_ofec_items($paged)['items'])===90 && count($calls)===93,'Limitar paginação a três páginas e 90 notícias');
    echo "$n verificações da importação OFEC passaram.\n";
} finally { if(is_dir($dir)) { $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST); foreach($it as $f) $f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname()); rmdir($dir); } }
