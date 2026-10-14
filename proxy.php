<?php
http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
echo 'O proxy de páginas foi desativado. Use feed.php para notícias importadas ou o link da fonte original.';
