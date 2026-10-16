<?php
$dir=sys_get_temp_dir().'/revista-detail-migration-'.bin2hex(random_bytes(6)); mkdir($dir,0700); putenv('RSS_DATA_DIR='.$dir); putenv('REVISTA_CONFIG_DIR='.$dir);
$legacy=new PDO('sqlite:'.$dir.'/rss.sqlite');
$legacy->exec("CREATE TABLE cms_sections(id INTEGER PRIMARY KEY AUTOINCREMENT,title TEXT,category TEXT,color TEXT,position INTEGER,active INTEGER DEFAULT 1);
INSERT INTO cms_sections VALUES(1,'Cidade','ponta-grossa','#ffaa00',2,1);
CREATE TABLE cms_banners(id INTEGER PRIMARY KEY AUTOINCREMENT,section_id INTEGER NOT NULL REFERENCES cms_sections(id) ON DELETE CASCADE,title TEXT NOT NULL,image TEXT NOT NULL,link TEXT NOT NULL DEFAULT '',active INTEGER NOT NULL DEFAULT 0);
INSERT INTO cms_banners VALUES(1,1,'Antigo','https://example.com/banner.png','https://example.com',1);
CREATE TABLE cms_meta(name TEXT PRIMARY KEY,value TEXT); INSERT INTO cms_meta VALUES('sections_seeded','1')");
require dirname(__DIR__).'/cms/core.php'; $db=cms_db(); $count=0;
function detail_check($ok,$message) { global $count; if(!$ok) throw new RuntimeException($message); $count++; }
$old=$db->query('SELECT * FROM cms_banners WHERE id=1')->fetch(PDO::FETCH_ASSOC);
detail_check($old['title']==='Antigo' && (int)$old['show_home']===1 && (int)$old['show_rss']===0,'Migrar banner antigo sem ativar posições novas');
detail_check($db->query('SELECT color FROM cms_sections')->fetchColumn()==='#ffaa00','Preservar cor e configuração anteriores');
cms_save_banner(0,['title'=>'Matérias','image'=>'https://example.com/article.png','banner_targets'=>1,'show_rss'=>1,'show_manual'=>1,'active'=>1]);
$banner=$db->query('SELECT * FROM cms_banners ORDER BY id DESC')->fetch(PDO::FETCH_ASSOC);
detail_check($banner['section_id']===null && !(int)$banner['show_home'] && (int)$banner['show_manual'],'Banner de matéria independe de seção da home');
ob_start(); cms_render_banners('rss'); $html=ob_get_clean(); detail_check(str_contains($html,'Matérias') && !str_contains($html,'Antigo'),'Separar posições dos banners');
cms_save_section(0,['title'=>'Nova','category'=>'viagem','color'=>'#123456','position'=>2,'active'=>1]);
cms_reorder_sections([2,1]); detail_check($db->query('SELECT id FROM cms_sections ORDER BY position')->fetchAll(PDO::FETCH_COLUMN)===[2,1],'Salvar sequência por IDs');
$bad=false; try { cms_reorder_sections([2,2]); } catch(InvalidArgumentException $e) {$bad=true;} detail_check($bad && $db->query('SELECT id FROM cms_sections ORDER BY position')->fetchAll(PDO::FETCH_COLUMN)===[2,1],'Rejeitar ordem inválida sem alteração parcial');
cms_save_news(0,0,['title'=>'Créditos','category'=>'viagem','image'=>'https://example.com/photo.png','body'=>'Texto','credit'=>'Fotógrafa Teste']);
detail_check(cms_news_entries()[0]['credit']==='Fotógrafa Teste','Persistir crédito da foto');
cms_save_news(0,0,['title'=>'Relacionada','category'=>'viagem','image'=>'https://example.com/photo.png','body'=>'Texto']);
cms_save_news(0,0,['title'=>'Outra categoria','category'=>'lazer','image'=>'https://example.com/photo.png','body'=>'Texto']);
$related=cms_related('viagem','cms:1'); detail_check(count($related)===1 && $related[0]['title']==='Relacionada','Relacionados excluem a atual e outras categorias');
cms_save_banner(0,['title'=>'Compartilhado','image'=>'https://example.com/shared.png','banner_targets'=>1,'show_home'=>1,'section_id'=>1,'show_rss'=>1,'active'=>1]);
$db->exec('DELETE FROM cms_sections'); detail_check((int)$db->query('SELECT count(*) FROM cms_banners')->fetchColumn()===2,'Excluir seção preserva banners exclusivos e compartilhados de matéria');
$db=null; $legacy=null; foreach(glob($dir.'/*') as $file) unlink($file); rmdir($dir); echo "$count verificações de migração e apresentação passaram.\n";
