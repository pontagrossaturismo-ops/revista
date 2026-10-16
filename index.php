<?php
require_once __DIR__.'/cms/core.php';
$pageStyles=['cms/site.css'];
$titulo=rss_escape(revista_setting('SITE_TITLE','Revista PG Turismo')); $pageDescription=rss_escape(revista_setting('SITE_DESCRIPTION','Turismo, lazer, gastronomia e notícias'));
$palavrasChave='turismo, notícias'; $url=rss_escape(revista_setting('SITE_URL','')); $imagem=''; $noticia=['titulo'=>$titulo,'foto1'=>''];
include 'header.php';
?>
<br>

<h2 > &nbsp; Última Edição</h2>

<section class="destaque"  >
    <div class="slider" style="background-color: #ddd; padding-top: 2px " align="center" >
        <!-- Slides dinâmicos com PHP -->
        
        <?php // include 'slider-destaque.php'; 
        
        if (is_file(__DIR__.'/edicoes/issuu101.html')) include __DIR__.'/edicoes/issuu101.html';
        
        ?>
             
    </div>
</section>

<?php $allNews=cms_news_entries(); cms_render_spotlight($allNews); foreach(cms_db()->query('SELECT * FROM cms_sections WHERE active=1 ORDER BY position,id') as $section):
$items=array_slice(array_values(array_filter($allNews,fn($entry)=>$entry['category']===$section['category'])),0,4); ?>
<section class="home-section" style="--section-color:<?=rss_escape($section['color'])?>" data-section="<?=rss_escape($section['category'])?>">
<div class="home-section-heading"><h2 class="section-title"><?=rss_escape($section['title'])?></h2><a href="categoria.php?categoria=<?=rawurlencode($section['category'])?>">Ver mais</a></div>
<div class="home-news-grid"><?php foreach($items as $entry) cms_render_card($entry); ?></div>
<?php if(!$items): ?><p>Novas notícias em breve.</p><?php endif; ?></section>
<?php cms_render_banners('home',$section['id']); endforeach; ?>

<div style="background: #f0f0f0; border-radius: 10px; box-shadow: var(--shadow-md); border: #ddd 2px solid; padding: 10px" ><br>



    <div class="margem80" align="center" >

   

        
       <h2 style="margin-top: 0px"> <strong>Conheça AQUI os melhores estabelecimetos de Ponta Grossa!</strong></h2>
            
         <a href="indicador-gastronomia.php" >
            <img src="img/iconGastro2.png" class="iconeConv" />
         </a>
        
         <a href="indicador-compras.php" >
            <img src="img/iconCompras2.png"  class="iconeConv" />
         </a>
        
          <a href="indicador-hospedagem.php" >
            <img src="img/iconHosp2.png"  class="iconeConv" />
         </a>
        

        

    
    </div>
    <br>
</div>
<br>


<?php include 'footer.php'; ?>