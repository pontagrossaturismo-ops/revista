"""PHP_BIN=/caminho/php python3 tests/setup-test.py"""
import http.cookiejar, json, os, re, socket, subprocess, tempfile, time, urllib.error, urllib.parse, urllib.request
from pathlib import Path
php=os.environ.get('PHP_BIN','php'); root=Path(__file__).resolve().parents[1]
count=0
with tempfile.TemporaryDirectory(prefix='revista-setup-') as tmp:
    private=Path(tmp)/'config'; env=os.environ.copy(); env['REVISTA_CONFIG_DIR']=str(private)
    for key in ['RSS_ADMIN_PASSWORD_HASH','RSS_DATA_DIR','DB_HOST','DB_USER','DB_NAME','DB_PASSWORD']: env.pop(key,None)
    with socket.socket() as sock: sock.bind(('127.0.0.1',0)); port=sock.getsockname()[1]
    base=f'http://127.0.0.1:{port}/'
    client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def request(path='instalar.php',post=None):
        try:
            with client.open(base+path,data=None if post is None else urllib.parse.urlencode(post).encode(),timeout=10) as r: return r.status,r.read().decode()
        except urllib.error.HTTPError as e: return e.code,e.read().decode()
    def check(ok,message):
        global count
        if not ok: raise AssertionError(message)
        count+=1
    with tempfile.TemporaryFile(mode='w+') as log:
        server=subprocess.Popen([php,'-d','session.save_path='+tmp,'-S',f'127.0.0.1:{port}','-t',str(root)],env=env,stdout=log,stderr=log)
        try:
            for _ in range(50):
                try: status,page=request(); break
                except urllib.error.URLError: time.sleep(.1)
            check(status==200 and 'configurar-token.php' in page,'Primeira instalação protegida')
            check(request(post={'action':'save','csrf':'bad'})[0]==403,'CSRF protege instalação')
            result=subprocess.run([php,'configurar-token.php'],cwd=root,env=env,capture_output=True,text=True,check=True)
            token=re.search(r'\b[a-f0-9]{48}\b',result.stdout)[0]
            check((private/'setup-token.hash').exists(),'Código criado fora da raiz pública')
            check(subprocess.run([php,'configurar-token.php'],cwd=root,env=env,capture_output=True).returncode==1,'Não sobrescrever código existente')
            status,page=request(); csrf=re.search('name="csrf" value="([^"]+)"',page)[1]
            check('Código ou senha inválidos' in request(post={'csrf':csrf,'activation':'wrong'})[1],'Código incorreto bloqueado')
            status,page=request(post={'csrf':csrf,'activation':token})
            check('revistapg.mysql.uhserver.com' in page and 'name="db_password"' in page,'Defaults do banco e formulário')
            form={'csrf':csrf,'action':'save','site_title':'Meu Turismo','site_description':'Descrição de teste','site_url':'https://example.com','interval':'15','admin_password':'test-only-pass-123','admin_confirm':'test-only-pass-123','db_port':'3306'}
            invalid=dict(form,admin_confirm='diferente'); check('confirmação igual' in request(post=invalid)[1],'Confirmar senha')
            check(not (private/'config.json').exists(),'Falha não cria configuração')
            invalid=dict(form,site_url='javascript:alert(1)'); check('Configuração salva' not in request(post=invalid)[1],'URL do site validada')
            invalid=dict(form,use_db='1',db_host='127.0.0.1',db_user='invalid',db_name='invalid',db_password='test',db_port='1')
            check('Não foi possível validar o banco' in request(post=invalid)[1],'Validar conexão antes de salvar')
            check(not (private/'config.json').exists(),'Conexão inválida não salva')
            invalid=dict(form,use_conv='1',conv_host='127.0.0.1',conv_user='invalid',conv_name='invalid',conv_password='test',conv_port='1')
            check('Não foi possível validar o banco/tabela lojas' in request(post=invalid)[1],'Banco opcional validado')
            check(not (private/'config.json').exists(),'Banco opcional inválido não salva')
            status,page=request(post=form); check(status==200 and 'Configuração salva' in page,'Salvar configuração RSS sem MySQL')
            config=json.loads((private/'config.json').read_text())
            check(config['INSTALLED'] and config['SITE_TITLE']=='Meu Turismo','Configuração persistida')
            check(config['RSS_ADMIN_PASSWORD_HASH']!='test-only-pass-123','Hash de senha')
            check((private/'config.json').stat().st_mode & 0o777==0o600,'Permissões privadas')
            check(not (private/'setup-token.hash').exists(),'Código inicial consumido')
            status,page=request('index.php'); check('Meu Turismo' in page,'Site usa configuração')
            status,page=request('rss-admin.php'); check('name="password"' in page,'Painel habilitado')
            admin_csrf=re.search('name="csrf" value="([^"]+)"',page)[1]
            check('Fontes cadastradas' in request('rss-admin.php',{'csrf':admin_csrf,'password':'test-only-pass-123'})[1],'Senha configurada funciona')
            env_import=env.copy(); env_import['RSS_DATA_DIR']=str(private/'rss')
            first=subprocess.run([php,'rss-importar.php','--force'],cwd=root,env=env_import,capture_output=True,text=True)
            second=subprocess.run([php,'rss-importar.php'],cwd=root,env=env_import,capture_output=True,text=True)
            check(first.returncode==0 and 'Aguardando intervalo' in second.stdout,'Cron respeita intervalo')
            client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
            status,page=request(); check('Senha do painel' in page and 'name="db_host"' not in page,'Configuração exige login após instalação')
            csrf=re.search('name="csrf" value="([^"]+)"',page)[1]
            check('Código ou senha inválidos' in request(post={'csrf':csrf,'activation':token})[1],'Código antigo não reabre instalador')
            status,page=request(post={'csrf':csrf,'activation':'test-only-pass-123'})
            check('name="db_host"' in page,'Senha atual permite reconfigurar')
            server.terminate(); server.wait(timeout=5); log.seek(0); logs=log.read()
            check('Fatal error' not in logs and 'PHP Warning' not in logs,'Sem erros nas rotas testadas')
            print(f'{count} verificações do instalador passaram.')
        finally:
            if server.poll() is None: server.terminate(); server.wait(timeout=5)
