<?php
require_once __DIR__.'/tourism.php'; require_once __DIR__.'/admin-layout.php';
header('Cache-Control: no-store'); header('X-Frame-Options: DENY');
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','samesite'=>'Strict']); session_start();
if(!revista_setting('RSS_ADMIN_PASSWORD_HASH')) { http_response_code(503); exit('Conclua a instalação.'); }
if(empty($_SESSION['rss_admin'])) { header('Location: admin.php'); exit; }
$_SESSION['csrf']=$_SESSION['csrf'] ?? bin2hex(random_bytes(32)); $area=($_GET['area'] ?? '')==='events'?'events':'routes'; $table=$area==='events'?'tourism_events':'tourism_routes'; $label=$area==='events'?'Agenda de eventos':'Roteiros'; $message=''; $success=false;
$db=tourism_db();
if($_SERVER['REQUEST_METHOD']==='POST') {
    if(!hash_equals($_SESSION['csrf'],(string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Formulário expirado.'); }
    try {
        $id=(int)($_POST['id'] ?? 0);
        if(($_POST['action'] ?? '')==='delete') { $db->prepare('DELETE FROM '.$table.' WHERE id=?')->execute([$id]); $message='Cadastro excluído.'; }
        elseif(($_POST['action'] ?? '')==='save') { $v=$_POST; if(isset($_FILES['image_file'])) { $image=cms_upload($_FILES['image_file']); if($image) $v['image']=$image; } tourism_save($area,$id,$v); $message='Cadastro salvo.'; }
        $success=true;
    } catch(Throwable $e) { $message=$e instanceof InvalidArgumentException?$e->getMessage():'Não foi possível salvar. Confira o endereço curto e as permissões.'; error_log('Turismo: '.$e->getMessage()); }
}
$edit=(int)($_GET['edit'] ?? 0); $record=[];
if($edit) { $stmt=$db->prepare('SELECT * FROM '.$table.' WHERE id=?'); $stmt->execute([$edit]); $record=$stmt->fetch(PDO::FETCH_ASSOC) ?: []; }
if(!$success && $_SERVER['REQUEST_METHOD']==='POST') $record=array_merge($record,$_POST);
$show=!$success && (isset($_GET['new']) || $edit || $_SERVER['REQUEST_METHOD']==='POST');
function tourism_admin_field($name,$label,$record,$type='text',$required=false) { ?><label><?=rss_escape($label)?><input name="<?=rss_escape($name)?>" type="<?=rss_escape($type)?>" value="<?=rss_escape($type==='datetime-local'?str_replace(' ','T',$record[$name] ?? ''):($record[$name] ?? ''))?>" <?=$required?'required':''?>></label><?php }
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=rss_escape($label)?> — Admin</title><link rel="stylesheet" href="rss-admin.css"><link rel="stylesheet" href="cms/site.css"><link rel="stylesheet" href="cms/admin.css"></head><body class="admin-shell"><main class="rss-panel"><?php cms_admin_sidebar($area); ?><header class="panel-heading"><h1><?=rss_escape($label)?></h1><a href="<?=$area==='events'?'agenda.php':'roteiros.php'?>">Ver no site</a></header><?php if($message): ?><p class="notice" role="status"><?=rss_escape($message)?></p><?php endif; ?>
<div class="admin-toolbar"><p><?=$area==='events'?'Eventos encerrados deixam a agenda pública automaticamente; o cadastro permanece aqui.':'Os roteiros iniciais são sugestões editoriais e podem ser revisados, ocultados ou excluídos.'?></p><a href="admin.php?area=<?=$area?>&new=1" class="admin-add">+ Adicionar item</a></div>
<?php if($show): ?><form method="post" enctype="multipart/form-data" class="panel-card cms-form"><input type="hidden" name="csrf" value="<?=rss_escape($_SESSION['csrf'])?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=(int)($record['id'] ?? 0)?>"><?php tourism_admin_field('title','Título',$record,'text',true);
if($area==='events') { foreach(['category'=>'Categoria (ex.: cultura, esporte, gastronomia)','starts'=>'Início — horário de Brasília','ends'=>'Término — horário de Brasília','location'=>'Local e endereço','price'=>'Preço ou informação de gratuidade','link'=>'Link oficial ou ingressos'] as $field=>$caption) tourism_admin_field($field,$caption,$record,in_array($field,['starts','ends'])?'datetime-local':'text',in_array($field,['starts','ends','location'])); }
else { tourism_admin_field('slug','Endereço curto',$record,'text',true); tourism_admin_field('duration','Duração sugerida',$record); tourism_admin_field('summary','Apresentação curta',$record); }
tourism_admin_field('image','Endereço da imagem (opcional)',$record); ?><label>Ou enviar imagem<input type="file" name="image_file" accept="image/jpeg,image/png,image/webp,image/gif"></label><label>Conteúdo<textarea name="body" rows="16" required><?=rss_escape($record['body'] ?? '')?></textarea></label><label><input type="checkbox" name="published" value="1" <?=!empty($record['published'])?'checked':''?>> Publicar no site</label><button>Salvar</button> <a href="admin.php?area=<?=$area?>">Cancelar</a></form><?php endif; ?>
<section class="panel-card"><h2>Cadastros</h2><?php foreach($db->query('SELECT * FROM '.$table.' ORDER BY id DESC') as $r): ?><article class="admin-item"><div><strong><?=rss_escape($r['title'])?></strong><p><?=$r['published']?'Publicado':'Rascunho'?><?=$area==='events'?' · '.rss_escape($r['starts']).' · '.($r['ends']<tourism_today()?'Encerrado':'Programado'):''?></p></div><div class="source-actions"><a href="admin.php?area=<?=$area?>&edit=<?=(int)$r['id']?>">Editar</a><form method="post" onsubmit="return confirm('Excluir este cadastro?')"><input type="hidden" name="csrf" value="<?=rss_escape($_SESSION['csrf'])?>"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><button name="action" value="delete" class="danger">Excluir</button></form></div></article><?php endforeach; ?></section></main></body></html>
