<?php
require_once __DIR__.'/news.php';
$pageDescription='Fique por dentro das novidades sobre o turismo'; $titulo=$category ? ucfirst($category) : 'Últimas Notícias';
$palavrasChave='turismo, notícias'; $url=''; $imagem=''; $noticia=['titulo'=>$titulo,'foto1'=>'']; $pageStyles=['news-pagination.css','cms/site.css'];
require_once dirname(__DIR__).'/cms/core.php';
$unavailable=false;
try { $entries=cms_news_entries($category); } catch(Throwable $e) { $entries=[]; $unavailable=true; error_log('Listagem CMS: '.$e->getMessage()); }
$pagination=rss_paginate($entries,$_GET['pagina'] ?? 1);
$categoryPath='categoria.php?categoria='.rawurlencode($category ?? '').'&';
include dirname(__DIR__).'/header.php';
?>
<section class="page-header"><h1 class="section-title"><?=rss_escape($titulo)?></h1><p class="page-description"><?=rss_escape($pageDescription)?></p>
<div class="category-filters"><?php foreach(cms_categories() as $c): ?><a href="categoria.php?categoria=<?=rawurlencode($c)?>" class="category-filter <?=$category===$c?'active':''?>"><?=rss_escape($c==='ponta-grossa'?'Ponta Grossa':ucfirst($c))?></a><?php endforeach; ?></div></section>
<section class="ultimas-noticias" id="noticias-rss"><h2 class="section-title">Notícias</h2>
<?php if($unavailable): ?><p>Não foi possível carregar as notícias RSS. Tente novamente mais tarde.</p><?php endif; ?>
<?php if(!$pagination['items']): ?><p>Nenhuma notícia RSS disponível nesta categoria no momento.</p><?php else: ?>
<p class="pagination-summary"><?=$pagination['total']?> notícias · Página <?=$pagination['page']?> de <?=$pagination['pages']?></p>
<div class="noticias-grid cms-news-grid"><?php foreach($pagination['items'] as $entry) { cms_render_card($entry); } ?></div>
<?php endif; ?>
<?php if($pagination['pages']>1): ?>
<nav class="news-pagination" aria-label="Paginação de notícias">
<?php if($pagination['page']>1): ?><a class="page-direction" rel="prev" href="<?=rss_escape($categoryPath)?>pagina=<?=$pagination['page']-1?>#noticias-rss">‹ Anterior</a><?php else: ?><span class="page-direction disabled" aria-disabled="true">‹ Anterior</span><?php endif; ?>
<div class="page-numbers"><?php for($i=1;$i<=$pagination['pages'];$i++): ?>
<?php if($i===$pagination['page']): ?><span class="page-number current" aria-current="page" aria-label="Página <?=$i?>, atual"><?=$i?></span><?php else: ?><a class="page-number" aria-label="Página <?=$i?>" href="<?=rss_escape($categoryPath)?>pagina=<?=$i?>#noticias-rss"><?=$i?></a><?php endif; ?>
<?php endfor; ?></div>
<?php if($pagination['page']<$pagination['pages']): ?><a class="page-direction" rel="next" href="<?=rss_escape($categoryPath)?>pagina=<?=$pagination['page']+1?>#noticias-rss">Próxima ›</a><?php else: ?><span class="page-direction disabled" aria-disabled="true">Próxima ›</span><?php endif; ?>
</nav><?php endif; ?></section>
<?php include dirname(__DIR__).'/footer.php'; ?>
