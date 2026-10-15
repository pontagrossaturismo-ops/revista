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
   - Senha do painel (12 caracteres ou mais), confirmação e intervalo de importação.
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

O importador respeita o intervalo escolhido na tela (5 a 1440 minutos) e impede execuções simultâneas. O site não tem acesso administrativo ao painel da hospedagem para cadastrar essa tarefa sozinho. Não há atualização dependente de visitas. Mantenha/rotacione logs fora da pasta pública. Se configurou REVISTA_CONFIG_DIR no PHP web, forneça o mesmo valor ao cron.

Para testar imediatamente:

```bash
cd /caminho/absoluto/do/site
php rss-importar.php --force
```

Código de saída 1 indica falha de uma ou mais fontes. O erro e a última tentativa também aparecem no painel. Falhas não apagam notícias anteriores nem impedem processar as demais fontes.

## Como as notícias aparecem

Os cards e o CSS atuais são mantidos. A página inicial e categorias exibem primeiro o bloco identificado “Notícias das fontes RSS”, ordenado por data, e depois as matérias locais. As duas bases não têm ordenação conjunta. O RSS usa SQLite, sem modificar `materia`. O importador aceita RSS/Atom, evita duplicatas da mesma fonte, atualiza itens modificados e abre as matérias dentro do próprio site, com crédito e link original. Importa o texto disponibilizado no RSS, incluindo content:encoded quando presente; não extrai a íntegra das páginas externas.

`feed.php` exporta notícias importadas e aceita `?categoria=viagem` e demais categorias. As rotas antigas `g1_turismo_rss.php` e `g1_turismo_generate_rss.php` exportam a categoria viagem; para receber G1, cadastre sua URL RSS no painel. `example_display.php` e `display.php` usam o armazenamento local. O proxy de páginas foi desativado.

A importação verifica TLS, protocolos, DNS/IPs públicos e cada redirecionamento; limita respostas a 2 MB/100 itens e bloqueia DTD/entidades XML. Precisa de DNS e saída HTTP/HTTPS direta para as fontes. URLs devem ser de feeds, não páginas iniciais. Recomenda-se limitar tentativas de login pelo servidor. Não há retenção global automática: monitore SQLite e backups.

## Alternativa: variáveis de ambiente

A configuração por variáveis continua compatível: `DB_HOST`, `DB_USER`, `DB_PASSWORD`, `DB_NAME`, `DB_PORT` (3306); equivalentes `DB_CONV_*` para o guia; `RSS_DATA_DIR` fora da pasta pública; `RSS_ADMIN_PASSWORD_HASH`; `RSS_INTERVAL_MINUTES`; `SITE_TITLE`, `SITE_DESCRIPTION` e `SITE_URL`. Não é necessário preenchê-las quando usar apenas a tela.

## Validação

```bash
php tests/rss-test.php
PHP_BIN=php python3 tests/http-test.py
PHP_BIN=php python3 tests/setup-test.py
```

Neste ambiente passaram 24 verificações RSS, 37 HTTP e 26 do instalador, além da sintaxe PHP. As verificações do instalador cobrem ativação, autenticação, campos, falha de conexão, persistência privada, consumo do código e intervalo de cron. O sucesso de uma conexão MySQL real não foi testado: o domínio informado não resolveu neste ambiente (erro 2002). Uma busca RSS externa também falhou em DNS. A conexão real, feeds e agendamento precisam ser confirmados na hospedagem. Imagens e edições originais não foram fornecidas para teste.

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

Para atualização automática no Windows, use o Agendador de Tarefas: programa `C:\xampp\php\php.exe`, argumento `"C:\xampp\htdocs\PG-2025\novo\rss-importar.php"`, repetindo a cada minuto. O intervalo configurado continua sendo respeitado. Os caminhos Windows foram verificados por testes de normalização/isolamento no Linux; ainda é necessário confirmar a execução em um XAMPP real.


## Importou itens, mas não encontrou as notícias?

Atualize todos os arquivos PHP do projeto, incluindo `index.php`, `noticias.php`, as páginas de categoria e as pastas `rss`/`config`. Atualizar apenas o importador não adiciona a exibição às páginas antigas. Preserve a configuração privada e o arquivo SQLite existente; não apague nem reinstale os dados para diagnosticar.

Abra `noticias.php#noticias-rss`: o bloco “Notícias das fontes RSS” aparece antes das matérias locais. Em `rss-admin.php`, confira os totais armazenados/visíveis e os caminhos em “Diagnóstico do armazenamento”. Compare com a execução:

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

Os cards abrem `rss-materia.php?id=...` na mesma aba, usando o layout atual. A página mostra título, fonte, data, imagem principal e texto do feed. Se a fonte publicar apenas resumo, o site mostrará apenas esse resumo, com link para consultar o original. Não há extração automática de páginas externas.

São reconhecidas imagens em media:content/media:thumbnail (inclusive grupos), enclosure e imagens no HTML de description/content:encoded, incluindo data-src/srcset. A imagem principal é baixada para `images` dentro do armazenamento RSS privado e servida por `rss-imagem.php`, sem expor a pasta. Imagens JPEG, PNG, GIF e WebP são aceitas, com limite de 5 MB e 40 milhões de pixels. Certificados e validação de destino continuam ativos. A primeira atualização pode demorar devido aos downloads.

Falha ao baixar uma imagem não impede armazenar o texto, mas a matéria fica oculta do site até que uma foto válida seja baixada. Não há mais alternativa com imagem remota. Itens cujo feed não forneça imagem também ficam ocultos. O terminal e o painel mostram falhas de imagens por fonte. O texto é exibido escapado, com parágrafos, sem executar HTML/scripts do feed. Inclua a pasta images nos backups; não há limpeza automática de imagens antigas.

Os testes adicionais `php tests/media-test.php` verificam migração de base antiga, conteúdo completo, extração/cache/validação de imagem, atualização sem duplicar e tolerância a erros de download. Os testes HTTP também verificam a leitura interna e a resposta PNG da imagem local.


## Painel de gestão RSS e publicação somente com foto

Acesse `rss-admin.php` com a senha existente. Atualize também `rss-admin.css` e os arquivos PHP do projeto. Não precisa reinstalar: a tabela de histórico é criada automaticamente no SQLite.

- Cada fonte possui **Editar nome, link e categoria**. O formulário vem preenchido e permite alterar a URL sem excluir/recriar a fonte. Alterar a categoria move também suas notícias já importadas. A URL não pode coincidir com a de outra fonte. Os dados anteriores são preservados se o formulário falhar.
- O painel informa notícias armazenadas, publicadas, sem foto local, tentativas de importação, execuções sem erros, falhas de feed e importações com falhas de imagem. Mostra também os erros recentes das imagens.
- O histórico contém uma linha por fonte/tentativa, data UTC, URL utilizada, resultado, quantidade processada, itens novos, existentes reprocessados, erros de imagem e mensagem de falha do feed. O filtro seleciona uma fonte; a tabela mostra as 100 últimas linhas. Os totais abrangem todo o histórico mantido.
- O histórico começa na atualização. Importações anteriores continuam armazenadas, mas não são contadas como execuções históricas inventadas. Reprocessar um item existente não aumenta o contador de novos.
- Somente notícias RSS com arquivo de foto **local, legível e válido** aparecem nos cards, nas páginas internas e no feed XML de saída. Sem foto, download bloqueado, arquivo removido ou corrompido: a matéria fica oculta. Uma foto local válida já baixada pode continuar sendo usada se uma tentativa posterior de download da mesma URL falhar.
- Itens ocultos permanecem armazenados e podem ser recuperados por nova execução do importador. Execute `rss-importar.php --force` no XAMPP para preencher fotos e iniciar o histórico desta versão. Não apague a configuração ou o banco.
- Pausar oculta as notícias da fonte. Excluir remove a fonte, notícias e histórico associados; os arquivos de imagem privados não são limpos automaticamente.

Os testes `php tests/management-test.php` cobrem edição, preservação de notícias/histórico, contagem de novos e existentes, erros de feed/imagem, fotos ausentes/corrompidas e exclusão. O teste HTTP cobre também edição autenticada, mudança de categoria e ocultação por link direto.
