<?php
require_once dirname(__DIR__).'/rss/core.php';
function cms_db() {
    $db=rss_db(); static $ready=false; if($ready) return $db;
    if(cms_schema_ready($db)) { $ready=true; return $db; }
    rss_schema_lock(function() use ($db) {
        if(cms_schema_ready($db)) return;
        $db->exec("CREATE TABLE IF NOT EXISTS content_order(seq INTEGER PRIMARY KEY AUTOINCREMENT,kind TEXT NOT NULL,item_key TEXT NOT NULL,registered_at TEXT NOT NULL,UNIQUE(kind,item_key));
        CREATE TABLE IF NOT EXISTS cms_sections(id INTEGER PRIMARY KEY AUTOINCREMENT,title TEXT NOT NULL,category TEXT NOT NULL,color TEXT NOT NULL,position INTEGER NOT NULL,active INTEGER NOT NULL DEFAULT 1);
        CREATE TABLE IF NOT EXISTS cms_banners(id INTEGER PRIMARY KEY AUTOINCREMENT,section_id INTEGER REFERENCES cms_sections(id) ON DELETE SET NULL,title TEXT NOT NULL,image TEXT NOT NULL,link TEXT NOT NULL DEFAULT '',active INTEGER NOT NULL DEFAULT 0,show_home INTEGER NOT NULL DEFAULT 1,show_rss INTEGER NOT NULL DEFAULT 0,show_manual INTEGER NOT NULL DEFAULT 0);
        CREATE TABLE IF NOT EXISTS cms_news(id INTEGER PRIMARY KEY AUTOINCREMENT,legacy_id INTEGER UNIQUE,title TEXT NOT NULL,category TEXT NOT NULL,image TEXT NOT NULL,body TEXT NOT NULL,created_at TEXT NOT NULL,deleted INTEGER NOT NULL DEFAULT 0);
        CREATE TABLE IF NOT EXISTS cms_companies(id INTEGER PRIMARY KEY AUTOINCREMENT,legacy_slug TEXT UNIQUE,slug TEXT NOT NULL UNIQUE,data TEXT NOT NULL,deleted INTEGER NOT NULL DEFAULT 0);
        CREATE TABLE IF NOT EXISTS company_views(company_key TEXT PRIMARY KEY,total INTEGER NOT NULL DEFAULT 0);
        CREATE TABLE IF NOT EXISTS cms_media(hash TEXT PRIMARY KEY,mime TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS cms_meta(name TEXT PRIMARY KEY,value TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS daily_metrics(day TEXT NOT NULL,kind TEXT NOT NULL,item TEXT NOT NULL,total INTEGER NOT NULL DEFAULT 0,PRIMARY KEY(day,kind,item))");
        $bannerColumns=$db->query('PRAGMA table_info(cms_banners)')->fetchAll(PDO::FETCH_ASSOC);
        foreach($bannerColumns as $column) if($column['name']==='section_id' && $column['notnull']) {
            $db->beginTransaction();
            try {
                $db->exec("CREATE TABLE cms_banners_new(id INTEGER PRIMARY KEY AUTOINCREMENT,section_id INTEGER REFERENCES cms_sections(id) ON DELETE SET NULL,title TEXT NOT NULL,image TEXT NOT NULL,link TEXT NOT NULL DEFAULT '',active INTEGER NOT NULL DEFAULT 0,show_home INTEGER NOT NULL DEFAULT 1,show_rss INTEGER NOT NULL DEFAULT 0,show_manual INTEGER NOT NULL DEFAULT 0);
                INSERT INTO cms_banners_new(id,section_id,title,image,link,active) SELECT id,section_id,title,image,link,active FROM cms_banners;
                DROP TABLE cms_banners; ALTER TABLE cms_banners_new RENAME TO cms_banners"); $db->commit();
            } catch(Throwable $e) { $db->rollBack(); throw $e; }
            break;
        }
        $db->exec("CREATE TRIGGER IF NOT EXISTS section_banner_cleanup AFTER DELETE ON cms_sections BEGIN
            UPDATE cms_banners SET show_home=0 WHERE section_id IS NULL;
            DELETE FROM cms_banners WHERE section_id IS NULL AND show_home=0 AND show_rss=0 AND show_manual=0;
        END");
        $newsColumns=array_column($db->query('PRAGMA table_info(cms_news)')->fetchAll(PDO::FETCH_ASSOC),'name');
        if(!in_array('credit',$newsColumns,true)) $db->exec("ALTER TABLE cms_news ADD COLUMN credit TEXT NOT NULL DEFAULT ''");
        if(!$db->query("SELECT 1 FROM cms_meta WHERE name='sections_seeded'")->fetchColumn()) {
            $db->beginTransaction();
            try {
                if(!$db->query("SELECT 1 FROM cms_meta WHERE name='sections_seeded'")->fetchColumn()) {
                    $stmt=$db->prepare('INSERT INTO cms_sections(title,category,color,position) VALUES(?,?,?,?)');
                    foreach(['Ponta Grossa'=>'ponta-grossa','Viagem'=>'viagem','Lazer'=>'lazer','Gastronomia'=>'gastronomia','Esporte'=>'esporte'] as $title=>$category) $stmt->execute([$title,$category,'#ad1431',($position=($position ?? 0)+1)]);
                    $db->exec("INSERT INTO cms_meta VALUES('sections_seeded','1')");
                }
                $db->commit();
            } catch(Throwable $e) { $db->rollBack(); throw $e; }
        }
        rss_sqlite_upsert($db,'INSERT INTO cms_meta(name,value) VALUES(?,?)',['schema_version','2'],'UPDATE cms_meta SET value=? WHERE name=?',['2','schema_version']);
    });
    $ready=true; return $db;
}
function cms_schema_ready($db) {
    if(!$db->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='cms_meta'")->fetchColumn()) return false;
    return $db->query("SELECT value FROM cms_meta WHERE name='schema_version'")->fetchColumn()==='2';
}
function cms_category($text) {
    $text=trim($text); $text=strtr(strtolower($text),['á'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c','Á'=>'a','Ã'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','Ç'=>'c']);
    $slug=trim(preg_replace('/[^a-z0-9]+/','-',$text),'-'); return $slug==='pontagrossa'?'ponta-grossa':$slug;
}
function cms_categories() {
    $defaults=['ponta-grossa','viagem','lazer','gastronomia','eventos','esporte','noticias'];
    return array_values(array_unique(array_merge($defaults,cms_db()->query('SELECT category FROM cms_sections UNION SELECT category FROM cms_news WHERE deleted=0 UNION SELECT category FROM sources')->fetchAll(PDO::FETCH_COLUMN))));
}
function cms_asset($value) {
    $value=trim((string)$value);
    if($value==='' || $value==='0') return '';
    if(str_starts_with($value,'//') || preg_match('/[\x00-\x20\x7f]/',$value) || (preg_match('~^[a-z][a-z0-9+.-]*:~i',$value) && !preg_match('~^https?://~i',$value))) throw new InvalidArgumentException('Use endereço HTTP/HTTPS ou caminho de arquivo do site.');
    return $value;
}
function cms_upload($file) {
    if(!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return '';
    if(($file['error'] ?? 1)!==UPLOAD_ERR_OK || ($file['size'] ?? 0)>5*1024*1024 || !is_uploaded_file($file['tmp_name'])) throw new InvalidArgumentException('Envie uma imagem de até 5 MB.');
    $bytes=file_get_contents($file['tmp_name']); $info=rss_image_info($bytes); $hash=hash('sha256',$bytes);
    $dir=rss_data_dir().'/cms-media'; if(!is_dir($dir) && !mkdir($dir,0700,true)) throw new RuntimeException('Falha ao criar pasta de imagens.');
    if(!is_file($dir.'/'.$hash)) {
        $temp=tempnam($dir,'.upload-');
        try { if(!move_uploaded_file($file['tmp_name'],$temp) || !rename($temp,$dir.'/'.$hash)) throw new RuntimeException('Não foi possível salvar imagem.'); chmod($dir.'/'.$hash,0600); }
        finally { if(is_file($temp)) unlink($temp); }
    }
    cms_db()->prepare('INSERT OR IGNORE INTO cms_media(hash,mime) VALUES(?,?)')->execute([$hash,$info['mime']]);
    return 'media.php?arquivo='.$hash;
}
function cms_register($kind,$key,$date=null) {
    $db=cms_db(); $stmt=$db->prepare('SELECT seq,registered_at FROM content_order WHERE kind=? AND item_key=?');
    $stmt->execute([$kind,(string)$key]); $existing=$stmt->fetch(PDO::FETCH_ASSOC); $stmt->closeCursor();
    if($existing) return $existing;
    $db->prepare('INSERT OR IGNORE INTO content_order(kind,item_key,registered_at) VALUES(?,?,?)')->execute([$kind,(string)$key,$date ?: gmdate('Y-m-d H:i:s')]);
    $stmt->execute([$kind,(string)$key]); return $stmt->fetch(PDO::FETCH_ASSOC);
}

function cms_legacy_news($id=null) {
    static $cache=[]; $key=$id===null?'all':(string)$id;
    if(isset($cache[$key])) return $cache[$key];
    if(!revista_setting('DB_HOST')) return [];
    try {
        $db=revista_connect(); $sql='SELECT * FROM materia'.($id!==null?' WHERE id=?':'').' ORDER BY id DESC'; $stmt=$db->prepare($sql);
        if($id!==null) $stmt->bind_param('i',$id);
        $stmt->execute(); $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); $db->close(); return $cache[$key]=$rows;
    } catch(Throwable $e) { error_log('CMS matérias SQL: '.$e->getMessage()); return []; }
}
function cms_legacy_photo($path) {
    $path=trim((string)$path); if(!$path || $path==='0') return '';
    try { $path=cms_asset($path); } catch(InvalidArgumentException $e) { return ''; }
    return preg_match('~^https?://|^/|^\.\./~i',$path)?$path:'../'.$path;
}
function cms_legacy_metadata($row) {
    $gallery=[]; for($i=2;$i<=8;$i++) if($photo=cms_legacy_photo($row['foto'.$i] ?? '')) $gallery[]=$photo;
    return ['gallery'=>$gallery,'caption'=>$row['legenda1'] ?? '','credit'=>$row['credito1'] ?? ''];
}
function cms_news_entries($category=null,$legacyRows=null) {
    $db=cms_db(); $entries=[]; $overrides=[];
    foreach($db->query('SELECT * FROM cms_news ORDER BY id') as $row) {
        if($row['legacy_id']!==null) $overrides[(string)$row['legacy_id']]=$row;
        else if(!$row['deleted']) $entries[]=cms_news_entry($row);
    }
    $legacySeeded=(bool)$db->query("SELECT 1 FROM cms_meta WHERE name='legacy_news_seeded'")->fetchColumn();
    $legacyNews=$legacyRows ?? cms_legacy_news(); usort($legacyNews,fn($a,$b)=>(int)$a['id']<=>(int)$b['id']);
    foreach($legacyNews as $row) {
        $override=$overrides[(string)$row['id']] ?? null;
        if($override) { if(!$override['deleted']) { $entry=cms_news_entry($override); $metadata=cms_legacy_metadata($row); if(empty($entry['credit'])) $entry['credit']=$metadata['credit']; $entries[]=$entry+$metadata; } continue; }
        $date=null;
        foreach(['data_cadastro','created_at','data','data_publicacao'] as $field) if(!empty($row[$field]) && ($stamp=strtotime($row[$field]))) { $date=gmdate('Y-m-d H:i:s',$stamp); break; }
        $order=cms_register('sql',$row['id'],$date ?: ($legacySeeded?gmdate('Y-m-d H:i:s'):'1970-01-01 00:00:00'));
        $entries[]=['kind'=>'sql','id'=>$row['id'],'key'=>'sql:'.$row['id'],'title'=>$row['titulo'],'category'=>cms_category($row['categoria'] ?? 'noticias'),'image'=>cms_legacy_photo($row['foto1'] ?? ''),'body'=>rss_body_text($row['conteudo'] ?? ''),'href'=>'materia.php?id='.(int)$row['id'],'order'=>$order]+cms_legacy_metadata($row);
    }
    if($legacyNews && !$legacySeeded) $db->exec("INSERT OR IGNORE INTO cms_meta(name,value) VALUES('legacy_news_seeded','1')");
    foreach(rss_public_articles(null,null) as $row) {
        $order=cms_register('rss',$row['id'],$row['published_at']);
        $entries[]=['kind'=>'rss','id'=>$row['id'],'key'=>'rss:'.$row['id'],'title'=>$row['title'],'category'=>cms_category($row['category']),'image'=>rss_image_src($row),'body'=>$row['body'],'href'=>'rss-materia.php?id='.(int)$row['id'],'order'=>$order,'sort_at'=>rss_source_type(['type'=>$row['source_type'] ?? '', 'url'=>$row['source_url'] ?? ''])==='ofec'?$row['published_at']:$order['registered_at']];
    }
    if($category!==null) $entries=array_filter($entries,fn($entry)=>$entry['category']===cms_category($category));
    usort($entries,fn($a,$b)=>strcmp($b['sort_at'] ?? $b['order']['registered_at'],$a['sort_at'] ?? $a['order']['registered_at']) ?: $b['order']['seq']<=>$a['order']['seq']);
    return array_values($entries);
}
function cms_news_entry($row) {
    $kind=$row['legacy_id']!==null?'sql':'cms'; $id=$row['legacy_id'] ?? $row['id'];
    return ['kind'=>$kind,'id'=>$id,'key'=>$kind.':'.$id,'title'=>$row['title'],'category'=>$row['category'],'image'=>$row['image'],'body'=>$row['body'],'credit'=>$row['credit'] ?? '','href'=>$kind==='sql'?'materia.php?id='.(int)$id:'noticia.php?id='.(int)$id,'order'=>cms_register($kind,$id,$row['created_at'])];
}
function cms_save_news($id,$legacy,$values) {
    $db=cms_db(); $title=trim($values['title'] ?? ''); $category=cms_category($values['category'] ?? ''); $body=trim($values['body'] ?? ''); $image=cms_asset($values['image'] ?? ''); $credit=trim((string)($values['credit'] ?? ''));
    if(!$title || strlen($title)>300 || !$category || !$body || !$image) throw new InvalidArgumentException('Preencha título, categoria, imagem e texto.');
    if($legacy) {
        $exists=$db->prepare('SELECT * FROM cms_news WHERE legacy_id=?'); $exists->execute([$legacy]); $old=$exists->fetch(PDO::FETCH_ASSOC);
        if(!$old && !cms_legacy_news($legacy)) throw new InvalidArgumentException('Matéria não encontrada.');
        $order=cms_register('sql',$legacy,'1970-01-01 00:00:00');
        rss_sqlite_upsert($db,'INSERT INTO cms_news(legacy_id,title,category,image,body,created_at,credit) VALUES(?,?,?,?,?,?,?)',[$legacy,$title,$category,$image,$body,$order['registered_at'],$credit],'UPDATE cms_news SET title=?,category=?,image=?,body=?,credit=?,deleted=0 WHERE legacy_id=?',[$title,$category,$image,$body,$credit,$legacy]);
        return;
    }
    if($id) {
        $stmt=$db->prepare('UPDATE cms_news SET title=?,category=?,image=?,body=?,credit=? WHERE id=? AND legacy_id IS NULL AND deleted=0'); $stmt->execute([$title,$category,$image,$body,$credit,$id]);
        if(!$stmt->rowCount()) throw new InvalidArgumentException('Notícia não encontrada.');
    } else {
        $db->prepare('INSERT INTO cms_news(title,category,image,body,created_at,credit) VALUES(?,?,?,?,?,?)')->execute([$title,$category,$image,$body,gmdate('Y-m-d H:i:s'),$credit]); cms_register('cms',$db->lastInsertId());
    }
}
function cms_delete_news($id,$legacy=0) {
    $db=cms_db();
    if($legacy) {
        rss_sqlite_upsert($db,"INSERT INTO cms_news(legacy_id,title,category,image,body,created_at,deleted) VALUES(?,'','','','','1970-01-01',1)",[$legacy],'UPDATE cms_news SET deleted=1 WHERE legacy_id=?',[$legacy]);
    } else $db->prepare('DELETE FROM cms_news WHERE id=? AND legacy_id IS NULL')->execute([$id]);
}
function cms_legacy_companies() {
    static $result=null; if($result!==null) return $result;
    foreach(['HOST','USER','NAME'] as $field) if(!revista_setting('DB_CONV_'.$field)) return ['state'=>'unconfigured','rows'=>[]];
    try { $db=revista_connect('DB_CONV'); $rows=$db->query('SELECT * FROM lojas')->fetch_all(MYSQLI_ASSOC); $db->close(); return $result=['state'=>'ok','rows'=>$rows]; }
    catch(Throwable $e) { error_log('CMS guia SQL: '.$e->getMessage()); return $result=['state'=>'unavailable','rows'=>[]]; }
}
function cms_companies($original=null) {
    $original=$original ?? cms_legacy_companies(); $rows=[]; $overrides=[];
    foreach(cms_db()->query('SELECT * FROM cms_companies') as $row) {
        if($row['legacy_slug']!==null) $overrides[$row['legacy_slug']]=$row;
        elseif(!$row['deleted']) { $data=json_decode($row['data'],true); $data['url']=$row['slug']; $data['_key']='cms:'.$row['id']; $data['_id']=$row['id']; $rows[]=$data; }
    }
    foreach($original['rows'] as $store) {
        $store['categoria']=cms_category($store['categoria'] ?? ''); $slug=(string)$store['url']; $override=$overrides[$slug] ?? null;
        if($override) { if($override['deleted']) continue; $store=array_merge($store,json_decode($override['data'],true)); $store['url']=$override['slug']; }
        $store['_key']='sql:'.$slug; $store['_legacy']=$slug; $rows[]=$store;
    }
    // Uma cópia editada permanece disponível mesmo se o banco remoto estiver fora do ar.
    foreach($overrides as $slug=>$row) if(!$row['deleted'] && !array_filter($rows,fn($store)=>($store['_legacy'] ?? null)===$slug)) { $store=json_decode($row['data'],true); $store['url']=$row['slug']; $store['_key']='sql:'.$slug; $store['_legacy']=$slug; $rows[]=$store; }
    return ['state'=>($rows || ($original['state']==='unconfigured' && cms_db()->query("SELECT 1 FROM cms_meta WHERE name='local_companies_enabled'")->fetchColumn()))?'ok':$original['state'],'rows'=>$rows];
}
function cms_whatsapp($value) {
    $digits=preg_replace('/\D/','',(string)$value); if(!$digits) { if(trim((string)$value)!=='') throw new InvalidArgumentException('Informe um número válido de WhatsApp.'); return ''; }
    if(strlen($digits)===10 || strlen($digits)===11) $digits='55'.$digits;
    if(strlen($digits)<12 || strlen($digits)>15 || $digits[0]==='0') throw new InvalidArgumentException('Informe o WhatsApp com DDD; para exterior, inclua o código do país.');
    return 'https://wa.me/'.$digits;
}
function cms_save_company($id,$legacy,$values) {
    $db=cms_db(); $data=[];
    foreach(['nome','categoria','local','bairro','horarios','faixa_preco','acessibilidade','familias','telefone','whatsapp','descritivo','perfil','capa','mapa','foto1','foto2','foto3','foto4','foto5','foto6','foto7','foto8'] as $field) $data[$field]=trim((string)($values[$field] ?? ''));
    $slug=cms_category($values['url'] ?? $data['nome']); $data['categoria']=cms_category($data['categoria']);
    if(!$data['nome'] || !$slug || strlen($slug)>100 || !in_array($data['categoria'],['gastronomia','compras','hospedagem'],true)) throw new InvalidArgumentException('Preencha nome, categoria e endereço curto com até 100 caracteres.');
    cms_whatsapp($data['whatsapp']);
    foreach(['perfil','capa','mapa','foto1','foto2','foto3','foto4','foto5','foto6','foto7','foto8'] as $field) $data[$field]=cms_asset($data[$field]);
    foreach(cms_companies()['rows'] as $store) if($store['url']===$slug && ($store['_key']!==($legacy?'sql:'.$legacy:'cms:'.$id))) throw new InvalidArgumentException('Esse endereço já pertence a outra empresa.');
    if($legacy) {
        $exists=false; foreach(cms_companies()['rows'] as $store) if(($store['_legacy'] ?? '')===$legacy) $exists=true;
        if(!$exists) throw new InvalidArgumentException('Empresa não encontrada.');
        rss_sqlite_upsert($db,'INSERT INTO cms_companies(legacy_slug,slug,data) VALUES(?,?,?)',[$legacy,$slug,json_encode($data,JSON_THROW_ON_ERROR)],'UPDATE cms_companies SET slug=?,data=?,deleted=0 WHERE legacy_slug=?',[$slug,json_encode($data,JSON_THROW_ON_ERROR),$legacy]);
    } elseif($id) {
        $stmt=$db->prepare('UPDATE cms_companies SET slug=?,data=? WHERE id=? AND legacy_slug IS NULL AND deleted=0'); $stmt->execute([$slug,json_encode($data,JSON_THROW_ON_ERROR),$id]); if(!$stmt->rowCount()) throw new InvalidArgumentException('Empresa não encontrada.');
    } else $db->prepare('INSERT INTO cms_companies(slug,data) VALUES(?,?)')->execute([$slug,json_encode($data,JSON_THROW_ON_ERROR)]);
    $db->exec("INSERT OR IGNORE INTO cms_meta(name,value) VALUES('local_companies_enabled','1')");
}
function cms_delete_company($id,$legacy='') {
    if($legacy) rss_sqlite_upsert(cms_db(),'INSERT INTO cms_companies(legacy_slug,slug,data,deleted) VALUES(?,?,?,1)',[$legacy,'deleted-'.hash('sha256',$legacy),'{}'],'UPDATE cms_companies SET deleted=1 WHERE legacy_slug=?',[$legacy]);
    else cms_db()->prepare('DELETE FROM cms_companies WHERE id=? AND legacy_slug IS NULL')->execute([$id]);
}
function cms_company_views($key,$increment=false) {
    $db=cms_db(); if($increment) rss_sqlite_upsert($db,'INSERT INTO company_views(company_key,total) VALUES(?,1)',[$key],'UPDATE company_views SET total=total+1 WHERE company_key=?',[$key]);
    $stmt=$db->prepare('SELECT total FROM company_views WHERE company_key=?'); $stmt->execute([$key]); return (int)$stmt->fetchColumn();
}
function cms_save_section($id,$values) {
    $title=trim($values['title'] ?? ''); $category=cms_category($values['category'] ?? ''); $color=$values['color'] ?? ''; $position=filter_var($values['position'] ?? '',FILTER_VALIDATE_INT); $active=empty($values['active'])?0:1;
    if(!$title || !$category || !preg_match('/^#[a-f0-9]{6}$/i',$color) || $position===false) throw new InvalidArgumentException('Confira título, categoria, cor e ordem.');
    if($id) cms_db()->prepare('UPDATE cms_sections SET title=?,category=?,color=?,position=?,active=? WHERE id=?')->execute([$title,$category,$color,$position,$active,$id]);
    else cms_db()->prepare('INSERT INTO cms_sections(title,category,color,position,active) VALUES(?,?,?,?,?)')->execute([$title,$category,$color,$position,$active]);
}
function cms_save_banner($id,$values) {
    $home=isset($values['banner_targets']) ? !empty($values['show_home']) : (isset($values['show_home']) ? !empty($values['show_home']) : true);
    $rss=!empty($values['show_rss']); $manual=!empty($values['show_manual']);
    $section=$home?(int)($values['section_id'] ?? 0):null;
    if($home) { $stmt=cms_db()->prepare('SELECT 1 FROM cms_sections WHERE id=?'); $stmt->execute([$section]); if(!$stmt->fetchColumn()) throw new InvalidArgumentException('Escolha a seção do banner na página inicial.'); }
    $title=trim($values['title'] ?? ''); $image=cms_asset($values['image'] ?? ''); $link=cms_asset($values['link'] ?? '');
    if(!$title || !$image || !($home || $rss || $manual)) throw new InvalidArgumentException('Preencha título, imagem e onde exibir o banner.');
    $args=[$section,$title,$image,$link,empty($values['active'])?0:1,(int)$home,(int)$rss,(int)$manual];
    if($id) { $args[]=$id; cms_db()->prepare('UPDATE cms_banners SET section_id=?,title=?,image=?,link=?,active=?,show_home=?,show_rss=?,show_manual=? WHERE id=?')->execute($args); }
    else cms_db()->prepare('INSERT INTO cms_banners(section_id,title,image,link,active,show_home,show_rss,show_manual) VALUES(?,?,?,?,?,?,?,?)')->execute($args);
}
function cms_render_banners($placement,$section=null) {
    $columns=['home'=>'show_home','rss'=>'show_rss','manual'=>'show_manual'];
    if(!isset($columns[$placement])) return;
    $sql='SELECT * FROM cms_banners WHERE active=1 AND '.$columns[$placement].'=1'; $args=[];
    if($placement==='home') { $sql.=' AND section_id=?'; $args[]=$section; }
    $stmt=cms_db()->prepare($sql.' ORDER BY id'); $stmt->execute($args);
    foreach($stmt as $banner) {
        echo '<aside class="home-banner ad-slot" aria-label="Publicidade"><p>Publicidade</p>';
        if($banner['link']) echo '<a href="'.rss_escape($banner['link']).'" target="_blank" rel="noopener sponsored">';
        echo '<img loading="lazy" src="'.rss_escape($banner['image']).'" alt="'.rss_escape($banner['title']).'">';
        if($banner['link']) echo '</a>'; echo '</aside>';
    }
}
function cms_reorder_sections($ids) {
    if(!is_array($ids)) throw new InvalidArgumentException('Ordem de seções inválida.');
    $ids=array_map(fn($id)=>filter_var($id,FILTER_VALIDATE_INT),$ids);
    $db=cms_db(); $db->beginTransaction();
    try {
        $current=$db->query('SELECT id FROM cms_sections ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        $sorted=$ids; sort($sorted,SORT_NUMERIC); $expected=array_map('intval',$current); sort($expected,SORT_NUMERIC);
        if(in_array(false,$ids,true) || count(array_unique($ids))!==count($ids) || $sorted!==$expected) throw new InvalidArgumentException('A lista mudou. Atualize o painel antes de ordenar novamente.');
        $stmt=$db->prepare('UPDATE cms_sections SET position=? WHERE id=?'); foreach($ids as $index=>$id) $stmt->execute([$index+1,$id]);
        $db->commit();
    } catch(Throwable $e) { $db->rollBack(); throw $e; }
}
function cms_related($category,$currentKey,$limit=4) {
    return array_slice(array_values(array_filter(cms_news_entries($category),fn($entry)=>$entry['key']!==$currentKey)),0,$limit);
}
function cms_render_related($category,$currentKey) {
    $entries=cms_related($category,$currentKey); if(!$entries) return;
    echo '<section class="related-articles article-related"><h2 class="section-title">Conteúdos relacionados</h2><div class="home-news-grid">';
    foreach($entries as $entry) cms_render_card($entry); echo '</div></section>';
}
function cms_category_label($category) { return ucwords(str_replace('-',' ',cms_category($category))); }
function cms_date($date,$format='d/m/Y') {
    try { return (new DateTimeImmutable($date,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format($format); }
    catch(Throwable $e) { return ''; }
}
function cms_render_card($entry) {
    echo '<article class="noticia-card compact-news">';
    if($entry['image']) echo '<a class="news-photo" href="'.rss_escape($entry['href']).'"><img loading="lazy" src="'.rss_escape($entry['image']).'" alt="'.rss_escape($entry['title']).'"></a>';
    echo '<div class="noticia-content"><h3><a href="'.rss_escape($entry['href']).'">'.rss_escape($entry['title']).'</a></h3></div></article>';
}

function cms_spotlight_items($entries) {
    $featured=null; $groups=[];
    foreach($entries as $entry) {
        if(empty($entry['image'])) continue;
        if($entry['category']==='viagem') { if($featured===null) $featured=$entry; continue; }
        $groups[$entry['category']][]=$entry;
    }
    $secondary=[];
    while($groups && count($secondary)<5) {
        foreach($groups as $category=>&$items) {
            $secondary[]=array_shift($items);
            if(!$items) unset($groups[$category]);
            if(count($secondary)===5) break;
        }
        unset($items);
    }
    return ['featured'=>$featured,'secondary'=>$secondary];
}
function cms_render_spotlight($entries) {
    $selection=cms_spotlight_items($entries); $featured=$selection['featured']; if(!$featured) return;
    $secondary=$selection['secondary']; $colors=[];
    foreach(cms_db()->query('SELECT category,color FROM cms_sections ORDER BY position,id') as $section) if(!isset($colors[$section['category']])) $colors[$section['category']]=$section['color'];
    $color=static function($category) use ($colors) { $value=$colors[$category] ?? '#ad1431'; return preg_match('/^#[a-f0-9]{6}$/i',$value)?$value:'#ad1431'; };
    ?><section class="home-spotlight <?=$secondary?'':'spotlight-solo'?>" aria-label="Destaques de Viagem e outras categorias">
    <article class="spotlight-main"><a href="<?=rss_escape($featured['href'])?>"><img src="<?=rss_escape($featured['image'])?>" alt="<?=rss_escape($featured['title'])?>" fetchpriority="high" decoding="async"><div class="spotlight-caption"><span class="spotlight-tag" style="--tag-color:<?=rss_escape($color('viagem'))?>">Viagem</span><h2><?=rss_escape($featured['title'])?></h2></div></a></article>
    <?php if($secondary): ?><div class="spotlight-side" style="--spotlight-count:<?=count($secondary)?>"><?php foreach($secondary as $entry): ?><article class="spotlight-small"><a href="<?=rss_escape($entry['href'])?>"><img src="<?=rss_escape($entry['image'])?>" alt="<?=rss_escape($entry['title'])?>" loading="lazy" decoding="async"><div><span class="spotlight-tag" style="--tag-color:<?=rss_escape($color($entry['category']))?>"><?=rss_escape(cms_category_label($entry['category']))?></span><h3><?=rss_escape($entry['title'])?></h3></div></a></article><?php endforeach; ?></div><?php endif; ?>
    </section><?php
}
