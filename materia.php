<?php
require_once __DIR__.'/cms/core.php';
$id=filter_var($_GET['id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); $kind=($_GET['origem'] ?? '')==='cms'?'cms':'sql'; $article=null;
foreach(cms_news_entries() as $entry) if($entry['kind']===$kind && (int)$entry['id']===$id) { $article=$entry; break; }
if(!$article) { http_response_code(404); exit('Matéria não encontrada.'); }
$titulo=rss_escape($article['title']); $pageDescription=rss_escape(substr($article['body'],0,160)); $palavrasChave=rss_escape($article['category']); $url=''; $imagem=rss_escape($article['image']); $noticia=['titulo'=>$article['title'],'foto1'=>'']; $pageStyles=['cms/site.css'];
include __DIR__.'/header.php';
?>
<article class="single-article"><div class="article-content"><span class="article-category"><?=rss_escape($article['category'])?></span><h1><?=$titulo?></h1>
<?php if($article['image']): ?><img class="main-image" src="<?=rss_escape($article['image'])?>" alt="<?=$titulo?>"><?php endif; ?>
<?php if(!empty($article['caption']) || !empty($article['credit'])): ?><p><small><?=rss_escape($article['caption'] ?? '')?> <?=rss_escape($article['credit'] ?? '')?></small></p><?php endif; ?>
<div class="conteudo"><?=nl2br(rss_escape($article['body']))?></div>
<?php if(!empty($article['gallery'])): ?><section class="photo-gallery"><h2>Galeria de fotos</h2><div class="gallery-grid"><?php foreach($article['gallery'] as $photo): ?><img class="gallery-image" src="<?=rss_escape($photo)?>" alt="<?=$titulo?>" loading="lazy"><?php endforeach; ?></div></section><?php endif; ?><p><a href="categoria.php?categoria=<?=rawurlencode($article['category'])?>">Voltar às notícias</a></p></div></article>
<?php include __DIR__.'/footer.php'; ?>
