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
<?php if($cover): ?><img src="<?=rss_escape($cover)?>" alt="<?=rss_escape($store['nome'] ?? '')?>" style="width:100%;max-height:350px;object-fit:cover"><?php endif; ?>
<div class="article-content">
<?php if($profile): ?><img src="<?=rss_escape($profile)?>" width="180" style="border-radius:100px" alt="<?=rss_escape($store['nome'] ?? '')?>"><?php endif; ?>
<h1><?=rss_escape($store['nome'] ?? '')?></h1><p class="company-address"><?=rss_escape($store['local'] ?? '')?></p>
<?php try { $whatsapp=cms_whatsapp($store['whatsapp'] ?? $store['whats'] ?? ''); } catch(InvalidArgumentException $e) { $whatsapp=''; } if($whatsapp): ?><a class="company-whatsapp" href="empresa-whatsapp.php?id=<?=rawurlencode($store['url'])?>" target="_blank" rel="noopener noreferrer"><svg viewBox="0 0 32 32" width="28" height="28" aria-hidden="true"><path d="M27 15a11 11 0 0 1-16 10L4 28l2-7A11 11 0 1 1 27 15Z" fill="none" stroke="currentColor" stroke-width="2"/><path d="M11 9c-2 3 3 10 8 12l3-3-4-2-2 1-3-4 1-2Z" fill="currentColor"/></svg> Falar pelo WhatsApp</a><?php endif; ?>
<p class="company-phone"><?=rss_escape($store['telefone'] ?? '')?></p>
<dl class="company-details"><?php foreach(['bairro'=>'Bairro','horarios'=>'Funcionamento informado','faixa_preco'=>'Faixa de preço','acessibilidade'=>'Acessibilidade','familias'=>'Para famílias'] as $field=>$label): if(!empty($store[$field])): ?><dt><?=rss_escape($label)?></dt><dd><?=rss_escape($store[$field])?></dd><?php endif; endforeach; ?></dl><p><a class="text-link" target="_blank" rel="noopener noreferrer" href="https://www.google.com/maps/search/?api=1&amp;query=<?=rawurlencode(($store['local'] ?? $store['nome']).' Ponta Grossa')?>">Ver endereço no mapa ↗</a></p><p class="field-help">Confirme horários e serviços diretamente com o estabelecimento.</p>
<div class="conteudo"><?=nl2br(rss_escape($store['descritivo'] ?? ''))?></div>
<div class="gallery-grid"><?php for($i=1;$i<=8;$i++): $photo=guia_asset($store['foto'.$i] ?? ''); if(!$photo) continue; ?><img src="<?=rss_escape($photo)?>" alt="Foto de <?=rss_escape($store['nome'] ?? 'estabelecimento')?>" loading="lazy" class="gallery-image"><?php endfor; ?></div>
<?php $map=guia_asset($store['mapa'] ?? ''); if(preg_match('~^https?://~i',$map)): ?><iframe src="<?=rss_escape($map)?>" title="Localização do estabelecimento" width="100%" height="480" loading="lazy" referrerpolicy="no-referrer"></iframe><?php endif; ?>
</div></article>
<?php endif; ?>
<p><a href="indicador-gastronomia.php">Voltar ao guia de gastronomia</a></p>
<?php include __DIR__.'/footer.php'; ?>
