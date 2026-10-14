<?php
require_once __DIR__.'/rss/core.php';
header('Cache-Control: no-store'); header('X-Frame-Options: DENY');
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','samesite'=>'Strict']); session_start();
$_SESSION['setup_csrf']=$_SESSION['setup_csrf'] ?? bin2hex(random_bytes(32));
$config=revista_config(); $installed=!empty($config['INSTALLED']);
$authHash=$installed ? revista_setting('RSS_ADMIN_PASSWORD_HASH','') : (is_file(revista_config_dir().'/setup-token.hash') ? file_get_contents(revista_config_dir().'/setup-token.hash') : '');
$authorized=$authHash && hash_equals(hash('sha256',$authHash),(string)($_SESSION['site_config_auth'] ?? ''));
$message=''; $saved=false;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!hash_equals($_SESSION['setup_csrf'],(string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Formulário expirado.'); }
    if (isset($_POST['activation'])) {
        if ($authHash && password_verify((string)$_POST['activation'],$authHash)) { session_regenerate_id(true); $_SESSION['site_config_auth']=hash('sha256',$authHash); $authorized=true; }
        else { usleep(500000); $message='Código ou senha inválidos.'; }
    } elseif ($authorized && ($_POST['action'] ?? '')==='save') {
        try {
            foreach (['curl','SimpleXML','pdo_sqlite','mysqli'] as $extension) if (!extension_loaded($extension)) throw new RuntimeException('Falta a extensão PHP '.$extension.'.');
            $new=$config;
            $new['SITE_TITLE']=trim((string)($_POST['site_title'] ?? ''));
            $new['SITE_DESCRIPTION']=trim((string)($_POST['site_description'] ?? ''));
            $new['SITE_URL']=rtrim(rss_url((string)($_POST['site_url'] ?? '')),'/');
            if (!$new['SITE_TITLE'] || strlen($new['SITE_TITLE'])>150) throw new RuntimeException('Preencha o nome do site (até 150 caracteres).');
            $interval=filter_var($_POST['interval'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>5,'max_range'=>1440]]);
            if ($interval===false) throw new RuntimeException('Intervalo entre 5 e 1440 minutos.');
            $new['RSS_INTERVAL_MINUTES']=$interval;
            $new['RSS_DATA_DIR']=revista_config_dir().'/rss';
            $password=(string)($_POST['admin_password'] ?? '');
            if ($password!=='' || !$installed) {
                if (strlen($password)<12 || $password!==(string)($_POST['admin_confirm'] ?? '')) throw new RuntimeException('A senha do painel precisa de 12 caracteres e confirmação igual.');
                $new['RSS_ADMIN_PASSWORD_HASH']=password_hash($password,PASSWORD_DEFAULT);
            }
            $useDb=!empty($_POST['use_db']);
            if ($useDb) {
                $new['DB_HOST']=trim((string)($_POST['db_host'] ?? ''));
                $new['DB_USER']=trim((string)($_POST['db_user'] ?? ''));
                $new['DB_NAME']=trim((string)($_POST['db_name'] ?? ''));
                $port=filter_var($_POST['db_port'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>65535]]);
                if (!$port) throw new RuntimeException('Porta do banco inválida.');
                $new['DB_PORT']=$port;
                $dbPassword=(string)($_POST['db_password'] ?? '');
                if ($dbPassword!=='' || !isset($new['DB_PASSWORD'])) $new['DB_PASSWORD']=$dbPassword;
                try { $db=revista_connect('DB',$new); $db->query('SELECT id,titulo,categoria,gravata,foto1,conteudo FROM materia LIMIT 0'); $db->close(); }
                catch(Throwable $e) { throw new RuntimeException('Não foi possível validar o banco/tabela materia. Confira os dados, acesso externo e existência da tabela. Nenhuma configuração foi salva.'); }
            } else { foreach(['DB_HOST','DB_USER','DB_NAME','DB_PASSWORD','DB_PORT'] as $key) unset($new[$key]); }
            if (!empty($_POST['use_conv'])) {
                foreach (['HOST','USER','NAME'] as $key) $new['DB_CONV_'.$key]=trim((string)($_POST['conv_'.strtolower($key)] ?? ''));
                $port=filter_var($_POST['conv_port'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>65535]]);
                if (!$port) throw new RuntimeException('Porta do banco do guia inválida.');
                $new['DB_CONV_PORT']=$port;
                $convPassword=(string)($_POST['conv_password'] ?? '');
                if ($convPassword!=='' || !isset($new['DB_CONV_PASSWORD'])) $new['DB_CONV_PASSWORD']=$convPassword;
                try { $db=revista_connect('DB_CONV',$new); $db->query('SELECT 1 FROM lojas LIMIT 0'); $db->close(); }
                catch(Throwable $e) { throw new RuntimeException('Não foi possível validar o banco/tabela lojas do guia. Confira os dados. Nenhuma configuração foi salva.'); }
            } else { foreach(['HOST','USER','NAME','PASSWORD','PORT'] as $key) unset($new['DB_CONV_'.$key]); }
            $feedUrl=trim((string)($_POST['feed_url'] ?? '')); $feedName=trim((string)($_POST['feed_name'] ?? '')); $category=$_POST['category'] ?? 'noticias';
            if ($feedUrl) {
                $feedUrl=rss_url($feedUrl);
                if (!$feedName || !in_array($category,rss_categories(),true)) throw new RuntimeException('Preencha nome e categoria da fonte.');
                rss_public_ips(parse_url($feedUrl,PHP_URL_HOST));
            }
            if (!is_dir($new['RSS_DATA_DIR']) && !mkdir($new['RSS_DATA_DIR'],0700,true)) throw new RuntimeException('Não foi possível preparar armazenamento RSS.');
            // SQLite independente da configuração ainda não salva.
            $storage=new PDO('sqlite:'.$new['RSS_DATA_DIR'].'/rss.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
            $storage->exec('CREATE TABLE IF NOT EXISTS sources (id INTEGER PRIMARY KEY, name TEXT NOT NULL, url TEXT NOT NULL UNIQUE, category TEXT NOT NULL, active INTEGER NOT NULL DEFAULT 1, checked_at TEXT, error TEXT)');
            $storage->beginTransaction();
            try {
                if ($feedUrl) $storage->prepare('INSERT INTO sources(name,url,category) VALUES(?,?,?) ON CONFLICT(url) DO UPDATE SET name=excluded.name,category=excluded.category')->execute([$feedName,$feedUrl,$category]);
                $new['INSTALLED']=true; revista_write_config($new); $storage->commit();
            } catch(Throwable $e) { if ($storage->inTransaction()) $storage->rollBack(); throw $e; }
            if (is_file(revista_config_dir().'/setup-token.hash')) unlink(revista_config_dir().'/setup-token.hash');
            $config=$new; $installed=true; $authHash=$new['RSS_ADMIN_PASSWORD_HASH']; $_SESSION['site_config_auth']=hash('sha256',$authHash);
            $message='Configuração salva. O site e o painel já podem usar estes dados.'; $saved=true;
        } catch(Throwable $e) { $message=$e->getMessage(); }
    } else { http_response_code(403); $message='Entre antes de configurar.'; }
}
function field($key,$default='') { global $config; return rss_escape($config[$key] ?? $default); }
function setup_csrf() { echo '<input type="hidden" name="csrf" value="'.rss_escape($_SESSION['setup_csrf']).'">'; }
?>
<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Configurar site</title><link rel="stylesheet" href="style.css"><style>.setup{max-width:800px;margin:30px auto;padding:24px}.setup label{display:block;margin:16px 0}.setup input:not([type=checkbox]),.setup textarea,.setup select{display:block;width:100%;padding:10px}.setup fieldset{padding:20px;margin:20px 0}.setup code{overflow-wrap:anywhere}.setup button{padding:12px 20px}</style><main class="setup"><h1>Configurar Revista PG Turismo</h1><p role="status"><?=rss_escape($message)?></p>
<?php if (!$authorized): ?>
<?php if (!$authHash): ?><p>Para proteger a primeira configuração, execute uma vez no terminal da hospedagem, dentro da pasta do site:</p><pre>php configurar-token.php</pre><p>Depois atualize esta página e informe o código gerado. O diretório pai da pasta do site precisa permitir criar uma pasta privada. Use HTTPS.</p>
<?php else: ?><form method="post"><?php setup_csrf(); ?><label><?=$installed?'Senha do painel':'Código de ativação'?><input type="password" name="activation" required autocomplete="current-password"></label><button>Entrar</button></form><?php endif; ?>
<?php else: ?>
<form method="post"><?php setup_csrf(); ?><input type="hidden" name="action" value="save">
<fieldset><legend>Site</legend><label>Nome do site<input name="site_title" maxlength="150" value="<?=field('SITE_TITLE','Revista PG Turismo')?>" required></label><label>Descrição<textarea name="site_description"><?=field('SITE_DESCRIPTION','Turismo, lazer, gastronomia e notícias')?></textarea></label><label>Endereço do site<input type="url" name="site_url" value="<?=field('SITE_URL','https://www.revistapgturismo.com.br')?>" required></label></fieldset>
<fieldset><legend>Banco de dados existente</legend><label><input type="checkbox" name="use_db" value="1" <?=!$installed || !empty($config['DB_HOST'])?'checked':''?>> Usar o banco MySQL das matérias</label><label>Servidor<input name="db_host" value="<?=field('DB_HOST','revistapg.mysql.uhserver.com')?>"></label><label>Usuário<input name="db_user" value="<?=field('DB_USER','revistapg')?>"></label><label>Senha do banco<input type="password" name="db_password" autocomplete="new-password"></label><p>Se já estiver salva, deixe a senha vazia para mantê-la. A senha nunca é exibida.</p><label>Nome do banco<input name="db_name" value="<?=field('DB_NAME','revistapg')?>"></label><label>Porta<input type="number" name="db_port" min="1" max="65535" value="<?=field('DB_PORT',3306)?>"></label><p>A conexão e a tabela materia são verificadas antes de salvar. Nenhuma tabela existente será alterada.</p></fieldset>
<fieldset><legend>Banco do guia de estabelecimentos (opcional)</legend><label><input type="checkbox" name="use_conv" value="1" <?=!empty($config['DB_CONV_HOST'])?'checked':''?>> Configurar também o guia</label>
<?php foreach(['host'=>'Servidor','user'=>'Usuário','name'=>'Nome do banco'] as $key=>$label): ?><label><?=rss_escape($label)?><input name="conv_<?=$key?>" value="<?=field('DB_CONV_'.strtoupper($key))?>"></label><?php endforeach; ?>
<label>Senha do banco do guia<input type="password" name="conv_password" autocomplete="new-password"></label><label>Porta<input type="number" name="conv_port" min="1" max="65535" value="<?=field('DB_CONV_PORT',3306)?>"></label><p>Deixe a senha vazia para manter a existente. A tabela lojas será validada.</p></fieldset>
<fieldset><legend>Acesso e atualização</legend><label><?=$installed?'Nova senha do painel (opcional)':'Senha do painel'?><input type="password" name="admin_password" minlength="12" autocomplete="new-password" <?=$installed?'':'required'?>></label><label>Confirmar senha<input type="password" name="admin_confirm" autocomplete="new-password" <?=$installed?'':'required'?>></label><label>Intervalo entre importações, em minutos<input type="number" name="interval" min="5" max="1440" value="<?=field('RSS_INTERVAL_MINUTES',15)?>" required></label></fieldset>
<fieldset><legend>Fonte RSS inicial (opcional)</legend><label>Nome da fonte<input name="feed_name"></label><label>URL do RSS/Atom<input type="url" name="feed_url"></label><label>Categoria<select name="category"><?php foreach(rss_categories() as $c): ?><option><?=rss_escape($c)?></option><?php endforeach; ?></select></label></fieldset><button>Validar e salvar configuração</button></form>
<?php if ($installed): ?><h2>Próximo passo: atualização automática</h2><p>Abra <a href="rss-admin.php">o painel de feeds</a> para cadastrar outras fontes. No painel da hospedagem, crie uma tarefa cron a cada minuto com este comando; o importador respeita o intervalo configurado:</p><pre><?=rss_escape('cd '.escapeshellarg(__DIR__).' && php rss-importar.php')?></pre><p>O site não consegue criar tarefas no painel da hospedagem. Para testar imediatamente no terminal, execute <code>php rss-importar.php --force</code>.</p><p><a href="index.php">Ver site</a></p><?php endif; ?>
<?php endif; ?></main></html>
