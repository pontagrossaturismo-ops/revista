"""PHP_BIN=php python3 tests/layout-browser-test.py; requer Playwright e Chromium."""
import os, re, socket, subprocess, tempfile, time, urllib.request, urllib.error
from pathlib import Path
from playwright.sync_api import sync_playwright
php=os.environ.get('PHP_BIN','php'); root=Path(__file__).resolve().parents[1]; count=0
with tempfile.TemporaryDirectory(prefix='revista-layout-') as tmp:
    env=os.environ.copy(); env['RSS_DATA_DIR']=tmp; env['REVISTA_CONFIG_DIR']=tmp+'/config'
    for key in ['DB_HOST','DB_CONV_HOST','DB_USER','DB_NAME','DB_PASSWORD']: env.pop(key,None)
    env['RSS_ADMIN_PASSWORD_HASH']=subprocess.check_output([php,'-r',"echo password_hash('test-only-layout-password',PASSWORD_DEFAULT);"],text=True)
    seed="""require 'cms/core.php';
    cms_save_news(0,0,['title'=>'Matéria local','category'=>'viagem','image'=>'https://example.com/photo.png','body'=>'Texto da matéria.','credit'=>'Foto Local']);
    cms_save_news(0,0,['title'=>'Relacionada local','category'=>'viagem','image'=>'https://example.com/photo2.png','body'=>'Outro texto.']);
    cms_save_news(0,0,['title'=>'Não relacionada','category'=>'lazer','image'=>'https://example.com/photo3.png','body'=>'Texto.']);
    cms_save_company(0,'',['nome'=>'Empresa layout','url'=>'empresa-layout','categoria'=>'gastronomia','local'=>'Rua Teste, 123','telefone'=>'42 3000-0000','whatsapp'=>'42 99999-1234']);
    rss_save_source(0,'Crédito RSS','https://example.com/feed','viagem');
    $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jX1sAAAAASUVORK5CYII=');
    $photo=rss_cache_image('https://example.com/rss.png',fn($u,$limit)=>[$png,$u]);
    rss_store(cms_db(),1,[['identity'=>'rss','title'=>'Matéria RSS','url'=>'https://example.com/article','summary'=>'Resumo','body'=>'Texto do RSS.','image'=>'https://example.com/rss.png','image_file'=>$photo,'published_at'=>'2026-10-14 15:22:00']]);
    cms_save_banner(0,['title'=>'Banner topo','image'=>'rss-imagem.php?id=1','link'=>'https://example.com','banner_targets'=>1,'show_home'=>1,'section_id'=>1,'show_rss'=>1,'show_manual'=>1,'active'=>1]);"""
    subprocess.run([php,'-r',seed],cwd=root,env=env,check=True)
    with socket.socket() as sock: sock.bind(('127.0.0.1',0)); port=sock.getsockname()[1]
    base=f'http://127.0.0.1:{port}/'
    def check(ok,message):
        global count
        if not ok: raise AssertionError(message)
        count+=1
    with tempfile.TemporaryFile(mode='w+') as log:
        server=subprocess.Popen([php,'-d','session.save_path='+tmp,'-S',f'127.0.0.1:{port}','-t',str(root)],env=env,stdout=log,stderr=log)
        try:
            for _ in range(50):
                try: urllib.request.urlopen(base+'index.php'); break
                except urllib.error.URLError: time.sleep(.1)
            with sync_playwright() as pw:
                browser=pw.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--disable-dev-shm-usage'])
                context=browser.new_context(viewport={'width':1280,'height':1700})
                context.route('**/*',lambda route: route.continue_() if route.request.url.startswith(base) else route.abort())
                page=context.new_page()
                check(context.request.post(base+'section-order.php',form={'csrf':'bad','order':'[1,2,3,4,5]'}).status==403,'Ordenação exige autenticação')
                page.goto(base+'admin.php'); page.locator('input[name=password]').fill('test-only-layout-password'); page.get_by_role('button',name='Entrar',exact=True).click()
                check(page.locator('#section-sort-list').count()==1,'Lista de seções arrastável disponível')
                csrf=page.locator('#section-sort-list').get_attribute('data-csrf')
                check(context.request.post(base+'section-order.php',form={'csrf':'bad','order':'[1,2,3,4,5]'}).status==403,'CSRF protege ordenação')
                check(context.request.post(base+'section-order.php',form={'csrf':csrf,'order':'[1,1]'}).status==422,'Rejeitar lista parcial/duplicada')
                first=page.locator('.section-sort-item').nth(0); third=page.locator('.section-sort-item').nth(2)
                first.scroll_into_view_if_needed(); handle=first.locator('.drag-handle').bounding_box(); target=third.bounding_box()
                page.mouse.move(handle['x']+handle['width']/2,handle['y']+handle['height']/2); page.mouse.down()
                page.mouse.move(target['x']+target['width']/2,target['y']+target['height']-3,steps=12); page.mouse.up()
                page.wait_for_function("document.querySelector('#section-sort-status').textContent.includes('Ordem salva')")
                order=page.locator('.section-sort-item').evaluate_all('(items)=>items.map(item=>item.dataset.sectionId)')
                check(order[:3]==['2','3','1'],'Arrastar muda ordem e salva no servidor')
                page.reload(); check(page.locator('.section-sort-item').nth(2).get_attribute('data-section-id')=='1','Ordem persiste após recarregar')
                page.locator('.section-sort-item').nth(2).locator('[data-move=up]').click(); page.wait_for_function("document.querySelector('#section-sort-status').textContent.includes('Ordem salva')")
                check(page.locator('.section-sort-item').nth(1).get_attribute('data-section-id')=='1','Alternativa por botão funciona')
                page.goto(base+'categoria.php?categoria=ponta-grossa')
                check(page.locator('h1').count()==1 and page.locator('h1').inner_text()=='Ponta Grossa','Categoria tem um único título selecionado')
                boxes=page.evaluate("()=>({filters:document.querySelector('.category-filters').getBoundingClientRect().bottom,title:document.querySelector('h1').getBoundingClientRect().top})")
                check(boxes['title']>boxes['filters'],'Título abaixo dos filtros')
                page.goto(base+'indicador.php?id=empresa-layout')
                cta=page.locator('.company-whatsapp'); floating=page.locator('a.whatsapp-button')
                check(cta.count()==1 and cta.evaluate('(e)=>getComputedStyle(e).position')=='static','WhatsApp da empresa no fluxo')
                address=page.locator('.company-address').bounding_box(); box=cta.bounding_box(); phone=page.locator('.company-phone').bounding_box()
                check(address['y']+address['height']<=box['y'] and box['y']+box['height']<=phone['y'],'CTA imediatamente após endereço e antes do telefone')
                check(floating.evaluate('(e)=>getComputedStyle(e).position')=='fixed' and floating.evaluate('(e)=>getComputedStyle(e).borderRadius')=='50%','Botão global permanece circular e flutuante')
                for path in ['rss-materia.php?id=1','noticia.php?id=1']:
                    page.goto(base+path)
                    check(page.locator('.article-page').evaluate('(e)=>parseFloat(getComputedStyle(e).marginTop)')>=36,'Margem do topo em '+path)
                    check(page.locator('.photo-credit').inner_text().startswith('Créditos:') and page.locator('.photo-credit').evaluate('(e)=>getComputedStyle(e).textAlign')=='right','Crédito à direita em '+path)
                    check(page.locator('.photo-credit').evaluate('(e)=>parseFloat(getComputedStyle(e).fontSize)')<=13,'Crédito menor em '+path)
                    check(page.locator('.article-body').evaluate('(e)=>parseFloat(getComputedStyle(e).marginTop)')>=30,'Margem do início do texto em '+path)
                    top=page.locator('.article-page > .ad-slot').bounding_box(); article=page.locator('article.single-article').bounding_box()
                    check(top['y']<article['y'],'Banner acima da matéria em '+path)
                    related=page.locator('.article-related'); check('Relacionada local' in related.inner_text() and 'Não relacionada' not in related.inner_text(),'Relacionados pela categoria em '+path)
                    check(related.bounding_box()['y']<page.locator('footer.site-footer').bounding_box()['y'],'Relacionados antes do rodapé em '+path)
                    if path.startswith('rss'):
                        check(bool(re.fullmatch(r'\d{2}/\d{2}/\d{4}',page.locator('time').inner_text())) and 'Fonte:' not in page.locator('article.single-article').inner_text(),'RSS sem hora e sem rótulo Fonte')
                page.goto(base+'index.php'); banner=page.locator('.ad-slot'); image=banner.locator('img')
                bounds=banner.bounding_box(); img=image.bounding_box()
                check(abs((bounds['x']+bounds['width']/2)-(img['x']+img['width']/2))<=2,'Publicidade centralizada na home')
                page.set_viewport_size({'width':390,'height':900}); page.goto(base+'rss-materia.php?id=1')
                check(page.evaluate('document.documentElement.scrollWidth<=window.innerWidth'),'Matéria responsiva sem rolagem horizontal')
                bounds=page.locator('.ad-slot').bounding_box(); img=page.locator('.ad-slot img').bounding_box()
                check(abs(bounds['x']+bounds['width']/2-img['x']-img['width']/2)<=2,'Publicidade centralizada também no celular')
                browser.close()
            server.terminate(); server.wait(timeout=5); log.seek(0); logs=log.read(); check('Fatal error' not in logs and 'PHP Warning' not in logs,'Sem erros PHP no navegador')
            print(f'{count} verificações de layout e interação no Chromium passaram.')
        finally:
            if server.poll() is None: server.terminate(); server.wait(timeout=5)
