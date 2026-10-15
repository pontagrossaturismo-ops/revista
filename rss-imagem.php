<?php
require_once __DIR__.'/rss/core.php';
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
try {
    $stmt=rss_db()->prepare('SELECT a.image_file FROM articles a JOIN sources s ON s.id=a.source_id WHERE a.id=? AND s.active=1');
    $stmt->execute([$id ?: 0]); $key=$stmt->fetchColumn();
    if (!$key || !preg_match('/^[a-f0-9]{64}$/',$key)) { http_response_code(404); exit; }
    $file=rss_data_dir().'/images/'.$key;
    if (!is_file($file) || filesize($file)>5*1024*1024) { http_response_code(404); exit; }
    $bytes=file_get_contents($file); $info=rss_image_info($bytes);
    header('Content-Type: '.$info['mime']); header('Content-Length: '.strlen($bytes));
    header('Cache-Control: public, max-age=3600'); echo $bytes;
} catch(Throwable $e) { error_log('RSS imagem: '.$e->getMessage()); http_response_code(404); }
