<?php
require_once dirname(__DIR__).'/cms/core.php';
function guia_lojas($field,$value) {
    if(!in_array($field,['categoria','url'],true)) throw new InvalidArgumentException('Filtro inválido.');
    $all=cms_companies();
    $all['rows']=array_values(array_filter($all['rows'],fn($store)=>$field==='categoria' ? cms_category($store[$field] ?? '')===cms_category($value) : ($store[$field] ?? '')===$value));
    return $all;
}
function guia_asset($path,$prefix='') {
    $path=trim((string)$path);
    if (!$path || $path==='0' || str_starts_with($path,'//') || preg_match('~^[a-z][a-z0-9+.-]*:~i',$path) && !preg_match('~^https?://~i',$path)) return '';
    if(str_starts_with($path,'media.php?arquivo=')) return $path;
    return preg_match('~^https?://|^/~i',$path) ? $path : $prefix.$path;
}
function guia_notice($state) {
    if ($state==='unconfigured' || $state==='unavailable') echo '<p role="status">O guia de estabelecimentos está temporariamente indisponível. Tente novamente mais tarde.</p>';
}
