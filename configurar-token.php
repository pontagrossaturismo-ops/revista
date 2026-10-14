<?php
if (PHP_SAPI!=='cli') { http_response_code(403); exit('Execute no terminal da hospedagem.'); }
require __DIR__.'/config/runtime.php';
try {
    $dir=revista_config_dir();
    if (!is_dir($dir) && !mkdir($dir,0700,true)) throw new RuntimeException('Sem permissão de escrita fora da pasta pública.');
    $token=bin2hex(random_bytes(24)); $file=$dir.'/setup-token.hash';
    $handle=fopen($file,'x');
    if (!$handle) throw new RuntimeException('Já existe código de ativação. Use o existente ou remova apenas setup-token.hash para gerar outro.');
    chmod($file,0600); fwrite($handle,password_hash($token,PASSWORD_DEFAULT)); fclose($handle);
    echo "Código de ativação do instalador (guarde em local seguro):\n".$token."\nAbra instalar.php por HTTPS e informe este código.\n";
} catch(Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
