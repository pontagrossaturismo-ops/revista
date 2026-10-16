"""Executar: PHP_BIN=/caminho/php python3 tests/http-test.py"""
import http.cookiejar, json, os, re, socket, subprocess, tempfile, time, urllib.request, urllib.parse, urllib.error
from pathlib import Path
php=os.environ.get('PHP_BIN','php')
root=Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix='revista-http-') as data:
    env=os.environ.copy(); env['RSS_DATA_DIR']=data; env['REVISTA_CONFIG_DIR']=data+'/config'
    env['RSS_ADMIN_PASSWORD_HASH']=subprocess.check_output([php,'-r',"echo password_hash('test-only-password', PASSWORD_DEFAULT);"],text=True)
    env.pop('DB_HOST',None)
    with socket.socket() as s: s.bind(('127.0.0.1',0)); port=s.getsockname()[1]
    seed="require 'rss/core.php'; $db=rss_db(); $db->exec(\"INSERT INTO sources(name,url,category) VALUES('Fonte Teste','https://example.com/rss','viagem')\"); rss_store($db,1,rss_parse('<rss><channel><item><title>Notícia &amp; teste</title><link>https://example.com/article</link><description>Resumo</description></item></channel></rss>','https://example.com/rss'));"
    seed+=" $pixel=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jX1sAAAAASUVORK5CYII='); $key=rss_cache_image('https://example.com/photo.png',fn($url,$limit)=>[$pixel,$url]); $db->prepare('UPDATE articles SET image=?,image_file=?,body=? WHERE id=1')->execute(['https://example.com/photo.png',$key,\"Corpo completo.\\n\\n<script>unsafe()</script>\"]);"
    subprocess.run([php,'-r',seed],cwd=root,env=env,check=True)
    with tempfile.TemporaryFile(mode='w+') as log:
        server=subprocess.Popen([php,'-d','session.save_path='+data,'-S',f'127.0.0.1:{port}','-t',str(root)],env=env,stdout=log,stderr=log)
        jar=http.cookiejar.CookieJar(); client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
        base=f'http://127.0.0.1:{port}/'
        count=0
        def get(path,post=None):
            global count
            data=None if post is None else urllib.parse.urlencode(post).encode()
            try:
                with client.open(base+path,data=data,timeout=5) as r: return r.status,r.read().decode()
            except urllib.error.HTTPError as e: return e.code,e.read().decode()
        def check(ok,message):
            global count
            if not ok: raise AssertionError(message)
            count+=1
        try:
            for i in range(50):
                try: status,page=get('rss-admin.php'); break
                except urllib.error.URLError: time.sleep(.1)
            check(status==200 and 'Senha' in page,'Login disponível')
            csrf=re.search('name="csrf" value="([^"]+)"',page)[1]
            status,guide=get('indicador-gastronomia.php')
            check(status==503 and 'Guia de Gastronomia' in guide and 'temporariamente indisponível' in guide and 'Fatal error' not in guide,'Guia sem banco configurado não exibe erro fatal')
            status,guide=get('indicador.php?id=restaurante')
            check(status==503 and 'temporariamente indisponível' in guide and 'Stack trace' not in guide,'Perfil sem banco configurado é tratado')
            check(get('indicador.php')[0]==404,'Perfil sem identificador retorna 404')
            config_dir=Path(env['REVISTA_CONFIG_DIR']); config_dir.mkdir(exist_ok=True)
            config_file=config_dir/'config.json'
            config_file.write_text(json.dumps({'DB_CONV_HOST':'127.0.0.1','DB_CONV_USER':'invalid','DB_CONV_NAME':'invalid','DB_CONV_PORT':1}))
            status,guide=get('indicador-gastronomia.php')
            check(status==503 and 'temporariamente indisponível' in guide and 'Fatal error' not in guide,'Falha de conexão do guia é tratada')
            config_file.unlink()
            for route,title in [('indicador-compras.php','Guia de Compras'),('indicador-hospedagem.php','Guia de Hospedagem'),('indicador-hospedage.php','Guia de Hospedagem')]:
                status,guide=get(route)
                check(status==503 and title in guide and 'Fatal error' not in guide,'Rota de guia existente e tratada: '+route)
            check(get('rss-admin.php',{'csrf':'invalid','password':'test-only-password'})[0]==403,'CSRF bloqueado')
            status,page=get('rss-admin.php',{'csrf':csrf,'password':'incorrect'})
            check('Senha inválida' in page and 'Fontes cadastradas' not in page,'Senha incorreta')
            status,page=get('rss-admin.php',{'csrf':csrf,'password':'test-only-password'})
            check(status==200 and 'Fontes cadastradas' in page and 'Fonte Teste' in page,'Login e fontes')
            check('1 notícias armazenadas' in page and '1 visíveis' in page,'Contadores do painel')
            diag=subprocess.run([php,'rss-importar.php','--diagnostico'],cwd=root,env=env,capture_output=True,text=True,check=True).stdout
            check('Notícias armazenadas: 1 | Visíveis: 1' in diag,'Diagnóstico confirma importação')
            storage_line=next(line for line in diag.splitlines() if line.startswith('Armazenamento RSS: '))
            check(storage_line.split(': ',1)[1] in page,'Apache e CLI usam o mesmo SQLite')
            history_seed="require 'rss/core.php'; rss_db()->prepare('INSERT INTO import_runs(source_id,source_url,finished_at,status,error) VALUES(?,?,?,?,?)')->execute([1,'https://example.com/rss','2026-01-01 10:00:00','error','Feed HTTP 403']);"
            subprocess.run([php,'-r',history_seed],cwd=root,env=env,check=True)
            status,page=get('rss-admin.php?source=1')
            check('Feed HTTP 403' in page and 'Link utilizado' in page and '<strong>1</strong><span>falhas de feed' in page,'Histórico e contadores de erro no painel')
            status,page=get('rss-admin.php',{'csrf':csrf,'action':'save','id':'1','name':'Fonte editada','url':'https://example.com/new-feed','category':'lazer'})
            check(status==200 and 'Fonte atualizada' in page and 'https://example.com/new-feed' in page,'Editar link e nome pelo painel')
            check('Notícia &amp; teste' in get('lazer.php')[1] and 'Notícia &amp; teste' not in get('viagem.php')[1],'Edição de categoria move notícia')
            status,page=get('rss-admin.php',{'csrf':csrf,'action':'save','id':'1','name':'Fonte Teste','url':'https://example.com/rss','category':'viagem'})
            check('Fonte atualizada' in page and 'value="https://example.com/rss"' in page,'Campos editáveis preenchidos e fonte preservada')
            status,page=get('rss-admin.php',{'csrf':csrf,'action':'save','name':'Privado','url':'http://127.0.0.1/feed','category':'viagem'})
            check('Não foi possível salvar' in page,'Cadastro privado bloqueado')
            status,page=get('viagem.php'); check(status==200 and 'Notícia &amp; teste' in page,'Categoria viagem')
            status,page=get('lazer.php'); check(status==200 and 'Notícia &amp; teste' not in page,'Separação de categoria')
            check('Nenhuma notícia RSS disponível nesta categoria' in page,'Estado vazio visível')
            for path in ['index.php','noticias.php','example_display.php']:
                status,page=get(path); check(status==200 and 'Notícia &amp; teste' in page,'RSS em '+path)
                if path!='example_display.php': check(('data-section="viagem"' in page if path=='index.php' else 'id="noticias-rss"' in page),'Notícias identificadas em '+path)
            status,page=get('rss-materia.php?id=1')
            check(status==200 and 'Corpo completo.' in page and 'Consultar publicação original' in page,'Leitura interna do conteúdo')
            check('&lt;script&gt;unsafe()&lt;/script&gt;' in page and '<script>unsafe()</script>' not in page,'Conteúdo escapado')
            check('src="rss-imagem.php?id=1"' in page,'Imagem local na matéria')
            with client.open(base+'rss-imagem.php?id=1') as response:
                check(response.status==200 and response.headers['Content-Type']=='image/png' and response.read().startswith(b'\x89PNG'),'Imagem servida como PNG')
            check(get('rss-materia.php?id=99999')[0]==404,'Matéria inexistente')
            check(get('rss-imagem.php?id=99999')[0]==404,'Imagem inexistente')
            status,page=get('feed.php'); check(status==200 and '<rss' in page and 'Notícia &amp; teste' in page,'Feed HTTP')
            check(get('feed.php?categoria=invalida')[0]==400,'Categoria inválida')
            check(get('rss-importar.php')[0]==403,'Importação somente CLI')
            check(get('proxy.php?url=https://example.com')[0]==410,'Proxy aposentado')
            hide="require 'rss/core.php'; rss_db()->exec(\"UPDATE articles SET image_file='' WHERE id=1\");"
            subprocess.run([php,'-r',hide],cwd=root,env=env,check=True)
            check('Notícia &amp; teste' not in get('noticias.php')[1] and 'Notícia &amp; teste' not in get('feed.php')[1],'Sem imagem local não aparece em listas ou XML')
            check(get('rss-materia.php?id=1')[0]==404,'Sem imagem local não abre por link direto')
            restore="require 'rss/core.php'; $key=hash('sha256','https://example.com/photo.png'); rss_db()->prepare('UPDATE articles SET image_file=? WHERE id=1')->execute([$key]);"
            subprocess.run([php,'-r',restore],cwd=root,env=env,check=True)
            status,page=get('rss-admin.php',{'csrf':csrf,'action':'toggle','id':'1'})
            check('Pausada' in page,'Pausar fonte')
            check('Notícia &amp; teste' not in get('noticias.php')[1],'Ocultar pausadas')
            check(get('rss-materia.php?id=1')[0]==404 and get('rss-imagem.php?id=1')[0]==404,'Fonte pausada oculta matéria e imagem')
            status,page=get('rss-admin.php',{'csrf':csrf,'action':'save','name':'OFEC teste','url':'https://operarioferroviario.com.br/noticias','category':'esporte','type':'ofec'})
            check(status==200 and 'Página OFEC / API pública' in page and 'value="ofec" selected' in page,'Cadastrar tipo OFEC pelo painel')
            bad=get('rss-admin.php',{'csrf':csrf,'action':'save','name':'Falsa OFEC','url':'https://example.com/noticias','category':'esporte','type':'ofec'})[1]
            check('Não foi possível salvar' in bad,'Tipo OFEC rejeita URL de outro domínio')
            credit_seed="require 'rss/core.php'; $source=rss_db()->query(\"SELECT id FROM sources WHERE name='OFEC teste'\")->fetchColumn(); $item=rss_ofec_article(['id'=>1556475,'title'=>'OFEC integrada','description'=>'Resumo','articleBody'=>'<p>Conteúdo OFEC.</p>','mainImage'=>'https://example.com/photo.png','photographer'=>'Fotógrafo OFEC','createdAt'=>'2026-10-08'],1556475); $item['image_file']=hash('sha256','https://example.com/photo.png'); rss_store(rss_db(),$source,[$item]); echo rss_db()->query(\"SELECT id FROM articles WHERE title='OFEC integrada'\")->fetchColumn();"
            article_id=subprocess.check_output([php,'-r',credit_seed],cwd=root,env=env,text=True).strip()
            status,page=get('rss-materia.php?id='+article_id)
            check(status==200 and 'Fotógrafo OFEC' in page and 'Conteúdo OFEC.' in page and 'https://operarioferroviario.com.br/noticias/1556475' in page,'Matéria OFEC interna com crédito e link original')
            check('OFEC integrada' in get('esporte.php')[1],'OFEC na categoria escolhida')
            source_id=subprocess.check_output([php,'-r',"require 'rss/core.php'; echo rss_db()->query(\"SELECT id FROM sources WHERE name='OFEC teste'\")->fetchColumn();"],cwd=root,env=env,text=True).strip()
            get('rss-admin.php',{'csrf':csrf,'action':'delete','id':source_id})
            status,page=get('rss-admin.php',{'csrf':csrf,'action':'logout'})
            check('Fontes cadastradas' not in page and 'Senha' in page,'Logout')
            pagination_seed="require 'rss/core.php'; $db=rss_db(); $db->exec('UPDATE sources SET active=1'); $rows=[]; for($i=0;$i<95;$i++) $rows[]=['identity'=>'page-'.$i,'title'=>'Paginação '.$i,'url'=>'https://example.com/page/'.$i,'summary'=>'Resumo','image'=>'https://example.com/photo.png','image_file'=>hash('sha256','https://example.com/photo.png'),'published_at'=>gmdate('Y-m-d H:i:s',strtotime('2025-01-01 UTC')+$i)]; rss_store($db,1,$rows); rss_prune($db);"
            subprocess.run([php,'-r',pagination_seed],cwd=root,env=env,check=True)
            status,page=get('viagem.php')
            check(status==200 and page.count('class="noticia-card compact-news"')==15 and 'Página 1 de 6' in page,'Primeira página com quinze cards')
            check('>Todas</a>' not in page and 'aria-current="page"' in page and 'news-pagination.css' in page,'Filtros e paginação acessível')
            status,last=get('viagem.php?pagina=6')
            check(status==200 and last.count('class="noticia-card compact-news"')==15 and 'Página 6 de 6' in last and 'pagina=7' not in last,'Última página limitada a seis')
            check('Página 6 de 6' in get('viagem.php?pagina=999')[1],'Endereço além do limite é normalizado')
            check('Paginação 94' in page and 'Paginação 6<' in last and 'Paginação 0<' not in last,'Retenção remove os itens mais antigos')
            server.terminate(); server.wait(timeout=5); log.seek(0); logs=log.read()
            check('Fatal error' not in logs and 'PHP Warning' not in logs,'Sem erros PHP nas rotas verificadas')
            print(f'{count} verificações HTTP passaram.')
        finally:
            if server.poll() is None: server.terminate(); server.wait(timeout=5)
