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
7. Abra **rss-admin.php** para cadastrar, editar nome/categoria (salvando novamente a mesma URL), pausar/ativar ou excluir fontes. Excluir remove também seus itens. Fontes pausadas são ocultadas.

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

Os cards e o CSS atuais são mantidos. A página inicial e categorias exibem primeiro as matérias locais e depois o bloco RSS, ordenado por data. As duas bases não têm ordenação conjunta. O RSS usa SQLite, sem modificar `materia`. O importador aceita RSS/Atom, evita duplicatas da mesma fonte, atualiza itens modificados e exibe resumo em texto com crédito e link original, sem extrair a íntegra das páginas de terceiros.

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

Neste ambiente passaram 24 verificações RSS, 18 HTTP e 26 do instalador, além da sintaxe PHP. As verificações do instalador cobrem ativação, autenticação, campos, falha de conexão, persistência privada, consumo do código e intervalo de cron. O sucesso de uma conexão MySQL real não foi testado: o domínio informado não resolveu neste ambiente (erro 2002). Uma busca RSS externa também falhou em DNS. A conexão real, feeds e agendamento precisam ser confirmados na hospedagem. Imagens e edições originais não foram fornecidas para teste.
