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
include('log-conv.php');

// Verifica conexão
if ($mysqli->connect_error) {
    die("Erro na conexão: " . $mysqli->connect_error);
}

// Categoria definida de forma segura
$categoria = 'gastronomia';

// Prepared statement para evitar SQL Injection
$stmt = $mysqli->prepare("SELECT * FROM lojas WHERE categoria = ?");
if (!$stmt) {
    die("Erro ao preparar: " . $mysqli->error);
}

$stmt->bind_param("s", $categoria);
$stmt->execute();
$result = $stmt->get_result();

// Verifica se houve retorno
if (!$result) {
    die("Erro ao executar consulta: " . $stmt->error);
}

?>
<div align="center"  >
<?php
while ($gastro = $result->fetch_assoc()) {
    // Escapa a saída para evitar XSS
    $url = htmlspecialchars($gastro['url'], ENT_QUOTES, 'UTF-8');
    $perfil = htmlspecialchars($gastro['perfil'], ENT_QUOTES, 'UTF-8');

        echo '
            <a href="indicador.php?id=' . urlencode($url) . '">
                <img src="../'.$perfil.'"  alt="Imagem da loja" class="img-logos">
            </a>
        ';
   
}
?>
</div>
<?php


echo "teste";

// Libera recursos
$stmt->close();
$mysqli->close();
?>



<br>
<?php include 'footer.php'; ?>