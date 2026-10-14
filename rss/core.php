<?php
require_once dirname(__DIR__).'/config/runtime.php';
// PHP 8.1+, PDO SQLite, cURL, SimpleXML e mbstring não é necessário.
function rss_escape($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function rss_categories() { return ['viagem','lazer','gastronomia','eventos','esporte','noticias']; }
function rss_db() {
    static $db;
    if ($db) return $db;
    $dir = revista_setting('RSS_DATA_DIR') ?: dirname(__DIR__, 2) . '/revista-rss-data';
    if (!is_dir($dir) && !mkdir($dir, 0700, true)) throw new RuntimeException('Não foi possível criar o diretório RSS.');
    $db = new PDO('sqlite:' . $dir . '/rss.sqlite', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA busy_timeout=10000; PRAGMA foreign_keys=ON');
    $db->exec('CREATE TABLE IF NOT EXISTS sources (id INTEGER PRIMARY KEY, name TEXT NOT NULL, url TEXT NOT NULL UNIQUE, category TEXT NOT NULL, active INTEGER NOT NULL DEFAULT 1, checked_at TEXT, error TEXT)');
    $db->exec('CREATE TABLE IF NOT EXISTS articles (id INTEGER PRIMARY KEY, source_id INTEGER NOT NULL REFERENCES sources(id) ON DELETE CASCADE, identity TEXT NOT NULL, title TEXT NOT NULL, url TEXT NOT NULL, summary TEXT NOT NULL, image TEXT NOT NULL, published_at TEXT NOT NULL, UNIQUE(source_id, identity))');
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
function rss_fetch($url) {
    for ($redirect=0; $redirect<5; $redirect++) {
        $url = rss_url($url); $p = parse_url($url); $ips = rss_public_ips($p['host']);
        $port = $p['port'] ?? ($p['scheme'] === 'https' ? 443 : 80);
        $body = ''; $location = ''; $overflow = false;
        $ch = curl_init($url);
        $ip = $ips[0]; if (str_contains($ip, ':')) $ip = '[' . $ip . ']';
        curl_setopt_array($ch, [CURLOPT_FOLLOWLOCATION=>false, CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_TIMEOUT=>15, CURLOPT_PROTOCOLS=>CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_PROXY=>'', CURLOPT_RESOLVE=>[$p['host'].':'.$port.':'.$ip], CURLOPT_USERAGENT=>'RevistaPG-RSS/1.0', CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_WRITEFUNCTION=>function($ch,$chunk) use (&$body,&$overflow) { if (strlen($body)+strlen($chunk)>2*1024*1024) { $overflow=true; return 0; } $body.=$chunk; return strlen($chunk); },
            CURLOPT_HEADERFUNCTION=>function($ch,$line) use (&$location) { if (stripos($line,'Location:')===0) $location=trim(substr($line,9)); return strlen($line); }]);
        $ok = curl_exec($ch); $code = curl_getinfo($ch,CURLINFO_HTTP_CODE); $error=curl_error($ch); curl_close($ch);
        if ($overflow) throw new RuntimeException('Feed excede 2 MB.');
        if ($ok === false) throw new RuntimeException('Falha ao buscar feed: ' . $error);
        if ($code>=300 && $code<400 && $location) { $url=rss_resolve($url,$location); continue; }
        if ($code!==200) throw new RuntimeException('Feed retornou HTTP '.$code);
        return [$body,$url];
    }
    throw new RuntimeException('Redirecionamentos em excesso.');
}
function rss_text($value) { return trim(html_entity_decode(strip_tags((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8')); }
function rss_parse($xml, $base) {
    if (preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) throw new RuntimeException('Declarações XML externas bloqueadas.');
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
        $raw=(string)($get('description') ?: ($get('summary') ?: ($get('encoded') ?: $get('content'))));
        $image='';
        foreach ($node->xpath('./*[local-name()="thumbnail" or local-name()="content" or local-name()="enclosure"]') as $m) {
            if (isset($m['url']) && ($m->getName()!=='enclosure' || str_starts_with((string)$m['type'],'image/'))) { $image=(string)$m['url']; break; }
        }
        if (!$image && preg_match('/<img[^>]+src=["\x27]([^"\x27]+)/i',$raw,$match)) $image=html_entity_decode($match[1],ENT_QUOTES,'UTF-8');
        try { $image=rss_resolve($base,$image); } catch (Throwable $e) { $image=''; }
        $date=(string)($get('pubDate') ?: ($get('published') ?: ($get('updated') ?: $get('date'))));
        $stamp=strtotime($date); if (!$stamp || $stamp>time()+86400) $stamp=time();
        $items[]=['identity'=>hash('sha256',(string)($get('guid') ?: ($get('id') ?: $link))), 'title'=>$title,'url'=>$link,'summary'=>rss_text($raw),'image'=>$image,'published_at'=>gmdate('Y-m-d H:i:s',$stamp)];
    }
    return $items;
}
function rss_store($db,$source,$items) {
    $stmt=$db->prepare('INSERT INTO articles(source_id,identity,title,url,summary,image,published_at) VALUES(?,?,?,?,?,?,?) ON CONFLICT(source_id,identity) DO UPDATE SET title=excluded.title,url=excluded.url,summary=excluded.summary,image=excluded.image');
    $db->beginTransaction();
    try { foreach ($items as $i) $stmt->execute([$source,$i['identity'],$i['title'],$i['url'],$i['summary'],$i['image'],$i['published_at']]); $db->commit(); } catch (Throwable $e) { $db->rollBack(); throw $e; }
}
function rss_refresh(?callable $fetcher=null) {
    $fetcher=$fetcher ?? 'rss_fetch';
    $db=rss_db(); $results=[];
    foreach ($db->query('SELECT * FROM sources WHERE active=1') as $s) {
        try { [$xml,$url]=$fetcher($s['url']); $items=rss_parse($xml,$url); rss_store($db,$s['id'],$items); $error=null; $results[]=$s['name'].': '.count($items).' itens processados'; }
        catch (Throwable $e) { $error=$e->getMessage(); $results[]=$s['name'].': ERRO — '.$error; }
        $db->prepare('UPDATE sources SET checked_at=?,error=? WHERE id=?')->execute([gmdate('Y-m-d H:i:s'),$error,$s['id']]);
    }
    return $results;
}
function rss_cards($category=null,$limit=9) {
    try {
        $sql='SELECT a.*,s.name,s.category FROM articles a JOIN sources s ON s.id=a.source_id WHERE s.active=1';
        $params=[]; if ($category) { $sql.=' AND s.category=?'; $params[]=$category; }
        $stmt=rss_db()->prepare($sql.' ORDER BY a.published_at DESC,a.id DESC LIMIT '.max(1,min(54,(int)$limit))); $stmt->execute($params);
        foreach ($stmt as $a) {
            echo '<article class="noticia-card">';
            if ($a['image']) echo '<img loading="lazy" src="'.rss_escape($a['image']).'" alt="'.rss_escape($a['title']).'">';
            echo '<div class="noticia-content"><span class="noticia-categoria">'.rss_escape($a['category']).'</span><h3><a href="'.rss_escape($a['url']).'" target="_blank" rel="noopener noreferrer">'.rss_escape($a['title']).'</a></h3><p class="noticia-resumo">'.rss_escape($a['summary']).'</p><div class="noticia-meta"><small>Fonte: '.rss_escape($a['name']).'</small><a class="ler-mais" href="'.rss_escape($a['url']).'" target="_blank" rel="noopener noreferrer">Ler na fonte</a></div></div></article>';
        }
    } catch (Throwable $e) { error_log('RSS: '.$e->getMessage()); }
}
