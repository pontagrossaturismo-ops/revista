<?php
require_once __DIR__.'/cms/core.php';
header('X-Content-Type-Options: nosniff'); header("Content-Security-Policy: default-src 'none'; sandbox");
$hash=$_GET['arquivo'] ?? '';
if(!is_string($hash) || !preg_match('/^[a-f0-9]{64}$/',$hash)) { http_response_code(404); exit; }
$stmt=cms_db()->prepare('SELECT mime FROM cms_media WHERE hash=?'); $stmt->execute([$hash]);
$file=rss_data_dir().'/cms-media/'.$hash;
if(!$stmt->fetchColumn() || !is_file($file) || filesize($file)>5*1024*1024) { http_response_code(404); exit; }
try { $bytes=file_get_contents($file); $info=rss_image_info($bytes); header('Content-Type: '.$info['mime']); header('Cache-Control: public, max-age=86400'); echo $bytes; }
catch(Throwable $e) { http_response_code(404); }
