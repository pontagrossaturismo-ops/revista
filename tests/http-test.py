"""Executar: PHP_BIN=/caminho/php python3 tests/http-test.py"""
import http.cookiejar, os, re, socket, subprocess, tempfile, time, urllib.request, urllib.parse, urllib.error
from pathlib import Path
php=os.environ.get('PHP_BIN','php')
root=Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix='revista-http-') as data:
    env=os.environ.copy(); env['RSS_DATA_DIR']=data; env['REVISTA_CONFIG_DIR']=data+'/config'
    env['RSS_ADMIN_PASSWORD_HASH']=subprocess.check_output([php,'-r',"echo password_hash('test-only-password', PASSWORD_DEFAULT);"],text=True)
    env.pop('DB_HOST',None)
    with socket.socket() as s: s.bind(('127.0.0.1',0)); port=s.getsockname()[1]
    seed="require 'rss/core.php'; $db=rss_db(); $db->exec(\"INSERT INTO sources(name,url,category) VALUES('Fonte Teste','https://example.com/rss','viagem')\"); rss_store($db,1,rss_parse('<rss><channel><item><title>Notícia &amp; teste</title><link>https://example.com/article</link><description>Resumo</description></item></channel></rss>','https://example.com/rss'));"
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
            check(get('rss-admin.php',{'csrf':'invalid','password':'test-only-password'})[0]==403,'CSRF bloqueado')
            status,page=get('rss-admin.php',{'csrf':csrf,'password':'incorrect'})
            check('Senha inválida' in page and 'Fontes cadastradas' not in page,'Senha incorreta')
            status,page=get('rss-admin.php',{'csrf':csrf,'password':'test-only-password'})
            check(status==200 and 'Fontes cadastradas' in page and 'Fonte Teste' in page,'Login e fontes')
            status,page=get('rss-admin.php',{'csrf':csrf,'action':'save','name':'Privado','url':'http://127.0.0.1/feed','category':'viagem'})
            check('Não foi possível salvar' in page,'Cadastro privado bloqueado')
            status,page=get('viagem.php'); check(status==200 and 'Notícia &amp; teste' in page and 'Fonte Teste' in page,'Categoria viagem')
            status,page=get('lazer.php'); check(status==200 and 'Notícia &amp; teste' not in page,'Separação de categoria')
            for path in ['index.php','noticias.php','example_display.php']:
                status,page=get(path); check(status==200 and 'Notícia &amp; teste' in page,'RSS em '+path)
            status,page=get('feed.php'); check(status==200 and '<rss' in page and 'Notícia &amp; teste' in page,'Feed HTTP')
            check(get('feed.php?categoria=invalida')[0]==400,'Categoria inválida')
            check(get('rss-importar.php')[0]==403,'Importação somente CLI')
            check(get('proxy.php?url=https://example.com')[0]==410,'Proxy aposentado')
            status,page=get('rss-admin.php',{'csrf':csrf,'action':'toggle','id':'1'})
            check('Pausada' in page,'Pausar fonte')
            check('Notícia &amp; teste' not in get('noticias.php')[1],'Ocultar pausadas')
            status,page=get('rss-admin.php',{'csrf':csrf,'action':'logout'})
            check('Fontes cadastradas' not in page and 'Senha' in page,'Logout')
            server.terminate(); server.wait(timeout=5); log.seek(0); logs=log.read()
            check('Fatal error' not in logs and 'PHP Warning' not in logs,'Sem erros PHP nas rotas verificadas')
            print(f'{count} verificações HTTP passaram.')
        finally:
            if server.poll() is None: server.terminate(); server.wait(timeout=5)
