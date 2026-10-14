<?php
require_once __DIR__.'/core.php';
function revista_manual_news($category=null,$limit=54) {
    if (!getenv('DB_HOST')) return [];
    try {
        include dirname(__DIR__).'/log.php';
        $sql='SELECT * FROM materia'; $params=[];
        if ($category) { $sql.=' WHERE categoria=?'; $params[]=$category; }
        $stmt=$mysqli->prepare($sql.' ORDER BY id DESC LIMIT '.max(1,min(54,(int)$limit)));
        if ($category) $stmt->bind_param('s',$category);
        $stmt->execute(); $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close(); $mysqli->close(); return $rows;
    } catch (Throwable $e) { error_log('Matérias: '.$e->getMessage()); return []; }
}
function revista_manual_cards($rows) {
    foreach ($rows as $a) {
        echo '<article class="noticia-card"><img src="'.rss_escape($a['foto1']).'" alt="'.rss_escape($a['titulo']).'"><div class="noticia-content"><span class="noticia-categoria">'.rss_escape($a['categoria']).'</span><h3><a href="materia.php?id='.(int)$a['id'].'">'.rss_escape($a['titulo']).'</a></h3><p class="noticia-resumo">'.rss_escape($a['gravata']).'</p><div class="noticia-meta"><a href="materia.php?id='.(int)$a['id'].'" class="ler-mais">Ler mais</a></div></div></article>';
    }
}
