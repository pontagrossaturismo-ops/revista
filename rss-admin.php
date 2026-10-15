<?php
require_once __DIR__.'/rss/core.php';
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','samesite'=>'Strict']);
session_start();
$password=revista_setting('RSS_ADMIN_PASSWORD_HASH');
if (!$password) { http_response_code(503); exit('Conclua a configuração em instalar.php para habilitar o painel.'); }
$_SESSION['csrf']=$_SESSION['csrf'] ?? bin2hex(random_bytes(32));
$message='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!hash_equals($_SESSION['csrf'],(string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Formulário expirado.'); }
    if (isset($_POST['password'])) {
        if (password_verify((string)$_POST['password'],$password)) { session_regenerate_id(true); $_SESSION['rss_admin']=true; }
        else { usleep(500000); $message='Senha inválida.'; }
    } elseif (!empty($_SESSION['rss_admin'])) {
        try {
            $db=rss_db(); $action=$_POST['action'] ?? '';
            if ($action==='logout') { $_SESSION=[]; session_destroy(); header('Location: rss-admin.php'); exit; }
            if ($action==='save') {
                $name=trim((string)($_POST['name'] ?? '')); $url=rss_url((string)($_POST['url'] ?? '')); $category=$_POST['category'] ?? '';
                if (!$name || strlen($name)>150 || !in_array($category,rss_categories(),true)) throw new InvalidArgumentException('Confira nome e categoria.');
                rss_public_ips(parse_url($url,PHP_URL_HOST));
                $db->prepare('INSERT INTO sources(name,url,category) VALUES(?,?,?) ON CONFLICT(url) DO UPDATE SET name=excluded.name,category=excluded.category')->execute([$name,$url,$category]);
                $message='Fonte salva. Execute o importador ou aguarde o próximo cron.';
            } elseif ($action==='toggle') {
                $db->prepare('UPDATE sources SET active=1-active WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]); $message='Status atualizado.';
            } elseif ($action==='delete') {
                $db->prepare('DELETE FROM sources WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]); $message='Fonte e itens removidos.';
            }
        } catch (Throwable $e) { error_log('RSS painel: '.$e->getMessage()); $message='Não foi possível salvar. Confira os campos, URL duplicada ou permissões do armazenamento.'; }
    } else { http_response_code(403); $message='Faça login.'; }
}
function csrf_field() { echo '<input type="hidden" name="csrf" value="'.rss_escape($_SESSION['csrf']).'">'; }
?>
<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Fontes RSS — Revista PG Turismo</title><link rel="stylesheet" href="style.css"><main style="max-width:1000px;margin:40px auto;padding:20px"><h1>Fontes RSS</h1><p><a href="instalar.php">Configurar site</a></p><p><?=rss_escape($message)?></p>
<?php if (empty($_SESSION['rss_admin'])): ?>
<form method="post"><?php csrf_field(); ?><label>Senha <input type="password" name="password" required autocomplete="current-password"></label><button>Entrar</button></form>
<?php else: ?>
<p>Cadastre a URL do RSS/Atom da fonte. Notícias serão exibidas na categoria escolhida, com imagem e leitura dentro do seu site, mantendo o crédito e o link da fonte.</p>
<form method="post"><?php csrf_field(); ?><input type="hidden" name="action" value="save"><p><label>Nome <input name="name" maxlength="150" required></label></p><p><label>URL RSS/Atom <input name="url" type="url" required style="width:80%"></label></p><p><label>Categoria <select name="category"><?php foreach(rss_categories() as $c): ?><option><?=rss_escape($c)?></option><?php endforeach; ?></select></label></p><button>Salvar fonte</button></form>
<?php $status=rss_status(); ?><p><strong><?= (int)$status['articles'] ?> notícias armazenadas · <?= (int)$status['visible'] ?> visíveis</strong> | <a href="noticias.php#noticias-rss">Ver notícias RSS</a> | <a href="feed.php" target="_blank" rel="noopener">Ver XML</a></p>
<details><summary>Diagnóstico do armazenamento</summary><p>Configuração: <?=rss_escape(revista_config_dir())?></p><p>Armazenamento RSS: <?=rss_escape($status['file'])?></p><p>Compare estes caminhos com a saída de <code>php rss-importar.php --diagnostico</code>. Se forem diferentes, o Apache e o terminal estão lendo configurações distintas.</p></details>
<h2>Fontes cadastradas</h2><p>Para editar nome/categoria, salve novamente a mesma URL. Desativar oculta seus itens; excluir remove também as notícias importadas.</p>
<?php foreach(rss_db()->query('SELECT s.*,(SELECT count(*) FROM articles a WHERE a.source_id=s.id) AS total,(SELECT count(*) FROM articles a WHERE a.source_id=s.id AND a.image_error<>"") AS image_errors FROM sources s ORDER BY name') as $s): ?>
<article style="padding:15px;border-bottom:1px solid #ccc"><h3><?=rss_escape($s['name'])?> — <?=rss_escape($s['category'])?></h3><p><?=rss_escape($s['url'])?></p><p><?=$s['active']?'Ativa':'Pausada'?> | <?=(int)$s['total']?> notícias armazenadas | <?=(int)$s['image_errors']?> imagens com falha no download | Última tentativa (UTC): <?=rss_escape($s['checked_at'] ?: 'Ainda não executada')?></p><?php if($s['error']): ?><p>Erro: <?=rss_escape($s['error'])?></p><?php endif; ?><form method="post"><?php csrf_field(); ?><input type="hidden" name="id" value="<?=(int)$s['id']?>"><button name="action" value="toggle"><?=$s['active']?'Pausar':'Ativar'?></button> <button name="action" value="delete" onclick="return confirm('Excluir fonte e notícias importadas?')">Excluir</button></form></article>
<?php endforeach; ?>
<form method="post"><?php csrf_field(); ?><button name="action" value="logout">Sair</button></form>
<?php endif; ?></main></html>
