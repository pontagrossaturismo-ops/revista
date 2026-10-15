<?php
require_once __DIR__.'/core.php';
$guideOptions=['gastronomia'=>['Gastronomia','gastronomia'],'compras'=>['Compras','compras'],'hospedagem'=>['Hospedagem','hospedagem']];
if (!isset($guideCategory,$guideOptions[$guideCategory])) { http_response_code(404); exit('Página não encontrada.'); }
[$label,$storeCategory]=$guideOptions[$guideCategory];
$guide=guia_lojas('categoria',$storeCategory);
if($guide['state']!=='ok') http_response_code(503);
$titulo='Guia de '.$label; $pageDescription='Conheça os estabelecimentos de '.$storeCategory.' de Ponta Grossa.';
$palavrasChave=$storeCategory.', Ponta Grossa'; $url=''; $imagem=''; $noticia=['titulo'=>$titulo,'foto1'=>''];
include dirname(__DIR__).'/header.php';
?>
<section class="page-header"><h1 class="section-title"><?=rss_escape($titulo)?></h1><p class="page-description"><?=rss_escape($pageDescription)?></p>
<div class="category-filters"><?php foreach($guideOptions as $slug=>$option): ?><a href="indicador-<?=rss_escape($slug)?>.php" class="category-filter <?=$guideCategory===$slug?'active':''?>"><?=rss_escape($option[0])?></a><?php endforeach; ?></div></section>
<?php guia_notice($guide['state']); ?>
<?php if($guide['state']==='ok' && !$guide['rows']): ?><p>Nenhum estabelecimento de <?=rss_escape($storeCategory)?> cadastrado no momento.</p><?php endif; ?>
<div class="noticias-grid"><?php foreach($guide['rows'] as $store): $profile=guia_asset($store['perfil'] ?? '', '../'); ?>
<article class="noticia-card"><a href="indicador.php?id=<?=rawurlencode($store['url'] ?? '')?>">
<?php if($profile): ?><img src="<?=rss_escape($profile)?>" alt="<?=rss_escape($store['nome'] ?? 'Estabelecimento')?>" class="img-logos"><?php endif; ?>
<div class="noticia-content"><h2><?=rss_escape($store['nome'] ?? 'Estabelecimento')?></h2></div></a></article>
<?php endforeach; ?></div>
<?php include dirname(__DIR__).'/footer.php'; ?>
