<?php
require_once __DIR__.'/guia/core.php';
$guide=guia_lojas('categoria','gastronomia');
if ($guide['state']!=='ok') http_response_code(503);
$titulo='Guia de Gastronomia'; $pageDescription='Conheça os estabelecimentos de gastronomia de Ponta Grossa.';
$palavrasChave='gastronomia, restaurantes, Ponta Grossa'; $url=''; $imagem=''; $noticia=['titulo'=>$titulo,'foto1'=>''];
include __DIR__.'/header.php';
?>
<section class="page-header"><h1 class="section-title">Guia de Gastronomia</h1><p class="page-description"><?=rss_escape($pageDescription)?></p></section>
<?php guia_notice($guide['state']); ?>
<?php if($guide['state']==='ok' && !$guide['rows']): ?><p>Nenhum estabelecimento de gastronomia cadastrado no momento.</p><?php endif; ?>
<div class="noticias-grid">
<?php foreach($guide['rows'] as $store): $profile=guia_asset($store['perfil'] ?? '', '../'); ?>
<article class="noticia-card"><a href="indicador.php?id=<?=rawurlencode($store['url'] ?? '')?>">
<?php if($profile): ?><img src="<?=rss_escape($profile)?>" alt="<?=rss_escape($store['nome'] ?? 'Estabelecimento')?>" class="img-logos"><?php endif; ?>
<div class="noticia-content"><h2><?=rss_escape($store['nome'] ?? 'Estabelecimento')?></h2></div></a></article>
<?php endforeach; ?></div>
<?php include __DIR__.'/footer.php'; ?>
