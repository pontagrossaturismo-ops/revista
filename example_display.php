<?php
$pageTitle='Notícias RSS — Revista PG Turismo';
$pageDescription='Notícias RSS'; $noticia=['titulo'=>$pageTitle,'foto1'=>'']; $titulo=$pageTitle; $descricao='Notícias das fontes cadastradas'; $palavrasChave='turismo, notícias'; $url=''; $imagem='';
include __DIR__.'/header.php';
?>
<section class="page-header"><h1 class="section-title">Notícias RSS</h1></section>
<section class="noticias-grid"><?php include __DIR__.'/display.php'; ?></section>
<?php include __DIR__.'/footer.php'; ?>
