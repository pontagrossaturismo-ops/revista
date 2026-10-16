<?php
require_once dirname(__DIR__).'/config/runtime.php';
require_once __DIR__.'/ofec.php';
// PHP 8.1+, PDO SQLite, cURL, SimpleXML e mbstring não é necessário.
function rss_escape($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function rss_categories() { return function_exists('cms_categories') ? cms_categories() : ['ponta-grossa','viagem','lazer','gastronomia','eventos','esporte','noticias']; }
function rss_data_dir() {
    return revista_setting('RSS_DATA_DIR') ?: dirname(__DIR__, 2).'/revista-rss-data';
}
function rss_status() {
    $db=rss_db();
    $file=$db->query('PRAGMA database_list')->fetch(PDO::FETCH_ASSOC)['file'];
    return ['file'=>$file, 'sources'=>(int)$db->query('SELECT count(*) FROM sources')->fetchColumn(),
        'articles'=>(int)$db->query('SELECT count(*) FROM articles')->fetchColumn(),
        'visible'=>count(rss_public_articles(null,null))];
}
function rss_db() {
    static $db;
    if ($db) return $db;
    $dir = rss_data_dir();
    if (!is_dir($dir) && !mkdir($dir, 0700, true)) throw new RuntimeException('Não foi possível criar o diretório RSS.');
    $db = new PDO('sqlite:' . $dir . '/rss.sqlite', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA busy_timeout=10000; PRAGMA foreign_keys=ON');
    $db->exec("CREATE TABLE IF NOT EXISTS content_order(seq INTEGER PRIMARY KEY AUTOINCREMENT,kind TEXT NOT NULL,item_key TEXT NOT NULL,registered_at TEXT NOT NULL,UNIQUE(kind,item_key))");
    $db->exec('CREATE TABLE IF NOT EXISTS sources (id INTEGER PRIMARY KEY, name TEXT NOT NULL, url TEXT NOT NULL UNIQUE, category TEXT NOT NULL, active INTEGER NOT NULL DEFAULT 1, checked_at TEXT, error TEXT)');
    $db->exec('CREATE TABLE IF NOT EXISTS articles (id INTEGER PRIMARY KEY, source_id INTEGER NOT NULL REFERENCES sources(id) ON DELETE CASCADE, identity TEXT NOT NULL, title TEXT NOT NULL, url TEXT NOT NULL, summary TEXT NOT NULL, image TEXT NOT NULL, published_at TEXT NOT NULL, UNIQUE(source_id, identity))');
    $sourceColumns=array_column($db->query('PRAGMA table_info(sources)')->fetchAll(PDO::FETCH_ASSOC),'name');
    if(!in_array('type',$sourceColumns,true)) $db->exec("ALTER TABLE sources ADD COLUMN type TEXT NOT NULL DEFAULT 'rss'");
    $columns=array_column($db->query('PRAGMA table_info(articles)')->fetchAll(PDO::FETCH_ASSOC),'name');
    foreach (['body','image_file','image_error','credit'] as $column) if (!in_array($column,$columns,true)) $db->exec('ALTER TABLE articles ADD COLUMN '.$column." TEXT NOT NULL DEFAULT ''");
    $db->exec('CREATE TABLE IF NOT EXISTS import_runs (id INTEGER PRIMARY KEY,source_id INTEGER NOT NULL REFERENCES sources(id) ON DELETE CASCADE,source_url TEXT NOT NULL,finished_at TEXT NOT NULL,status TEXT NOT NULL,processed INTEGER NOT NULL DEFAULT 0,created INTEGER NOT NULL DEFAULT 0,existing INTEGER NOT NULL DEFAULT 0,image_errors INTEGER NOT NULL DEFAULT 0,error TEXT)');
    $runColumns=array_column($db->query('PRAGMA table_info(import_runs)')->fetchAll(PDO::FETCH_ASSOC),'name');
    if(!in_array('content_errors',$runColumns,true)) $db->exec('ALTER TABLE import_runs ADD COLUMN content_errors INTEGER NOT NULL DEFAULT 0');
    $db->exec("INSERT OR IGNORE INTO content_order(kind,item_key,registered_at) SELECT 'rss',CAST(id AS TEXT),published_at FROM articles ORDER BY id;
    CREATE TRIGGER IF NOT EXISTS articles_order_cleanup AFTER DELETE ON articles BEGIN DELETE FROM content_order WHERE kind='rss' AND item_key=CAST(OLD.id AS TEXT); END");
    return $db;
}
function rss_url($url) {
    $p = parse_url(trim($url));
    if (!$p || !in_array(strtolower($p['scheme'] ?? ''), ['http','https'], true) || empty($p['host']) || isset($p['user']) || isset($p['pass']) || (isset($p['port']) && !in_array($p['port'], [80,443], true))) throw new InvalidArgumentException('Use uma URL pública HTTP ou HTTPS, sem autenticação.');
    return trim($url);
}
function rss_public_ips($host) {
    $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : array_merge(array_column(@dns_get_record($host, DNS_A) ?: [], 'ip'), array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6'));
    if (!$ips) throw new RuntimeException('Domínio sem endereço público.');
    foreach ($ips as $ip) if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) || (str_contains($ip, ':') && !preg_match('/^[23][0-9a-f]{3}:/i', $ip)) || (!str_contains($ip, ':') && (int)explode('.', $ip)[0] >= 224)) throw new RuntimeException('Endereço privado ou reservado bloqueado.');
    return $ips;
}
function rss_resolve($base, $ref) {
    $ref = trim($ref);
    if (preg_match('~^https?://~i', $ref)) return rss_url($ref);
    if (str_starts_with($ref, '//')) return rss_url(parse_url($base, PHP_URL_SCHEME) . ':' . $ref);
    if ($ref === '' || preg_match('~^[a-z][a-z0-9+.-]*:~i', $ref)) return '';
    $p = parse_url($base);
    $path = str_starts_with($ref, '/') ? $ref : preg_replace('~/[^/]*$~', '/', $p['path'] ?? '/') . $ref;
    return rss_url($p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '') . $path);
}
function rss_fetch($url,$maxBytes=2097152) {
    for ($redirect=0; $redirect<5; $redirect++) {
        $url = rss_url($url); $p = parse_url($url); $ips = rss_public_ips($p['host']);
        $port = $p['port'] ?? ($p['scheme'] === 'https' ? 443 : 80);
        $body = ''; $location = ''; $overflow = false;
        $ch = curl_init($url);
        $ip = $ips[0]; if (str_contains($ip, ':')) $ip = '[' . $ip . ']';
        curl_setopt_array($ch, [CURLOPT_FOLLOWLOCATION=>false, CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_TIMEOUT=>15, CURLOPT_PROTOCOLS=>CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_PROXY=>'', CURLOPT_RESOLVE=>[$p['host'].':'.$port.':'.$ip], CURLOPT_USERAGENT=>'RevistaPG-RSS/1.0', CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_WRITEFUNCTION=>function($ch,$chunk) use (&$body,&$overflow,$maxBytes) { if (strlen($body)+strlen($chunk)>$maxBytes) { $overflow=true; return 0; } $body.=$chunk; return strlen($chunk); },
            CURLOPT_HEADERFUNCTION=>function($ch,$line) use (&$location) { if (stripos($line,'Location:')===0) $location=trim(substr($line,9)); return strlen($line); }]);
        $ok = curl_exec($ch); $code = curl_getinfo($ch,CURLINFO_HTTP_CODE); $error=curl_error($ch); curl_close($ch);
        if ($overflow) throw new RuntimeException('Resposta excede o limite de tamanho.');
        if ($ok === false) throw new RuntimeException('Falha ao buscar feed: ' . $error);
        if ($code>=300 && $code<400 && $location) { $url=rss_resolve($url,$location); continue; }
        if ($code!==200) throw new RuntimeException('Feed retornou HTTP '.$code);
        return [$body,$url];
    }
    throw new RuntimeException('Redirecionamentos em excesso.');
}
function rss_text($value) { return trim(html_entity_decode(strip_tags((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8')); }
function rss_prepare_xml($xml) {
    // A declaração de tipo pode existir em feeds legítimos, mas nunca é carregada.
    if(str_contains($xml,"\0")) throw new RuntimeException('Codificação XML não suportada. Use um feed UTF-8.');
    $masked=preg_replace_callback('~<!--.*?-->|<!\[CDATA\[.*?\]\]>~s',fn($m)=>str_repeat(' ',strlen($m[0])),$xml);
    if(preg_match('/<!ENTITY/i',$masked)) throw new RuntimeException('O feed contém declarações de entidades XML não permitidas.');
    $doctype=<<<'REGEX'
~<!DOCTYPE\s+[A-Za-z_:][A-Za-z0-9_.:-]*(?:\s+(?:SYSTEM\s+(?:"[^"]*"|'[^']*')|PUBLIC\s+(?:"[^"]*"|'[^']*')\s+(?:"[^"]*"|'[^']*')))?\s*>~i
REGEX;
    preg_match_all($doctype,$masked,$matches,PREG_OFFSET_CAPTURE);
    foreach(array_reverse($matches[0]) as [$declaration,$offset]) {
        $xml=substr_replace($xml,str_repeat(' ',strlen($declaration)),$offset,strlen($declaration));
        $masked=substr_replace($masked,str_repeat(' ',strlen($declaration)),$offset,strlen($declaration));
    }
    if(preg_match('/<!DOCTYPE/i',$masked)) throw new RuntimeException('O feed contém uma DTD interna não permitida. Solicite uma versão RSS sem declarações de entidades.');
    if(preg_match('~^\s*(?:<\?xml[^>]*>\s*)?(?:<!--.*?-->\s*)*<html(?:\s|>)~is',ltrim($xml,"\xEF\xBB\xBF"))) throw new RuntimeException('O endereço retornou uma página HTML, não um feed RSS/Atom. Confira a URL da fonte; pode ser uma página do site ou um bloqueio do servidor.');
    return $xml;
}
function rss_parse($xml, $base) {
    $xml=rss_prepare_xml($xml);
    $previous=libxml_use_internal_errors(true);
    try { $root=simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA); }
    finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    if (!$root) throw new RuntimeException('XML inválido.');
    $atom=$root->getName()==='feed';
    if (!$atom && $root->getName()!=='rss' && $root->getName()!=='RDF') throw new RuntimeException('Documento não é RSS/Atom.');
    $nodes=$atom ? $root->xpath('//*[local-name()="entry"]') : $root->xpath('//*[local-name()="item"]');
    $items=[];
    foreach (array_slice($nodes ?: [],0,100) as $node) {
        $get=function($name) use ($node) { $v=$node->xpath('./*[local-name()="'.$name.'"]'); return $v[0] ?? ''; };
        $title=rss_text($get('title')); $link=(string)$get('link');
        if ($atom) foreach ($node->xpath('./*[local-name()="link"]') as $l) if (!isset($l['rel']) || (string)$l['rel']==='alternate') { $link=(string)$l['href']; break; }
        try { $link=rss_resolve($base,$link); } catch (Throwable $e) { continue; }
        if (!$title || !$link) continue;
        $raw=rss_node_markup($get('description')); if ($raw==='') $raw=rss_node_markup($get('summary'));
        $full=rss_node_markup($get('encoded')); if ($full==='') $full=rss_node_markup($get('content'));
        if (!$raw) $raw=$full;
        $image='';
        foreach ($node->xpath('.//*[local-name()="thumbnail" or local-name()="content" or local-name()="enclosure"]') as $m) {
            $type=(string)$m['type']; $medium=(string)$m['medium'];
            if (isset($m['url']) && (!$type || str_starts_with($type,'image/')) && (!$medium || $medium==='image')) { $image=(string)$m['url']; break; }
        }
        if (!$image) foreach ($node->xpath('./*[local-name()="image" or local-name()="thumbnail"]') as $m) {
            $urls=$m->xpath('./*[local-name()="url"]'); $image=trim((string)($m['url'] ?? '')) ?: trim((string)($urls[0] ?? $m)); if ($image) break;
        }
        if (!$image && $atom) foreach ($node->xpath('./*[local-name()="link"]') as $m) if ((string)$m['rel']==='enclosure' && str_starts_with((string)$m['type'],'image/')) { $image=(string)$m['href']; break; }
        if (!$image) $image=rss_html_image($full ?: $raw);
        if (!$image && $full) $image=rss_html_image($raw);
        try { $image=rss_resolve($link,$image); } catch (Throwable $e) { $image=''; }
        $date=(string)($get('pubDate') ?: ($get('published') ?: ($get('updated') ?: $get('date'))));
        $stamp=strtotime($date); if (!$stamp || $stamp>time()+86400) $stamp=time();
        $items[]=['identity'=>hash('sha256',(string)($get('guid') ?: ($get('id') ?: $link))), 'title'=>$title,'url'=>$link,'summary'=>rss_body_text($raw),'body'=>rss_body_text($full ?: $raw),'image'=>$image,'published_at'=>gmdate('Y-m-d H:i:s',$stamp)];
    }
    return $items;
}
function rss_node_markup($node) {
    if (!$node instanceof SimpleXMLElement) return (string)$node;
    $children=$node->xpath('./*');
    if (!$children) return (string)$node;
    return implode('',array_map(fn($child)=>$child->asXML(),$children));
}
function rss_page_image($html,$base) {
    $doc=rss_html_document($html); $xpath=new DOMXPath($doc);
    foreach (['og:image:secure_url','og:image','twitter:image','twitter:image:src'] as $key) {
        foreach ($xpath->query('//meta[@property="'.$key.'" or @name="'.$key.'"]') as $meta) {
            try { $url=rss_resolve($base,$meta->getAttribute('content')); if ($url) return $url; } catch(Throwable $e) { /* Tenta o próximo metadado. */ }
        }
    }
    foreach ($xpath->query('//link[@rel="image_src"]') as $link) {
        try { $url=rss_resolve($base,$link->getAttribute('href')); if ($url) return $url; } catch(Throwable $e) { }
    }
    // Não escolhe logos/banners genéricos da página como foto da matéria.
    foreach ($xpath->query('//article') as $article) {
        $candidate=rss_html_image($doc->saveHTML($article));
        try { $url=rss_resolve($base,$candidate); if ($url) return $url; } catch(Throwable $e) { }
    }
    throw new RuntimeException('Nenhuma foto encontrada no feed ou nos metadados da matéria.');
}
function rss_html_document($html) {
    $doc=new DOMDocument(); $previous=libxml_use_internal_errors(true);
    try { $doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET); }
    finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    foreach (['script','style','iframe','object','noscript'] as $tag) while ($node=$doc->getElementsByTagName($tag)->item(0)) $node->parentNode->removeChild($node);
    return $doc;
}
function rss_html_image($html) {
    if (!$html) return '';
    $doc=rss_html_document($html);
    foreach ($doc->getElementsByTagName('img') as $img) {
        foreach (['data-src','data-lazy-src','data-original','src'] as $attribute) {
            $value=trim($img->getAttribute($attribute));
            if ($value && !str_starts_with($value,'data:')) return $value;
        }
        $srcset=$img->getAttribute('srcset');
        if ($srcset) return preg_split('/\s+/',trim(explode(',',$srcset)[0]))[0];
    }
    $decoded=html_entity_decode($html,ENT_QUOTES | ENT_HTML5,'UTF-8');
    if ($decoded!==$html) return rss_html_image($decoded);
    return '';
}
function rss_body_text($html) {
    if (!$html) return '';
    $doc=rss_html_document($html);
    foreach (['p','div','br','li','h1','h2','h3','blockquote'] as $tag) foreach (iterator_to_array($doc->getElementsByTagName($tag)) as $node) $node->appendChild($doc->createTextNode("\n\n"));
    return trim(preg_replace('/\n[ \t]*\n(?:[ \t]*\n)+/',"\n\n",$doc->getElementsByTagName('body')->item(0)->textContent ?? ''));
}
function rss_image_info($bytes) {
    $info=@getimagesizefromstring($bytes);
    if (!$info || !in_array($info['mime'] ?? '',['image/jpeg','image/png','image/gif','image/webp'],true) || $info[0]*$info[1]>40000000) throw new RuntimeException('Formato de imagem inválido ou dimensões excessivas.');
    return $info;
}
function rss_cache_image($url,?callable $fetcher=null) {
    $dir=rss_data_dir().'/images';
    if (!is_dir($dir) && !mkdir($dir,0700,true)) throw new RuntimeException('Sem permissão para salvar imagens.');
    $key=hash('sha256',$url); $file=$dir.'/'.$key;
    if (is_file($file)) {
        try { if (filesize($file)>5*1024*1024) throw new RuntimeException('Cache excede o limite.'); rss_image_info(file_get_contents($file)); return $key; }
        catch (Throwable $e) { if (!unlink($file)) throw new RuntimeException('Não foi possível substituir imagem local inválida.'); }
    }
    [$bytes]=($fetcher ?? 'rss_fetch')($url,5*1024*1024);
    if (strlen($bytes)>5*1024*1024) throw new RuntimeException('Imagem excede 5 MB.');
    rss_image_info($bytes);
    $temp=tempnam($dir,'.image-');
    try {
        if (!$temp || file_put_contents($temp,$bytes,LOCK_EX)===false || !rename($temp,$file)) throw new RuntimeException('Não foi possível gravar imagem.');
        chmod($file,0600);
    } finally { if ($temp && is_file($temp)) unlink($temp); }
    return $key;
}
function rss_image_src($article) {
    return !empty($article['image_file']) ? 'rss-imagem.php?id='.(int)$article['id'] : $article['image'];
}
function rss_store($db,$source,$items) {
    $stmt=$db->prepare('INSERT INTO articles(source_id,identity,title,url,summary,image,published_at,body,image_file,image_error,credit) VALUES(?,?,?,?,?,?,?,?,?,?,?) ON CONFLICT(source_id,identity) DO UPDATE SET title=excluded.title,url=excluded.url,summary=excluded.summary,image=excluded.image,body=excluded.body,image_file=CASE WHEN excluded.image_file=\'\' AND articles.image=excluded.image THEN articles.image_file ELSE excluded.image_file END,image_error=excluded.image_error,credit=excluded.credit');
    $db->beginTransaction();
    try {
        $lookup=$db->prepare('SELECT id FROM articles WHERE source_id=? AND identity=?');
        $register=$db->prepare("INSERT OR IGNORE INTO content_order(kind,item_key,registered_at) VALUES('rss',?,?)");
        usort($items,fn($a,$b)=>strcmp($a['published_at'],$b['published_at']));
        foreach ($items as $i) {
            $stmt->execute([$source,$i['identity'],$i['title'],$i['url'],$i['summary'],$i['image'],$i['published_at'],$i['body'] ?? $i['summary'],$i['image_file'] ?? '',$i['image_error'] ?? '',$i['credit'] ?? '']);
            $lookup->execute([$source,$i['identity']]); $register->execute([(string)$lookup->fetchColumn(),gmdate('Y-m-d H:i:s')]);
        }
        $db->commit();
    } catch (Throwable $e) { $db->rollBack(); throw $e; }
}
function rss_prune($db=null) {
    $db=$db ?? rss_db(); $removed=0; $files=[];
    $db->beginTransaction();
    try {
        $categories=$db->query('SELECT DISTINCT category FROM sources')->fetchAll(PDO::FETCH_COLUMN);
        $find=$db->prepare('SELECT a.id,a.image_file FROM articles a JOIN sources s ON s.id=a.source_id WHERE s.category=? ORDER BY a.published_at DESC,a.id DESC LIMIT -1 OFFSET 90');
        $delete=$db->prepare('DELETE FROM articles WHERE id=?');
        foreach ($categories as $category) {
            $find->execute([$category]); $older=$find->fetchAll(PDO::FETCH_ASSOC);
            foreach ($older as $article) { $delete->execute([$article['id']]); $removed++; if ($article['image_file']) $files[$article['image_file']]=true; }
        }
        $db->commit();
    } catch(Throwable $e) { $db->rollBack(); throw $e; }
    $references=$db->prepare('SELECT 1 FROM articles WHERE image_file=? LIMIT 1');
    foreach (array_keys($files) as $key) {
        if (!preg_match('/^[a-f0-9]{64}$/',$key)) continue;
        $references->execute([$key]);
        if (!$references->fetchColumn()) { $file=rss_data_dir().'/images/'.$key; if (is_file($file) && !unlink($file)) error_log('RSS: não foi possível remover imagem antiga.'); }
    }
    return $removed;
}
function rss_paginate($entries,$requestedPage=1) {
    $entries=array_slice($entries,0,90); $total=count($entries); $pages=max(1,(int)ceil($total/15));
    $requested=filter_var($requestedPage,FILTER_VALIDATE_INT);
    $page=max(1,min($pages,$requested===false ? 1 : $requested));
    return ['items'=>array_slice($entries,($page-1)*15,15),'page'=>$page,'pages'=>$pages,'total'=>$total];
}
function rss_refresh(?callable $fetcher=null,?callable $imageImporter=null,?callable $pageFetcher=null,?callable $progress=null) {
    $fetcher=$fetcher ?? 'rss_fetch';
    $db=rss_db(); $results=[];
    $sources=$db->query('SELECT * FROM sources WHERE active=1')->fetchAll(PDO::FETCH_ASSOC);
    if (!$sources && $progress) $progress('Nenhuma fonte ativa cadastrada.');
    foreach ($sources as $s) {
        if ($progress) $progress('Fonte '.$s['name'].(rss_source_type($s)==='ofec'?': consultando API OFEC...':': buscando RSS...'));
        $processed=0; $created=0; $existing=0; $failed=0; $contentErrors=[]; $status='error';
        try {
            if(rss_source_type($s)==='ofec') { $batch=rss_ofec_items($fetcher,$progress); $items=$batch['items']; $contentErrors=$batch['errors']; if($progress && $batch['skipped']) $progress('OFEC: '.$batch['skipped'].' matérias externas ignoradas.'); }
            else { [$xml,$url]=$fetcher($s['url']); $items=array_values(array_column(rss_parse($xml,$url),null,'identity')); }
            $lookup=$db->prepare('SELECT image,image_file FROM articles WHERE source_id=? AND identity=?');
            $position=0;
            foreach ($items as &$item) {
                $position++;
                if ($progress) $progress($s['name'].' — item '.$position.'/'.count($items).': '.$item['title']);
                $lookup->execute([$s['id'],$item['identity']]);
                $old=$lookup->fetch(PDO::FETCH_ASSOC); if ($old) $existing++; else $created++;
                try {
                    if (!$item['image'] && $old && rss_has_image($old)) {
                        $item['image']=$old['image']; $item['image_file']=$old['image_file']; if ($progress) $progress('Foto local reutilizada.'); continue;
                    }
                    if (!$item['image'] && rss_source_type($s)==='ofec') throw new RuntimeException('A API OFEC não forneceu foto para esta matéria.');
                    if (!$item['image']) {
                        if ($progress) $progress('Procurando foto na página original (timeout de até 15 segundos)...');
                        try { [$page,$pageUrl]=($pageFetcher ?? 'rss_fetch')($item['url']); $item['image']=rss_page_image($page,$pageUrl); }
                        catch(Throwable $e) { throw new RuntimeException('Busca da foto na página: '.$e->getMessage()); }
                    }
                    if ($progress) $progress('Baixando/validando a foto...');
                    $item['image_file']=($imageImporter ?? 'rss_cache_image')($item['image']);
                }
                catch (Throwable $e) { $item['image_error']=$e->getMessage(); $failed++; if ($progress) $progress('Item sem foto válida: '.$e->getMessage()); }
            }
            unset($item);
            rss_store($db,$s['id'],$items); $error=$contentErrors?implode(' ',array_slice($contentErrors,0,5)):null; $processed=count($items); $status=($failed || $contentErrors) ? 'partial' : 'success';
            $downloaded=count(array_filter($items,fn($item)=>!empty($item['image_file'])));
            $results[]=$s['name'].': '.$processed.' itens processados | novos: '.$created.' | existentes: '.$existing.' | imagens locais: '.$downloaded.' | falhas de imagem: '.$failed.' | falhas de conteúdo: '.count($contentErrors);
        } catch (Throwable $e) { $error=$e->getMessage(); $created=0; $existing=0; $results[]=$s['name'].': ERRO — '.$error; }
        $at=gmdate('Y-m-d H:i:s');
        $db->prepare('UPDATE sources SET checked_at=?,error=? WHERE id=?')->execute([$at,$error,$s['id']]);
        $db->prepare('INSERT INTO import_runs(source_id,source_url,finished_at,status,processed,created,existing,image_errors,error,content_errors) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([$s['id'],$s['url'],$at,$status,$processed,$created,$existing,$failed,$error,count($contentErrors)]);
        if ($progress) $progress($results[count($results)-1]);
    }
    $removed=rss_prune($db);
    if ($removed && $progress) $progress('Limpeza: '.$removed.' notícias antigas excluídas (máximo 90 por categoria).');
    return $results;
}
function rss_has_image($article) {
    $key=$article['image_file'] ?? '';
    if (!preg_match('/^[a-f0-9]{64}$/',$key)) return false;
    $file=rss_data_dir().'/images/'.$key;
    if (!is_file($file) || !is_readable($file) || filesize($file)>5*1024*1024) return false;
    $info=@getimagesize($file);
    return $info && in_array($info['mime'] ?? '',['image/jpeg','image/png','image/gif','image/webp'],true) && $info[0]*$info[1]<=40000000;
}
function rss_public_articles($category=null,$limit=54) {
    $sql="SELECT a.*,s.name,s.category FROM articles a JOIN sources s ON s.id=a.source_id WHERE s.active=1 AND a.image_file<>''";
    $params=[]; if ($category) { $sql.=' AND s.category=?'; $params[]=$category; }
    $stmt=rss_db()->prepare($sql.' ORDER BY a.published_at DESC,a.id DESC'); $stmt->execute($params);
    $rows=[];
    foreach ($stmt as $article) {
        if (!rss_has_image($article)) continue;
        $rows[]=$article;
        if ($limit!==null && count($rows)>=max(1,min(54,(int)$limit))) break;
    }
    return $rows;
}
function rss_save_source($id,$name,$url,$category,$type='auto') {
    $name=trim($name); $url=rss_url($url);
    if(!in_array($type,['auto','rss','ofec'],true)) throw new InvalidArgumentException('Tipo de fonte inválido.');
    if($type==='auto' || rss_ofec_url($url)) $type=rss_ofec_url($url)?'ofec':'rss';
    if($type==='ofec') { if(!rss_ofec_url($url)) throw new InvalidArgumentException('Para OFEC, use https://operarioferroviario.com.br/noticias.'); $url='https://operarioferroviario.com.br/noticias'; }
    if (!$name || strlen($name)>150 || !in_array($category,rss_categories(),true)) throw new InvalidArgumentException('Confira nome e categoria.');
    $db=rss_db();
    if ($id) {
        $stmt=$db->prepare('SELECT id FROM sources WHERE id=?'); $stmt->execute([$id]);
        if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Fonte não encontrada.');
        $db->prepare('UPDATE sources SET name=?,url=?,category=?,type=?,error=CASE WHEN url<>? THEN NULL ELSE error END,checked_at=CASE WHEN url<>? THEN NULL ELSE checked_at END WHERE id=?')->execute([$name,$url,$category,$type,$url,$url,$id]);
    } else $db->prepare('INSERT INTO sources(name,url,category,type) VALUES(?,?,?,?)')->execute([$name,$url,$category,$type]);
    rss_prune($db);
}
function rss_cards($category=null,$limit=9) {
    $count=0;
    try {
        foreach (rss_public_articles($category,$limit) as $a) {
            $count++;
            rss_render_card($a);
        }
    } catch (Throwable $e) { error_log('RSS: '.$e->getMessage()); return null; }
    return $count;
}
function rss_render_card($a) {
            echo '<article class="noticia-card">';
            if ($a['image']) echo '<img loading="lazy" src="'.rss_escape(rss_image_src($a)).'" alt="'.rss_escape($a['title']).'">';
            echo '<div class="noticia-content"><span class="noticia-categoria">'.rss_escape($a['category']).'</span><h3><a href="rss-materia.php?id='.(int)$a['id'].'">'.rss_escape($a['title']).'</a></h3><p class="noticia-resumo">'.rss_escape($a['summary']).'</p><div class="noticia-meta"><small>Fonte: '.rss_escape($a['name']).'</small><a class="ler-mais" href="rss-materia.php?id='.(int)$a['id'].'">Ler mais</a></div></div></article>';
}
function rss_section($category=null,$limit=9) {
    echo '<section class="ultimas-noticias" id="noticias-rss"><h2 class="section-title">Notícias das fontes RSS</h2><div class="noticias-grid">';
    $count=rss_cards($category,$limit);
    echo '</div>';
    if ($count===0) echo '<p>Nenhuma notícia RSS disponível nesta categoria no momento.</p>';
    if ($count===null) echo '<p>Não foi possível carregar as notícias RSS. Consulte o diagnóstico no painel de fontes.</p>';
    echo '</section>';
}

