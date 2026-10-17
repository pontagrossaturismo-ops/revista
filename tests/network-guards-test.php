<?php
require dirname(__DIR__).'/rss/core.php';
$n=0; function check($yes,$message) { global $n; if(!$yes) throw new RuntimeException($message); $n++; }
$count=0; $resolver=function($host) use (&$count) { $count++; return ['93.184.216.34']; };
check(rss_public_ips('cache-test.example',$resolver)===['93.184.216.34'],'Resolver endereço público');
check(rss_public_ips('CACHE-TEST.EXAMPLE',fn()=>throw new RuntimeException('DNS temporariamente indisponível'))===['93.184.216.34'] && $count===1,'Reutilizar resolução validada durante a execução');
$failed=false; try { rss_public_ips('private-test.example',fn()=>['127.0.0.1']); } catch(Throwable $e) { $failed=true; } check($failed,'Cache não permite endereço privado');
check(rss_public_ips('private-test.example',fn()=>['93.184.216.34'])===['93.184.216.34'],'Não guardar resolução rejeitada');
$recordCalls=0; $windows=rss_resolve_host('windows-resolver.example',fn()=>['93.184.216.34'],function() use (&$recordCalls) { $recordCalls++; return []; });
check($windows===['93.184.216.34'] && $recordCalls===0,'Resolvedor do sistema funciona sem dns_get_record, como no XAMPP');
$ipv6=rss_resolve_host('ipv6-only.example',fn()=>false,fn()=>['2606:4700:4700::1111']);
check($ipv6===['2606:4700:4700::1111'],'Usar alternativa quando sistema não retorna IPv4');
$cname=rss_resolve_host('cname-test.example',fn()=>['93.184.216.34','93.184.216.34'],fn()=>[]);
check($cname===['93.184.216.34'],'Aceitar resolução do sistema com CNAME e remover duplicatas');
$blocked=false; try { rss_public_ips('hosts-private.example',fn($host)=>rss_resolve_host($host,fn()=>['192.168.1.5'],fn()=>['93.184.216.34'])); } catch(Throwable $e) { $blocked=true; }
check($blocked,'Resolvedor do sistema continua bloqueando endereço privado, sem trocar para outro público');
$failed=false; try { rss_public_ips('missing-dns.example',fn($host)=>rss_resolve_host($host,fn()=>false,fn()=>[])); } catch(Throwable $e) { $failed=str_contains($e->getMessage(),'Falha de DNS') && str_contains($e->getMessage(),'missing-dns.example'); }
check($failed,'Explicar falha dos dois resolvedores identificando o domínio');
check(rss_public_ips('missing-dns.example',fn()=>['93.184.216.34'])===['93.184.216.34'],'Nova tentativa após falha de DNS não fica presa no cache');
$article=['id'=>1,'title'=>'Matéria preservada','articleBody'=>'<p>Texto</p>','mainImage'=>'https://example.com/photo.jpg','createdAt'=>'2026-10-01'];
$fetch=fn($url)=>[json_encode(['data'=>str_contains($url,'?')?['items'=>[['id'=>1],['id'=>2]]]:$article]),$url];
$ticks=0; $clock=function() use (&$ticks) { return $ticks++*40; };
$batch=rss_ofec_items($fetch,null,$clock);
check(count($batch['items'])===1 && str_contains(implode(' ',$batch['errors']),'2 minutos'),'Limite de consulta preserva matérias já obtidas');
$calls=[]; $failing=function($url) use (&$calls,$article) { $calls[]=$url; if(str_contains($url,'?')) return [json_encode(['data'=>['items'=>[['id'=>1],['id'=>2],['id'=>3],['id'=>4],['id'=>5]]]]),$url]; if(str_ends_with($url,'/1')) return [json_encode(['data'=>$article]),$url]; throw new RuntimeException('Connection timed out'); };
$batch=rss_ofec_items($failing);
check(count($batch['items'])===1 && count($calls)===5 && str_contains(implode(' ',$batch['errors']),'três falhas'),'Interromper falhas consecutivas sem perder os itens já obtidos');
$later=function($url) use ($article) { if(str_contains($url,'?')) { parse_str(parse_url($url,PHP_URL_QUERY),$q); if($q['page']==='3') throw new RuntimeException('Domínio sem endereço público.'); $rows=[]; for($i=1;$i<=30;$i++) $rows[]=['id'=>(int)$q['page']*100+$i]; return [json_encode(['data'=>['items'=>$rows]]),$url]; } return [json_encode(['data'=>array_merge($article,['id'=>(int)basename(parse_url($url,PHP_URL_PATH))])]),$url]; };
$batch=rss_ofec_items($later);
check(count($batch['items'])===60 && str_contains(implode(' ',$batch['errors']),'Página 3'),'Falha de DNS na página 3 preserva as duas páginas anteriores');
$expired=rss_curl_progress_options(hrtime(true)-1,false);
check(isset($expired[CURLOPT_PROGRESSFUNCTION]) && $expired[CURLOPT_NOPROGRESS]===false,'Selecionar callback legado com verificação de tempo habilitada');
check($expired[CURLOPT_PROGRESSFUNCTION](null,0,0,0,0)===1 && $expired[CURLOPT_PROGRESSFUNCTION](0,0,0,0)===1,'Interromper prazo vencido nas duas assinaturas antigas');
$future=rss_curl_progress_options(hrtime(true)+15000000000,false);
check($future[CURLOPT_PROGRESSFUNCTION](null,0,0,0,0)===0,'Permitir transferência dentro do prazo usando callback legado');
$temp=tempnam(sys_get_temp_dir(),'revista-curl-'); file_put_contents($temp,'Conteúdo de teste');
try {
    $ch=curl_init('file://'.$temp);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true]+$future);
    check(curl_exec($ch)==='Conteúdo de teste','cURL executa transferência com callback compatível');
    curl_close($ch);
    $ch=curl_init('file://'.$temp);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true]+$expired);
    check(curl_exec($ch)===false && curl_errno($ch)===CURLE_ABORTED_BY_CALLBACK,'cURL realmente interrompe transferência com prazo vencido');
    curl_close($ch);
} finally { unlink($temp); }
echo "$n verificações de proteção de rede passaram.\n";
