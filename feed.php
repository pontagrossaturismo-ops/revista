<?php
require_once __DIR__.'/rss/core.php';
header('Content-Type: application/rss+xml; charset=utf-8');
try {
    $category=$_GET['categoria'] ?? null;
    if ($category && !in_array($category,rss_categories(),true)) { http_response_code(400); exit('Categoria inválida'); }
    $sql='SELECT a.*,s.name FROM articles a JOIN sources s ON s.id=a.source_id WHERE s.active=1';
    $params=[]; if($category) { $sql.=' AND s.category=?'; $params[]=$category; }
    $stmt=rss_db()->prepare($sql.' ORDER BY a.published_at DESC,a.id DESC LIMIT 54'); $stmt->execute($params);
    $xml=new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel/></rss>');
    $channel=$xml->channel;
    $add=function($node,$key,$value) { $child=$node->addChild($key); $child[0]=$value; return $child; };
    $add($channel,'title','Revista PG Turismo — Notícias');
    $add($channel,'link','https://www.revistapgturismo.com.br/');
    $add($channel,'description','Notícias das fontes cadastradas na Revista PG Turismo');
    foreach($stmt as $a) {
        $i=$channel->addChild('item'); $add($i,'title',$a['title']); $add($i,'link',$a['url']); $add($i,'description',$a['summary']);
        $guid=$add($i,'guid','urn:revistapg:rss:'.$a['id']); $guid->addAttribute('isPermaLink','false');
        $add($i,'pubDate',gmdate(DATE_RSS,strtotime($a['published_at'].' UTC'))); $add($i,'source',$a['name']);
    }
    echo $xml->asXML();
} catch(Throwable $e) { error_log('RSS feed: '.$e->getMessage()); http_response_code(503); echo '<error>Feed temporariamente indisponível</error>'; }
