<?php
require dirname(__DIR__).'/rss/core.php';
$n=0; function check($yes,$message) { global $n; if(!$yes) throw new RuntimeException($message); $n++; }
$count=0; $resolver=function($host) use (&$count) { $count++; return ['93.184.216.34']; };
check(rss_public_ips('cache-test.example',$resolver)===['93.184.216.34'],'Resolver endereço público');
check(rss_public_ips('CACHE-TEST.EXAMPLE',fn()=>throw new RuntimeException('DNS temporariamente indisponível'))===['93.184.216.34'] && $count===1,'Reutilizar resolução validada durante a execução');
$failed=false; try { rss_public_ips('private-test.example',fn()=>['127.0.0.1']); } catch(Throwable $e) { $failed=true; } check($failed,'Cache não permite endereço privado');
check(rss_public_ips('private-test.example',fn()=>['93.184.216.34'])===['93.184.216.34'],'Não guardar resolução rejeitada');
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
echo "$n verificações de proteção de rede passaram.\n";
