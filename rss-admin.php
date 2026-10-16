<?php
require_once __DIR__.'/cms/core.php';
header('Cache-Control: no-store'); header('X-Frame-Options: DENY');
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','samesite'=>'Strict']); session_start();
$password=revista_setting('RSS_ADMIN_PASSWORD_HASH');
if (!$password) { http_response_code(503); exit('Conclua a configuração em instalar.php para habilitar o painel.'); }
$_SESSION['csrf']=$_SESSION['csrf'] ?? bin2hex(random_bytes(32)); $message='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!hash_equals($_SESSION['csrf'],(string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Formulário expirado.'); }
    if (isset($_POST['password'])) {
        if (password_verify((string)$_POST['password'],$password)) { session_regenerate_id(true); $_SESSION['rss_admin']=true; }
        else { usleep(500000); $message='Senha inválida.'; }
    } elseif (!empty($_SESSION['rss_admin'])) {
        try {
            $db=rss_db(); $action=$_POST['action'] ?? ''; $id=(int)($_POST['id'] ?? 0);
            if ($action==='logout') { $_SESSION=[]; session_destroy(); header('Location: rss-admin.php'); exit; }
            if ($action==='save') {
                $url=rss_url((string)($_POST['url'] ?? '')); $host=parse_url($url,PHP_URL_HOST);
                if (filter_var($host,FILTER_VALIDATE_IP)) rss_public_ips($host);
                if (strtolower($host)==='localhost') throw new InvalidArgumentException('Use uma URL pública.');
                rss_save_source($id,(string)($_POST['name'] ?? ''),$url,(string)($_POST['category'] ?? ''),(string)($_POST['type'] ?? 'auto'));
                $message=$id ? 'Fonte atualizada. Nome, link e categoria foram salvos.' : 'Fonte salva. Execute o importador ou aguarde o próximo agendamento.';
            } elseif ($action==='toggle') {
                $db->prepare('UPDATE sources SET active=1-active WHERE id=?')->execute([$id]); $message='Status atualizado.';
            } elseif ($action==='delete') {
                $db->prepare('DELETE FROM sources WHERE id=?')->execute([$id]); $message='Fonte, itens e histórico removidos.';
            }
        } catch(Throwable $e) { error_log('RSS painel: '.$e->getMessage()); $message='Não foi possível salvar. '.($e instanceof InvalidArgumentException ? $e->getMessage() : 'Verifique se a URL já está cadastrada e se o armazenamento permite escrita.'); }
    } else { http_response_code(403); $message='Faça login.'; }
}
if(!empty($_SESSION['rss_admin'])) rss_db();
function csrf_field() { echo '<input type="hidden" name="csrf" value="'.rss_escape($_SESSION['csrf']).'">'; }
function source_fields($source=[]) { ?>
<input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=(int)($source['id'] ?? 0)?>">
<label>Nome<input name="name" maxlength="150" required value="<?=rss_escape($source['name'] ?? '')?>"></label>
<label>Tipo de fonte<select name="type"><option value="rss" <?=rss_source_type($source)==='rss'?'selected':''?>>RSS / Atom</option><option value="ofec" <?=rss_source_type($source)==='ofec'?'selected':''?>>Página de notícias OFEC (API pública)</option></select></label><p class="field-help">Para OFEC, use https://operarioferroviario.com.br/noticias. O clube também é reconhecido automaticamente pelo endereço.</p>
<label>Link RSS/Atom ou página OFEC<input name="url" type="url" required value="<?=rss_escape($source['url'] ?? '')?>"></label>
<label>Categoria<select name="category"><?php foreach(rss_categories() as $category): ?><option value="<?=rss_escape($category)?>" <?=($source['category'] ?? '')===$category?'selected':''?>><?=rss_escape(ucwords(str_replace('-',' ',$category)))?></option><?php endforeach; ?></select></label>
<button>Salvar <?=$source?'alterações':'fonte'?></button>
<?php }
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Painel RSS — Revista PG Turismo</title><link rel="stylesheet" href="style.css"><link rel="stylesheet" href="rss-admin.css"></head><body><main class="rss-panel">
<header class="panel-heading"><div><p class="eyebrow">Revista PG Turismo</p><h1>Fontes RSS</h1><p>Gerencie suas fontes e acompanhe cada importação.</p></div><a href="instalar.php">Configurar site</a></header>
<?php if($message): ?><p class="notice" role="status"><?=rss_escape($message)?></p><?php endif; ?>
<?php if(empty($_SESSION['rss_admin'])): ?>
<form method="post" class="panel-card login-form"><?php csrf_field(); ?><label>Senha<input type="password" name="password" required autocomplete="current-password"></label><button>Entrar</button></form>
<?php else:
$db=rss_db(); $status=rss_status();
$totals=$db->query("SELECT count(*) AS attempts,coalesce(sum(status='error'),0) AS errors,coalesce(sum(status='partial'),0) AS partial,coalesce(sum(created),0) AS created,coalesce(sum(image_errors),0) AS image_errors FROM import_runs")->fetch(PDO::FETCH_ASSOC);
$sources=$db->query("SELECT s.*,count(r.id) AS attempts,coalesce(sum(r.status='error'),0) AS errors,coalesce(sum(r.status='partial'),0) AS partial,coalesce(sum(r.image_errors>0),0) AS image_failures,coalesce(sum(r.content_errors),0) AS content_errors FROM sources s LEFT JOIN import_runs r ON r.source_id=s.id GROUP BY s.id ORDER BY s.name")->fetchAll(PDO::FETCH_ASSOC);
?>
<section class="panel-stats" aria-label="Resumo"><div class="panel-card"><strong><?=(int)$status['articles']?></strong><span>notícias armazenadas</span></div><div class="panel-card"><strong><?=(int)$status['visible']?></strong><span>visíveis com foto local</span></div><div class="panel-card"><strong><?=(int)$totals['attempts']?></strong><span>tentativas de importação</span></div><div class="panel-card"><strong><?=(int)$totals['errors']?></strong><span>falhas de feed</span></div><div class="panel-card"><strong><?=(int)$totals['image_errors']?></strong><span>falhas de imagem</span></div></section>
<p class="sr-summary"><?=(int)$status['articles']?> notícias armazenadas · <?=(int)$status['visible']?> visíveis</p>
<nav class="panel-links"><a href="admin.php">Administrar site, notícias e empresas</a><a href="noticias.php#noticias-rss">Ver notícias RSS</a><a href="feed.php" target="_blank" rel="noopener">Ver XML</a><a href="#historico">Histórico de importações</a></nav>
<p class="notice">Somente matérias RSS com imagem baixada e válida são publicadas. As demais ficam armazenadas para novas tentativas, até 90 pendências por fonte. O limite de 90 publicadas por categoria considera a ordem de cadastro; itens sem foto não ocupam essas vagas. Os contadores de tentativas e erros começam nesta versão, sem reconstruir execuções anteriores.</p>
<details class="panel-card"><summary>Cadastrar fonte RSS ou OFEC</summary><form method="post" class="source-form"><?php csrf_field(); source_fields(); ?></form></details>
<section><h2>Fontes cadastradas</h2>
<?php if(!$sources): ?><p>Nenhuma fonte cadastrada. Use o formulário acima para começar.</p><?php endif; ?>
<?php foreach($sources as $source):
$items=$db->prepare('SELECT image_file FROM articles WHERE source_id=?'); $items->execute([$source['id']]); $stored=0; $photos=0;
foreach($items as $article) { $stored++; if(rss_has_image($article)) $photos++; }
?>
<article class="panel-card source-card"><div class="source-heading"><div><h3><?=rss_escape($source['name'])?></h3><p class="source-url"><?=rss_escape($source['url'])?></p></div><span class="badge <?=$source['active']?'':'paused'?>"><?=$source['active']?'Ativa':'Pausada'?></span></div>
<p>Tipo: <?=rss_source_type($source)==='ofec'?'Página OFEC / API pública':'RSS / Atom'?></p><p>Categoria: <strong><?=rss_escape(ucfirst($source['category']))?></strong></p>
<div class="source-metrics"><span><?=$stored?> armazenadas</span><span><?=$source['active']?$photos:0?> publicadas</span><span><?=$stored-$photos?> sem foto local válida</span><span><?=(int)$source['attempts']?> tentativas</span><span><?=(int)$source['attempts']-(int)$source['errors']-(int)$source['partial']?> importações sem erros</span><span><?=(int)$source['errors']?> falhas de feed</span><span><?=(int)$source['image_failures']?> importações com falha de imagem</span><span><?=(int)$source['content_errors']?> falhas de conteúdo</span></div>
<p>Última tentativa (UTC): <?=rss_escape($source['checked_at'] ?: 'Ainda não executada')?></p><?php if($source['error']): ?><p class="error-message">Erro: <?=rss_escape($source['error'])?></p><?php endif; ?>
<?php $errors=$db->prepare("SELECT title,image_error FROM articles WHERE source_id=? AND image_error<>'' ORDER BY id DESC LIMIT 10"); $errors->execute([$source['id']]); $imageErrors=$errors->fetchAll(PDO::FETCH_ASSOC); if($imageErrors): ?><details><summary>Erros de imagem recentes (até 10 itens)</summary><ul><?php foreach($imageErrors as $imageError): ?><li><strong><?=rss_escape($imageError['title'])?></strong>: <?=rss_escape($imageError['image_error'])?></li><?php endforeach; ?></ul></details><?php endif; ?>
<details><summary>Editar nome, link e categoria</summary><form method="post" class="source-form"><?php csrf_field(); source_fields($source); ?></form><p>Editar mantém o identificador, as notícias e o histórico. Trocar a categoria move também as notícias já importadas.</p></details>
<form method="post" class="source-actions"><?php csrf_field(); ?><input type="hidden" name="id" value="<?=(int)$source['id']?>"><button name="action" value="toggle" class="secondary"><?=$source['active']?'Pausar':'Ativar'?></button><button name="action" value="delete" class="danger" onclick="return confirm('Excluir fonte, notícias e histórico?')">Excluir</button></form>
</article><?php endforeach; ?></section>
<section id="historico" class="panel-card"><h2>Histórico de importações</h2><p>Resultados por fonte, em UTC. Exibindo as 100 execuções mais recentes. “Existentes” são itens reprocessados, não novos cadastros.</p>
<form method="get" class="history-filter"><label>Fonte<select name="source"><option value="0">Todas</option><?php foreach($sources as $source): ?><option value="<?=(int)$source['id']?>" <?=(int)($_GET['source'] ?? 0)===(int)$source['id']?'selected':''?>><?=rss_escape($source['name'])?></option><?php endforeach; ?></select></label><button>Filtrar</button></form>
<?php $filter=(int)($_GET['source'] ?? 0); $query='SELECT r.*,s.name FROM import_runs r JOIN sources s ON s.id=r.source_id'; $args=[]; if($filter) { $query.=' WHERE r.source_id=?'; $args[]=$filter; } $runs=$db->prepare($query.' ORDER BY r.id DESC LIMIT 100'); $runs->execute($args); $history=$runs->fetchAll(PDO::FETCH_ASSOC); ?>
<?php if(!$history): ?><p>Nenhuma importação registrada ainda. Execute o importador ou aguarde o agendamento.</p><?php else: ?><div class="table-scroll"><table><thead><tr><th>Data / fonte</th><th>Resultado</th><th>Processados</th><th>Novos</th><th>Existentes</th><th>Erros de imagem</th><th>Erros de conteúdo</th></tr></thead><tbody>
<?php foreach($history as $run): ?><tr><td><?=rss_escape($run['finished_at'])?><br><strong><?=rss_escape($run['name'])?></strong><details><summary>Link utilizado</summary><?=rss_escape($run['source_url'])?></details></td><td><?=rss_escape(['success'=>'Sucesso','partial'=>'Parcial','error'=>'Erro de feed'][$run['status']] ?? $run['status'])?><?php if($run['error']): ?><br><span class="error-message"><?=rss_escape($run['error'])?></span><?php endif; ?></td><td><?=(int)$run['processed']?></td><td><?=(int)$run['created']?></td><td><?=(int)$run['existing']?></td><td><?=(int)$run['image_errors']?></td><td><?=(int)$run['content_errors']?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<details class="panel-card"><summary>Diagnóstico do armazenamento</summary><p>Configuração: <?=rss_escape(revista_config_dir())?></p><p>Armazenamento RSS: <?=rss_escape($status['file'])?></p><p>Compare com <code>php rss-importar.php --diagnostico</code>.</p></details>
<form method="post"><?php csrf_field(); ?><button name="action" value="logout" class="secondary">Sair</button></form>
<?php endif; ?></main></body></html>
