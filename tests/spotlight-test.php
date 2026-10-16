<?php
require dirname(__DIR__).'/cms/core.php';
$n=0; function check($yes,$label) { global $n; if(!$yes) throw new RuntimeException($label); $n++; }
$entry=fn($id,$category,$image='photo.jpg')=>['key'=>'cms:'.$id,'title'=>'Notícia '.$id,'category'=>$category,'image'=>$image,'href'=>'noticia.php?id='.$id];
$entries=[$entry(1,'esporte'),$entry(2,'esporte'),$entry(3,'viagem'),$entry(4,'lazer'),$entry(5,'gastronomia'),$entry(6,'ponta-grossa'),$entry(7,'eventos'),$entry(8,'viagem'),$entry(9,'compras','')];
$selection=cms_spotlight_items($entries);
check($selection['featured']['key']==='cms:3','Destaque principal vem de Viagem');
check(count($selection['secondary'])===5,'Até cinco chamadas laterais');
check(array_column($selection['secondary'],'category')===['esporte','lazer','gastronomia','ponta-grossa','eventos'],'Intercalar categorias em vez de repetir apenas a primeira');
check(count(array_unique(array_column($selection['secondary'],'key')))===5,'Sem chamadas repetidas no bloco');
check(!in_array('viagem',array_column($selection['secondary'],'category'),true),'Chamadas laterais usam outras categorias');
check(cms_spotlight_items([$entry(1,'viagem')])['secondary']===[],'Viagem sozinha não cria chamadas vazias');
check(cms_spotlight_items([$entry(1,'viagem',''),$entry(2,'viagem')])['featured']['key']==='cms:2','Ignorar chamadas sem imagem');
check(cms_spotlight_items([$entry(1,'lazer')])['featured']===null,'Sem Viagem não inventar destaque de outra categoria');
$only=cms_spotlight_items([$entry(1,'viagem'),$entry(2,'lazer'),$entry(3,'lazer'),$entry(4,'lazer')]);
check(count($only['secondary'])===3,'Completar chamadas com a mesma categoria quando não há outras');
ob_start(); cms_render_spotlight([]); check(ob_get_clean()==='','Não exibir bloco vazio');
echo "$n verificações do bloco de destaque passaram.\n";
