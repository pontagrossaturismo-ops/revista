# Configuração do site e RSS

## Instalação pela tela

1. Faça backup do site/banco. Envie os arquivos para a pasta atual do site, preservando `img`, `imagens`, `edicoes` e outros arquivos não enviados para análise.
2. Use PHP 8.1+ com cURL, SimpleXML, PDO SQLite e MySQLi/mysqlnd. As sessões PHP devem estar habilitadas e permitir escrita. Não precisa de Composer.
3. No terminal da hospedagem, dentro da pasta do site, execute uma vez:

   ```bash
   php configurar-token.php
   ```

   Guarde o código exibido. Esse passo impede que um visitante configure o site antes do proprietário. O diretório pai da pasta do site precisa permitir criar `revista-config`, com permissão privada. Se necessário, configure `REVISTA_CONFIG_DIR` com um caminho absoluto fora da pasta pública, igual no PHP web e no cron. O instalador não publica o código de ativação.
4. Acesse **instalar.php por HTTPS**, informe o código e preencha os campos:
   - Nome, descrição e endereço do site.
   - Banco MySQL: servidor/usuário/nome já sugeridos para o banco informado; senha e porta. Se já houver uma senha privada salva, deixar o campo vazio a mantém.
   - Banco do guia de estabelecimentos, opcional, para as páginas que usam `log-conv.php`/tabela `lojas`.
   - Senha do painel (12 caracteres ou mais), confirmação. O intervalo automático é de 12 horas.
   - Uma fonte RSS/Atom inicial, opcional, com nome e categoria.
5. Clique em **Validar e salvar configuração**. O instalador verifica extensões, conexão MySQL e tabela `materia`; para o guia opcional, verifica `lojas`. Não cria/altera tabelas MySQL. Uma falha de validação não salva os campos. Sem o MySQL, é possível desmarcar essa opção e usar apenas RSS; as páginas de matérias/guia originais ainda dependem dos respectivos bancos.
6. A configuração é gerada automaticamente em `revista-config/config.json`, fora do site público, com permissão 0600. As senhas do banco ficam apenas nesse arquivo; a senha do painel é armazenada como hash. Inclua a pasta privada nos backups e use o mesmo usuário para PHP e cron. O código de ativação é consumido. Depois de instalado, `instalar.php` exige a senha atual do painel para reconfigurar.
7. Abra **rss-admin.php** para cadastrar, editar nome, link e categoria pelo formulário de cada fonte, pausar/ativar ou excluir fontes. Excluir remove também seus itens. Fontes pausadas são ocultadas.

O código usa `getenv('DB_HOST')` para ler o **nome de uma variável**, e não `getenv('nome-do-servidor')`. A tela elimina a necessidade de escrever esses valores no PHP. Variáveis de ambiente existentes têm prioridade sobre o arquivo: se já as utiliza, ajuste/remova os valores na hospedagem para que os campos da tela tenham efeito. Nunca salve senhas no GitHub. Troque as senhas antigas que estavam expostas nos arquivos enviados.

## Ativar a atualização automática

O instalador mostra o comando completo com a pasta correta. No painel da hospedagem, crie uma tarefa cron **a cada minuto**, usando o caminho real do PHP, por exemplo:

```cron
* * * * * cd /caminho/absoluto/do/site && /usr/bin/php rss-importar.php >> /home/usuario/revista-rss-importacao.log 2>&1
```

O importador respeita o intervalo fixo de **12 horas** e impede execuções simultâneas, compartilhando a mesma trava entre cron, site e botão do painel. Sem cron, o primeiro acesso público após o intervalo aciona uma importação em segundo plano pelo navegador. Para atualizar também quando não houver visitantes, cadastre o cron acima. O site não tem acesso administrativo ao painel da hospedagem para cadastrar essa tarefa sozinho. Mantenha/rotacione logs fora da pasta pública. Se configurou REVISTA_CONFIG_DIR no PHP web, forneça o mesmo valor ao cron.

Para testar imediatamente:

```bash
cd /caminho/absoluto/do/site
php rss-importar.php --force
```

Código de saída 1 indica falha de uma ou mais fontes. O erro e a última tentativa também aparecem no painel. Falhas não apagam notícias anteriores nem impedem processar as demais fontes.

## Como as notícias aparecem

Os cards e o CSS atuais são mantidos. A página inicial tem seções configuráveis com quatro chamadas; RSS, matérias do MySQL e notícias novas do painel são misturados por ordem de cadastro. As categorias usam a mesma ordem e paginação. O RSS usa SQLite, sem modificar `materia`. O importador aceita RSS/Atom, evita duplicatas da mesma fonte, atualiza itens modificados e abre as matérias dentro do próprio site, com crédito e link original. Importa o texto disponibilizado no RSS, incluindo content:encoded quando presente; não extrai a íntegra das páginas externas.

`feed.php` exporta notícias importadas e aceita `?categoria=viagem` e demais categorias. As rotas antigas `g1_turismo_rss.php` e `g1_turismo_generate_rss.php` exportam a categoria viagem; para receber G1, cadastre sua URL RSS no painel. `example_display.php` e `display.php` usam o armazenamento local. O proxy de páginas foi desativado.

A importação verifica TLS, protocolos, DNS/IPs públicos e cada redirecionamento; limita respostas a 2 MB/100 itens e bloqueia DTD/entidades XML. Precisa de DNS e saída HTTP/HTTPS direta para as fontes. URLs devem ser de feeds, não páginas iniciais. Recomenda-se limitar tentativas de login pelo servidor. A retenção automática mantém até 90 notícias RSS por categoria; monitore também arquivos e backups.

## Alternativa: variáveis de ambiente

A configuração por variáveis continua compatível: `DB_HOST`, `DB_USER`, `DB_PASSWORD`, `DB_NAME`, `DB_PORT` (3306); equivalentes `DB_CONV_*` para o guia; `RSS_DATA_DIR` fora da pasta pública; `RSS_ADMIN_PASSWORD_HASH`; `SITE_TITLE`, `SITE_DESCRIPTION` e `SITE_URL`. Não é necessário preenchê-las quando usar apenas a tela. O antigo `RSS_INTERVAL_MINUTES` não altera mais o intervalo fixo de 12 horas.

## Validação

```bash
php tests/rss-test.php
PHP_BIN=php python3 tests/http-test.py
PHP_BIN=php python3 tests/setup-test.py
```

Neste ambiente passaram 24 verificações RSS, 49 HTTP e 27 do instalador, além da sintaxe PHP. As verificações do instalador cobrem ativação, autenticação, campos, falha de conexão, persistência privada, consumo do código e intervalo de cron. O sucesso de uma conexão MySQL real não foi testado: o domínio informado não resolveu neste ambiente (erro 2002). Uma busca RSS externa também falhou em DNS. A conexão real, feeds e agendamento precisam ser confirmados na hospedagem. Imagens e edições originais não foram fornecidas para teste.

## Testar no XAMPP/Windows

Para arquivos em `C:\xampp\htdocs\PG-2025\novo`, use PHP 8.1+ do XAMPP, habilite as extensões citadas acima e inicie Apache. No Prompt de Comando (CMD):

```bat
cd /d C:\xampp\htdocs\PG-2025\novo
C:\xampp\php\php.exe configurar-token.php
```

Copie o código exibido e abra `http://localhost/PG-2025/novo/instalar.php`. O instalador é PHP e não deve ser aberto diretamente como arquivo. No XAMPP, a configuração privada fica automaticamente em uma pasta `C:\xampp\revista-config-...`, fora de todo o `htdocs`. Atualize `config/runtime.php` para a versão com suporte a Windows antes de gerar o código. Mantenha a mesma pasta privada no terminal e no Apache.

Para testar apenas RSS, desmarque a opção MySQL. Para testar matérias existentes com banco local, inicie MySQL e importe uma cópia do banco pelo phpMyAdmin; o instalador não cria a tabela `materia`. O banco remoto depende de permissão de acesso externo da hospedagem.

Para importar no CMD:

```bat
C:\xampp\php\php.exe C:\xampp\htdocs\PG-2025\novo\rss-importar.php --force
```

Para atualização automática no Windows, use o Agendador de Tarefas: programa `C:\xampp\php\php.exe`, argumento `"C:\xampp\htdocs\PG-2025\novo\rss-importar.php"`, repetindo a cada cinco minutos (o importador só trabalha quando completar 12 horas). Para criar a tarefa, execute `agendar-rss-windows.bat` na pasta do site. O arquivo usa `C:\xampp\php\php.exe` e o caminho da própria pasta, incluindo espaços. Confira a tarefa `RevistaPG-RSS` no Agendador e teste sua execução; o computador precisa estar ligado e a conta disponível para a tarefa. A instalação dessa tarefa Windows não pode ser executada a partir deste ambiente Linux. O intervalo de 12 horas continua sendo respeitado. Os caminhos Windows foram verificados por testes de normalização/isolamento no Linux; ainda é necessário confirmar a execução em um XAMPP real.


## Importou itens, mas não encontrou as notícias?

Atualize todos os arquivos PHP do projeto, incluindo `index.php`, `noticias.php`, as páginas de categoria e as pastas `rss`/`config`. Atualizar apenas o importador não adiciona a exibição às páginas antigas. Preserve a configuração privada e o arquivo SQLite existente; não apague nem reinstale os dados para diagnosticar.

Abra `noticias.php#noticias-rss`: a listagem mistura as notícias disponíveis, sem separar por origem. Em `rss-admin.php`, confira os totais armazenados/visíveis e os caminhos em “Diagnóstico do armazenamento”. Compare com a execução:

```bat
C:\xampp\php\php.exe C:\xampp\htdocs\PG-2025\novo\rss-importar.php --diagnostico
```

Os caminhos do SQLite precisam coincidir no terminal e no painel. Se forem diferentes, verifique variáveis `REVISTA_CONFIG_DIR` e `RSS_DATA_DIR`, versões dos arquivos e pasta acessada no navegador. Não compartilhe `config.json` nem suas senhas. `feed.php` permite verificar os itens exportados no navegador. Um erro 403 de uma fonte indica que aquele servidor recusou a requisição; ele não impede exibir itens importados de outras fontes.


## Imagens e matérias dentro do site

Atualize também `rss-materia.php`, `rss-imagem.php`, `rss/core.php` e `rss-admin.php`. Não apague o SQLite nem refaça a instalação. As colunas novas são adicionadas automaticamente, preservando os itens existentes.

Execute novamente a importação para preencher o conteúdo e baixar as imagens dos itens antigos:

```bat
C:\xampp\php\php.exe C:\xampp\htdocs\PG-2025\novo\rss-importar.php --force
```

Os cards abrem `rss-materia.php?id=...` na mesma aba, usando o layout atual. A página mostra título, fonte, data, imagem principal e texto do feed. Se a fonte publicar apenas resumo, o site mostrará apenas esse resumo, com link para consultar o original. Não há extração do texto integral das páginas externas; elas podem ser consultadas apenas para localizar a foto editorial.

São reconhecidas imagens em media:content/media:thumbnail (inclusive grupos), enclosure, image/url, Atom XHTML e imagens no HTML de description/content:encoded, incluindo campos de carregamento tardio e HTML com entidades adicionais. Quando o feed não fornecer uma foto reconhecida, o importador consulta a página pública da matéria e procura og:image, twitter:image, image_src ou uma imagem dentro de article. Não seleciona um logo genérico fora do artigo. A imagem principal é baixada para `images` dentro do armazenamento RSS privado e servida por `rss-imagem.php`, sem expor a pasta. Imagens JPEG, PNG, GIF e WebP são aceitas, com limite de 5 MB e 40 milhões de pixels. Certificados e validação de destino continuam ativos. A primeira atualização pode demorar devido aos downloads.

Falha ao baixar uma imagem não impede armazenar o texto, mas a matéria fica oculta do site até que uma foto válida seja baixada. Não há mais alternativa com imagem remota. Itens cujo feed não forneça imagem também ficam ocultos. O terminal e o painel mostram falhas de imagens por fonte. O texto é exibido escapado, com parágrafos, sem executar HTML/scripts do feed. Inclua a pasta images nos backups; a retenção remove imagens dos itens excluídos apenas quando não têm outras referências.

Os testes adicionais `php tests/media-test.php` verificam migração de base antiga, conteúdo completo, extração/cache/validação de imagem, atualização sem duplicar e tolerância a erros de download. Os testes HTTP também verificam a leitura interna e a resposta PNG da imagem local.


## Painel de gestão RSS e publicação somente com foto

Acesse `rss-admin.php` com a senha existente. Atualize também `rss-admin.css` e os arquivos PHP do projeto. Não precisa reinstalar: a tabela de histórico é criada automaticamente no SQLite.

- Cada fonte possui **Editar nome, link e categoria**. O formulário vem preenchido e permite alterar a URL sem excluir/recriar a fonte. Alterar a categoria move também suas notícias já importadas. A URL não pode coincidir com a de outra fonte. Os dados anteriores são preservados se o formulário falhar.
- O painel informa notícias armazenadas, publicadas, sem foto local, tentativas de importação, execuções sem erros, falhas de feed e importações com falhas de imagem. Mostra também os erros recentes das imagens.
- O histórico contém uma linha por fonte/tentativa, data UTC, URL utilizada, resultado, quantidade processada, itens novos, existentes reprocessados, erros de imagem e mensagem de falha do feed. O filtro seleciona uma fonte; a tabela mostra as 100 últimas linhas. Os totais abrangem todo o histórico mantido.
- O histórico começa na atualização. Importações anteriores continuam armazenadas, mas não são contadas como execuções históricas inventadas. Reprocessar um item existente não aumenta o contador de novos.
- Somente notícias RSS com arquivo de foto **local, legível e válido** aparecem nos cards, nas páginas internas e no feed XML de saída. Sem foto, download bloqueado, arquivo removido ou corrompido: a matéria fica oculta. Uma foto local válida já baixada pode continuar sendo usada se uma tentativa posterior de download da mesma URL falhar.
- Itens ocultos permanecem armazenados dentro do limite de 90 notícias por categoria e podem ser recuperados por nova execução do importador. Execute `rss-importar.php --force` no XAMPP para preencher fotos e iniciar o histórico desta versão. Não apague a configuração ou o banco.
- Pausar oculta as notícias da fonte. Excluir remove a fonte, notícias e histórico associados; os arquivos de imagem privados não são limpos automaticamente.

Os testes `php tests/management-test.php` cobrem edição, preservação de notícias/histórico, contagem de novos e existentes, erros de feed/imagem, fotos ausentes/corrompidas e exclusão. O teste HTTP cobre também edição autenticada, mudança de categoria e ocultação por link direto.


## Notícias armazenadas, nenhuma visível e zero erros de imagem

Versões anteriores pulavam a busca quando o feed não continha um endereço de foto reconhecido. Isso podia resultar em importações com sucesso, muitos itens armazenados e nenhuma publicação com foto. A versão atual reconhece mais formatos e tenta a foto editorial na página original. Se não encontrar, ou se a página recusar acesso, registra uma falha de imagem com o motivo. A regra de publicar somente foto local válida continua obrigatória.

Atualize `rss/core.php` e rode `rss-importar.php --force`. A consulta inicial pode demorar porque cada matéria sem foto exige uma requisição adicional (limite de 2 MB, timeout de 15 segundos por página), seguida do download da imagem. Nas próximas importações, uma foto local válida já associada ao mesmo item é reaproveitada. TLS, validação de DNS/IPs, limites de download e validação de imagens continuam ativos; não há contorno de bloqueios 403, login ou conteúdo restrito. O texto exibido continua sendo o fornecido pelo RSS.

Os testes `php tests/image-discovery-test.php` usam documentos de exemplo para verificar Open Graph, Twitter, XHTML, entidades, reaproveitamento, cache inválido e diagnósticos de ausência/bloqueio. As fontes externas desta instalação não puderam ser consultadas neste ambiente devido à política de rede; a descoberta nas fontes reais deve ser confirmada após executar o importador no XAMPP.


## Guia de gastronomia e terminal aparentemente parado

`indicador-gastronomia.php` e `indicador.php` usam o banco do guia, configurado separadamente em `DB_CONV_*`, com a tabela `lojas`. Configurar somente o banco das matérias (`DB_*`, tabela `materia`) não configura automaticamente o guia.

Se aparecer a mensagem de conexão ausente, abra `instalar.php`, entre com sua senha, marque **Configurar também o guia** e preencha os dados do banco que contém `lojas`. Salvar valida essa tabela antes de gravar. Não é necessário criar um banco novo se ela já existe em um banco seu. Se usar uma cópia local no XAMPP, importe a base que contém `lojas` pelo phpMyAdmin. Não presuma que ela esteja no mesmo banco das notícias.

As páginas do guia agora tratam ausência/falha de conexão sem mostrar exceção ou stack trace ao visitante, e não consultam mais a tabela de notícias para montar o cabeçalho. A falta de configuração continua exigindo preencher os dados; uma mensagem tratada não significa que os estabelecimentos foram recuperados. As imagens/logos originais precisam existir nas pastas do site.

O importador agora imprime início, fonte, posição do item, busca da foto, erros e resultado por fonte. Antes, ele só mostrava resultados depois de terminar todas as fontes; uma execução com muitas páginas sem foto podia ficar vários minutos sem saída. A ausência de um novo prompt no terminal indica que o processo ainda não devolveu o controle. Aguarde o progresso; se quiser substituir os arquivos durante uma execução antiga, interrompa essa execução com **Ctrl+C**, atualize o código e execute novamente. Não inicie processos adicionais em paralelo: o bloqueio impedirá importações concorrentes. Nas próximas execuções, imagens válidas já baixadas podem ser reaproveitadas.


## Paginação, limite por categoria e demais guias

O filtro “Todas” foi removido. `noticias.php` continua como entrada geral pelo menu Notícias; as abas levam diretamente a Viagem, Lazer, Gastronomia, Eventos e Esporte. As listagens têm no máximo **15 cards por página e 6 páginas**. Os botões Anterior/Próxima, números e indicação da página atual se adaptam ao celular. Atualize também `news-pagination.css` e `header.php`.

O limite de 15 inclui RSS e matérias editoriais, misturadas pela ordem de cadastro, até 90 posições por listagem. A página inicial tem até quatro chamadas por seção configurável.

Ao terminar uma importação, o sistema **exclui permanentemente** as notícias RSS mais antigas que excederem 90 registros por categoria, somando todas as fontes dessa categoria. A ordem é data de publicação e ID como desempate; inclui registros ocultos ou de fontes pausadas. Alterar uma fonte também aplica a limpeza, inclusive quando ela é movida para uma categoria que já atingiu o limite. Faça backup antes da primeira execução com esta atualização se quiser preservar o histórico de matérias antigas. As matérias editoriais da tabela MySQL `materia` não são apagadas. O histórico das importações é preservado; imagens das notícias excluídas são removidas somente se nenhum outro registro as utiliza.

Para aplicar o limite à base existente, atualize os arquivos e execute novamente `rss-importar.php --force`. Sem nova importação, a listagem já limita as posições a 90, mas registros anteriores ainda podem estar armazenados até a limpeza. Fontes podem reenviar itens antigos: o limite continua sendo reaplicado ao fim de cada execução.

Foram adicionados `indicador-compras.php`, `indicador-hospedagem.php` e o endereço compatível `indicador-hospedage.php`. Todos usam `guia/listing.php` com o mesmo banco `DB_CONV` e a tabela `lojas`. Os filtros são `categoria = compras`, `categoria = hospedagem` e `categoria = gastronomia`. Se a conexão estiver correta mas não houver estabelecimentos nessas categorias, será exibida uma mensagem de lista vazia. Envie também a pasta `guia`, além dos arquivos das páginas.

Execute `php tests/pagination-test.php` para verificar limite por categoria, ordenação, descarte, preservação de fotos compartilhadas, histórico e limites da paginação. Os testes HTTP verificam os 15 cards, a sexta página, os links do navegador e a existência das três rotas de guia novas/compatíveis; não foi possível confirmar o conteúdo do MySQL real neste ambiente.


## Administrar a página inicial, notícias e empresas

Acesse **admin.php** com a mesma senha do painel RSS. O link também está disponível em `rss-admin.php`. Atualize **todos os arquivos**, inclusive a pasta nova `cms`, `admin.php`, `categoria.php`, `noticia.php`, `media.php` e o CSS. Não precisa reinstalar: as tabelas adicionais são criadas automaticamente no SQLite privado já utilizado pelo site. Faça backup desse arquivo e de suas imagens antes de atualizar.

### Página inicial e seções

Após Última Edição, as seções iniciais são Ponta Grossa, Viagem, Lazer, Gastronomia e Esporte, nessa ordem. Cada uma mostra até quatro notícias recentes, com apenas título e foto clicável. O desktop usa quatro colunas; telas intermediárias usam duas e celulares menores, uma. A seção fica vazia se ainda não houver notícias daquela categoria. Para alimentar Ponta Grossa por RSS, edite a fonte e escolha a categoria **ponta-grossa**; cadastrar uma notícia como noticias não a coloca automaticamente nessa seção.

Na área **Seções**, crie, edite, reordene, oculte ou exclua uma seção. Escolha título, categoria e cor do título. Categorias novas também ficam disponíveis no painel RSS e pelo endereço `categoria.php?categoria=...`. Arraste as seções pela alça para mudar a sequência; a ordem é salva automaticamente ao soltar. Os botões de subir/descer oferecem a mesma função. Excluir uma seção não apaga suas notícias. Banners exclusivos da seção são removidos; os que também aparecem nas matérias são preservados nesses locais.

Na área **Banners**, envie uma imagem ou informe seu endereço, informe o link opcional, marque os locais de exibição: página inicial, matérias RSS e/ou notícias manuais. Para a inicial, escolha depois de qual seção aparecerá. Nas matérias, aparece entre o cabeçalho e o conteúdo. Marque **Banner ativo**. Todos os anúncios ficam centralizados. Desmarcar oculta o anúncio sem excluir o cadastro. Não é permitido inserir scripts/HTML de anúncios; os espaços recebem imagem e link, identificados como publicidade.

### Notícias manuais e ordem de cadastro

Na área **Notícias manuais**, cadastre título, categoria, imagem e texto. Pode enviar JPEG/PNG/GIF/WebP de até 5 MB (também sujeito a upload_max_filesize/post_max_size do PHP) ou informar um endereço de imagem. Os textos são exibidos com parágrafos, sem executar HTML. As novas notícias abrem em `noticia.php`; as anteriores do MySQL continuam usando `materia.php`. As galerias e créditos anteriores são preservados quando fornecidos pela base.

RSS e notícias manuais não ficam separados na home nem nas categorias. Uma ordem comum registra os cadastros novos; editar uma notícia não a move para o início. Os registros antigos não tinham uma data comum de entrada: o MySQL usa a data disponível (quando existe) ou a ordem de seus IDs na carga inicial; o RSS antigo usa a data que já possuía. Essa primeira ordenação é aproximada. Os novos cadastros do painel e as novas importações recebem data/ordem de entrada. Registros novos percebidos posteriormente no MySQL sem data recebem a data da primeira leitura pelo site. A paginação e a regra de foto local válida para RSS continuam funcionando.

### Empresas, acessos e WhatsApp

Na área **Empresas**, cadastre/edite/exclua empresas de Gastronomia, Compras e Hospedagem. O formulário inclui nome, endereço curto (único), telefone, endereço, descrição, WhatsApp opcional, logotipo, capa, galeria e URL do mapa. As imagens aceitam envio de arquivo ou endereço. Empresas novas podem ser cadastradas mesmo sem um MySQL novo; passam a aparecer nos mesmos guias.

Cada perfil mostra o botão **Falar pelo WhatsApp** quando o número é válido e preenchido. Números brasileiros aceitam DDD; internacionais devem incluir o código do país. O painel mostra acessos ao perfil por empresa: é contada no máximo uma visita por empresa a cada 30 minutos na mesma sessão do navegador. O contador começa nesta atualização, não representa visitantes únicos históricos e não armazena IPs. Renomear uma empresa não zera seus acessos.

### Dados existentes e backups

As tabelas MySQL `materia` e `lojas` são lidas como antes. **O novo painel não altera fisicamente essas tabelas:** novos cadastros, correções e exclusões de exibição ficam no SQLite privado. Ao editar uma notícia/empresa antiga, a versão corrigida é aplicada no site; ao excluir, ela deixa de aparecer e seu endereço deixa de abrir. O registro original continua no MySQL. Isso evita exigir alterações no esquema legado que não foi fornecido. Alterações feitas diretamente em formulários antigos/MySQL não substituem uma versão que já foi editada pelo novo painel. Exportações externas que leem apenas MySQL não incluem as alterações do painel.

Inclua o SQLite, a pasta `cms-media` e a configuração privada nos backups. Os arquivos enviados são armazenados fora da raiz pública e servidos por `media.php` com tipo validado. Arquivos de mídia compartilhados não são apagados automaticamente ao excluir um cadastro.

### Apresentação das matérias

As páginas `rss-materia.php`, `materia.php` e `noticia.php` têm maior afastamento do cabeçalho, data sem horário, créditos pequenos à direita da imagem e espaço antes do texto. Informe os créditos das notícias manuais no campo **Créditos da foto**; o RSS usa o nome da fonte. No final, até quatro sugestões misturam as origens da mesma categoria e excluem a matéria aberta.

Atualize também a pasta `cms` e `section-order.php`. A estrutura privada é atualizada automaticamente, preservando banners existentes como anúncios da página inicial; não é necessário reinstalar.

### Verificação do CMS

```bash
php tests/cms-test.php
php tests/detail-migration-test.php
PHP_BIN=php python3 tests/cms-http-test.py
PHP_BIN=php python3 tests/layout-browser-test.py
```

Os testes cobrem seções/cores/banners, cadastro/edição/exclusão, upload validado, ordenação entre origens com dados de exemplo, fotos clicáveis, quatro chamadas sem resumo, WhatsApp e contagem de acessos sem inflar por recarga. A leitura da base MySQL real da hospedagem continua dependendo da conexão externa e não foi confirmada neste ambiente.


### Dashboard, visitas e cliques no WhatsApp

`admin.php` abre no Dashboard, com menus na barra lateral e Configurações em uma área própria. As áreas Seções, Banners, Notícias manuais e Empresas mostram inicialmente os cadastros; clique em **Adicionar item** ou **Editar** para abrir o formulário. Empresas aparecem por categoria em cartões, com até cinco por linha em telas grandes.

O gráfico permite escolher dias do mês, semanas do mês ou os doze meses do ano selecionado. As semanas são faixas do mês: 1–7, 8–14, 15–21, 22–28 e 29–31. O ranking mostra até cinco matérias publicadas com mais visitas no mesmo período, misturando RSS, MySQL e notícias locais.

A coleta começa após esta atualização, pelo script público do rodapé. Visitas gerais, páginas e matérias são deduplicadas por sessão durante 30 minutos; são métricas aproximadas de navegação, sem registrar IP ou identificação pessoal. Navegadores sem JavaScript não entram nas métricas do site. Os acessos da sessão autenticada no painel são excluídos dessas novas contagens. Os acessos históricos dos perfis de empresas são preservados. Os novos cliques de WhatsApp são contados no redirecionamento `empresa-whatsapp.php`, com deduplicação de 30 minutos por sessão e empresa; não medem mensagens enviadas nem conversas iniciadas.

O botão **Atualizar RSS agora** aparece dentro de `admin.php` e antecipa a próxima importação. Ele exige login e proteção CSRF, mostra resultados por fonte e informa quando outra execução já está ativa. Os detalhes continuam no histórico de Fontes RSS. Falhas de uma fonte não impedem processar as demais. Não use `--force` na tarefa automática: essa opção ignora o intervalo e destina-se a testes manuais.

Atualize os novos arquivos da raiz, as pastas `cms` e `rss`, além de `footer.php`, `indicador.php`, `instalar.php` e `rss-importar.php`. Não é preciso reinstalar nem criar um banco MySQL: as tabelas de métricas são criadas automaticamente no SQLite privado. Faça backup do armazenamento privado antes da atualização.

Verificação adicional:

```bash
php tests/dashboard-test.php
PHP_BIN=php python3 tests/metrics-http-test.py
PHP_BIN=php python3 tests/layout-browser-test.py
```
