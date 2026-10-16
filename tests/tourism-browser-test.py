"""PHP_BIN=php python3 tests/tourism-browser-test.py; requer Playwright/Chromium."""
import os, re, socket, subprocess, tempfile, time, urllib.request, urllib.error
from pathlib import Path
from playwright.sync_api import sync_playwright
php=os.environ.get('PHP_BIN','php'); root=Path(__file__).resolve().parents[1]; count=0
with tempfile.TemporaryDirectory(prefix='revista-tourism-browser-') as tmp:
    env=os.environ.copy(); env.update(RSS_DATA_DIR=tmp+'/data',REVISTA_CONFIG_DIR=tmp+'/config')
    env['RSS_ADMIN_PASSWORD_HASH']=subprocess.check_output([php,'-r',"echo password_hash('test-tourism-browser-password',PASSWORD_DEFAULT);"],text=True)
    seed="""require 'cms/tourism.php';
    cms_save_news(0,0,['title'=>'Caminhos para explorar os Campos Gerais','category'=>'viagem','image'=>'assets/route-nature.svg','body'=>'Uma notícia de teste para validar o compartilhamento.']);
    foreach(['lazer','gastronomia','esporte','eventos','ponta-grossa'] as $cat) cms_save_news(0,0,['title'=>'Descobertas na categoria '.cms_category_label($cat),'category'=>$cat,'image'=>'assets/route-city.svg','body'=>'Conteúdo de teste.']);
    cms_save_company(0,'',['nome'=>'Café de teste','url'=>'cafe-teste','categoria'=>'gastronomia','bairro'=>'Centro','local'=>'Rua de teste','faixa_preco'=>'Econômico','horarios'=>'Terça a sábado, 10h às 18h','whatsapp'=>'42999991234','perfil'=>'assets/placeholder.svg']);
    tourism_save('events',0,['title'=>'Feira de teste','category'=>'gastronomia','starts'=>'2099-12-01T10:00','ends'=>'2099-12-01T18:00','location'=>'Centro de teste','body'=>'Programação de teste.','price'=>'Consulte o organizador','published'=>1]);"""
    subprocess.run([php,'-r',seed],env=env,cwd=root,check=True)
    with socket.socket() as sock: sock.bind(('127.0.0.1',0)); port=sock.getsockname()[1]
    base=f'http://127.0.0.1:{port}/'
    def check(ok,label):
        global count
        if not ok: raise AssertionError(label)
        count+=1
    with tempfile.TemporaryFile(mode='w+') as log:
        server=subprocess.Popen([php,'-d','session.save_path='+tmp,'-S',f'127.0.0.1:{port}','-t',str(root)],env=env,stdout=log,stderr=log)
        try:
            for _ in range(50):
                try: urllib.request.urlopen(base+'index.php'); break
                except urllib.error.URLError: time.sleep(.1)
            with sync_playwright() as pw:
                browser=pw.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--disable-dev-shm-usage'])
                context=browser.new_context(viewport={'width':1440,'height':1000},permissions=['clipboard-read','clipboard-write'])
                context.route('**/*',lambda route: route.continue_() if route.request.url.startswith(base) else route.abort())
                page=context.new_page(); page.route('**/rss-atualizar.php',lambda r:r.fulfill(json={'state':'waiting','results':[]}))
                errors=[]; page.on('pageerror',lambda error:errors.append(str(error)))
                page.goto(base+'index.php'); page.wait_for_load_state('networkidle')
                check(page.locator('.home-spotlight').bounding_box()['y']<page.locator('#ultima-edicao').bounding_box()['y'],'Destaques antes da revista')
                check(page.locator('.discovery-card').count()==5,'Inicial com quatro roteiros e um evento')
                check(page.locator('.logo-placeholder img').evaluate('e=>e.complete&&e.naturalWidth>0'),'Marca alternativa carregada')
                check(page.locator('.edition-empty').count()==1,'Sem bloco cinza vazio quando edição não foi enviada')
                check(page.evaluate('document.documentElement.scrollWidth<=innerWidth+1'),'Inicial sem transbordamento no desktop')
                page.screenshot(path='/tmp/revista-tourism-home.png',full_page=True)
                page.locator('.search-icon').click(); page.locator('.search-container input').fill('criancas'); page.locator('.search-container button').click()
                check('busca.php' in page.url and 'O que fazer com crianças' in page.locator('.search-results').inner_text(),'Busca do cabeçalho encontra roteiro sem acento')
                page.locator('.search-results a').first.click(); check(page.locator('.route-body h2').count()>=4,'Roteiro com paradas e conteúdo completo')
                check(page.locator('.share-tools a[href^="https://wa.me/"]').count()==1,'Compartilhar roteiro pelo WhatsApp')
                with page.expect_response(lambda r:r.url.endswith('/metricas.php')):
                    page.reload()
                page.goto(base+'agenda.php'); check(page.locator('.discovery-card h3').inner_text()=='Feira de teste','Agenda exibe evento publicado')
                page.locator('input[name=from]').fill('2100-01-01'); page.get_by_role('button',name='Filtrar eventos',exact=True).click()
                check(page.locator('.empty-state').count()==1,'Filtro de datas apresenta estado vazio útil')
                page.goto(base+'indicador-gastronomia.php'); page.locator('select[name=bairro]').select_option('Centro'); page.locator('select[name=preco]').select_option('Econômico'); page.get_by_role('button',name='Filtrar empresas',exact=True).click()
                check('bairro=Centro' in page.url and 'Café de teste' in page.locator('.noticias-grid').inner_text(),'Filtro do guia funciona no navegador')
                page.locator('.noticia-card a').click(); check('10h às 18h' in page.locator('.company-details').inner_text(),'Perfil exibe horários informados')
                page.goto(base+'noticia.php?id=1'); share=page.locator('.share-tools'); check(base+'noticia.php?id=1'==share.get_attribute('data-share-url'),'Compartilhamento aponta para matéria interna')
                share.locator('[data-copy-link]').click(); page.wait_for_function("document.querySelector('[data-share-status]').textContent.includes('copiado')")
                check(page.evaluate('navigator.clipboard.readText()')==base+'noticia.php?id=1','Copiar link funciona')
                check(page.locator('meta[property="og:url"]').get_attribute('content')==base+'noticia.php?id=1','Metadados sociais com URL da matéria')
                page.set_viewport_size({'width':390,'height':844}); page.goto(base+'index.php'); page.locator('.menu-toggle').click()
                check(page.locator('.main-navigation ul').is_visible() and page.locator('.menu-toggle').get_attribute('aria-expanded')=='true','Menu móvel acessível: '+str(page.locator('.main-navigation ul').is_visible())+' / '+str(page.locator('.menu-toggle').get_attribute('aria-expanded')))
                page.locator('.main-navigation a[href="roteiros.php"]').click()
                check('roteiros.php' in page.url and page.evaluate('document.documentElement.scrollWidth<=innerWidth+1'),'Explorar pelo menu móvel sem rolagem horizontal')
                page.screenshot(path='/tmp/revista-tourism-mobile.png',full_page=True)
                page.goto(base+'admin.php'); page.locator('input[name=password]').fill('test-tourism-browser-password'); page.get_by_role('button',name='Entrar',exact=True).click()
                check('Acessos a roteiros' in page.content() and page.locator('.tourism-ranking li').count()>=1,'Dashboard recebe visitas aos novos conteúdos')
                page.locator('.admin-sidebar a[href="admin.php?area=events"]').click(); check(page.locator('.cms-form').count()==0,'Agenda abre sem formulário expandido')
                page.get_by_role('link',name='+ Adicionar item',exact=True).click(); page.locator('input[name=title]').fill('Oficina de teste'); page.locator('input[name=starts]').fill('2099-12-02T10:00'); page.locator('input[name=ends]').fill('2099-12-02T12:00'); page.locator('input[name=location]').fill('Local de teste'); page.locator('textarea[name=body]').fill('Descrição de teste'); page.locator('input[name=published]').check(); page.get_by_role('button',name='Salvar',exact=True).click()
                check('Cadastro salvo.' in page.content() and 'Oficina de teste' in page.content(),'Criar evento no admin pelo celular')
                page.locator('.admin-sidebar a[href="admin.php?area=routes"]').click(); check('Um dia em Ponta Grossa' in page.content(),'Menu de roteiros com conteúdo editável')
                check(not errors,'Sem exceções JavaScript nos fluxos')
                browser.close()
            server.terminate(); server.wait(timeout=5); log.seek(0); logs=log.read(); check('Fatal error' not in logs and 'PHP Warning' not in logs,'Sem avisos PHP nos fluxos')
            print(f'{count} verificações de navegação e turismo no Chromium passaram.')
        finally:
            if server.poll() is None: server.terminate(); server.wait(timeout=5)
