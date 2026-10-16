<?php
// Adaptador da API pública observada nos scripts oficiais da OFEC (ownerId 195).
function rss_ofec_url($url) {
    $p=parse_url($url); return in_array(strtolower($p['host'] ?? ''),['operarioferroviario.com.br','www.operarioferroviario.com.br'],true) && in_array(rtrim($p['path'] ?? '', '/'),['/noticias','/feed'],true);
}
function rss_source_type($source) { return ($source['type'] ?? '')==='ofec' || rss_ofec_url($source['url'] ?? '') ? 'ofec' : 'rss'; }
function rss_ofec_json($fetcher,$url) {
    [$raw]=$fetcher($url);
    try { $data=json_decode($raw,true,64,JSON_THROW_ON_ERROR); } catch(JsonException $e) { throw new RuntimeException('A API OFEC não retornou JSON válido. Verifique o acesso à fonte.'); }
    if(!is_array($data)) throw new RuntimeException('Resposta da API OFEC inválida.');
    if(isset($data['header']['codigo']) && (int)$data['header']['codigo']!==1) throw new RuntimeException('A API OFEC informou uma falha na consulta.');
    $data=$data['data'] ?? $data;
    if(!is_array($data)) throw new RuntimeException('Dados da API OFEC inválidos.');
    return $data;
}
function rss_ofec_string($value) { return is_string($value) || is_numeric($value) ? (string)$value : ''; }
function rss_ofec_article($row,$expectedId) {
    if(!is_array($row) || rss_ofec_string($row['id'] ?? '')!==(string)$expectedId) throw new RuntimeException('A API retornou uma matéria diferente da solicitada.');
    if(!empty($row['isExternal'])) return null; // Não copia iframes/conteúdo externo de terceiros.
    $title=rss_text(rss_ofec_string($row['title'] ?? '')); $body=rss_body_text(rss_ofec_string($row['articleBody'] ?? ''));
    if($title==='' || $body==='') throw new RuntimeException('Matéria sem título ou texto na API OFEC.');
    $image=trim(rss_ofec_string($row['mainImage'] ?? ''));
    if($image!=='') { try { $image=rss_resolve('https://operarioferroviario.com.br/',$image); } catch(Throwable $e) { $image=''; } }
    $date=trim(rss_ofec_string($row['createdAt'] ?? ''));
    try { $stamp=$date!=='' ? (new DateTimeImmutable($date,new DateTimeZone('America/Sao_Paulo')))->getTimestamp() : time(); } catch(Throwable $e) { $stamp=time(); }
    if($stamp>time()+86400) $stamp=time();
    return ['identity'=>hash('sha256','ofec:'.$expectedId),'title'=>$title,'url'=>'https://operarioferroviario.com.br/noticias/'.$expectedId,'summary'=>rss_body_text(rss_ofec_string($row['description'] ?? '')),'body'=>$body,'image'=>$image,'published_at'=>gmdate('Y-m-d H:i:s',$stamp),'credit'=>rss_text(rss_ofec_string($row['photographer'] ?? ''))];
}
function rss_ofec_items($fetcher,$progress=null) {
    $api='https://fancontentapi.fanbase.com.br/api/owner/195/newsArticles'; $items=[]; $errors=[]; $seen=[]; $skipped=0;
    // Até 90 notícias por execução, sem percorrer todo o arquivo do clube.
    for($page=1;$page<=3;$page++) {
        if($progress) $progress('OFEC: consultando lista de notícias, página '.$page.'...');
        try { $data=rss_ofec_json($fetcher,$api.'?page='.$page.'&size=30'); if(!isset($data['items']) || !is_array($data['items'])) throw new RuntimeException('A lista da API OFEC mudou de formato (items ausente).'); }
        catch(Throwable $e) { if($page===1) throw $e; $errors[]='Página '.$page.': '.$e->getMessage(); break; }
        $rows=array_slice($data['items'],0,30);
        foreach($rows as $row) {
            $id=is_array($row)?($row['id'] ?? null):null;
            if(!is_scalar($id) || !preg_match('/^[1-9][0-9]{0,17}$/',(string)$id)) { $errors[]='Identificador inválido na lista OFEC.'; continue; }
            if(isset($seen[(string)$id])) continue; $seen[(string)$id]=true;
            if($progress) $progress('OFEC: buscando matéria '.$id.'...');
            try { $article=rss_ofec_article(rss_ofec_json($fetcher,$api.'/'.$id),$id); if($article) $items[]=$article; else $skipped++; }
            catch(Throwable $e) { $errors[]='Matéria '.$id.': '.$e->getMessage(); if($progress) $progress(end($errors)); }
        }
        if(count($rows)<30 || (isset($data['totalPages']) && $page>=(int)$data['totalPages'])) break;
    }
    if(!$items && $errors) throw new RuntimeException('Não foi possível importar matérias da OFEC. '.implode(' ',array_slice($errors,0,3)));
    return compact('items','errors','skipped');
}
