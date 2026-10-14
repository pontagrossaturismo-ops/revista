<?php

// Verifica se o ID existe e é numérico
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID inválido ou não fornecido");
}

$id = (int)$_GET['id']; // Converte para inteiro

// Conexão com o banco de dados
require_once('log.php');

// Verifica erros de conexão
if ($mysqli->connect_error) {
    die("Erro na conexão: " . $mysqli->connect_error);
}

// Prepara a consulta com um placeholder (?)
$sql = "SELECT * FROM materia WHERE id = ?";
$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    die("Erro na preparação da consulta: " . $mysqli->error);
}

// Associa o parâmetro (s = string, i = inteiro, d = double, b = blob)
$stmt->bind_param("i", $id);

// Executa a consulta
if (!$stmt->execute()) {
    error_log("Erro na execução da consulta: " . $stmt->error);
    die("Ocorreu um erro, tente novamente mais tarde.");
}

// Obtém os resultados
$result = $stmt->get_result();
$noticia = $result->fetch_array();

// Fecha a declaração e a conexão
$stmt->close();
$mysqli->close();

// Verifica se encontrou a matéria
if (!$noticia) {
    die("Matéria não encontrada");
}

    $pageTitle = htmlspecialchars($noticia['titulo']) . " - Revista PG Turismo";
    $pageDescription = substr(strip_tags($noticia['conteudo']), 0, 150) . "...";
 
 
$texto = $noticia['descritivo'];

// Função para limpar e limitar o resumo
function gerarDescricaoResumida($texto, $limite = 160) {
    $limpo = strip_tags($texto);
    return substr($limpo, 0, $limite);
}   

// Dados para SEO
$titulo = htmlspecialchars($noticia['titulo'], ENT_QUOTES, 'UTF-8');
$descricao = htmlspecialchars($noticia['descritivo'], ENT_QUOTES, 'UTF-8');
$palavrasChave = htmlspecialchars('notícia, conteúdo, revista, turismo', ENT_QUOTES, 'UTF-8');
$url = "http://www.revistapgturismo.com.br/materia.php?id=".$id;
$imagem = "https://www.revistapgturismo.com.br/" . htmlspecialchars($noticia['foto1'], ENT_QUOTES, 'UTF-8');


include 'header.php';
?>




<br>

<article class="single-article">
    <div class="article-header">
        <!-- Foto Principal -->
        <div class="main-image-container">
        <img src="<?php echo "../" . htmlspecialchars($noticia['foto1']) ;?>" alt="<?php echo htmlspecialchars($noticia['titulo'], ENT_QUOTES, 'UTF-8'); ?>" class="main-image">
            
        <div style="font-size: 12px" >
            &nbsp; &nbsp;<?php echo  htmlspecialchars($noticia['legenda1'], ENT_QUOTES, 'UTF-8');?> | Crédito: <?php echo  htmlspecialchars($noticia['credito1'], ENT_QUOTES, 'UTF-8');;?> </div>
        </div>
        
        <div class="article-meta" style="margin: 0px">
            <span class="article-category"><?php echo  htmlspecialchars($noticia['categoria'], ENT_QUOTES, 'UTF-8');; ?></span>
            
        </div>
    </div>
    
    <div class="article-content">
        <h1> <?php echo  htmlspecialchars($noticia['titulo'], ENT_QUOTES, 'UTF-8'); ?></h1>
        <h3 class="gravata"><?php echo  htmlspecialchars($noticia['gravata'], ENT_QUOTES, 'UTF-8'); ?></h3><br>
        
        
        <div class="conteudo" >
        
     <?php echo nl2br(html_entity_decode($noticia['conteudo'] , ENT_QUOTES, 'UTF-8')); ?>
            
        </div>
        


        <!-- Galeria de Fotos - Só exibe se houver fotos -->
        <?php if (!empty($noticia['foto2'])): ?>
        <br><br>
        <section class="photo-gallery">
            <h2 class="gallery-title">Galeria de Fotos</h2>
            <p class="gallery-description">Confira mais imagens</p>
            
            <div class="gallery-grid">
                
                <?php 
                  
                if ($noticia ['foto2'] != "0"){
                ?>
                    
                <div class="gallery-item">
                    <img src="../<?php echo  htmlspecialchars($noticia['foto2'], ENT_QUOTES, 'UTF-8'); ?>" width="100%" style="max-width: 350px" alt="<?php echo  htmlspecialchars($noticia['titulo'], ENT_QUOTES, 'UTF-8'); ?>" class="gallery-image">
                </div>
                
                <?php } 
                ?>
                
                <?php 
                  
                if ($noticia ['foto3'] != "0"){
                ?>
                    
                <div class="gallery-item">
                    <img src="../<?php echo  htmlspecialchars($noticia['foto3'], ENT_QUOTES, 'UTF-8'); ?> width="100%" style="max-width: 350px"" alt="<?php echo  htmlspecialchars($noticia['titulo'], ENT_QUOTES, 'UTF-8'); ?>" class="gallery-image">
                </div>
                
                <?php } 
                ?>
		
                <?php 
                  
                if ($noticia ['foto4'] != "0"){
                ?>
                    
                <div class="gallery-item">
                    <img src="<?php echo  htmlspecialchars($noticia['foto4'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo  htmlspecialchars($noticia['titulo'], ENT_QUOTES, 'UTF-8'); ?>" class="gallery-image">
                </div>
                
                <?php } 
                ?>
                
                <?php 
                  
                if ($noticia ['foto5'] != "0"){
                ?>
                    
                <div class="gallery-item">
                    <img src="<?php echo  htmlspecialchars($noticia['foto5'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo  htmlspecialchars($noticia['titulo'], ENT_QUOTES, 'UTF-8'); ?>" class="gallery-image">
                </div>
                
                <?php } 
                ?>
                <?php 
                  
                if ($noticia ['foto6'] != "0"){
                ?>
                    
                <div class="gallery-item">
                    <img src="<?php echo  htmlspecialchars($noticia['foto6'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo  htmlspecialchars($noticia['titulo'], ENT_QUOTES, 'UTF-8'); ?>" class="gallery-image">
                </div>
                
                <?php } 
                ?>
                
                <?php 
                  
                if ($noticia ['foto7'] != "0"){
                ?>
                    
                <div class="gallery-item">
                    <img src="<?php echo  htmlspecialchars($noticia['foto7'], ENT_QUOTES, 'UTF-8');?>" alt="<?php echo  htmlspecialchars($noticia['titulo'], ENT_QUOTES, 'UTF-8'); ?>" class="gallery-image">
                </div>
                
                <?php } 
                ?>
                <?php 
                  
                if ($noticia ['foto8'] != "0"){
                ?>
                    
                <div class="gallery-item">
                    <img src="<?php echo  htmlspecialchars($noticia['foto8'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo  htmlspecialchars($noticia['titulo'], ENT_QUOTES, 'UTF-8');?>" class="gallery-image">
                </div>
                
                <?php } 
                ?>
                
                <?php 
                  
                if ($noticia ['foto9'] != "0"){
                ?>
                    
                <div class="gallery-item">
                    <img src="<?php echo  htmlspecialchars($noticia['foto9'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo  htmlspecialchars($noticia['titulo'], ENT_QUOTES, 'UTF-8');?>" class="gallery-image">
                </div>
                
                <?php } 
                ?>
                
                <?php 
                  
                if ($noticia ['foto10'] != "0"){
                ?>
                    
                <div class="gallery-item">
                    <img src="<?php echo  htmlspecialchars($noticia['foto10'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo  htmlspecialchars($noticia['titulo'], ENT_QUOTES, 'UTF-8'); ?>" class="gallery-image">
                </div>
                
                <?php } 
                ?>
            </div>
        </section>
        <?php endif; ?>
    </div>
</article>

<aside class="related-articles">
    <h2 class="section-title">Matérias Relacionadas</h2>
    
    <div class="noticias-grid" style="grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));">
        
        <?php
        	
                

// Conexão com o banco de dados
include('log.php');

// Verifica erros de conexão
if ($mysqli->connect_error) {
    die("Erro na conexão: " . $mysqli->connect_error);
}

// Prepara a consulta com um placeholder (?)
        
       
$sql = "SELECT * FROM materia WHERE categoria = ? ORDER BY RAND() LIMIT 4";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("s", $noticia['categoria']); // 's' para string


if (!$stmt) {
    die("Erro na preparação da consulta: " . $mysqli->error);
}



// Executa a consulta
if (!$stmt->execute()) {
    error_log("Erro na execução da consulta: " . $stmt->error);
    die("Ocorreu um erro, tente novamente mais tarde.");
}

// Obtém os resultados
$result = $stmt->get_result();
//$noticia2 = $result->fetch_array();

       
// Exibe as notícias com segurança (escapando as saídas)
while ($noticia2 = $result->fetch_assoc()) {
    
?>
        
        <article class="noticia-card">
            <img src="<?php echo "../" . htmlspecialchars($noticia2['foto1']) ;?>" alt="<?php echo "../" . htmlspecialchars($noticia2['titulo']) ;?>">
            <div class="noticia-content">
                <span class="noticia-categoria"><?php echo htmlspecialchars($noticia2['categoria'], ENT_QUOTES, 'UTF-8');  ?></span>
                <h3><a href="materia.php?id=<?php echo htmlspecialchars($noticia2['id'], ENT_QUOTES, 'UTF-8'); ?> "><?php echo htmlspecialchars($noticia2['titulo'], ENT_QUOTES, 'UTF-8');  ?></a></h3>
                <div class="noticia-meta">
                    
                    <a href="materia.php?id= <?php echo htmlspecialchars($noticia2['id'], ENT_QUOTES, 'UTF-8'); ?> "  class="ler-mais">Ler mais</a>
                </div>
            </div>
        </article>
              



<?php } 

// Fecha a declaração e a conexão
$stmt->close();
$mysqli->close();

?>
      
    </div>
</aside>
    
<?php include 'footer.php'; ?>


















