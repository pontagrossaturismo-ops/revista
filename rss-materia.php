<?php
require_once __DIR__.'/rss/core.php';
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
try {
    $stmt=rss_db()->prepare('SELECT a.*,s.name,s.category FROM articles a JOIN sources s ON s.id=a.source_id WHERE a.id=? AND s.active=1');
    $stmt->execute([$id ?: 0]); $article=$stmt->fetch(PDO::FETCH_ASSOC);
} catch(Throwable $e) { error_log('RSS matéria: '.$e->getMessage()); http_response_code(503); exit('Notícia temporariamente indisponível.'); }
if (!$article || !rss_has_image($article)) { http_response_code(404); exit('Notícia não encontrada.'); }
$titulo=rss_escape($article['title']); $pageDescription=rss_escape(substr($article['summary'],0,180));
$palavrasChave=rss_escape($article['category'].', turismo, notícias');
$url=rss_escape(rtrim(revista_setting('SITE_URL',''),'/').'/rss-materia.php?id='.(int)$article['id']);
$imagem=rss_escape($article['image']); $noticia=['titulo'=>$article['title'],'foto1'=>$article['image']];
include __DIR__.'/header.php';
?>
<article class="single-article">
<div class="article-header"><div class="article-meta"><span class="article-category"><?=rss_escape($article['category'])?></span> <time datetime="<?=rss_escape(str_replace(' ','T',$article['published_at']).'Z')?>"><?=rss_escape(gmdate('d/m/Y H:i',strtotime($article['published_at'].' UTC')))?> UTC</time></div></div>
<div class="article-content"><h1><?=$titulo?></h1>
<p>Fonte: <a href="<?=rss_escape($article['url'])?>" target="_blank" rel="noopener noreferrer"><?=rss_escape($article['name'])?></a></p>
<?php if (rss_has_image($article)): ?><figure class="main-image-container"><img class="main-image" src="<?=rss_escape(rss_image_src($article))?>" alt="<?=$titulo?>"><figcaption>Imagem disponibilizada no feed de <?=rss_escape($article['name'])?>.</figcaption></figure><?php endif; ?>
<div class="conteudo"><?=nl2br(rss_escape($article['body'] ?: $article['summary']))?></div>
<p><small>Conteúdo disponibilizado pela fonte no RSS. O feed pode conter apenas um resumo.</small></p>
<p><a class="ler-mais" href="<?=rss_escape($article['url'])?>" target="_blank" rel="noopener noreferrer">Consultar publicação original</a></p>
<p><a href="noticias.php#noticias-rss">Voltar às notícias</a></p></div>
</article>
<?php include __DIR__.'/footer.php'; ?>
