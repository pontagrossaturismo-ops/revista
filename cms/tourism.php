<?php
require_once __DIR__.'/core.php';
function tourism_db() {
    $db=cms_db(); static $ready=false;
    if($ready || $db->query("SELECT value FROM cms_meta WHERE name='tourism_version'")->fetchColumn()==='1') { $ready=true; return $db; }
    rss_schema_lock(function() use ($db) {
        if($db->query("SELECT value FROM cms_meta WHERE name='tourism_version'")->fetchColumn()==='1') return;
        $db->beginTransaction();
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS tourism_events(id INTEGER PRIMARY KEY AUTOINCREMENT,title TEXT NOT NULL,category TEXT NOT NULL,starts TEXT NOT NULL,ends TEXT NOT NULL,location TEXT NOT NULL,price TEXT NOT NULL,link TEXT NOT NULL,image TEXT NOT NULL,body TEXT NOT NULL,published INTEGER NOT NULL DEFAULT 0);
            CREATE TABLE IF NOT EXISTS tourism_routes(id INTEGER PRIMARY KEY AUTOINCREMENT,slug TEXT NOT NULL UNIQUE,title TEXT NOT NULL,duration TEXT NOT NULL,summary TEXT NOT NULL,body TEXT NOT NULL,image TEXT NOT NULL,published INTEGER NOT NULL DEFAULT 0); CREATE INDEX IF NOT EXISTS tourism_events_schedule ON tourism_events(published,ends,starts); CREATE INDEX IF NOT EXISTS tourism_events_category ON tourism_events(category,published)");
            $insert=$db->prepare('INSERT OR IGNORE INTO tourism_routes(slug,title,duration,summary,body,image,published) VALUES(?,?,?,?,?,?,1)');
            foreach(tourism_route_seeds() as $route) $insert->execute($route);
            $db->exec("INSERT INTO cms_meta(name,value) VALUES('tourism_version','1')"); $db->commit();
        } catch(Throwable $e) { $db->rollBack(); throw $e; }
    }); $ready=true; return $db;
}
function tourism_route_seeds() {
    $check="Antes de sair: confirme acesso, funcionamento, necessidade de reserva e condições do tempo nos canais oficiais de cada local. Tempos de deslocamento variam. Este roteiro é uma sugestão editorial, sem parceria comercial.";
    return [
        ['um-dia-em-ponta-grossa','Um dia em Ponta Grossa','Um dia','Natureza, história e sabores para conhecer a cidade sem correr.',"MANHÃ — Natureza\nReserve a manhã para o Parque Estadual de Vila Velha. Escolha as atividades conforme o tempo disponível, seu ritmo e as condições de acesso. Consulte antecipadamente ingressos, reservas e restrições. Evite incluir outro passeio distante na mesma manhã.\n\nALMOÇO — Sabores locais\nVolte para a cidade e escolha um restaurante no nosso guia de Gastronomia. Confira atendimento e faça reserva se necessário.\n\nTARDE — Centro e memória\nCaminhe pelo entorno da Praça Marechal Floriano Peixoto e conheça os exteriores do conjunto histórico. Inclua a Estação Saudade se houver visitação ou programação confirmada.\n\nNOITE — Jantar e programação\nCombine um jantar com um evento da agenda. Se estiver com crianças ou depois de uma trilha longa, prefira encerrar mais cedo.\n\nO QUE LEVAR\nCalçado confortável, água, proteção solar, documento e uma camada de roupa para mudanças de temperatura.\n\n".$check,'assets/route-nature.svg'],
        ['passeios-gratuitos','Passeios gratuitos em Ponta Grossa','Meio dia ou um dia','Áreas públicas e caminhadas para aproveitar a cidade com orçamento leve.',"PRIMEIRA PARADA — Parque Ambiental\nComece com uma caminhada pelas áreas públicas do Parque Ambiental. Observe a paisagem urbana e os espaços abertos do entorno ferroviário, respeitando eventuais áreas isoladas.\n\nSEGUNDA PARADA — Centro histórico\nExplore a região da Praça Marechal Floriano Peixoto a pé. A proposta é conhecer os exteriores e a vida da cidade; entrada em prédios e atividades internas depende das regras de cada espaço.\n\nTERCEIRA PARADA — Lago de Olarias\nEm tempo firme, encerre nas áreas públicas do Lago de Olarias. Escolha um trajeto compatível com seu ritmo, utilize os caminhos permitidos e mantenha crianças supervisionadas perto da água.\n\nORÇAMENTO\nO roteiro prioriza áreas públicas ao ar livre. Transporte, estacionamento, alimentação, exposições especiais e serviços opcionais podem ter custos; confirme antes de sair.\n\nALTERNATIVA\nSe chover ou houver manutenção nos espaços abertos, consulte o roteiro para dias de chuva e verifique quais atividades internas são gratuitas.\n\n".$check,'assets/route-city.svg'],
        ['ponta-grossa-com-criancas','O que fazer com crianças','Passeios curtos, com pausas','Um roteiro flexível para explorar ao ar livre e respeitar o ritmo da família.',"MANHÃ — Brincar e caminhar\nEscolha uma área pública do Parque Ambiental para uma caminhada curta. Faça pausas, combine um ponto de encontro e confira as condições dos equipamentos antes de utilizá-los.\n\nALMOÇO — Conforto primeiro\nNo guia de Gastronomia, procure opções que informem atendimento a famílias. Confirme cadeirões, espaço infantil e acessibilidade diretamente com a empresa; não presuma que esses serviços estejam disponíveis.\n\nTARDE — Lago de Olarias\nSe o tempo permitir, faça um passeio breve pelas áreas públicas do lago. Leve água e lanches, evite horários de calor intenso e mantenha supervisão constante perto da água.\n\nPARA CRIANÇAS MAIORES\nUma visita a Vila Velha pode substituir o programa da manhã, desde que a família confirme exigências de idade, percurso, ingressos, acesso e duração. Não tente encaixar todos os passeios no mesmo dia.\n\nPLANO B\nEm caso de chuva, escolha uma atividade cultural interna com programação adequada à faixa etária e funcionamento confirmado.\n\n".$check,'assets/route-family.svg'],
        ['ponta-grossa-com-chuva','O que fazer quando chove','Meio dia','Cultura, gastronomia e uma visita tranquila, com menos tempo ao ar livre.',"MANHÃ — Museus e cultura\nConsulte a programação do Museu Campos Gerais e de outros espaços culturais da cidade. Confirme horário, exposições em cartaz, acessibilidade e necessidade de agendamento antes do deslocamento. Escolha um local aberto no dia, em vez de tentar visitar vários sem confirmação.\n\nALMOÇO — Descobertas à mesa\nUse o guia de Gastronomia para escolher um restaurante ou café. Confira funcionamento e localização; em chuva forte, prefira reduzir os deslocamentos.\n\nTARDE — Uma atividade programada\nProcure na agenda uma exposição, sessão de cinema, oficina ou apresentação em ambiente coberto. Confira faixa etária e disponibilidade de ingressos com o organizador.\n\nSE A CHUVA APERTAR\nAdie trilhas e passeios em áreas naturais quando as condições não forem adequadas. Não atravesse áreas alagadas e siga os avisos oficiais locais.\n\nO QUE LEVAR\nGuarda-chuva, calçado adequado, roupa extra para crianças e os contatos dos locais escolhidos.\n\n".$check,'assets/route-rain.svg']
    ];
}
function tourism_today() { return (new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i'); }
function tourism_routes($all=false) { return tourism_db()->query('SELECT * FROM tourism_routes'.($all?'':' WHERE published=1').' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC); }
function tourism_events($filters=[], $all=false) {
    $sql='SELECT * FROM tourism_events'; $where=[]; $args=[];
    if(!$all) { $where[]='published=1'; $where[]='ends>=?'; $args[]=tourism_today(); }
    foreach(['from'=>'ends>=?','to'=>'starts<=?','category'=>'category=?'] as $key=>$condition) if(!empty($filters[$key])) { $where[]=$condition; $args[]=$filters[$key]; }
    $stmt=tourism_db()->prepare($sql.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY starts,id'); $stmt->execute($args); return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function tourism_save($area,$id,$v) {
    $title=trim((string)($v['title'] ?? '')); $body=trim((string)($v['body'] ?? '')); $image=cms_asset($v['image'] ?? ''); $published=empty($v['published'])?0:1;
    if(!$title || strlen($title)>300 || !$body) throw new InvalidArgumentException('Preencha título e conteúdo.');
    if($area==='events') {
        $dates=[]; foreach(['starts','ends'] as $key) { $raw=str_replace('T',' ',(string)($v[$key] ?? '')); $d=DateTimeImmutable::createFromFormat('!Y-m-d H:i',$raw,new DateTimeZone('America/Sao_Paulo')); if(!$d || $d->format('Y-m-d H:i')!==$raw) throw new InvalidArgumentException('Confira as datas e os horários.'); $dates[$key]=$raw; }
        $location=trim((string)($v['location'] ?? '')); if(!$location || $dates['ends']<$dates['starts']) throw new InvalidArgumentException('Informe o local e um término posterior ao início.');
        $args=[$title,(cms_category($v['category'] ?? 'cultura') ?: 'cultura'),$dates['starts'],$dates['ends'],$location,trim((string)($v['price'] ?? '')),cms_asset($v['link'] ?? ''),$image,$body,$published];
        $fields=['title','category','starts','ends','location','price','link','image','body','published']; $table='tourism_events';
    } elseif($area==='routes') {
        $slug=cms_category($v['slug'] ?? $title); if(!$slug || strlen($slug)>100) throw new InvalidArgumentException('Confira o endereço curto.');
        $args=[$slug,$title,trim((string)($v['duration'] ?? '')),trim((string)($v['summary'] ?? '')),$body,$image,$published]; $fields=['slug','title','duration','summary','body','image','published']; $table='tourism_routes';
    } else throw new InvalidArgumentException('Área inválida.');
    if($id) { $stmt=tourism_db()->prepare('SELECT id FROM '.$table.' WHERE id=?'); $stmt->execute([$id]); if(!$stmt->fetchColumn()) throw new InvalidArgumentException('Cadastro não encontrado.'); $args[]=$id; tourism_db()->prepare('UPDATE '.$table.' SET '.implode(',',array_map(fn($f)=>$f.'=?',$fields)).' WHERE id=?')->execute($args); }
    else tourism_db()->prepare('INSERT INTO '.$table.'('.implode(',',$fields).') VALUES('.implode(',',array_fill(0,count($fields),'?')).')')->execute($args);
}
function tourism_match($text,$query) { return str_contains(cms_category($text),cms_category($query)); }
function tourism_search($q,$type='all') {
    if(trim($q)==='' || cms_category($q)==='') return []; $result=[];
    if(in_array($type,['all','news'],true)) foreach(cms_news_entries() as $r) if(tourism_match($r['title'].' '.$r['body'],$q)) $result[]=['type'=>'Notícia','title'=>$r['title'],'href'=>$r['href'],'description'=>cms_category_label($r['category'])];
    if(in_array($type,['all','companies'],true)) foreach(cms_companies()['rows'] as $r) if(tourism_match(($r['nome'] ?? '').' '.($r['local'] ?? '').' '.($r['bairro'] ?? '').' '.($r['descritivo'] ?? ''),$q)) $result[]=['type'=>'Empresa','title'=>$r['nome'],'href'=>'indicador.php?id='.rawurlencode($r['url']),'description'=>$r['local'] ?? ''];
    if(in_array($type,['all','events'],true)) foreach(tourism_events() as $r) if(tourism_match($r['title'].' '.$r['location'].' '.$r['body'],$q)) $result[]=['type'=>'Evento','title'=>$r['title'],'href'=>'evento.php?id='.$r['id'],'description'=>$r['location']];
    if(in_array($type,['all','routes'],true)) foreach(tourism_routes() as $r) if(tourism_match($r['title'].' '.$r['body'],$q)) $result[]=['type'=>'Roteiro','title'=>$r['title'],'href'=>'roteiro.php?slug='.rawurlencode($r['slug']),'description'=>$r['summary']];
    return $result;
}
function tourism_card($r,$event=false) {
    $image=$r['image'] ?: 'assets/placeholder.svg'; $href=$event?'evento.php?id='.$r['id']:'roteiro.php?slug='.rawurlencode($r['slug']);
    ?><article class="discovery-card"><a href="<?=rss_escape($href)?>"><img src="<?=rss_escape($image)?>" alt="" loading="lazy" width="640" height="400"><div><span class="eyebrow"><?=rss_escape($event?tourism_date($r['starts'],'d/m/Y'):$r['duration'])?></span><h3><?=rss_escape($r['title'])?></h3><p><?=rss_escape($event?$r['location']:$r['summary'])?></p><span class="text-link"><?=$event?'Ver evento':'Explorar roteiro'?> →</span></div></a></article><?php
}

function tourism_date($value,$format='d/m/Y H:i') { return (new DateTimeImmutable($value,new DateTimeZone('America/Sao_Paulo')))->format($format); }
function tourism_report($period,$month) {
    $zone=new DateTimeZone('America/Sao_Paulo'); $start=new DateTimeImmutable($month.'-01',$zone);
    if($period==='months') { $start=$start->setDate((int)$start->format('Y'),1,1); $end=$start->modify('+1 year'); } else $end=$start->modify('+1 month');
    $stmt=cms_db()->prepare("SELECT kind,item,SUM(total) AS total FROM daily_metrics WHERE day>=? AND day<? AND kind IN ('route','event','whatsapp') GROUP BY kind,item"); $stmt->execute([$start->format('Y-m-d'),$end->format('Y-m-d')]); $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $totals=['route'=>0,'event'=>0,'whatsapp'=>0]; $lookup=[];
    foreach(tourism_routes(true) as $r) $lookup['route:'.$r['id']]=['title'=>$r['title'],'href'=>'roteiro.php?slug='.rawurlencode($r['slug'])];
    foreach(tourism_events([],true) as $r) $lookup['event:'.$r['id']]=['title'=>$r['title'],'href'=>'evento.php?id='.$r['id']];
    $ranking=[]; foreach($rows as $r) { $totals[$r['kind']]+=(int)$r['total']; if(isset($lookup[$r['kind'].':'.$r['item']])) $ranking[]=$lookup[$r['kind'].':'.$r['item']]+['visits'=>(int)$r['total']]; }
    usort($ranking,fn($a,$b)=>$b['visits']<=>$a['visits']); return ['totals'=>$totals,'ranking'=>array_slice($ranking,0,5)];
}
