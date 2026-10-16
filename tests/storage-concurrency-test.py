"""PHP_BIN=php python3 tests/storage-concurrency-test.py"""
import os, subprocess, sqlite3, tempfile, socket, time, urllib.request, urllib.parse, urllib.error, http.cookiejar, re
from pathlib import Path
root=Path(__file__).resolve().parents[1]; php=os.environ.get('PHP_BIN','php'); count=0
with tempfile.TemporaryDirectory(prefix='revista-storage-') as tmp:
    env=os.environ.copy(); env.update(RSS_DATA_DIR=tmp+'/data',REVISTA_CONFIG_DIR=tmp+'/config')
    for key in list(env):
        if key.startswith(('DB_','DB_CONV_')): env.pop(key,None)
    env['RSS_ADMIN_PASSWORD_HASH']=subprocess.check_output([php,'-r',"echo password_hash('test-storage-password',PASSWORD_DEFAULT);"],text=True)
    def check(ok,label):
        global count
        if not ok: raise AssertionError(label)
        count+=1
    # Inicializações de PHP realmente simultâneas, inclusive migração e seções padrão.
    code="require 'cms/metrics.php'; cms_metrics_db(); echo 'ready';"
    children=[subprocess.Popen([php,'-r',code],cwd=root,env=env,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True) for _ in range(6)]
    for child in children:
        out,err=child.communicate(timeout=20); check(child.returncode==0 and out=='ready' and 'Warning' not in err,'Inicialização concorrente sem conflitos de estrutura')
    dbpath=tmp+'/data/rss.sqlite'
    db=sqlite3.connect(dbpath,timeout=2)
    check(db.execute('PRAGMA journal_mode').fetchone()[0]=='wal','WAL ativo')
    check(db.execute('PRAGMA user_version').fetchone()[0]==1,'Versão RSS persistida')
    check(db.execute("SELECT count(*) FROM cms_sections").fetchone()[0]==5,'Seções sem duplicação')
    check(db.execute("SELECT value FROM cms_meta WHERE name='schema_version'").fetchone()[0]=='2','Versão CMS persistida')
    subprocess.run([php,'-r',"require 'cms/core.php'; cms_save_news(0,0,['title'=>'Preservada','category'=>'esporte','image'=>'foto.jpg','body'=>'Conteúdo']);"],cwd=root,env=env,check=True)
    readonly="require 'cms/metrics.php'; rss_db()->exec('PRAGMA query_only=ON'); cms_db(); rss_status(); cms_news_entries(); cms_dashboard('days','2026-10'); echo 'read-only';"
    out=subprocess.check_output([php,'-r',readonly],cwd=root,env=env,text=True)
    check(out=='read-only','Consultas do painel não tentam gravar/preparar novamente')
    with socket.socket() as sock: sock.bind(('127.0.0.1',0)); port=sock.getsockname()[1]
    base=f'http://127.0.0.1:{port}/'; client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def req(path,data=None):
        request=urllib.request.Request(base+path,data=urllib.parse.urlencode(data).encode() if data is not None else None)
        try:
            with client.open(request,timeout=16) as r: return r.status,r.read().decode(),r.headers
        except urllib.error.HTTPError as e: return e.code,e.read().decode(),e.headers
    with tempfile.TemporaryFile() as log:
        server=subprocess.Popen([php,'-d','session.save_path='+tmp,'-S',f'127.0.0.1:{port}','-t',str(root)],env=env,stdout=log,stderr=log)
        try:
            for _ in range(50):
                try: _,html,_=req('admin.php'); break
                except urllib.error.URLError: time.sleep(.1)
            csrf=re.search('name="csrf" value="([^"]+)"',html)[1]; req('admin.php',{'csrf':csrf,'password':'test-storage-password'})
            db.execute('BEGIN IMMEDIATE'); db.execute("UPDATE cms_sections SET title='Edição em andamento' WHERE id=1")
            started=time.monotonic()
            for path in ['rss-admin.php','admin.php','admin.php?area=news']:
                status,html,_=req(path); check(status==200 and 'Fatal error' not in html and 'Warning:' not in html,'Painel disponível durante gravação: '+path)
            check(time.monotonic()-started<3,'Leitura não espera o término da gravação')
            db.rollback()
            # Simula atualização de estrutura enquanto outro processo mantém a escrita.
            db.execute('PRAGMA user_version=0'); db.execute('BEGIN IMMEDIATE'); db.execute("UPDATE cms_sections SET title='Não confirmar' WHERE id=1")
            status,html,headers=req('rss-admin.php')
            check(status==503 and headers.get('Retry-After')=='5','Bloqueio persistente retorna 503 recuperável')
            check('temporariamente ocupado' in html and 'Stack trace' not in html and 'PDOException' not in html and 'Fatal error' not in html,'Não expor erro fatal e caminhos internos')
            db.rollback()
            status,html,_=req('rss-admin.php'); check(status==200 and 'Fontes RSS' in html,'Recuperar acesso após liberar o bloqueio')
            check(db.execute('SELECT title FROM cms_news').fetchone()[0]=='Preservada','Preservar notícias existentes')
            print(f'{count} verificações de concorrência SQLite passaram.')
        finally:
            db.rollback(); db.close(); server.terminate(); server.wait(timeout=5)
