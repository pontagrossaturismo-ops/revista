<?php
function rss_storage_is_busy($error) {
    return $error instanceof PDOException && (in_array((int)($error->errorInfo[1] ?? 0),[5,6],true) || preg_match('/database (?:is )?(?:locked|busy)/i',$error->getMessage()));
}
function rss_storage_unavailable($error) {
    error_log('SQLite ocupado: '.$error->getMessage());
    $message='O armazenamento está ocupado por outra operação. Aguarde alguns segundos e recarregue a página. Seus dados foram preservados.';
    if(PHP_SAPI==='cli') throw new RuntimeException($message,0,$error);
    if(!headers_sent()) { http_response_code(503); header('Retry-After: 5'); header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-store'); }
    echo '<section role="alert" style="font:16px system-ui;padding:28px;max-width:720px;margin:35px auto;background:#fff;border:1px solid #ddd;border-radius:12px"><h1>Armazenamento temporariamente ocupado</h1><p>'.$message.'</p><button onclick="location.reload()">Tentar novamente</button></section>'; exit;
}
function rss_schema_lock($callback) {
    $lock=@fopen(rss_data_dir().'/schema.lock','c');
    if(!$lock) throw new RuntimeException('Não foi possível abrir a trava de atualização do armazenamento.');
    try {
        $deadline=microtime(true)+10;
        while(!flock($lock,LOCK_EX|LOCK_NB)) {
            if(microtime(true)>=$deadline) { $e=new PDOException('database is locked: atualização da estrutura em andamento'); rss_storage_unavailable($e); }
            usleep(50000);
        }
        return $callback();
    } catch(PDOException $e) { if(rss_storage_is_busy($e)) rss_storage_unavailable($e); throw $e; }
    finally { flock($lock,LOCK_UN); fclose($lock); }
}
