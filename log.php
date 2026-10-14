<?php
$prefix = 'DB';
$host = getenv($prefix . '_HOST');
$user = getenv($prefix . '_USER');
$pass = getenv($prefix . '_PASSWORD');
$database = getenv($prefix . '_NAME');
if (!$host || !$user || !$database) {
    throw new RuntimeException('Configure as variáveis de conexão do banco no servidor.');
}
$mysqli = new mysqli($host, $user, $pass === false ? '' : $pass, $database, (int)(getenv($prefix . '_PORT') ?: 3306));
$mysqli->set_charset('utf8mb4');
