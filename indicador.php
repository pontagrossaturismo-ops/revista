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
<article class="single-article">
<?php $cover=guia_asset($store['capa'] ?? ''); $profile=guia_asset($store['perfil'] ?? ''); ?>
<?php if($cover): ?><img src="<?=rss_escape($cover)?>" alt="<?=rss_escape($store['nome'] ?? '')?>" style="width:100%;max-height:350px;object-fit:cover"><?php endif; ?>
<div class="article-content">
<?php if($profile): ?><img src="<?=rss_escape($profile)?>" width="180" style="border-radius:100px" alt="<?=rss_escape($store['nome'] ?? '')?>"><?php endif; ?>
<h1><?=rss_escape($store['nome'] ?? '')?></h1><p><?=rss_escape($store['local'] ?? '')?><br><?=rss_escape($store['telefone'] ?? '')?></p>
<?php try { $whatsapp=cms_whatsapp($store['whatsapp'] ?? $store['whats'] ?? ''); } catch(InvalidArgumentException $e) { $whatsapp=''; } if($whatsapp): ?><a class="whatsapp-button" href="<?=rss_escape($whatsapp)?>" target="_blank" rel="noopener noreferrer">Falar pelo WhatsApp</a><?php endif; ?>
<div class="conteudo"><?=nl2br(rss_escape($store['descritivo'] ?? ''))?></div>
<div class="gallery-grid"><?php for($i=1;$i<=8;$i++): $photo=guia_asset($store['foto'.$i] ?? ''); if(!$photo) continue; ?><img src="<?=rss_escape($photo)?>" alt="Foto de <?=rss_escape($store['nome'] ?? 'estabelecimento')?>" loading="lazy" class="gallery-image"><?php endfor; ?></div>
<?php $map=guia_asset($store['mapa'] ?? ''); if(preg_match('~^https?://~i',$map)): ?><iframe src="<?=rss_escape($map)?>" title="Localização do estabelecimento" width="100%" height="480" loading="lazy" referrerpolicy="no-referrer"></iframe><?php endif; ?>
</div></article>
<?php endif; ?>
<p><a href="indicador-gastronomia.php">Voltar ao guia de gastronomia</a></p>
<?php include __DIR__.'/footer.php'; ?>
