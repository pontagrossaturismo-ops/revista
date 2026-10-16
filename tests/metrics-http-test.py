"""PHP_BIN=php python3 tests/metrics-http-test.py"""
import os, tempfile, subprocess, socket, urllib.request, urllib.parse, urllib.error, http.cookiejar, time, json, re
from pathlib import Path
root=Path(__file__).resolve().parents[1]; php=os.environ.get('PHP_BIN','php'); count=0
with tempfile.TemporaryDirectory(prefix='revista-metrics-') as tmp:
    env=os.environ.copy(); env.update(RSS_DATA_DIR=tmp,REVISTA_CONFIG_DIR=tmp+'/config')
    env['RSS_ADMIN_PASSWORD_HASH']=subprocess.check_output([php,'-r',"echo password_hash('test-metrics-password',PASSWORD_DEFAULT);"],text=True)
    seed="""require 'cms/metrics.php'; cms_save_news(0,0,['title'=>'Notícia acessada','category'=>'viagem','image'=>'foto.png','body'=>'Texto']); cms_save_company(0,'',['nome'=>'Empresa teste','url'=>'empresa','categoria'=>'compras','whatsapp'=>'42999991234']);"""
    subprocess.run([php,'-r',seed],env=env,cwd=root,check=True)
    with socket.socket() as sock: sock.bind(('127.0.0.1',0)); port=sock.getsockname()[1]
    base=f'http://127.0.0.1:{port}/'; admin=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar())); guest=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    class NoRedirect(urllib.request.HTTPRedirectHandler):
        def redirect_request(self,*args): return None
    redirect_guest=urllib.request.build_opener(NoRedirect,urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def req(client,path,data=None):
        request=urllib.request.Request(base+path,data=urllib.parse.urlencode(data).encode() if data is not None else None)
        try:
            with client.open(request,timeout=8) as response: return response.status,response.read().decode(),response.headers
        except urllib.error.HTTPError as error: return error.code,error.read().decode(),error.headers
    def check(ok,label):
        global count
        if not ok: raise AssertionError(label)
        count+=1
    with tempfile.TemporaryFile() as log:
        server=subprocess.Popen([php,'-d','session.save_path='+tmp,'-S',f'127.0.0.1:{port}','-t',str(root)],env=env,stdout=log,stderr=log)
        try:
            for _ in range(50):
                try: status,html,_=req(admin,'admin.php'); break
                except urllib.error.URLError: time.sleep(.1)
            csrf=re.search('name="csrf" value="([^"]+)"',html)[1]
            check(req(guest,'rss-atualizar.php',{'manual':1,'csrf':csrf})[0]==403,'Atualização manual exige login')
            check(req(guest,'rss-atualizar.php')[0]==405,'Atualização exige POST')
            check(json.loads(req(guest,'rss-atualizar.php',{})[1])['state']=='done','Primeiro acionamento automático')
            check(json.loads(req(guest,'rss-atualizar.php',{})[1])['state']=='waiting','Próximo acionamento aguarda 12h')
            req(admin,'admin.php',{'csrf':csrf,'password':'test-metrics-password'})
            check(req(admin,'rss-atualizar.php',{'manual':1,'csrf':'bad'})[0]==403,'CSRF protege importação manual')
            check(json.loads(req(admin,'rss-atualizar.php',{'manual':1,'csrf':csrf})[1])['state']=='done','Botão manual antecipa intervalo')
            check(req(guest,'metricas.php',{'page':'noticia.php','id':1})[0]==204,'Registrar acesso público')
            req(guest,'metricas.php',{'page':'noticia.php','id':1}); req(admin,'metricas.php',{'page':'noticia.php','id':1})
            dashboard=req(admin,'admin.php')[1]
            check('Warning:' not in dashboard and 'Deprecated:' not in dashboard,'Dashboard sem avisos PHP')
            check('Notícia acessada' in dashboard and '1 visitas' in dashboard,'Ranking registra um acesso e exclui recarga/admin')
            check(req(guest,'metricas.php',{'page':'../admin.php'})[0]==400,'Não contar páginas administrativas')
            check(req(guest,'metricas.php',{'page':'inventada.php'})[0]==400,'Não contar páginas inexistentes')
            status,_,headers=req(redirect_guest,'empresa-whatsapp.php?id=empresa')
            check(status==302 and headers['Location']=='https://wa.me/5542999991234','Redirecionar WhatsApp com número salvo')
            req(redirect_guest,'empresa-whatsapp.php?id=empresa')
            companies=req(admin,'admin.php?area=companies')[1]
            check('1</strong> cliques no WhatsApp' in companies,'Contar clique sem inflar por repetição')
            check(req(guest,'empresa-whatsapp.php?id=nao-existe')[0]==404,'Não aceitar destino de WhatsApp arbitrário')
            check('class="panel-card cms-form"' not in req(admin,'admin.php?area=news')[1],'Lista não exibe cadastro')
            check('class="panel-card cms-form"' in req(admin,'admin.php?area=news&new=1')[1],'Adicionar abre formulário')
            print(f'{count} verificações HTTP de métricas e atualização passaram.')
        finally: server.terminate(); server.wait(timeout=5)
