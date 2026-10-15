<?php
require dirname(__DIR__).'/config/runtime.php';
$count=0;
function verify_path($ok,$message) { global $count; if (!$ok) throw new RuntimeException($message); $count++; }
verify_path(revista_path_normalize('C:\\XAMPP\\htdocs\\PG-2025\\novo')==='c:/xampp/htdocs/pg-2025/novo','Normalizar caminho Windows');
verify_path(revista_path_inside('C:\\XAMPP\\HTDOCS\\site','c:/xampp/htdocs'),'Comparação sem diferenciar maiúsculas Windows');
verify_path(!revista_path_inside('C:/xampp/htdocs-backup','C:/xampp/htdocs'),'Respeitar limite do diretório');
$private=revista_default_config_dir('C:\\xampp\\htdocs\\PG-2025\\novo');
verify_path(str_starts_with($private,'C:/xampp/revista-config-'),'Pasta privada do XAMPP');
verify_path(!revista_path_inside($private,'C:/xampp/htdocs'),'Configuração fora de todo o htdocs');
verify_path($private===revista_default_config_dir('c:/XAMPP/HTDOCS/pg-2025/NOVO') || strtolower($private)===strtolower(revista_default_config_dir('c:/XAMPP/HTDOCS/pg-2025/NOVO')),'Pasta consistente no Windows');
verify_path(revista_default_config_dir('/workspace/revista')==='/workspace/revista-config','Compatibilidade Linux');
verify_path(revista_default_config_dir('C:/xampp/htdocs/outro')!==$private,'Isolar instalações XAMPP');
echo "$count verificações de caminhos passaram.\n";
