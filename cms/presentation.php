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
    $parts=parse_url($url);
    if($url!=='' && (!filter_var($url,FILTER_VALIDATE_URL) || !$parts || !in_array(strtolower($parts['scheme'] ?? ''),['https','http'],true) || isset($parts['user']) || isset($parts['pass']) || preg_match('/[\x00-\x20\x7f]/',$url))) {
        throw new InvalidArgumentException('Informe somente o link HTTP/HTTPS de incorporação da revista, sem código iframe.');
    }
    $label=trim((string)($values['edition_label'] ?? ''));
    if(!preg_match('/^.{0,120}$/usD',$label)) throw new InvalidArgumentException('O nome da edição deve ter até 120 caracteres.');
    $settings=['edition_url'=>$url,'edition_label'=>$label];
    $logo=cms_upload($file);
    if($logo) $settings['brand_logo']=$logo;
    $db=cms_db(); $db->beginTransaction();
    try {
        $stmt=$db->prepare('INSERT INTO cms_meta(name,value) VALUES(?,?) ON CONFLICT(name) DO UPDATE SET value=excluded.value');
        foreach($settings as $key=>$value) $stmt->execute([$key,$value]);
        $db->commit();
    } catch(Throwable $e) { if($db->inTransaction()) $db->rollBack(); throw $e; }
}
