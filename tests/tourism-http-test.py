"""PHP_BIN=php python3 tests/tourism-http-test.py"""
import os, tempfile, subprocess, socket, urllib.request, urllib.parse, urllib.error, http.cookiejar, time, re
from pathlib import Path
root=Path(__file__).resolve().parents[1]; php=os.environ.get('PHP_BIN','php'); count=0
with tempfile.TemporaryDirectory(prefix='revista-tourism-http-') as tmp:
    env=os.environ.copy(); env.update(RSS_DATA_DIR=tmp+'/data',REVISTA_CONFIG_DIR=tmp+'/config')
    env['RSS_ADMIN_PASSWORD_HASH']=subprocess.check_output([php,'-r',"echo password_hash('test-tourism-password',PASSWORD_DEFAULT);"],text=True)
    with socket.socket() as sock: sock.bind(('127.0.0.1',0)); port=sock.getsockname()[1]
    base=f'http://127.0.0.1:{port}/'; admin=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar())); guest=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
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
        server=subprocess.Popen([php,'-d','display_errors=1','-d','error_reporting=-1','-d','session.save_path='+tmp,'-S',f'127.0.0.1:{port}','-t',str(root)],env=env,stdout=log,stderr=log)
        try:
            for _ in range(50):
                try: status,html,_=req(admin,'admin.php'); break
                except urllib.error.URLError: time.sleep(.1)
            csrf=re.search('name="csrf" value="([^"]+)"',html)[1]; req(admin,'admin.php',{'csrf':csrf,'password':'test-tourism-password'})
            for area in ['events','routes']:
                html=req(admin,'admin.php?area='+area)[1]
                check('admin-sidebar' in html and 'class="panel-card cms-form"' not in html,'Novo menu com formulário recolhido: '+area)
                check('class="panel-card cms-form"' in req(admin,'admin.php?area='+area+'&new=1')[1],'Adicionar abre formulário: '+area)
                check(req(admin,'admin.php?area='+area,{'csrf':'bad','action':'save'})[0]==403,'CSRF: '+area)
            event=dict(csrf=csrf,action='save',title='Festival de teste',category='cultura',starts='2099-12-01T10:00',ends='2099-12-01T18:00',location='Centro de Ponta Grossa',price='Consultar organizador',link='https://example.com/ingressos',body='Evento cadastrado pelo proprietário.',published=1)
            check('Cadastro salvo.' in req(admin,'admin.php?area=events',event)[1],'Cadastrar evento')
            check('Festival de teste' in req(guest,'agenda.php')[1],'Evento aparece na agenda')
            check('Festival de teste' not in req(guest,'agenda.php?from=2100-01-01')[1],'Filtro por datas')
            detail=req(guest,'evento.php?id=1')[1]
            check('01/12/2099 10:00' in detail and 'https://example.com/ingressos' in detail and 'share-tools' in detail,'Evento com datas, ingresso e compartilhamento')
            check('Festival de teste' in req(guest,'busca.php?q=festival&type=events')[1],'Busca unificada encontra evento')
            route=req(guest,'roteiro.php?slug=um-dia-em-ponta-grossa')[1]
            check('Vila Velha' in route and 'share-tools' in route,'Roteiro prático com conteúdo e compartilhamento')
            check(req(guest,'roteiro.php?slug=inexistente')[0]==404,'Roteiro inexistente retorna 404')
            check('O que fazer com crianças' in req(guest,'busca.php?q=criancas&type=routes')[1],'Busca de roteiro sem acento')
            check('Cadastro salvo.' in req(admin,'admin.php?area=routes',dict(csrf=csrf,action='save',id=1,slug='um-dia-em-ponta-grossa',title='Roteiro revisado',body='Conteúdo revisado pelo proprietário.',duration='Um dia',summary='Resumo',published=1))[1],'Editar roteiro inicial')
            check('Roteiro revisado' in req(guest,'roteiro.php?slug=um-dia-em-ponta-grossa')[1],'Edição é exibida')
            company=dict(csrf=csrf,action='save',area='companies',nome='Café do Centro',url='cafe-centro',categoria='gastronomia',bairro='Centro',local='Rua de teste',horarios='Terça a sábado',faixa_preco='Econômico',acessibilidade='Consulte a equipe',familias='Atende famílias',whatsapp='42999991234')
            check('Alterações salvas.' in req(admin,'admin.php',company)[1],'Cadastrar novos dados da empresa')
            company['nome']='Café de Uvaranas'; company['url']='cafe-uvaranas'; company['bairro']='Uvaranas'; company['faixa_preco']='Médio'; req(admin,'admin.php',company)
            filtered=req(guest,'indicador-gastronomia.php?bairro=Centro&preco=Econ%C3%B4mico')[1]
            check('Café do Centro' in filtered and 'Café de Uvaranas' not in filtered,'Filtrar guia por bairro e preço')
            profile=req(guest,'indicador.php?id=cafe-centro')[1]
            check('Terça a sábado' in profile and 'Consulte a equipe' in profile and 'google.com/maps/search' in profile,'Empresa exibe horários, serviços e mapa')
            req(guest,'metricas.php',{'page':'roteiro.php','slug':'um-dia-em-ponta-grossa'}); req(guest,'metricas.php',{'page':'evento.php','id':1})
            report=req(admin,'admin.php')[1]
            check('Roteiro revisado' in report and 'Acessos a roteiros' in report and 'Acessos a eventos' in report,'Dashboard acompanha roteiros e eventos')
            for path in ['indicador-hospedagem.php','indicador-compras.php','indicador-gastronomia.php','busca.php','agenda.php','roteiro.php','empresa-whatsapp.php','indicador-hospedagem.php?q[]=teste&bairro[]=teste&preco[]=teste']:
                response=req(guest,path)[1]
                check('Warning:' not in response and 'Deprecated:' not in response and 'Fatal error:' not in response,'Parâmetros opcionais ausentes ou inválidos sem avisos: '+path)
            settings=dict(csrf=csrf,area='settings',action='save_presentation',edition_label='Edição 102',edition_url='https://example.com/embed/revista?edition=102&view=book')
            check('Logo e edição atualizadas.' in req(admin,'admin.php',settings)[1],'Salvar edição atual pelo painel')
            edition_home=req(guest,'index.php')[1]
            check('src="https://example.com/embed/revista?edition=102&amp;view=book"' in edition_home and 'Edição 102' in edition_home,'Exibir iframe e nome atualizados na inicial')
            check('Edição 102' in req(admin,'admin.php?area=settings')[1],'Persistir edição no formulário')
            check(req(admin,'admin.php',dict(settings,csrf='bad'))[0]==403,'Proteger edição contra CSRF')
            check('Logo e edição atualizadas.' not in req(guest,'admin.php',settings)[1],'Impedir edição anônima')
            for unsafe in ['javascript:alert(1)','data:text/html,teste','https://user:secret@example.com/embed','<iframe src="javascript:alert(1)"></iframe>']:
                check('Informe um link HTTP/HTTPS válido' in req(admin,'admin.php',dict(settings,edition_url=unsafe))[1],'Recusar iframe inseguro: '+unsafe)
            check('edition=102&amp;view=book' in req(guest,'index.php')[1],'Preservar edição após validação rejeitada')
            calameo='<iframe src="//v.calameo.com/?bkcode=006701491b2a879071753&mode=mini" width="100%" height="600px" frameborder="0" scrolling="no" allowtransparency allowfullscreen style="margin:0 auto;">'
            check('Logo e edição atualizadas.' in req(admin,'admin.php',dict(settings,edition_url=calameo))[1],'Aceitar iframe Calaméo original sem fechamento')
            calameo_home=req(guest,'index.php')[1]
            check('src="https://v.calameo.com/?bkcode=006701491b2a879071753&amp;mode=mini"' in calameo_home,'Normalizar Calaméo para HTTPS e manter parâmetros')
            check('onload=' not in req(guest,'index.php')[1],'Nunca reproduzir atributos arbitrários do iframe')
            req(admin,'admin.php',dict(settings,edition_url='<iframe src="//v.calameo.com/?bkcode=123&amp;mode=mini" onload="alert(1)"></iframe>'))
            check('onload="alert(1)"' not in req(guest,'index.php')[1] and 'bkcode=123&amp;mode=mini' in req(guest,'index.php')[1],'Extrair apenas src e decodificar entidades HTML')
            req(admin,'admin.php',dict(settings,edition_url='',edition_label=''))
            home=req(guest,'index.php')[1]
            check('Roteiros para o seu ritmo' in home and 'Festival de teste' in home and 'edition-empty' in home,'Inicial integra roteiros, agenda e alternativa à edição ausente')
            check('<head>' in home and home.count('<head>')==1 and 'assets/brand.svg' in home,'Cabeçalho válido e marca alternativa')
            req(admin,'admin.php?area=events',dict(event,id=1,starts='2020-01-01T10:00',ends='2020-01-01T18:00'))
            check('Festival de teste' not in req(guest,'agenda.php')[1] and 'já foi encerrado' in req(guest,'evento.php?id=1')[1],'Encerrado sai da agenda mas conserva aviso no link direto')
            req(admin,'admin.php?area=routes',{'csrf':csrf,'action':'delete','id':1})
            check(req(guest,'roteiro.php?slug=um-dia-em-ponta-grossa')[0]==404,'Excluir roteiro')
            check('Warning:' not in home and 'Fatal error' not in home,'Inicial sem avisos PHP')
            print(f'{count} verificações HTTP de turismo passaram.')
        finally: server.terminate(); server.wait(timeout=5)
