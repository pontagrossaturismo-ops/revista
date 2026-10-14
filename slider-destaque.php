<?php
// includes/slider-destaque.php
$destaques = [
    [
        'imagem' => '/imagens/destaque-1.jpg',
        'titulo' => 'Conheça as belezas de Ponta Grossa',
        'link' => '/noticias/belezas-ponta-grossa'
    ],
    [
        'imagem' => '/imagens/destaque-2.jpg',
        'titulo' => 'Roteiro gastronômico na região',
        'link' => '/noticias/roteiro-gastronomico'
    ],
    [
        'imagem' => '/imagens/destaque-3.jpg',
        'titulo' => 'Novo ponto turístico inaugurado',
        'link' => '/noticias/novo-ponto-turistico'
    ]
];
?>

<div class="slider-container">
    <?php foreach ($destaques as $destaque): ?>
    <div class="slide">
        <img src="<?= $destaque['imagem'] ?>" alt="<?= $destaque['titulo'] ?>">
        <div class="slide-overlay">
            <h3><a href="<?= $destaque['link'] ?>"><?= $destaque['titulo'] ?></a></h3>
        </div>
    </div>
    <?php endforeach; ?>
</div>