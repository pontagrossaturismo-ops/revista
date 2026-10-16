<?php
require_once __DIR__.'/cms/core.php';
require_once __DIR__.'/cms/engagement.php';
$id=filter_var($_GET['id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); $kind=($_GET['origem'] ?? '')==='cms'?'cms':'sql'; $article=null;
foreach(cms_news_entries() as $entry) if($entry['kind']===$kind && (int)$entry['id']===$id) { $article=$entry; break; }
if(!$article) { http_response_code(404); exit('Matéria não encontrada.'); }
$titulo=rss_escape($article['title']); $pageDescription=rss_escape(substr($article['body'],0,160)); $palavrasChave=rss_escape($article['category']); $url=cms_public_url($article['href']); $imagem=rss_escape($article['image']); $noticia=['titulo'=>$article['title'],'foto1'=>'']; $pageStyles=['cms/site.css'];
include __DIR__.'/header.php';
?>
<div class="article-page"><?php cms_render_banners('manual'); ?>
<article class="single-article"><div class="article-content"><div class="article-meta"><span class="article-category"><?=rss_escape(cms_category_label($article['category']))?></span><?php if(!str_starts_with($article['order']['registered_at'],'1970-')): ?><time datetime="<?=rss_escape(cms_date($article['order']['registered_at'],'Y-m-d'))?>"><?=rss_escape(cms_date($article['order']['registered_at']))?></time><?php endif; ?></div><h1><?=$titulo?></h1>
<?php if($article['image']): ?><figure class="main-image-container"><img class="main-image" src="<?=rss_escape($article['image'])?>" alt="<?=$titulo?>"><figcaption class="photo-credit">Créditos: <?=rss_escape($article['credit'] ?? '') ?: 'não informados'?></figcaption></figure><?php endif; ?>
<?php if(!empty($article['caption'])): ?><p class="photo-caption"><?=rss_escape($article['caption'])?></p><?php endif; ?>
<div class="conteudo article-body"><?=nl2br(rss_escape($article['body']))?></div>
<?php if(!empty($article['gallery'])): ?><section class="photo-gallery"><h2>Galeria de fotos</h2><div class="gallery-grid"><?php foreach($article['gallery'] as $photo): ?><img class="gallery-image" src="<?=rss_escape($photo)?>" alt="<?=$titulo?>" loading="lazy"><?php endforeach; ?></div></section><?php endif; ?><p><a href="categoria.php?categoria=<?=rawurlencode($article['category'])?>">Voltar às notícias</a></p></div></article>
<?php cms_share($article['title'],$article['href']); ?>
<?php cms_render_related($article['category'],$article['key']); ?></div>
<?php include __DIR__.'/footer.php'; ?>
