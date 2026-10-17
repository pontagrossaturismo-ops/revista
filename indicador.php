<?php
require_once __DIR__.'/guia/core.php';
$slug=isset($_GET['id']) && is_string($_GET['id']) ? trim($_GET['id']) : '';
$guide=$slug!=='' && strlen($slug)<=100 ? guia_lojas('url',$slug) : ['state'=>'ok','rows'=>[]];
$store=$guide['rows'][0] ?? null;
if ($guide['state']!=='ok') http_response_code(503); elseif (!$store) http_response_code(404);
if($store) {
    if(session_status()!==PHP_SESSION_ACTIVE) {
        session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','samesite'=>'Lax']); session_start();
    }
    $visitKey=$store['_key']; $lastVisit=(int)($_SESSION['company_visits'][$visitKey] ?? 0);
    if(time()-$lastVisit>=1800) { cms_company_views($visitKey,true); $_SESSION['company_visits'][$visitKey]=time(); }
}
$pageStyles=['cms/site.css'];
$titulo=rss_escape($store['nome'] ?? 'Guia de estabelecimentos');
$pageDescription=rss_escape(substr(strip_tags($store['descritivo'] ?? 'Conheça os estabelecimentos de Ponta Grossa.'),0,180));
$palavrasChave='estabelecimentos, Ponta Grossa'; $url=''; $imagem=rss_escape(guia_asset($store['perfil'] ?? '')); $noticia=['titulo'=>$store['nome'] ?? 'Guia de estabelecimentos','foto1'=>''];
include __DIR__.'/header.php';
guia_notice($guide['state']);
?>
<?php if($guide['state']==='ok' && !$store): ?><h1>Estabelecimento não encontrado</h1><p>Confira o endereço ou volte ao guia.</p><?php endif; ?>
<?php if($store): ?>
<article class="single-article company-profile">
<?php $cover=guia_asset($store['capa'] ?? ''); $profile=guia_asset($store['perfil'] ?? ''); ?>
<div class="company-hero <?=$cover?'':'company-hero-empty'?>"><?php if($cover): ?><img class="company-cover" src="<?=rss_escape($cover)?>" alt="Capa de <?=rss_escape($store['nome'] ?? '')?>" width="1280" height="400"><?php endif; ?></div>
<div class="article-content">
<?php if($profile): ?><div class="company-logo-frame"><img class="company-logo" src="<?=rss_escape($profile)?>" width="160" height="160" alt="Logo de <?=rss_escape($store['nome'] ?? '')?>"></div><?php endif; ?>
<h1><?=rss_escape($store['nome'] ?? '')?></h1><p class="company-address"><?=rss_escape($store['local'] ?? '')?></p>
<?php try { $whatsapp=cms_whatsapp($store['whatsapp'] ?? $store['whats'] ?? ''); } catch(InvalidArgumentException $e) { $whatsapp=''; } if($whatsapp): ?><a class="company-whatsapp" href="empresa-whatsapp.php?id=<?=rawurlencode($store['url'])?>" target="_blank" rel="noopener noreferrer"><?=cms_whatsapp_icon()?> Falar pelo WhatsApp</a><?php endif; ?>
<p class="company-phone"><?=rss_escape($store['telefone'] ?? '')?></p>
<dl class="company-details"><?php foreach(['bairro'=>'Bairro','horarios'=>'Funcionamento informado','faixa_preco'=>'Faixa de preço','acessibilidade'=>'Acessibilidade','familias'=>'Para famílias'] as $field=>$label): if(!empty($store[$field])): ?><dt><?=rss_escape($label)?></dt><dd><?=rss_escape($store[$field])?></dd><?php endif; endforeach; ?></dl><p><a class="text-link" target="_blank" rel="noopener noreferrer" href="https://www.google.com/maps/search/?api=1&amp;query=<?=rawurlencode(($store['local'] ?? $store['nome']).' Ponta Grossa')?>">Ver endereço no mapa ↗</a></p><p class="field-help">Confirme horários e serviços diretamente com o estabelecimento.</p>
<div class="conteudo"><?=nl2br(rss_escape($store['descritivo'] ?? ''))?></div>
<?php $photos=[]; for($i=1;$i<=8;$i++) { $photo=guia_asset($store['foto'.$i] ?? ''); if($photo) $photos[]=$photo; } if($photos): ?>
<section class="company-gallery" aria-label="Fotos da empresa"><h2>Conheça o espaço</h2><p class="field-help">Clique em uma foto para ampliar.</p><div class="gallery-grid"><?php foreach($photos as $index=>$photo): ?><a class="company-gallery-link" href="<?=rss_escape($photo)?>" target="_blank" rel="noopener noreferrer" aria-label="Ampliar foto <?=$index+1?> de <?=rss_escape($store['nome'] ?? 'estabelecimento')?>"><img src="<?=rss_escape($photo)?>" alt="Foto <?=$index+1?> de <?=rss_escape($store['nome'] ?? 'estabelecimento')?>" loading="lazy" class="gallery-image" width="400" height="300"></a><?php endforeach; ?></div></section>
<?php endif; ?>
<?php $map=guia_asset($store['mapa'] ?? ''); if(preg_match('~^https?://~i',$map)): ?><iframe src="<?=rss_escape($map)?>" title="Localização do estabelecimento" width="100%" height="480" loading="lazy" referrerpolicy="no-referrer"></iframe><?php endif; ?>
</div></article>
<?php endif; ?>
<p><a href="indicador-gastronomia.php">Voltar ao guia de gastronomia</a></p>
<?php include __DIR__.'/footer.php'; ?>
