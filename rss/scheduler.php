<?php
require_once __DIR__.'/core.php';
function rss_scheduled_refresh($force=false,$progress=null) {
    $dir=rss_data_dir(); $lock=@fopen($dir.'/import.lock','c');
    if(!$lock) throw new RuntimeException('Não foi possível abrir a trava de importação.');
    if(!flock($lock,LOCK_EX|LOCK_NB)) { if($lock) fclose($lock); return ['state'=>'busy','results'=>[]]; }
    try {
        $stamp=$dir.'/last-import'; clearstatcache(true,$stamp);
        if(!$force && is_file($stamp) && time()-filemtime($stamp)<43200) return ['state'=>'waiting','results'=>[]];
        // Reserva o intervalo antes de iniciar para evitar ciclos de repetição após falhas.
        if(!@touch($stamp)) throw new RuntimeException('Não foi possível registrar o horário da importação.');
        $results=rss_refresh(null,null,null,$progress);
        return ['state'=>'done','results'=>$results];
    } finally { flock($lock,LOCK_UN); fclose($lock); }
}
