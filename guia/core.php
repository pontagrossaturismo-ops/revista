<?php
require_once dirname(__DIR__).'/rss/core.php';
function guia_lojas($field,$value) {
    if (!in_array($field,['categoria','url'],true)) throw new InvalidArgumentException('Filtro inválido.');
    foreach (['HOST','USER','NAME'] as $key) if (!revista_setting('DB_CONV_'.$key)) return ['state'=>'unconfigured','rows'=>[]];
    try {
        $db=revista_connect('DB_CONV');
        $stmt=$db->prepare('SELECT * FROM lojas WHERE '.$field.'=?'); $stmt->bind_param('s',$value); $stmt->execute();
        $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); $db->close();
        return ['state'=>'ok','rows'=>$rows];
    } catch(Throwable $e) { error_log('Guia de estabelecimentos: '.$e->getMessage()); return ['state'=>'unavailable','rows'=>[]]; }
}
function guia_asset($path,$prefix='') {
    $path=trim((string)$path);
    if (!$path || $path==='0' || str_starts_with($path,'//') || preg_match('~^[a-z][a-z0-9+.-]*:~i',$path) && !preg_match('~^https?://~i',$path)) return '';
    return preg_match('~^https?://|^/~i',$path) ? $path : $prefix.$path;
}
function guia_notice($state) {
    if ($state==='unconfigured' || $state==='unavailable') echo '<p role="status">O guia de estabelecimentos está temporariamente indisponível. Tente novamente mais tarde.</p>';
}
