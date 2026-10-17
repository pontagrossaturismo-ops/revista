<?php
require dirname(__DIR__).'/rss/storage.php';
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('PRAGMA foreign_keys=ON; CREATE TABLE sources(id INTEGER PRIMARY KEY,url TEXT UNIQUE NOT NULL,name TEXT NOT NULL,active INTEGER NOT NULL DEFAULT 1); CREATE TABLE articles(id INTEGER PRIMARY KEY,source_id INTEGER NOT NULL REFERENCES sources(id) ON DELETE CASCADE);');
$count=0;
function compat_check($ok,$label) { global $count; if(!$ok) throw new RuntimeException($label); $count++; }
function save_source($db,$name) { rss_sqlite_upsert($db,'INSERT INTO sources(url,name) VALUES(?,?)',['https://example.com/feed',$name],'UPDATE sources SET name=? WHERE url=?',[$name,'https://example.com/feed']); }
save_source($db,'Original'); $id=(int)$db->query('SELECT id FROM sources')->fetchColumn();
$db->exec('INSERT INTO articles(source_id) VALUES('.$id.'); UPDATE sources SET active=0');
save_source($db,'Editada');
compat_check((int)$db->query('SELECT id FROM sources')->fetchColumn()===$id,'Preservar identificador da fonte ao atualizar');
compat_check((int)$db->query('SELECT COUNT(*) FROM articles')->fetchColumn()===1,'Preservar matérias ligadas à fonte');
compat_check((int)$db->query('SELECT active FROM sources')->fetchColumn()===0,'Preservar estado e campos não atualizados');
save_source($db,'Editada');
compat_check((int)$db->query('SELECT COUNT(*) FROM sources')->fetchColumn()===1,'Gravação idêntica não duplica fonte');
$db->beginTransaction(); save_source($db,'Temporária');
compat_check($db->inTransaction(),'Não confirmar transação iniciada pelo chamador');
$db->rollBack(); compat_check($db->query('SELECT name FROM sources')->fetchColumn()==='Editada','Rollback externo desfaz atualização');
$db->exec('CREATE TABLE totals(day TEXT,kind TEXT,total INTEGER NOT NULL,PRIMARY KEY(day,kind))');
for($i=0;$i<3;$i++) rss_sqlite_upsert($db,'INSERT INTO totals(day,kind,total) VALUES(?,?,1)',['2026-10-17','site'],'UPDATE totals SET total=total+1 WHERE day=? AND kind=?',['2026-10-17','site']);
compat_check((int)$db->query('SELECT total FROM totals')->fetchColumn()===3,'Incrementar contador com chave composta');
$failed=false;
try { rss_sqlite_upsert($db,'INSERT INTO sources(url,name) VALUES(?,?)',['https://example.com/invalid',null],'UPDATE sources SET name=? WHERE url=?',['Inexistente','https://example.com/invalid']); }
catch(PDOException $e) { $failed=true; }
compat_check($failed && !$db->inTransaction(),'Falha de integridade desfaz transação própria sem ocultar erro');
compat_check((int)$db->query('SELECT COUNT(*) FROM sources')->fetchColumn()===1,'Falha não deixa gravação parcial');
echo "$count verificações de gravação SQLite compatível passaram.\n";
