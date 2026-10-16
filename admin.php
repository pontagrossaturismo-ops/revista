<?php
require_once __DIR__.'/cms/core.php';
header('Cache-Control: no-store'); header('X-Frame-Options: DENY');
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','samesite'=>'Strict']); session_start();
$password=revista_setting('RSS_ADMIN_PASSWORD_HASH');
if(!$password) { http_response_code(503); exit('Conclua instalar.php antes de acessar o painel.'); }
$_SESSION['csrf']=$_SESSION['csrf'] ?? bin2hex(random_bytes(32));
$areas=['sections'=>'Seções','banners'=>'Banners','news'=>'Notícias manuais','companies'=>'Empresas'];
$area=is_string($_POST['area'] ?? $_GET['area'] ?? '') ? ($_POST['area'] ?? $_GET['area'] ?? 'sections') : 'sections'; if(!isset($areas[$area])) $area='sections';
$message=''; $success=false;
if($_SERVER['REQUEST_METHOD']==='POST') {
    if(!hash_equals($_SESSION['csrf'],(string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Formulário expirado.'); }
    if(isset($_POST['password'])) {
        if(password_verify((string)$_POST['password'],$password)) { session_regenerate_id(true); $_SESSION['rss_admin']=true; }
        else { usleep(500000); $message='Senha inválida.'; }
    } elseif(!empty($_SESSION['rss_admin'])) {
        try {
            $action=$_POST['action'] ?? ''; $id=(int)($_POST['id'] ?? 0); $legacy=(string)($_POST['legacy'] ?? '');
            if($action==='logout') { $_SESSION=[]; session_destroy(); header('Location: admin.php'); exit; }
            if($action==='delete') {
                if($area==='news') cms_delete_news($id,(int)$legacy);
                elseif($area==='companies') cms_delete_company($id,$legacy);
                else { $table=$area==='sections'?'cms_sections':'cms_banners'; cms_db()->prepare('DELETE FROM '.$table.' WHERE id=?')->execute([$id]); }
                $message='Registro removido do site.'; $success=true;
            } elseif($action==='save') {
                $values=$_POST;
                $imageFields=$area==='companies'?['perfil','capa','foto1','foto2','foto3','foto4','foto5','foto6','foto7','foto8']:['image'];
                foreach($imageFields as $field) if(isset($_FILES[$field.'_file'])) { $image=cms_upload($_FILES[$field.'_file']); if($image) $values[$field]=$image; }
                if($area==='sections') cms_save_section($id,$values);
                elseif($area==='banners') cms_save_banner($id,$values);
                elseif($area==='news') cms_save_news($id,(int)$legacy,$values);
                else cms_save_company($id,$legacy,$values);
                $message='Alterações salvas.'; $success=true;
            }
        } catch(Throwable $e) { error_log('Admin CMS: '.$e->getMessage()); $message=$e instanceof InvalidArgumentException ? $e->getMessage() : 'Não foi possível salvar. Confira campos duplicados e permissões do armazenamento.'; }
    } else { http_response_code(403); $message='Faça login para continuar.'; }
}
function admin_hidden($area,$id=0,$legacy='',$action='save') { ?>
<input type="hidden" name="csrf" value="<?=rss_escape($_SESSION['csrf'])?>"><input type="hidden" name="area" value="<?=rss_escape($area)?>"><input type="hidden" name="action" value="<?=rss_escape($action)?>"><input type="hidden" name="id" value="<?=(int)$id?>"><input type="hidden" name="legacy" value="<?=rss_escape($legacy)?>">
<?php }
function admin_input($name,$label,$value='',$type='text',$required=false) { ?><label><?=rss_escape($label)?><input name="<?=rss_escape($name)?>" type="<?=rss_escape($type)?>" value="<?=rss_escape($value)?>" <?=$required?'required':''?> <?=$name==='category'?'list="category-options"':''?>></label><?php }
function admin_image($field,$label,$value='') { admin_input($field,$label.' — endereço da imagem',$value); ?><label>Ou enviar imagem<input type="file" name="<?=rss_escape($field)?>_file" accept="image/jpeg,image/png,image/gif,image/webp"></label><?php }
function admin_delete($area,$id,$legacy='') { ?><form method="post" onsubmit="return confirm('Remover este registro? As alterações não poderão ser desfeitas pelo painel.')"><?php admin_hidden($area,$id,$legacy,'delete'); ?><button class="danger">Excluir</button></form><?php }
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Administrar Revista PG Turismo</title><link rel="stylesheet" href="rss-admin.css"><link rel="stylesheet" href="cms/site.css"></head><body><main class="rss-panel">
<header class="panel-heading"><div><p class="eyebrow">Revista PG Turismo</p><h1>Administrar site</h1></div><a href="index.php">Ver site</a></header>
<?php if($message): ?><p class="notice" role="status"><?=rss_escape($message)?></p><?php endif; ?>
<?php if(empty($_SESSION['rss_admin'])): ?>
<form method="post" class="panel-card login-form"><?php admin_hidden($area); ?><label>Senha do painel<input type="password" name="password" required autocomplete="current-password"></label><button>Entrar</button></form>
<?php else:
$db=cms_db(); $editId=(int)($_GET['edit'] ?? 0); $editLegacy=is_string($_GET['legacy'] ?? '')?($_GET['legacy'] ?? ''):''; $record=[];
if(!$success) {
    if($area==='sections' || $area==='banners') { $table=$area==='sections'?'cms_sections':'cms_banners'; $stmt=$db->prepare('SELECT * FROM '.$table.' WHERE id=?'); $stmt->execute([$editId]); $record=$stmt->fetch(PDO::FETCH_ASSOC) ?: []; }
    elseif($area==='news') {
        foreach(cms_news_entries() as $entry) if(($editLegacy && $entry['kind']==='sql' && (string)$entry['id']===$editLegacy) || (!$editLegacy && $entry['kind']==='cms' && (int)$entry['id']===$editId)) $record=$entry;
    } else foreach(cms_companies()['rows'] as $store) if(($editLegacy && ($store['_legacy'] ?? '')===$editLegacy) || (!$editLegacy && (int)($store['_id'] ?? 0)===$editId && $editId)) $record=$store;
    if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='save') $record=array_merge($record,$_POST);
}
$sections=$db->query('SELECT * FROM cms_sections ORDER BY position,id')->fetchAll(PDO::FETCH_ASSOC);
?>
<nav class="admin-nav"><?php foreach($areas as $key=>$label): ?><a class="<?=$area===$key?'active':''?>" href="admin.php?area=<?=$key?>"><?=rss_escape($label)?></a><?php endforeach; ?><a href="rss-admin.php">Fontes RSS</a><a href="instalar.php">Configurações</a></nav>
<h2><?=rss_escape($areas[$area])?></h2><p><a href="admin.php?area=<?=$area?>">+ Novo cadastro</a></p>
<form method="post" enctype="multipart/form-data" class="panel-card cms-form">
<?php
$id=$success?0:($area==='companies'?($record['_id'] ?? $record['id'] ?? 0):($record['id'] ?? 0));
$legacy='';
if(!$success) {
    if($area==='companies') $legacy=$record['_legacy'] ?? $record['legacy'] ?? '';
    else $legacy=($record['kind'] ?? '')==='sql' ? ($record['id'] ?? '') : ($record['legacy'] ?? '');
}
admin_hidden($area,$id,$legacy);
?>
<h3><?=$record?'Editar cadastro':'Novo cadastro'?></h3>
<?php if($area==='sections'):
admin_input('title','Título da seção',$record['title'] ?? '','text',true); admin_input('category','Categoria das notícias',$record['category'] ?? '','text',true); admin_input('color','Cor do título',$record['color'] ?? '#ad1431','color',true); admin_input('position','Ordem de exibição',$record['position'] ?? count($sections)+1,'number',true); ?>
<label><input type="checkbox" name="active" value="1" <?=($record['active'] ?? 1)?'checked':''?>> Exibir seção na página inicial</label><p class="field-help">Cada seção exibe até quatro notícias da categoria. Excluir uma seção também remove seus banners, mas preserva as notícias.</p>
<?php elseif($area==='banners'):
admin_input('title','Descrição do banner',$record['title'] ?? '','text',true); ?>
<label>Exibir após a seção<select name="section_id" required><?php foreach($sections as $section): ?><option value="<?=(int)$section['id']?>" <?=(int)($record['section_id'] ?? 0)===(int)$section['id']?'selected':''?>><?=rss_escape($section['title'])?></option><?php endforeach; ?></select></label>
<?php admin_image('image','Banner',$record['image'] ?? ''); admin_input('link','Endereço ao clicar (opcional)',$record['link'] ?? ''); ?>
<label><input type="checkbox" name="active" value="1" <?=!empty($record['active'])?'checked':''?>> Banner ativo</label>
<?php elseif($area==='news'):
admin_input('title','Título',$record['title'] ?? '','text',true); admin_input('category','Categoria',$record['category'] ?? 'ponta-grossa','text',true); admin_image('image','Foto principal',$record['image'] ?? ''); ?>
<label>Texto da notícia<textarea name="body" rows="12" required><?=rss_escape($record['body'] ?? '')?></textarea></label><p class="field-help">Digite o texto com parágrafos. Não é necessário inserir código HTML. Editar não altera a ordem original de cadastro.</p>
<?php else:
admin_input('nome','Nome da empresa',$record['nome'] ?? '','text',true); admin_input('url','Endereço curto (ex.: restaurante-central)',$record['url'] ?? '','text',true); ?>
<label>Categoria<select name="categoria"><?php foreach(['gastronomia','compras','hospedagem'] as $category): ?><option <?=($record['categoria'] ?? '')===$category?'selected':''?>><?=$category?></option><?php endforeach; ?></select></label>
<?php admin_input('local','Endereço',$record['local'] ?? ''); admin_input('telefone','Telefone',$record['telefone'] ?? ''); admin_input('whatsapp','WhatsApp com DDD (opcional)',$record['whatsapp'] ?? ''); ?>
<label>Descrição<textarea name="descritivo" rows="8"><?=rss_escape($record['descritivo'] ?? '')?></textarea></label>
<?php admin_image('perfil','Logotipo',$record['perfil'] ?? ''); admin_image('capa','Foto de capa',$record['capa'] ?? ''); admin_input('mapa','URL do mapa (opcional)',$record['mapa'] ?? ''); ?>
<details><summary>Galeria de fotos (opcional)</summary><?php for($i=1;$i<=8;$i++) admin_image('foto'.$i,'Foto '.$i,$record['foto'.$i] ?? ''); ?></details>
<?php endif; ?>
<button>Salvar <?=$record?'alterações':'cadastro'?></button>
<datalist id="category-options"><?php foreach(cms_categories() as $option): ?><option value="<?=rss_escape($option)?>"><?=rss_escape(ucwords(str_replace('-',' ',$option)))?></option><?php endforeach; ?></datalist>
</form>
<section class="panel-card"><h3>Cadastros existentes</h3>
<?php if($area==='sections'): foreach($sections as $row): ?><div class="admin-item"><div><strong><?=rss_escape($row['title'])?></strong><p><?=rss_escape($row['category'])?> · ordem <?=(int)$row['position']?> · <?=$row['active']?'ativa':'oculta'?> · <?=rss_escape($row['color'])?></p></div><div class="source-actions"><a href="admin.php?area=sections&edit=<?=(int)$row['id']?>">Editar</a><?php admin_delete($area,$row['id']); ?></div></div><?php endforeach; ?>
<?php elseif($area==='banners'): foreach($db->query('SELECT b.*,s.title AS section_title FROM cms_banners b JOIN cms_sections s ON s.id=b.section_id ORDER BY b.id DESC') as $row): ?><div class="admin-item"><div><strong><?=rss_escape($row['title'])?></strong><p>Após <?=rss_escape($row['section_title'])?> · <?=$row['active']?'ativo':'desativado'?></p><img class="cms-preview" src="<?=rss_escape($row['image'])?>" alt="<?=rss_escape($row['title'])?>"></div><div class="source-actions"><a href="admin.php?area=banners&edit=<?=(int)$row['id']?>">Editar</a><?php admin_delete($area,$row['id']); ?></div></div><?php endforeach; ?>
<?php elseif($area==='news'): foreach(cms_news_entries() as $row): if($row['kind']==='rss') continue; $legacy=$row['kind']==='sql'?$row['id']:''; ?><div class="admin-item"><div><strong><?=rss_escape($row['title'])?></strong><p><?=rss_escape($row['category'])?></p></div><div class="source-actions"><a href="<?=rss_escape($row['href'])?>">Ver</a><a href="admin.php?area=news&<?=$legacy?'legacy='.(int)$legacy:'edit='.(int)$row['id']?>">Editar</a><?php admin_delete($area,$legacy?0:$row['id'],$legacy); ?></div></div><?php endforeach; ?>
<?php else: $allCompanies=cms_companies(); if($allCompanies['state']!=='ok'): ?><p>O banco anterior do guia está indisponível ou não configurado. Você pode cadastrar empresas neste painel; os registros anteriores reaparecem quando a conexão for restabelecida.</p><?php endif; foreach($allCompanies['rows'] as $row): $legacy=$row['_legacy'] ?? ''; ?><div class="admin-item"><div><strong><?=rss_escape($row['nome'])?></strong><p><?=rss_escape($row['categoria'])?> · <?=cms_company_views($row['_key'])?> acessos ao perfil</p></div><div class="source-actions"><a href="indicador.php?id=<?=rawurlencode($row['url'])?>">Ver</a><a href="admin.php?area=companies&<?=$legacy?'legacy='.rawurlencode($legacy):'edit='.(int)$row['_id']?>">Editar</a><?php admin_delete($area,$row['_id'] ?? 0,$legacy); ?></div></div><?php endforeach; endif; ?>
</section><p class="field-help">Os novos cadastros e ajustes ficam no armazenamento privado do site. Os registros originais do MySQL são preservados; exclusões no painel os retiram da exibição.</p>
<form method="post"><?php admin_hidden($area,0,'','logout'); ?><button class="secondary">Sair</button></form>
<?php endif; ?></main></body></html>
