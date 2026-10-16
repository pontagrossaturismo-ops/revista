<?php
require __DIR__.'/cms/tourism.php'; $titulo='Explore Ponta Grossa'; $pageDescription='Roteiros práticos para conhecer a cidade no seu ritmo.'; $url=''; $imagem=''; $palavrasChave='roteiros, Ponta Grossa'; $noticia=['titulo'=>$titulo,'foto1'=>'']; include __DIR__.'/header.php';
?>
<section class="discovery-heading"><p class="eyebrow">Planeje sua visita</p><h1>Explore Ponta Grossa</h1><p>Natureza, cultura e sabores. Escolha um roteiro e adapte as paradas ao seu tempo.</p></section><?php $routes=tourism_routes(); if(!$routes): ?><p class="empty-state">Novos roteiros serão disponibilizados em breve. Consulte também a <a href="agenda.php">agenda de eventos</a>.</p><?php endif; ?><div class="discovery-grid"><?php foreach($routes as $r) tourism_card($r); ?></div><p class="editorial-note">Roteiros editoriais sugeridos. Confirme horários, reservas e condições de acesso nos canais oficiais dos locais antes de sair.</p>
<?php include __DIR__.'/footer.php'; ?>
