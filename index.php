<?php
require_once __DIR__.'/rss/news.php';
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

<?php rss_section(null,9); ?>
<section class="ultimas-noticias">
    <h2 class="section-title" >Últimas Notícias</h2>
    <div class="noticias-grid">
<?php revista_manual_cards(revista_manual_news(null,9)); ?>
    </div>
    
    <!--
    <div class="ver-mais-container">
        <a href="/noticias" class="ver-mais">Ver todas as notícias</a>
    </div>
    -->
    
</section>

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