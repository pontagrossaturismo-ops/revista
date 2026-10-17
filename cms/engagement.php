<?php
require_once __DIR__.'/presentation.php';
function cms_public_url($path) {
    $base=rtrim((string)revista_setting('SITE_URL',''),'/');
    if(!$base) { $host=$_SERVER['HTTP_HOST'] ?? 'localhost'; if(!preg_match('/^[a-z0-9.\-:\[\]]+$/i',$host)) $host='localhost'; $dir=rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')),'/.'); $base=(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off'?'https':'http').'://'.$host.$dir; }
    return $base.'/'.ltrim($path,'/');
}
function cms_whatsapp_icon() {
    return '<img class="whatsapp-icon" data-whatsapp-icon src="'.rss_escape(cms_whatsapp_icon_src()).'" width="28" height="28" alt="" decoding="async" referrerpolicy="no-referrer">';
}
function cms_share($title,$path) {
    $url=cms_public_url($path); $text=rawurlencode($title.' '.$url); $encoded=rawurlencode($url);
    ?><nav class="share-tools" aria-label="Compartilhar conteúdo" data-share-title="<?=rss_escape($title)?>" data-share-url="<?=rss_escape($url)?>"><span>Compartilhar</span><a target="_blank" rel="noopener noreferrer" href="https://wa.me/?text=<?=$text?>"><?=cms_whatsapp_icon()?>WhatsApp</a><a target="_blank" rel="noopener noreferrer" href="https://www.facebook.com/sharer/sharer.php?u=<?=$encoded?>">Facebook</a><a target="_blank" rel="noopener noreferrer" href="https://twitter.com/intent/tweet?text=<?=rawurlencode($title)?>&url=<?=$encoded?>">X</a><a target="_blank" rel="noopener noreferrer" href="https://t.me/share/url?url=<?=$encoded?>&text=<?=rawurlencode($title)?>">Telegram</a><a target="_blank" rel="noopener noreferrer" href="https://www.linkedin.com/sharing/share-offsite/?url=<?=$encoded?>">LinkedIn</a><button type="button" data-copy-link>Copiar link</button><button type="button" data-native-share hidden>Mais opções</button><small data-share-status role="status"></small></nav><?php
}
