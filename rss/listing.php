<?php
require_once __DIR__.'/news.php';
$pageDescription='Fique por dentro das novidades sobre o turismo';
$titulo=$category ? ucfirst($category) : 'Últimas Notícias';
$palavrasChave='turismo, notícias'; $url=''; $imagem='';
$noticia=['titulo'=>$titulo,'foto1'=>''];
include dirname(__DIR__).'/header.php';
?>
<section class="page-header"><h1 class="section-title"><?=rss_escape($titulo)?></h1><p class="page-description"><?=rss_escape($pageDescription)?></p>
<div class="category-filters"><a href="noticias.php" class="category-filter <?=$category===null?'active':''?>">Todas</a>
<?php foreach(['viagem','lazer','gastronomia','eventos','esporte'] as $c): ?><a href="<?=rss_escape($c)?>.php" class="category-filter <?=$category===$c?'active':''?>"><?=rss_escape(ucfirst($c))?></a><?php endforeach; ?></div></section>
<section class="noticias-grid">
<?php revista_manual_cards(revista_manual_news($category,54)); rss_cards($category,54); ?>
</section>
<aside class="sidebar"><h2 class="sidebar-title">Mais Notícias</h2><div class="featured-news">
<?php foreach(revista_manual_news(null,4) as $a): ?><h3><a href="materia.php?id=<?=(int)$a['id']?>"><?=rss_escape($a['titulo'])?></a></h3><p><?=rss_escape($a['gravata'])?></p><?php endforeach; ?>
</div></aside>
<?php include dirname(__DIR__).'/footer.php'; ?>
