<?php require_once __DIR__.'/config/runtime.php'; $siteName=htmlspecialchars(revista_setting('SITE_TITLE','Revista PG Turismo'),ENT_QUOTES,'UTF-8'); ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <meta data-rh="true" name="theme-color" content="#c8102e">
    
    <title><?= $siteName ?> - <?php echo $titulo; ?></title>
    <meta name="description" content="<?php echo $pageDescription ?>">
    
    <!-- Favicon -->
    <link rel="icon" href="/img/logomarca.png" type="image/x-icon">
    
    <!-- CSS -->
    <link rel="stylesheet" href="style.css">
    
    <!-- Fontes -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    
    <!-- Ícones -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    
    <head>
    <meta charset="UTF-8">
    <title><?= $titulo ?> | Revista PG</title>
    <meta name="description" content="<?= $pageDescription ?>">
    <meta name="keywords" content="<?= $palavrasChave ?>">
    <meta name="author" content="Revista PG">
    <link rel="canonical" href="<?= $url ?>">

    <!-- Open Graph (Facebook, LinkedIn) -->    
    <meta property="og:title" content="<?= $titulo ?>">
    <meta property="og:description" content="<?= $pageDescription ?>">
    <meta property="og:type" content="article">
    <meta property="og:url" content="<?= $url ?>">
    <meta property="og:image" content="<?= $imagem ?>">
    <meta property="og:site_name" content="Revista PG">


    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= $titulo ?>">
    <meta name="twitter:description" content="<?= $pageDescription ?>">
    <meta name="twitter:image" content="<?= $imagem ?>">
    <meta name="twitter:site" content="@revistaPG">
    <meta name="twitter:creator" content="@revistaPG">
 <?php        
// Dados estruturados (Schema.org)
    $schemaArticle = [
        "@context" => "https://schema.org",
        "@type" => "NewsArticle",
        "headline" => $noticia['titulo'],
        "description" => $pageDescription,
        "author" => [
            "@type" => "Person",
            "name" => 'Revista PG Turismo',
        ],
        "publisher" => [
            "@type" => "Organization",
            "name" => "Revista PG Turismo",
            "logo" => [
                "@type" => "ImageObject",
                "url" => "https://www.revistapgturismo.com.br/img/logomarca.png"
            ]
        ],
        "image" => [
            "https://www.revistapgturismo.com.br" . $noticia['foto1']
        ],
        "mainEntityOfPage" => $url
    ];
    
?>   
</head>
    
<body>
    <header class="site-header">
        <div class="header-top-bar">
            <div class="container">
                <div class="social-links">
                    <a href="https://www.facebook.com/RevistaPGTurismo" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="https://www.instagram.com/revistapgturismo/" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <!-- Ícone de busca substituindo o YouTube -->
                    <button class="search-icon" aria-label="Buscar">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                
            </div>
        </div>
        
        <div class="header-main">
            <div class="container">
                <div class="header-content">
                    <!-- Logo quadrada -->
                    <div class="logo-placeholder">
                        <a href="index.php"><img src="img/logomarca.png"></a>
                    </div>
                    
                    <!-- Menu principal -->
                    <nav class="main-navigation">
                        <ul>
                            <li><a href="index.php">Início</a></li>
                            <li><a href="noticias.php">Notícias</a></li>
                            <li><a href="#">Edições</a></li>
                        </ul>
                    </nav>
                    
                    <!-- Menu hamburger (mobile) -->
                    <button class="menu-toggle" aria-label="Abrir menu">
                        <span class="hamburger"></span>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Barra de busca -->
        <div class="search-container">
            <form action="busca.php" method="get">
                <input type="text" name="q" placeholder="Digite sua busca" aria-label="Buscar no site">
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
        </div>
    </header>
      
    <!-- Schema.org JSON-LD -->
    <script type="application/ld+json">
        <?php echo json_encode($schemaArticle, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
    </script>
    <main class="container">