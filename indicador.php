<?php



// Conexão com o banco de dados
require_once('log.php');

// Verifica erros de conexão
if ($mysqli->connect_error) {
    die("Erro na conexão: " . $mysqli->connect_error);
}

// Prepara a consulta com um placeholder (?)
$sql = "SELECT * FROM materia ORDER BY id desc";
$stmt = $mysqli->prepare($sql);

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
$url = "http://www.revistapgturismo.com.br/materia.php?id=".htmlspecialchars($noticia['id'], ENT_QUOTES, 'UTF-8');;
$imagem = "https://www.revistapgturismo.com.br/" . htmlspecialchars($noticia['foto1'], ENT_QUOTES, 'UTF-8');


include 'header.php';
?>
<br>




<?php
// Valida e sanitiza o parâmetro 'id' (tipo texto/slug)
$tipo = null;
if (isset($_GET['id'])) {
    $tipo = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_SPECIAL_CHARS);
    $tipo = substr($tipo, 0, 100); // Limita o tamanho para evitar abusos
}


include('log-conv.php');

// Verifica conexão
if ($mysqli->connect_error) {
    die("Erro na conexão: " . $mysqli->connect_error);
}

// CORRETO: Prepared statement com ?
$stmt = $mysqli->prepare("SELECT * FROM lojas WHERE url = ?");
if (!$stmt) {
    die("Erro ao preparar consulta: " . $mysqli->error);
}

$stmt->bind_param("s", $tipo); // "s" = string
$stmt->execute();

// Pega o resultado com segurança
$result = $stmt->get_result();
if (!$result) {
    die("Erro ao executar consulta: " . $stmt->error);
}

// Pega os dados
$gastro = $result->fetch_assoc();
?>


<div class="capa" style="background-image: url(<?php echo $gastro['capa']; ?>)" align="center" >
    
      
	
	<img src="<?php echo $gastro['perfil']; ?>" style="border-radius: 100px; opacity: 0.8; margin: 7px; margin: auto" width="180px;">
	


</div>

<div style="width: 90%; margin: auto">
    
    <h1 align="center" style="color: #222">
        <?= htmlspecialchars($gastro['nome']) ?>
    </h1>

    <h3 style="color: #777; margin: 6px; font-size: 14px; font-family: Gotham, 'Helvetica Neue', Helvetica, Arial, sans-serif">
        <?= htmlspecialchars($gastro['local']) ?><br>
        <?= htmlspecialchars($gastro['telefone']) ?>
    </h3>

    <h2 align="justify" style="color: #222; font-size: 14px">
        <?= nl2br(htmlspecialchars($gastro['descritivo'])) ?>
    </h2>

    <div id="img" style=" gap: 10px; margin-top: 20px;" align="center">
       <?php
// Exibe diretamente os caminhos das fotos
for ($i = 1; $i <= 8; $i++) {
    $foto = isset($gastro['foto' . $i]) ? $gastro['foto' . $i] : '';
    if (!empty($foto)) {
        
        
          ?>
        
         <div  style=" width: 200px; height: 200px; background: #fff; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.2); display: inline-block; align-content: center; text-align: center ; margin: 10px" align="center">
     
        <img src="<?php echo htmlspecialchars($foto) ; ?>" width="100%" height="100%" />
             
         </div>
     
    <?php
    }
}

?>

    </div>

    <br>

    <?php if (!empty($gastro['mapa'])): ?>
        <iframe 
            src="<?= htmlspecialchars($gastro['mapa']) ?>" 
            width="100%" 
            height="480" 
            frameborder="0" 
            style="margin-top: 20px;"
            allowfullscreen
        ></iframe>
    <?php endif; ?>

    <hr>
</div>


<?php

 // echo phpversion();

// Libera recursos
$stmt->close();
$mysqli->close();
?>



<br>
<?php include 'footer.php'; ?>