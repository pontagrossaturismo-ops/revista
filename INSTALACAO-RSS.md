# RSS na Revista PG Turismo

## O que foi ajustado

- Painel `rss-admin.php`: login, cadastro de fontes RSS/Atom, categoria, pausa/ativação, exclusão e diagnóstico da última importação. Para editar nome/categoria, cadastre novamente a mesma URL.
- `rss-importar.php`: importação pelo terminal/cron, sem duplicar o mesmo item de uma fonte. Atualiza título, resumo, imagem e link quando o item muda. Erros de uma fonte não interrompem as demais nem apagam as notícias anteriores.
- Notícias RSS aparecem na página inicial e nas categorias, usando os mesmos cards/CSS. Matérias do MySQL continuam disponíveis e separadas das notícias RSS. Os blocos são exibidos em sequência: matérias locais e depois feeds ordenados por data; não há ordenação conjunta entre as duas bases.
- Conteúdo importado é resumo em texto, com crédito e link para ler na fonte. O site não extrai a íntegra das páginas de terceiros.
- `feed.php` exporta as notícias RSS já importadas; aceita `?categoria=viagem` e as demais categorias. As rotas antigas `g1_turismo_rss.php` e `g1_turismo_generate_rss.php` agora exportam a categoria viagem, e não uma fonte G1 fixa. Cadastre o G1 no painel se desejar.
- O proxy de páginas foi desativado, eliminando a busca sem verificação TLS. `example_display.php` e `display.php` usam o armazenamento local.

## Instalar na hospedagem

1. Faça backup do site e do banco. Envie os arquivos deste pacote para a mesma pasta do site. Mantenha as pastas existentes `img`, `imagens`, `edicoes` e demais arquivos não enviados para análise.
2. Use PHP **8.1 ou superior**, com `curl`, `SimpleXML`, `PDO`, `pdo_sqlite` e `mysqli`/`mysqlnd`. Não há dependências Composer.
3. Configure no servidor as variáveis `DB_HOST`, `DB_USER`, `DB_PASSWORD`, `DB_NAME` e, se necessário, `DB_PORT` (padrão 3306). `log-conv.php` usa os nomes equivalentes com prefixo `DB_CONV_`. Os arquivos enviados continham credenciais: troque as senhas antigas. Nenhuma senha foi incluída no pacote ajustado.
4. Configure `RSS_DATA_DIR` com um caminho absoluto **fora da pasta pública**. Exemplo: `/home/usuario/revista-rss-data`. A pasta será criada automaticamente, mas seu diretório pai precisa permitir escrita pelo PHP/cron. Use o mesmo usuário e o mesmo caminho no PHP web e no cron. Essa pasta contém o SQLite; inclua-a nos backups. Não exige mudar a tabela `materia`.
5. Gere um hash de senha, com entrada oculta em um terminal Bash:

   ```bash
   read -r -s -p 'Senha do painel: ' RSS_PANEL_PASSWORD; echo
   export RSS_PANEL_PASSWORD
   php -r 'echo password_hash(getenv("RSS_PANEL_PASSWORD"), PASSWORD_DEFAULT), PHP_EOL;'
   unset RSS_PANEL_PASSWORD
   ```

   Salve o hash obtido na variável de servidor `RSS_ADMIN_PASSWORD_HASH`. Não coloque a senha ou o hash em arquivos públicos. A geração acima ocorre na sua hospedagem; não precisa enviar valores no chat. Se a hospedagem não oferecer variáveis, configure-as pelo painel ou pela configuração PHP/FPM fora do diretório público.
6. Abra `rss-admin.php` por **HTTPS**, entre e cadastre nome, URL do feed e categoria. O painel inicia bloqueado enquanto o hash não estiver configurado. Recomenda-se limitar tentativas de login no servidor/provedor.
7. Teste a importação no terminal:

   ```bash
   cd /caminho/absoluto/do/site
   php rss-importar.php
   ```

   As variáveis também precisam estar disponíveis no terminal. Código de saída 1 indica falha de alguma fonte; consulte a mensagem e a última tentativa no painel.
8. Programe o cron da hospedagem a cada 15 minutos:

   ```cron
   */15 * * * * cd /caminho/absoluto/do/site && /usr/bin/php rss-importar.php >> /home/usuario/revista-rss-importacao.log 2>&1
   ```

   Adapte caminhos, configure as variáveis no ambiente do cron e mantenha o log fora da pasta pública. Faça rotação do log pelo provedor. Nenhuma atualização depende de visitas ao site. Uma segunda execução simultânea é evitada por bloqueio.
9. Verifique notícias, categoria, crédito/link original e `feed.php`. Se a fonte falhar, o conteúdo anterior é preservado e o painel mostra o erro. Não cadastrar uma URL da página inicial: use o endereço RSS/Atom disponibilizado pelo veículo.

## Segurança e operação

A importação valida protocolos, DNS e IPs públicos, fixa o endereço resolvido no cURL, verifica certificados TLS, valida cada redirecionamento, bloqueia XML com DTD/entidades e limita cada resposta a 2 MB/100 itens. O servidor precisa permitir DNS e conexões de saída HTTP/HTTPS diretas para as fontes; este importador não usa proxies externos. Resumos são tratados como texto e escapados ao exibir. Fontes pausadas são ocultadas; excluir uma fonte remove seus itens. Não há limite global de retenção: monitore o tamanho do SQLite e seus backups.

## Validação realizada neste ambiente

Sintaxe de todos os arquivos PHP validada com PHP 8.4. Passaram 24 verificações de RSS/armazenamento e 18 verificações HTTP (login, CSRF, páginas, categorias, pausa e logout). Testes automatizados: parsing RSS/Atom, entidades, URLs relativas, imagens, documentos inválidos, bloqueio de endereços privados, duplicatas, atualização, categorias, pausa, exclusão e exportação RSS. Execute novamente com:

```bash
php tests/rss-test.php
```

O banco MySQL original, imagens, edições e configuração da hospedagem não foram fornecidos. A integração com essas partes precisa ser validada na hospedagem. A busca de um feed externo foi bloqueada por resolução DNS neste ambiente; o recebimento real de fontes e o cron da hospedagem ainda precisam ser confirmados após a configuração.
