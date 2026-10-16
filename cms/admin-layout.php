<?php
function cms_admin_sidebar($active) {
    $menus=['dashboard'=>'Dashboard','sections'=>'Seções','banners'=>'Banners','news'=>'Notícias manuais','companies'=>'Empresas','rss'=>'Fontes RSS','settings'=>'Configurações'];
    ?><aside class="admin-sidebar"><div class="admin-brand">Revista PG<small>PAINEL ADMINISTRATIVO</small></div><nav class="admin-nav" aria-label="Menu administrativo"><?php foreach($menus as $key=>$label): ?><a class="<?=$active===$key?'active':''?>" <?=$active===$key?'aria-current="page"':''?> href="admin.php?area=<?=$key?>"><?=rss_escape($label)?></a><?php endforeach; ?></nav><div class="sidebar-bottom"><a href="index.php">↗ Abrir o site</a></div></aside><?php
}
function cms_admin_refresh() {
    ?><section class="rss-refresh-box"><button type="button" id="refresh-rss" data-csrf="<?=rss_escape($_SESSION['csrf'])?>">↻ Atualizar RSS agora</button><p>Atualização automática a cada 12 horas. O botão permite antecipar a próxima importação.</p><?php $lastRun=rss_data_dir().'/last-import'; if(is_file($lastRun)): ?><p>Último início: <?=rss_escape(cms_date(gmdate('Y-m-d H:i:s',filemtime($lastRun)),'d/m/Y H:i'))?> · horário de Brasília</p><?php endif; ?><div id="refresh-status" role="status" aria-live="polite"></div></section><?php
}
