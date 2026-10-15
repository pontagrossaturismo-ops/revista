<?php
function revista_path_normalize($path) {
    $path=rtrim(str_replace('\\','/',$path),'/');
    return PHP_OS_FAMILY==='Windows' || preg_match('~^[a-z]:/~i',$path) || str_starts_with($path,'//') ? strtolower($path) : $path;
}
function revista_path_inside($path,$root) {
    $path=revista_path_normalize($path); $root=revista_path_normalize($root);
    return $path===$root || str_starts_with($path,$root.'/');
}
function revista_default_config_dir($project) {
    $project=str_replace('\\','/',$project);
    // No XAMPP, arquivos privados precisam ficar fora de todo o htdocs.
    if (preg_match('~^(.*)/htdocs(?:/|$)~i',$project,$match)) return $match[1].'/revista-config-'.substr(hash('sha256',revista_path_normalize($project)),0,12);
    return dirname($project).'/revista-config';
}
function revista_config_dir() {
    $project=dirname(__DIR__);
    $dir=getenv('REVISTA_CONFIG_DIR') ?: revista_default_config_dir($project);
    if (!(str_starts_with($dir,'/') || preg_match('~^[a-z]:[\\\\/]~i',$dir) || str_starts_with($dir,'\\\\')) || is_link($dir)) throw new RuntimeException('Diretório de configuração inválido. Use um caminho absoluto.');
    $parent=realpath(is_dir($dir) ? $dir : dirname($dir));
    $web=realpath($project);
    $documentRoot=!empty($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $normalized=str_replace('\\','/',$project);
    if (!$documentRoot && preg_match('~^(.*?/htdocs)(?:/|$)~i',$normalized,$match)) $documentRoot=realpath($match[1]);
    if (!$parent || revista_path_inside($parent,$web) || ($documentRoot && revista_path_inside($parent,$documentRoot))) throw new RuntimeException('A configuração deve ficar fora da pasta pública.');
    return rtrim($dir,'/\\');
}
function revista_config() {
    $file=revista_config_dir().'/config.json';
    return is_file($file) ? json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR) : [];
}
function revista_setting($key,$default=null) {
    $env=getenv($key); if ($env!==false) return $env;
    return revista_config()[$key] ?? $default;
}
function revista_write_config($data) {
    $dir=revista_config_dir();
    if (!is_dir($dir) && !mkdir($dir,0700,true)) throw new RuntimeException('Sem permissão para criar configuração privada.');
    $temp=tempnam($dir,'.config-');
    if (!$temp) throw new RuntimeException('Não foi possível preparar configuração.');
    try {
        if (!chmod($temp,0600) || file_put_contents($temp,json_encode($data,JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),LOCK_EX)===false || !rename($temp,$dir.'/config.json')) throw new RuntimeException('Não foi possível salvar configuração.');
    } finally { if (is_file($temp)) unlink($temp); }
}
function revista_connect($prefix='DB',$values=null) {
    $get=fn($key,$default=null)=>$values!==null ? ($values[$key] ?? $default) : revista_setting($key,$default);
    $host=$get($prefix.'_HOST'); $user=$get($prefix.'_USER'); $database=$get($prefix.'_NAME');
    if (!$host || !$user || !$database) throw new RuntimeException('Preencha a conexão no instalador do site.');
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db=mysqli_init(); $db->options(MYSQLI_OPT_CONNECT_TIMEOUT,5);
    @$db->real_connect($host,$user,$get($prefix.'_PASSWORD',''),$database,(int)$get($prefix.'_PORT',3306));
    $db->set_charset('utf8mb4'); return $db;
}
