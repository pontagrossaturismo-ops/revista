<?php
require_once __DIR__.'/core.php';

function cms_presentation(): array {
    $stmt=cms_db()->query("SELECT name,value FROM cms_meta WHERE name IN ('edition_url','edition_label','brand_logo')");
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}
function cms_brand_logo(): string {
    $settings=cms_presentation();
    return $settings['brand_logo'] ?? (is_file(dirname(__DIR__).'/img/logomarca.png')?'img/logomarca.png':'assets/brand.svg');
}
function cms_save_presentation(array $values, array $file=[]): void {
    $url=trim((string)($values['edition_url'] ?? ''));
    if(str_starts_with($url,'<')) {
        $previous=libxml_use_internal_errors(true);
        try {
            $document=new DOMDocument();
            $document->loadHTML('<?xml encoding="utf-8" ?>'.$url,LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $frames=$document->getElementsByTagName('iframe');
            if($frames->length!==1 || !preg_match('/^<iframe\b/i',$url)) throw new InvalidArgumentException('Cole um único iframe da revista ou seu link HTTP/HTTPS.');
            $url=trim($frames->item(0)->getAttribute('src'));
            if($url==='') throw new InvalidArgumentException('O iframe precisa conter o endereço src da revista.');
        } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    }
    if(str_starts_with($url,'//')) $url='https:'.$url;
    $parts=parse_url($url);
    if($url!=='' && (!filter_var($url,FILTER_VALIDATE_URL) || !$parts || !in_array(strtolower($parts['scheme'] ?? ''),['https','http'],true) || isset($parts['user']) || isset($parts['pass']) || preg_match('/[\x00-\x20\x7f]/',$url))) {
        throw new InvalidArgumentException('Informe um link HTTP/HTTPS válido ou o iframe de incorporação da revista.');
    }
    $label=trim((string)($values['edition_label'] ?? ''));
    if(!preg_match('/^.{0,120}$/usD',$label)) throw new InvalidArgumentException('O nome da edição deve ter até 120 caracteres.');
    $settings=['edition_url'=>$url,'edition_label'=>$label];
    $logo=cms_upload($file);
    if($logo) $settings['brand_logo']=$logo;
    $db=cms_db(); $db->beginTransaction();
    try {
        foreach($settings as $key=>$value) rss_sqlite_upsert($db,'INSERT INTO cms_meta(name,value) VALUES(?,?)',[$key,$value],'UPDATE cms_meta SET value=? WHERE name=?',[$value,$key]);
        $db->commit();
    } catch(Throwable $e) { if($db->inTransaction()) $db->rollBack(); throw $e; }
}
