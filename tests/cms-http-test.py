"""PHP_BIN=php python3 tests/cms-http-test.py"""
import http.cookiejar, os, re, socket, subprocess, tempfile, time, urllib.request, urllib.parse, urllib.error, uuid
from pathlib import Path
php=os.environ.get('PHP_BIN','php'); root=Path(__file__).resolve().parents[1]; count=0
with tempfile.TemporaryDirectory(prefix='revista-cms-http-') as tmp:
    env=os.environ.copy(); env['RSS_DATA_DIR']=tmp; env['REVISTA_CONFIG_DIR']=tmp+'/config'
    env['RSS_ADMIN_PASSWORD_HASH']=subprocess.check_output([php,'-r',"echo password_hash('test-only-cms-password',PASSWORD_DEFAULT);"],text=True)
    for key in ['DB_HOST','DB_USER','DB_NAME','DB_PASSWORD','DB_CONV_HOST','DB_CONV_USER','DB_CONV_NAME']: env.pop(key,None)
    with socket.socket() as sock: sock.bind(('127.0.0.1',0)); port=sock.getsockname()[1]
    base=f'http://127.0.0.1:{port}/'; client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def req(path,post=None,files=None):
        data=None; headers={}
        if files:
            boundary='----cms'+uuid.uuid4().hex; parts=[]
            for key,value in post.items(): parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
            for key,(filename,mime,body) in files.items():
                parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"; filename="{filename}"\r\nContent-Type: {mime}\r\n\r\n'.encode()+body+b'\r\n')
            parts.append(f'--{boundary}--\r\n'.encode()); data=b''.join(parts); headers['Content-Type']='multipart/form-data; boundary='+boundary
        elif post is not None: data=urllib.parse.urlencode(post).encode()
        try:
            with client.open(urllib.request.Request(base+path,data=data,headers=headers),timeout=8) as response: return response.status,response.read().decode()
        except urllib.error.HTTPError as e: return e.code,e.read().decode()
    def check(ok,message):
        global count
        if not ok: raise AssertionError(message)
        count+=1
    with tempfile.TemporaryFile(mode='w+') as log:
        server=subprocess.Popen([php,'-d','session.save_path='+tmp,'-S',f'127.0.0.1:{port}','-t',str(root)],env=env,stdout=log,stderr=log)
        try:
            for _ in range(50):
                try: status,page=req('admin.php'); break
                except urllib.error.URLError: time.sleep(.1)
            check(status==200 and 'Senha do painel' in page,'Login administrativo')
            csrf=re.search('name="csrf" value="([^"]+)"',page)[1]
            check(req('admin.php',{'csrf':csrf,'area':'news','action':'save','title':'Sem autorização'})[0]==403,'Bloquear escrita sem login')
            status,page=req('admin.php',{'csrf':csrf,'password':'test-only-cms-password'})
            check(status==200 and 'Notícias manuais' in page and 'Empresas' in page,'Áreas do painel disponíveis')
            check(req('admin.php',{'csrf':'bad','action':'save','area':'sections'})[0]==403,'CSRF administrativo')
            def save(area,**fields):
                status,page=req('admin.php',dict(csrf=csrf,area=area,action='save',**fields))
                check(status==200 and 'Alterações salvas.' in page,'Salvar '+area)
                return page
            for i in range(1,5): save('news',title=f'Notícia {i}',category='Ponta Grossa',image='https://example.com/photo.png',body=f'Conteúdo exclusivo {i}')
            import base64
            png=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jX1sAAAAASUVORK5CYII=')
            status,page=req('admin.php',dict(csrf=csrf,area='news',action='save',title='Notícia 5',category='Ponta Grossa',image='',body='Conteúdo exclusivo 5'),{'image_file':('foto.png','image/png',png)})
            check(status==200 and 'Alterações salvas.' in page,'Enviar foto de notícia')
            status,home=req('index.php')
            sections=re.findall(r'<section class="home-section".*?</section>',home,re.S)
            check(len(sections)==5 and home.index('Última Edição')<home.index('data-section="ponta-grossa"'),'Seções iniciais após Última Edição')
            check(sections[0].count('class="noticia-card compact-news"')==4 and 'Notícia 5' in sections[0] and 'Notícia 1<' not in sections[0],'Quatro notícias recentes por seção')
            check('noticia-resumo' not in home and 'Conteúdo exclusivo' not in home,'Chamadas somente com título')
            check('<a class="news-photo" href="noticia.php?id=5">' in home and 'cms/site.css' in home,'Foto clicável e grid compacto')
            media=re.search(r'src="(media.php\?arquivo=[a-f0-9]+)"',home)[1]
            with client.open(base+media) as response: check(response.headers['Content-Type']=='image/png' and response.read()==png,'Upload servido como imagem validada')
            check('Conteúdo exclusivo 1' in req('noticia.php?id=1')[1],'Matéria manual abre dentro do site')
            save('news',id=1,title='Primeira editada',category='ponta-grossa',image='https://example.com/photo.png',body='Texto alterado')
            check('Texto alterado' in req('noticia.php?id=1')[1] and 'Primeira editada' not in re.findall(r'<section class="home-section".*?</section>',req('index.php')[1],re.S)[0],'Editar texto sem mudar ordem')
            save('banners',section_id=1,title='Anúncio de teste',image='https://example.com/banner.png',link='https://example.com/promocao')
            check('Anúncio de teste' not in req('index.php')[1],'Banner desativado não aparece')
            save('banners',id=1,section_id=1,title='Anúncio de teste',image='https://example.com/banner.png',link='https://example.com/promocao',active=1)
            home=req('index.php')[1]
            check(home.index('data-section="ponta-grossa"')<home.index('Anúncio de teste')<home.index('data-section="viagem"'),'Banner entre seções')
            save('sections',id=1,title='Minha Cidade',category='ponta-grossa',color='#112233',position=1,active=1)
            check('--section-color:#112233' in req('index.php')[1] and 'Minha Cidade' in req('index.php')[1],'Cor e título configuráveis')
            save('sections',title='Eventos locais',category='eventos',color='#334455',position=6,active=1)
            check('data-section="eventos"' in req('index.php')[1],'Criar seção')
            save('sections',id=3,title='Lazer',category='lazer',color='#ad1431',position=3)
            check('data-section="lazer"' not in req('index.php')[1],'Desativar seção')
            status,page=req('admin.php',dict(csrf=csrf,area='sections',action='delete',id=6))
            check('Registro removido' in page and 'data-section="eventos"' not in req('index.php')[1],'Excluir seção')
            company=dict(nome='Restaurante Teste',url='restaurante-teste',categoria='gastronomia',local='Rua Central',telefone='42 3000-0000',whatsapp='42 99999-1234',descritivo='Descrição do restaurante',perfil='https://example.com/logo.png')
            save('companies',**company)
            check('Restaurante Teste' in req('indicador-gastronomia.php')[1],'Empresa aparece no guia sem depender de novo MySQL')
            status,profile=req('indicador.php?id=restaurante-teste')
            check(status==200 and 'https://wa.me/5542999991234' in profile and 'Falar pelo WhatsApp' in profile,'Botão WhatsApp da empresa')
            req('indicador.php?id=restaurante-teste')
            check('1 acessos ao perfil' in req('admin.php?area=companies')[1],'Contar visita sem inflar por atualização da sessão')
            save('companies',id=1,**dict(company,nome='Restaurante Editado',categoria='compras',whatsapp=''))
            check('Restaurante Editado' in req('indicador-compras.php')[1] and 'Restaurante Editado' not in req('indicador-gastronomia.php')[1],'Editar categoria de empresa')
            check('Falar pelo WhatsApp' not in req('indicador.php?id=restaurante-teste')[1],'Sem WhatsApp não mostra botão')
            status,page=req('admin.php?area=companies&edit=1')
            check('value="Restaurante Editado"' in page and 'value="restaurante-teste"' in page,'Formulário da empresa preenchido')
            status,page=req('admin.php',dict(csrf=csrf,area='companies',action='delete',id=1))
            check('Registro removido' in page and req('indicador.php?id=restaurante-teste')[0]==404,'Apagar empresa retira perfil')
            status,page=req('admin.php',dict(csrf=csrf,area='news',action='delete',id=1))
            check('Registro removido' in page and req('noticia.php?id=1')[0]==404,'Apagar notícia retira matéria')
            status,page=req('admin.php',dict(csrf=csrf,area='news',action='save',title='Inválida',category='ponta-grossa',body='Texto',image='javascript:alert(1)'))
            check('Use endereço HTTP/HTTPS' in page,'Bloquear imagem executável')
            server.terminate(); server.wait(timeout=5); log.seek(0); logs=log.read()
            check('Fatal error' not in logs and 'PHP Warning' not in logs,'Sem erros PHP nos fluxos verificados')
            print(f'{count} verificações HTTP do CMS passaram.')
        finally:
            if server.poll() is None: server.terminate(); server.wait(timeout=5)
