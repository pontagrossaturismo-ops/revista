<?php
$dir=sys_get_temp_dir().'/revista-tourism-'.bin2hex(random_bytes(6)); putenv('RSS_DATA_DIR='.$dir); putenv('REVISTA_CONFIG_DIR='.$dir.'/config');
require dirname(__DIR__).'/cms/tourism.php'; require dirname(__DIR__).'/cms/engagement.php';
$n=0; function check($ok,$label) { global $n; if(!$ok) throw new RuntimeException($label); $n++; } function rejects($fn,$label) { try { $fn(); } catch(InvalidArgumentException $e) { check(true,$label); return; } check(false,$label); }
try {
    check(count(tourism_routes())===4,'Criar quatro roteiros editoriais publicados');
    check(count(tourism_routes())===4,'Não duplicar roteiros ao consultar');
    $base=['title'=>'Festival de teste','category'=>'cultura','starts'=>'2099-12-01T10:00','ends'=>'2099-12-01T18:00','location'=>'Centro','price'=>'Consulte organização','link'=>'https://example.com/ingressos','body'=>'Programação informada pelo organizador.','published'=>1];
    tourism_save('events',0,$base); check(count(tourism_events())===1,'Evento futuro publicado aparece');
    tourism_save('events',0,array_merge($base,['title'=>'Encerrado','starts'=>'2020-01-01T10:00','ends'=>'2020-01-01T12:00'])); check(count(tourism_events())===1 && count(tourism_events([],true))===2,'Encerrados saem da agenda e ficam no admin');
    tourism_save('events',0,array_merge($base,['title'=>'Rascunho','published'=>0])); check(count(tourism_events())===1,'Não publicar rascunho');
    check(tourism_events(['from'=>'2100-01-01 00:00'])===[],'Filtro de datas');
    check(tourism_events(['category'=>'esporte'])===[],'Filtro por categoria');
    rejects(fn()=>tourism_save('events',0,array_merge($base,['ends'=>'2099-11-30T10:00'])),'Não aceitar término anterior');
    rejects(fn()=>tourism_save('events',0,array_merge($base,['starts'=>'2099-02-31T10:00'])),'Validar datas de calendário');
    rejects(fn()=>tourism_save('events',0,array_merge($base,['link'=>'javascript:alert(1)'])),'Recusar link executável');
    check(tourism_date('2099-12-01 00:30')==='01/12/2099 00:30','Mostrar horário local sem deslocar para o dia anterior');
    cms_save_news(0,0,['title'=>'Festival notícia','category'=>'cultura','image'=>'foto.jpg','body'=>'Texto cultural']);
    cms_save_company(0,'',['nome'=>'Festival Café','url'=>'festival-cafe','categoria'=>'gastronomia','bairro'=>'Órfãs','horarios'=>'Terça a sábado','faixa_preco'=>'Médio','acessibilidade'=>'Consulte a equipe','familias'=>'Atendimento familiar']);
    check(count(tourism_search('festival'))===3,'Busca encontra notícia, empresa e evento');
    check(count(tourism_search('festival','events'))===1,'Filtro de tipo na busca');
    check(count(tourism_search('criancas','routes'))>=1,'Busca sem acentos encontra roteiro');
    check(tourism_search('%')===[],'Não transformar pesquisa sem letras em todos os registros');
    check(tourism_search("' OR 1=1 --")===[],'Pesquisa não interpreta SQL');
    $store=cms_companies()['rows'][0]; check($store['bairro']==='Órfãs' && $store['horarios']==='Terça a sábado' && $store['faixa_preco']==='Médio','Persistir novos campos das empresas');
    $route=tourism_routes()[0]; tourism_save('routes',$route['id'],array_merge($route,['title'=>'Roteiro revisado','published'=>0])); check(count(tourism_routes())===3,'Ocultar e editar roteiro');
    tourism_db()->prepare('DELETE FROM tourism_routes WHERE id=?')->execute([$route['id']]); check(count(tourism_routes(true))===3,'Excluir roteiro sem recriar sementes');
    $_SERVER['HTTP_HOST']='localhost:8080'; $_SERVER['SCRIPT_NAME']='/PG-2025/novo/noticia.php'; check(cms_public_url('noticia.php?id=1')==='http://localhost:8080/PG-2025/novo/noticia.php?id=1','Compartilhamento preserva subpasta do XAMPP');
    ob_start(); cms_share('Título & viagem','noticia.php?id=1'); $html=ob_get_clean(); check(str_contains($html,'https://wa.me/?text=') && str_contains($html,'facebook.com/sharer') && str_contains($html,'data-copy-link'),'Renderizar opções de compartilhamento');
    echo "$n verificações de agenda, roteiros, busca e empresas passaram.\n";
} finally { if(is_dir($dir)) { $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST); foreach($it as $f) $f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname()); rmdir($dir); } }
