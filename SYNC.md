## 29/04/26 - Contra-cheque com creditos extras, PDF tradicional e WhatsApp

Causa:
- a tela `settings/contra-cheque` ainda mostrava badges de loja para usuarios com multiplas unidades, o que conflita com a regra solicitada de nao exibir nenhuma nesses casos;
- nao existia cadastro de creditos extras por periodo/usuario, entao o contra-cheque nao permitia lancamentos como `Primeiro Domingo`, `Feriado`, `Bonificacao` ou `Outros`;
- a impressao 80mm nao discriminava o tipo gravado desses creditos e nao havia opcao de PDF no formato tradicional brasileiro;
- o cadastro de usuario ainda nao possuia campo `telefone`, impedindo o envio do resumo do contra-cheque por WhatsApp;
- o `UserController` local ainda sobrescrevia `funcao_original` na edicao e filtrava a listagem por `funcao` atual, divergindo do projeto original.

O que foi alterado:
- `settings/contra-cheque` recebeu o botao `+` ao lado das acoes do card, abrindo modal para cadastrar credito extra com os tipos `Primeiro Domingo`, `Feriado`, `Bonificacao` e `Outros`;
- foi criada a tabela `tb28_contra_cheque_creditos` e o model `ContraChequeCredito` para armazenar os creditos por usuario e periodo;
- `PayrollController` passou a carregar, somar e discriminar os creditos extras no payload do contra-cheque;
- quando o usuario tiver mais de uma loja vinculada, `resolveUserUnitNames()` agora retorna vazio e a interface nao mostra nenhuma badge de unidade;
- a impressao 80mm passou a listar cada credito com o tipo gravado, sem usar o texto generico `Creditos extras`;
- foi adicionado o botao `PDF`, reaproveitando a mesma base do contra-cheque para abrir uma impressao em layout tradicional brasileiro;
- foi adicionado o botao `WhatsApp`, que usa o telefone do funcionario para abrir o envio de um resumo do contra-cheque;
- o cadastro e a edicao de usuario passaram a aceitar e persistir o campo `phone`;
- `UserController` foi sincronizado com a regra atual do original: filtro de `funcao` usando `funcao_original` e preservacao de `funcao_original` na edicao;
- testes de usuarios e folha foram ajustados para cobrir telefone, preservacao de `funcao_original`, creditos extras e ocultacao de lojas em usuarios multiunidade.

Como sincronizar no projeto espelho:
- copiar `routes/web.php`;
- copiar `app/Http/Controllers/PayrollController.php`;
- copiar `app/Http/Controllers/UserController.php`;
- copiar `app/Models/User.php` e o novo `app/Models/ContraChequeCredito.php`;
- copiar `resources/js/Pages/Settings/ContraCheque.jsx`;
- copiar `resources/js/Utils/contraChequePrint.js`;
- copiar `resources/js/Pages/Users/UserCreate.jsx` e `resources/js/Pages/Users/UserEdit.jsx`;
- copiar as migrations `2026_04_29_040000_create_tb28_contra_cheque_creditos_table.php` e `2026_04_29_050000_add_phone_to_users_table.php`;
- copiar os ajustes de `tests/Feature/UserManagementTest.php` e `tests/Feature/PayrollReportTest.php` se quiser manter a mesma cobertura automatizada;
- nao ha update nem delete em registros nesta etapa; a mudanca de banco e apenas estrutural.

Arquivos alterados:
- `routes/web.php`
- `app/Http/Controllers/PayrollController.php`
- `app/Http/Controllers/UserController.php`
- `app/Models/User.php`
- `app/Models/ContraChequeCredito.php`
- `resources/js/Pages/Settings/ContraCheque.jsx`
- `resources/js/Utils/contraChequePrint.js`
- `resources/js/Pages/Users/UserCreate.jsx`
- `resources/js/Pages/Users/UserEdit.jsx`
- `tests/Feature/UserManagementTest.php`
- `tests/Feature/PayrollReportTest.php`
- `database/migrations/2026_04_29_040000_create_tb28_contra_cheque_creditos_table.php`
- `database/migrations/2026_04_29_050000_add_phone_to_users_table.php`
- `SYNC.md`

## 24/04/26 - Busca textual do Dashboard sem FULLTEXT

Causa:
- a busca textual do Dashboard em `app/Http/Controllers/ProductController.php` ainda dependia de `MATCH ... AGAINST`;
- foi solicitado remover o indice `FULLTEXT` `tb1_produto_nome_fulltext`;
- sem ajuste no backend, a remocao do indice faria a busca por nome falhar.

O que foi alterado:
- `ProductController::search()` deixou de usar `FULLTEXT` para termos textuais;
- a busca textual passou a usar somente `LIKE '%termo%'` em `tb1_nome`;
- a busca numerica por `tb1_id` e `tb1_codbar` foi mantida como estava;
- criada a migration `2026_04_24_000000_drop_tb1_produto_nome_fulltext_index.php` para remover o indice `tb1_produto_nome_fulltext` sem mexer em registros;
- o `down()` da migration recria o `FULLTEXT` caso seja necessario reverter.

Como sincronizar no projeto espelho:
- copiar `app/Http/Controllers/ProductController.php`;
- copiar `database/migrations/2026_04_24_000000_drop_tb1_produto_nome_fulltext_index.php`;
- executar a migration somente quando quiser remover o indice no banco;
- nao envolve update ou delete em registros.

Arquivos alterados:
- `app/Http/Controllers/ProductController.php`
- `database/migrations/2026_04_24_000000_drop_tb1_produto_nome_fulltext_index.php`
- `SYNC.md`

## 24/04/26 - Fallback com LIKE na busca de produtos do Dashboard

Causa:
- a busca de sugestoes do Dashboard em `app/Http/Controllers/ProductController.php` usava `FULLTEXT ... AGAINST` como unico criterio para nomes;
- esse tipo de indice pode deixar de retornar produtos existentes quando o termo sofre com tokenizacao do MySQL, acentos, caracteres especiais, palavras curtas ou combinacoes que nao formam tokens validos;
- nesses cenarios o frontend recebia lista vazia e exibia `Nenhum produto encontrado`, mesmo com o item cadastrado.

O que foi alterado:
- `ProductController::search()` continua priorizando a busca numerica exata por `tb1_id` e `tb1_codbar`, sem mudar esse comportamento;
- para busca textual, a rotina continua tentando primeiro o `FULLTEXT` em `tb1_nome`;
- quando o `FULLTEXT` nao retorna nenhum produto, a rotina agora faz fallback com `LIKE '%termo%'` em `tb1_nome`;
- o filtro por tipo continua sendo aplicado tanto na busca principal quanto no fallback;
- a ordenacao por status e nome foi mantida, e o ranking por relevancia do `MATCH` continua valendo quando o `FULLTEXT` encontra resultados.

Como sincronizar no projeto espelho:
- copiar `app/Http/Controllers/ProductController.php`;
- nao alterar rotas, frontend ou banco de dados para esta etapa;
- essa sincronizacao e importante especialmente no `C:\xampp\htdocs\pec1`, porque la o sintoma relatado foi o `Nenhum produto encontrado` com produto existente;
- nao envolve migration, update ou delete em banco.

Arquivos alterados:
- `app/Http/Controllers/ProductController.php`
- `SYNC.md`

## 24/04/26 - Relatorio PDR CACHE no dropdown

Causa:
- a lista de produtos carregada no cache rapido do Dashboard nao tinha uma tela de conferencia;
- sem relatorio, ficava dificil validar quais itens estavam entre os 300 produtos usados para leitura rapida por codigo de barras.

O que foi alterado:
- adicionada a rota `reports.pdr-cache` em `routes/web.php`;
- criado o metodo `pdrCache()` em `app/Http/Controllers/SalesReportController.php`;
- o relatorio reutiliza `ProductQuickLookupCache::forRequest()` para exibir a mesma lista usada pelo Dashboard;
- `ProductQuickLookupCache` passou a expor `limit()` e `ttlHours()` para a tela mostrar limite e validade do cache;
- criada a pagina `resources/js/Pages/Reports/PdrCache.jsx`;
- a tela exibe posicao, nome, codigo de barras, ID e valor de venda dos itens do cache;
- adicionado o item `PDR CACHE` no dropdown do layout principal;
- adicionado o item nas telas de menu, organizacao de menu e permissao por perfil;
- adicionado tambem na lista geral de relatorios.

Como sincronizar no projeto espelho:
- copiar `resources/js/Pages/Reports/PdrCache.jsx`;
- adicionar `reports.pdr-cache` em `routes/web.php`;
- adicionar `pdrCache()` em `SalesReportController.php` e o import de `ProductQuickLookupCache`;
- adicionar `limit()` e `ttlHours()` em `app/Support/ProductQuickLookupCache.php`;
- incluir `reports_pdr_cache` em `AuthenticatedLayout.jsx`, `Settings/Menu.jsx`, `Settings/MenuOrder.jsx` e `Settings/ProfileAccess.jsx`;
- incluir o card do relatorio em `Reports/Index.jsx` e na lista retornada por `SalesReportController::index()`;
- nao envolve banco de dados, migrations, updates ou deletes.

Arquivos alterados:
- `app/Support/ProductQuickLookupCache.php`
- `app/Http/Controllers/SalesReportController.php`
- `routes/web.php`
- `resources/js/Pages/Reports/PdrCache.jsx`
- `resources/js/Layouts/AuthenticatedLayout.jsx`
- `resources/js/Pages/Reports/Index.jsx`
- `resources/js/Pages/Settings/Menu.jsx`
- `resources/js/Pages/Settings/MenuOrder.jsx`
- `resources/js/Pages/Settings/ProfileAccess.jsx`
- `SYNC.md`

## 24/04/26 - Cache de produtos mais vendidos para leitura rapida

Causa:
- a venda por codigo de barras dependia de consulta ao servidor mesmo para produtos recorrentes;
- os produtos mais vendidos normalmente sao repetidos muitas vezes na mesma unidade, entao vale carregar esses itens ao abrir o Dashboard;
- sem esse cache inicial, cada leitura de produto ainda nao conhecido precisava aguardar a busca antes de entrar no carrinho.

O que foi alterado:
- criada a classe `app/Support/ProductQuickLookupCache.php`;
- o cache monta os 300 produtos mais vendidos dos ultimos 30 dias por unidade ativa, considerando vendas finalizadas (`status = 1`);
- o cache usa TTL de 8 horas e chave por unidade;
- a rota `/dashboard` agora envia `quickLookupProducts` para o frontend;
- criada a rota `products.quick-lookup` antes de `products.search`;
- `ProductController::quickLookup()` faz busca direta por ID, codigo de barras ou ID extraido de etiqueta de balanca;
- quando o lookup encontra produto fora do cache inicial, ele inclui esse produto no cache da unidade;
- o `Dashboard.jsx` monta um cache local por `id:{tb1_id}` e `barcode:{tb1_codbar}`;
- ao ler codigo numerico, o frontend prioriza o cache local; se nao encontrar, chama `products.quick-lookup` e adiciona o retorno ao cache;
- a busca de sugestoes por nome continua usando `products.search`; entradas numericas deixam de disparar sugestoes.

Como sincronizar no projeto espelho:
- copiar `app/Support/ProductQuickLookupCache.php`;
- adicionar `use App\Support\ProductQuickLookupCache;` em `routes/web.php`;
- alterar a rota `/dashboard` para enviar `quickLookupProducts`;
- adicionar a rota `/products/quick-lookup` antes de `/products/search`;
- adicionar `use App\Support\ProductQuickLookupCache;`, `quickLookup()` e `parseWeightedBarcode()` em `ProductController.php`;
- atualizar `resources/js/Pages/Dashboard.jsx` com `quickLookupProducts`, cache local por ID/codigo de barras e chamada a `products.quick-lookup`;
- nao envolve banco de dados, migrations, updates ou deletes.

Arquivos alterados:
- `app/Support/ProductQuickLookupCache.php`
- `routes/web.php`
- `app/Http/Controllers/ProductController.php`
- `resources/js/Pages/Dashboard.jsx`
- `SYNC.md`

## 24/04/26 - Logs do Laravel ignorados pelo Git

Causa:
- o arquivo `storage/logs/laravel.log` estava sem uma regra especifica no `.gitignore`;
- logs do Laravel podem crescer bastante e nao devem ser versionados junto com o codigo da aplicacao.

O que foi alterado:
- adicionada a regra `/storage/logs/*.log` no `.gitignore`;
- a pasta `storage/logs` continua existindo, mas arquivos `.log` dentro dela passam a ser ignorados pelo Git.

Como sincronizar no projeto espelho:
- adicionar a mesma regra `/storage/logs/*.log` no `.gitignore` do projeto `C:\xampp\htdocs\pec1`;
- nao e necessario alterar banco de dados, migrations ou codigo da aplicacao;
- nao apagar a pasta `storage/logs`, apenas ignorar os arquivos `.log`.

Arquivos alterados:
- `.gitignore`
- `SYNC.md`

## 22/04/26 - Cadastro de boletos volta a usar a unidade ativa para perfis sem select

Causa:
- ao adicionar o select de loja para o perfil `Master`, a rotina de gravacao do boleto em `app/Http/Controllers/BoletoController.php` passou a validar a unidade ativa com `ManagementScope::canManageUnit(...)`;
- essa validacao atende perfis administrativos, mas nao atende `CAIXA`, mesmo quando ele esta com uma unidade valida na sessao;
- com isso, usuarios sem o select de loja viam a unidade ativa na tela, mas o `POST /boletos` retornava `Unidade ativa nao definida para registrar o boleto.`

O que foi alterado:
- em `app/Http/Controllers/BoletoController.php`, o fallback da unidade ativa no metodo `resolveTargetUnitForWrite()` deixou de depender apenas da regra de gestao;
- foi criada a validacao `canWriteBoletoToUnit()`, que:
- continua usando `ManagementScope::canManageUnit(...)` para perfis administrativos;
- passa a aceitar a unidade ativa para perfis criadores sem select, desde que essa unidade esteja vinculada ao proprio usuario via unidade principal ou relacionamento `units`;
- o fluxo do `Master` com select de loja nao foi alterado.

Como sincronizar no projeto espelho:
- em `app/Http/Controllers/BoletoController.php`, localizar o metodo `resolveTargetUnitForWrite()`;
- trocar a validacao final da `activeUnit` para usar uma helper propria de escrita do boleto;
- adicionar a helper `canWriteBoletoToUnit()` usando:
- `ManagementScope::canManageUnit(...)` para admin;
- `ManagementScope::targetUserUnitIds($user)->contains($unitId)` para validar a unidade ativa dos perfis sem select;
- nao alterar a logica do select do `Master` no frontend, porque a correcao e toda no backend;
- nao depende de migration;
- nao ha criacao ou alteracao de tabela.

Arquivos alterados:
- `app/Http/Controllers/BoletoController.php`
- `SYNC.md`

## 22/04/26 - Alinhamento corrigido no campo "Arquivo do certificado"

Causa:
- o campo `input type="file"` estava reutilizando a mesma classe visual dos inputs comuns, incluindo altura fixa;
- o botao nativo interno do navegador nao respeita essa altura do mesmo jeito e acabava parecendo deslocado para cima.

O que foi alterado:
- em `resources/js/Pages/Settings/FiscalConfig.jsx`, o campo `Arquivo do certificado` passou a usar uma classe propria;
- a altura fixa foi removida apenas do `input type="file"`, mantendo padding e borda alinhados com o restante do formulario;
- isso centraliza melhor o botao nativo e o texto do arquivo selecionado.

Como sincronizar no projeto espelho:
- separar o estilo do campo de arquivo do estilo padrao dos inputs em `resources/js/Pages/Settings/FiscalConfig.jsx`;
- nao aplicar `h-12` no `input type="file"`;
- manter as classes `file:*` para preservar a aparencia do botao.

Arquivos alterados:
- `resources/js/Pages/Settings/FiscalConfig.jsx`
- `SYNC.md`

## 22/04/26 - Selecao de unidade da tela fiscal trocada de select para botoes

Causa:
- a tela fiscal ainda usava `select` com botao `Carregar unidade`, exigindo duas interacoes para trocar de loja;
- isso deixava a navegacao mais lenta e diferente do comportamento solicitado.

O que foi alterado:
- em `resources/js/Pages/Settings/FiscalConfig.jsx`, o bloco de selecao da unidade agora usa um botao para cada loja gerenciada;
- ao clicar em uma unidade, a tela recarrega diretamente `settings.fiscal` com a loja selecionada;
- a unidade ativa recebe destaque visual;
- o filtro atual de notas fiscais (`invoiceStatusFilter`) continua preservado ao trocar de unidade.

Como sincronizar no projeto espelho:
- remover o `select` e o submit manual da unidade em `resources/js/Pages/Settings/FiscalConfig.jsx`;
- criar um conjunto de botoes com destaque para a unidade ativa;
- ao clicar, chamar `router.get(route('settings.fiscal'), { unit_id, invoice_status })` preservando scroll/estado.

Arquivos alterados:
- `resources/js/Pages/Settings/FiscalConfig.jsx`
- `SYNC.md`

## 22/04/26 - Filtro inicial de notas fiscais definido como Erro

Causa:
- depois da inclusao dos filtros em `Ultimas notas preparadas`, o valor inicial ainda estava configurado como `all`;
- com isso a tela abria mostrando todas as notas, em vez de priorizar os registros com problema.

O que foi alterado:
- `app/Http/Controllers/FiscalConfigurationController.php` agora usa `error` como filtro padrao de `invoice_status`;
- `resources/js/Pages/Settings/FiscalConfig.jsx` passou a considerar `error` como valor inicial do filtro no frontend;
- ao abrir a tela fiscal sem query string explicita, o botao `Erro` ja fica selecionado.

Como sincronizar no projeto espelho:
- em `FiscalConfigurationController@index`, trocar o valor padrao de `invoice_status` para `error`;
- em `resources/js/Pages/Settings/FiscalConfig.jsx`, ajustar o fallback de `invoiceStatusFilter` para `error`;
- manter o restante da logica dos filtros como esta.

Arquivos alterados:
- `app/Http/Controllers/FiscalConfigurationController.php`
- `resources/js/Pages/Settings/FiscalConfig.jsx`
- `SYNC.md`

## 22/04/26 - Filtros e limite de 20 notas em "Ultimas notas preparadas"

Causa:
- a tela fiscal carregava apenas as ultimas `15` notas sem qualquer filtro por status;
- isso dificultava localizar rapidamente registros de erro, notas assinadas e notas emitidas.

O que foi alterado:
- `app/Http/Controllers/FiscalConfigurationController.php` agora aceita o filtro `invoice_status` pela query string;
- os filtros implementados foram:
- `all` para todas;
- `error` para `erro_validacao` e `erro_transmissao`;
- `signed` para `xml_assinado`;
- `issued` para `emitida`;
- a consulta passou a retornar as ultimas `20` notas do filtro selecionado;
- `resources/js/Pages/Settings/FiscalConfig.jsx` ganhou botoes `Todas`, `Erro`, `Assinada` e `Emitida` na secao `Ultimas notas preparadas`;
- a tela tambem informa explicitamente que esta exibindo as ultimas `20` notas do filtro atual.

Como sincronizar no projeto espelho:
- atualizar `FiscalConfigurationController@index` para ler `invoice_status`, filtrar por status e limitar a `20` registros;
- enviar `invoiceStatusFilter` para o Inertia junto com `invoices`;
- em `resources/js/Pages/Settings/FiscalConfig.jsx`, adicionar os botoes de filtro e recarregar `route('settings.fiscal')` preservando `unit_id`;
- manter a tabela atual e alterar apenas o cabecalho/filtros e a origem da lista.

Arquivos alterados:
- `app/Http/Controllers/FiscalConfigurationController.php`
- `resources/js/Pages/Settings/FiscalConfig.jsx`
- `SYNC.md`

## 22/04/26 - Reorganizacao visual da tela fiscal

Causa:
- a tela `resources/js/Pages/Settings/FiscalConfig.jsx` estava com cards empilhados e grids diferentes entre si;
- `Unidade`, dados da unidade, `Associacao loja e certificado` e `Diagnostico do ambiente` nao ficavam alinhados lado a lado;
- os blocos `Emissao e numeracao` e `Dados do emitente` usavam campos com espacamentos e aparencia inconsistentes em relacao ao layout desejado.

O que foi alterado:
- o topo da tela fiscal foi reorganizado para colocar `Unidade` e o card com dados/endereco da unidade na mesma linha;
- os cards `Associacao loja e certificado` e `Diagnostico do ambiente` passaram a dividir a mesma linha;
- o formulario `Emissao e numeracao` foi refeito com:
- bloco lateral para `Emitir NF-e` e `Emitir NFC-e`;
- linha principal com `CRT`, `Ambiente`, `Serie` e `Proximo numero`;
- secao `Credenciais fiscais` com campos e cards auxiliares na mesma linguagem visual;
- os cards `Reprocessamento fiscal` e `Geracao automatica de notas fiscais` agora aparecem lado a lado com o card do arquivo atual do certificado;
- o bloco `Dados do emitente` foi padronizado com a mesma aparencia visual dos campos acima, mantendo o grid responsivo.

Como sincronizar no projeto espelho:
- replicar em `resources/js/Pages/Settings/FiscalConfig.jsx` as novas classes visuais (`fiscalSectionClassName`, `fiscalPanelClassName`, `fiscalFieldClassName`, `fiscalFileInputClassName`) e o helper `FiscalSectionHeader`;
- reorganizar os grids do topo para manter os pares de cards na mesma linha;
- atualizar o layout do formulario fiscal sem alterar a logica de submit, estados ou rotas;
- preservar todos os nomes de campos e comportamento funcional, mudando apenas estrutura e aparencia.

Arquivos alterados:
- `resources/js/Pages/Settings/FiscalConfig.jsx`
- `SYNC.md`

## 22/04/26 - Impressao em lote dos boletos com barras visiveis no preview

Causa:
- a primeira versao da impressao em lote desenhava as barras com elementos HTML usando `background`;
- no preview ou na impressao do navegador esses fundos podiam ser ignorados, deixando a area da barra em branco.

O que foi alterado:
- em `resources/js/Pages/Finance/BoletoIndex.jsx`, a impressao em lote passou a gerar o codigo de barras como `SVG` inline;
- cada barra preta agora e renderizada como `<rect>` dentro do `SVG`, o que melhora a confiabilidade no preview e na impressao;
- a modal em tela foi mantida como estava, porque o problema ocorria apenas no layout da janela de impressao.

Como sincronizar no projeto espelho:
- trocar a helper de impressao `renderBarcodeBarsHtml` por uma helper que gere `SVG` inline em `resources/js/Pages/Finance/BoletoIndex.jsx`;
- manter a modal em lote atual e alterar apenas o HTML/CSS da janela de impressao;
- preservar descricao, vencimento, valor e os digitos do codigo abaixo da barra.

Arquivos alterados:
- `resources/js/Pages/Finance/BoletoIndex.jsx`
- `SYNC.md`

## 22/04/26 - Modal para ver e imprimir codigos de barras em lote nos boletos

Causa:
- a tela de boletos so permitia visualizar o codigo de barras individualmente em cada boleto;
- nao existia acao para reunir os codigos do resultado atual da pesquisa em uma unica modal com impressao.

O que foi alterado:
- em `resources/js/Pages/Finance/BoletoIndex.jsx` foi adicionado o botao `Ver codigos em lote` no topo da listagem;
- o botao abre uma modal com os boletos carregados na pesquisa atual da pagina;
- cada item da modal mostra `Descricao`, representacao em barras do `barcode`, `Vencimento` e `Valor`;
- a modal inclui botao `Imprimir`, que abre uma janela de impressao com todos os boletos atualmente carregados;
- a impressao reutiliza a mesma logica de segmentos do codigo de barras, mas gera HTML proprio para impressao em lote.

Como sincronizar no projeto espelho:
- em `resources/js/Pages/Finance/BoletoIndex.jsx`, adicionar o estado `showBatchBarcodeModal` e o botao `Ver codigos em lote`;
- criar a modal em lote usando `boletos.data` atual da tela, sem nova requisicao ao backend;
- adicionar as helpers `escapeHtml`, `renderBarcodeBarsHtml` e `buildBoletoBatchPrintHtml` para suportar a impressao;
- manter o layout da modal com descricao, vencimento, valor e barra de cada boleto.

Arquivos alterados:
- `resources/js/Pages/Finance/BoletoIndex.jsx`
- `SYNC.md`

## 22/04/26 - Filtros Inicio e Fim de boletos com calendario nativo

Causa:
- os campos `Inicio` e `Fim` em `resources/js/Pages/Finance/BoletoIndex.jsx` estavam como `type="text"` com digitacao manual;
- por isso a tela de boletos nao mostrava o seletor nativo de calendario para escolher as datas.

O que foi alterado:
- os filtros `Inicio` e `Fim` da listagem de boletos passaram a usar `input type="date"`;
- o estado inicial desses filtros agora trabalha em ISO (`YYYY-MM-DD`) para ser compativel com o calendario do navegador;
- o envio do filtro continua convertendo corretamente para o backend usando a mesma rotina de resolucao de data.

Como sincronizar no projeto espelho:
- em `resources/js/Pages/Finance/BoletoIndex.jsx`, trocar os campos de filtro `Inicio` e `Fim` de `text` para `date`;
- ajustar o estado inicial dos filtros para usar valor ISO em vez de `DD/MM/AA`;
- manter o envio do formulario passando `start_date` e `end_date` em formato ISO.

Arquivos alterados:
- `resources/js/Pages/Finance/BoletoIndex.jsx`
- `SYNC.md`

## 22/04/26 - Modal ampliada do codigo de barras sem rolagem horizontal

Causa:
- a modal ampliada ainda usava largura minima fixa no modo grande da barra;
- isso forcava overflow horizontal e exibia barra de rolagem.

O que foi alterado:
- o modo amplo de `BoletoBarcode` em `resources/js/Pages/Finance/BoletoIndex.jsx` deixou de usar largura minima fixa;
- a barra ampliada agora ocupa `100%` da largura disponivel da modal;
- a rolagem horizontal foi removida no modo ampliado com `overflow-hidden`;
- o modo `compact` do detalhe foi mantido sem mudancas.

Como sincronizar no projeto espelho:
- em `resources/js/Pages/Finance/BoletoIndex.jsx`, remover `min-w` fixa e `overflow-x-auto` do modo amplo de `BoletoBarcode`;
- manter o modo amplo com `w-full` para preencher a largura da modal sem barra de rolagem;
- nao alterar a miniatura clicavel do card de detalhes.

Arquivos alterados:
- `resources/js/Pages/Finance/BoletoIndex.jsx`
- `SYNC.md`

## 22/04/26 - Miniatura clicavel e modal ampliada para codigo de barras do boleto

Causa:
- a primeira versao da barra foi renderizada com largura minima fixa e rolagem horizontal;
- no card `Codigo de barras` do modal de detalhes isso fazia a barra aparecer cortada, sem mostrar tudo de uma vez.

O que foi alterado:
- `resources/js/Pages/Finance/BoletoIndex.jsx` agora usa dois modos no componente `BoletoBarcode`:
- `compact`, para encaixar a barra na largura do campo como miniatura;
- padrao/amplo, para exibir a barra com mais espaco dentro de uma modal dedicada;
- o card `Codigo de barras` do modal de detalhes virou uma area clicavel com texto orientando a ampliar;
- ao clicar, abre uma nova modal maior mostrando o codigo de barras completo com mais espaco horizontal;
- ao fechar o modal principal `Detalhes`, a modal ampliada tambem e encerrada para nao deixar estado pendente.

Como sincronizar no projeto espelho:
- atualizar `BoletoBarcode` em `resources/js/Pages/Finance/BoletoIndex.jsx` para aceitar a prop `compact`;
- no detalhe do boleto, trocar a renderizacao direta da barra por um botao clicavel contendo `<BoletoBarcode value={selectedBoleto.barcode} compact />`;
- adicionar o estado `showBarcodeModal` e a nova `Modal` ampliada que reaproveita o mesmo componente em modo padrao;
- manter o numero do codigo de barras em texto abaixo da miniatura e tambem na modal ampliada.

Arquivos alterados:
- `resources/js/Pages/Finance/BoletoIndex.jsx`
- `SYNC.md`

## 22/04/26 - Representacao em barra no detalhe do boleto

Causa:
- o modal `Detalhes` de boletos exibia apenas o numero bruto de `barcode`;
- nao havia nenhuma rotina no frontend para converter os 44 digitos do boleto em barras visuais.

O que foi alterado:
- criada em `resources/js/Pages/Finance/BoletoIndex.jsx` uma rotina local de renderizacao visual do codigo de barras do boleto;
- a conversao usa os 44 digitos sanitizados de `barcode` e monta a barra em frontend, sem dependencia externa;
- o bloco `Codigo de barras` do modal de detalhes agora mostra primeiro a representacao visual em barras e abaixo continua exibindo o numero para copia;
- quando o valor nao tiver exatamente `44` digitos, o modal mostra uma mensagem de fallback informando que nao foi possivel gerar a barra.

Como sincronizar no projeto espelho:
- replicar as constantes/helpers de barcode e o componente `BoletoBarcode` em `resources/js/Pages/Finance/BoletoIndex.jsx`;
- inserir `<BoletoBarcode value={selectedBoleto.barcode} />` dentro do card `Codigo de barras` do modal `Detalhes`, acima do texto com o numero;
- nao adicionar biblioteca npm: a implementacao foi feita 100% no arquivo da pagina.

Arquivos alterados:
- `resources/js/Pages/Finance/BoletoIndex.jsx`
- `SYNC.md`

## 22/04/26 - Endpoint de boletos com edicao, filtro por loja, total e validacoes

- causa do problema:
  - o modulo de boletos tinha apenas cadastro, listagem e baixa;
  - nao existia rota nem fluxo de edicao;
  - a validacao aceitava qualquer quantidade de caracteres em `barcode` e `digitable_line`;
  - a listagem ficava presa a unidade ativa da sessao, sem filtro por loja;
  - a tela abria sem filtro inicial de data/status;
  - nao havia totalizador da consulta;
  - as linhas da tabela nao diferenciavam visualmente pago, nao pago, vencendo hoje e atrasado;
  - as datas desta tela nao estavam padronizadas em `DD/MM/AA`.

- o que foi ajustado no backend:
  - `BoletoController@index` agora inicia com:
    - `start_date = hoje`
    - `end_date = hoje`
    - `paid = unpaid`
  - a listagem de boletos agora aceita filtro por loja para usuarios administrativos;
  - o filtro de loja respeita o escopo de unidades permitido por `ManagementScope`;
  - o backend agora calcula `listTotalAmount` com base em toda a consulta filtrada, nao apenas na pagina atual;
  - foi criado o endpoint de edicao `PUT /boletos/{boleto}`;
  - a baixa e a edicao deixaram de depender da unidade ativa da sessao e passaram a validar a loja do boleto pelo escopo permitido do usuario;
  - `due_date` passou a aceitar e normalizar `DD/MM/AA`;
  - `barcode` passou a ser sanitizado para apenas digitos e validado com exatamente `44` digitos;
  - `digitable_line` passou a ser sanitizada para apenas digitos e validada com `47` ou `48` digitos;
  - usuario `Master` agora pode selecionar a loja do boleto no cadastro/edicao;
  - usuarios nao-master continuam cadastrando na loja/unidade corrente.

- o que foi ajustado no frontend:
  - a tela `Finance/BoletoIndex` foi reorganizada para deixar `Descricao`, `Vencimento` e `Valor` na mesma linha;
  - o formulario passou a usar datas em `DD/MM/AA`;
  - a listagem ganhou filtro de:
    - loja
    - inicio
    - fim
    - status
  - foi adicionado o card `Total da consulta`;
  - foi implementado o fluxo de `Editar boleto` reaproveitando o formulario superior;
  - o `Master` passou a ver o combo de loja no formulario;
  - a tabela passou a mostrar o codigo de barras abaixo da descricao;
  - o WhatsApp passou a enviar tambem o codigo de barras;
  - as linhas da tabela agora usam legenda e cores:
    - verde: pago
    - laranja: nao pago
    - vermelho: vence hoje
    - pink: atrasado
  - o modal de detalhes foi ajustado para exibir o status com a mesma regra visual da lista.

- arquivos alterados:
  - `app/Http/Controllers/BoletoController.php`
  - `resources/js/Pages/Finance/BoletoIndex.jsx`
  - `routes/web.php`
  - `SYNC.md`

- testes/validacao executados:
  - `php -l app/Http/Controllers/BoletoController.php`
  - `php -l routes/web.php`
  - `npm run build`

- observacoes importantes para sincronizar em `pec1`:
  - sincronizar exatamente:
    - `app/Http/Controllers/BoletoController.php`
    - `resources/js/Pages/Finance/BoletoIndex.jsx`
    - `routes/web.php`
  - nao depende de migration;
  - nao cria tabela nova;
  - o endpoint novo de edicao precisa ir junto com a tela, senao o botao `Editar` quebra no outro projeto;
  - o filtro padrao do modulo agora sempre entra com o dia atual em inicio/fim e `Nao pagos`;
  - o `Master` pode escolher loja no cadastro/edicao, mas os demais perfis continuam gravando na loja corrente;
  - a validacao numerica de boletos passa a rejeitar:
    - `barcode` diferente de `44` digitos
    - `digitable_line` diferente de `47` ou `48` digitos

## 20/04/26 - NF Consumidor com destinatario identificado no caixa

- causa do problema:
  - o fluxo atual do caixa gerava a NFC-e sempre como consumidor nao identificado;
  - na pratica isso era uma NF de balcão, porque o sistema nao coletava dados fiscais do consumidor;
  - o XML era montado sem a tag `<dest>`, entao nao existia forma de emitir NF Consumidor a partir da venda ja registrada.

- regra adotada neste ajuste:
  - a venda continua sendo criada normalmente;
  - a nota segue como balcao enquanto nao houver destinatario informado;
  - no modal final da venda foi criado o botao `NF Consumidor`;
  - ao informar os dados fiscais do cliente, a mesma nota e reprocessada com destinatario identificado;
  - o codigo do municipio IBGE do consumidor passou a ser obrigatorio na modal para garantir XML valido sem depender de consulta externa.

- o que foi ajustado:
  - o modal final da venda agora mostra o consumidor atual da nota;
  - o botao de impressao da nota deixou de exibir `Cupom Fiscal` e passou a identificar a nota como `NF Balcao` ou `NF Consumidor`;
  - foi criado um endpoint para atualizar a nota fiscal da venda com os dados do consumidor;
  - o backend passou a validar e salvar os dados do consumidor dentro de `tb27_payload['consumer']`;
  - o gerador do XML da NFC-e passou a incluir a tag `<dest>` e `enderDest` quando houver consumidor informado;
  - o DANFE 80mm passou a imprimir tambem documento e endereco do consumidor quando a nota for identificada.

- backend envolvido:
  - `routes/web.php`
    - adicionada a rota `sales.fiscal.consumer`.
  - `app/Http/Controllers/SaleController.php`
    - criado o metodo `updateConsumerFiscalInvoice`;
    - adicionada validacao/sanitizacao dos dados fiscais do consumidor;
    - o resumo da nota (`buildFiscalSummary`) agora devolve o consumidor salvo no payload.
  - `app/Support/FiscalInvoicePreparationService.php`
    - `prepareForPayment` agora aceita opcionalmente os dados do consumidor;
    - o payload da nota passou a armazenar `consumer`;
    - a validacao fiscal agora valida CPF/CNPJ, CEP, UF e codigo IBGE do consumidor;
    - a nota reaproveita o consumidor salvo no payload quando for reprocessada.
  - `app/Support/FiscalNfceXmlService.php`
    - `buildSignedXml` passou a receber o consumidor;
    - criada a montagem do grupo `dest` e `enderDest` no XML da NFC-e.

- frontend envolvido:
  - `resources/js/Pages/Dashboard.jsx`
    - adicionada modal `NF Consumidor` no fechamento da venda;
    - adicionado formulario com nome, CPF/CNPJ, CEP, logradouro, numero, complemento, bairro, municipio, codigo municipio IBGE e UF;
    - adicionado envio para o novo endpoint da nota;
    - o botao de impressao fiscal agora mostra `NF Balcao` quando a nota nao tem destinatario e `NF Consumidor` quando a nota tem destinatario.
  - `resources/js/Utils/receipt.js`
    - o DANFE 80mm passou a mostrar documento e endereco do consumidor.

- testes/validacao executados:
  - `php -l app/Http/Controllers/SaleController.php`
  - `php -l app/Support/FiscalInvoicePreparationService.php`
  - `php -l app/Support/FiscalNfceXmlService.php`
  - `php artisan test --filter=FiscalInvoicePreparationServiceTest`
  - `php artisan test --filter=FiscalNfceXmlServiceTest`
  - `npm run build`

- observacoes importantes para sincronizar em `pec1`:
  - sincronizar exatamente:
    - `routes/web.php`
    - `app/Http/Controllers/SaleController.php`
    - `app/Support/FiscalInvoicePreparationService.php`
    - `app/Support/FiscalNfceXmlService.php`
    - `resources/js/Pages/Dashboard.jsx`
    - `resources/js/Utils/receipt.js`
    - `tests/Unit/FiscalInvoicePreparationServiceTest.php`
    - `tests/Unit/FiscalNfceXmlServiceTest.php`
  - nao depende de migration;
  - nao cria tabela nova;
  - os dados do consumidor ficam em `tb27_payload['consumer']`;
  - para emitir NF Consumidor e obrigatorio informar o codigo do municipio IBGE do cliente;
  - o botao `NF Consumidor` so faz sentido antes da nota ser finalizada/transmitida definitivamente; depois de `emitida`, a troca para consumidor fica bloqueada.

## 20/04/26 - Correcao do schema XML da NF Consumidor no grupo `dest`

- causa do problema:
  - a primeira implementacao da `NF Consumidor` montou o grupo `dest` na ordem errada;
  - o XML estava saindo com `indIEDest` antes de `enderDest`;
  - no schema da SEFAZ, depois de `indIEDest` o parser passa a esperar `IE`, `ISUF` ou `IM`, entao a presenca de `enderDest` nessa posicao causava a rejeicao:
    - `Falha de Esquema: O elemento pai: 'dest' não estava esperando o elemento 'enderDest'. O elemento esperado é: 'IE, ISUF,IM,'`.

- o que foi ajustado:
  - corrigida a ordem dos elementos filhos de `dest`;
  - `enderDest` agora e montado antes de `indIEDest`;
  - adicionado teste unitario validando explicitamente a ordem:
    - `CPF/CNPJ`
    - `xNome`
    - `enderDest`
    - `indIEDest`

- arquivos alterados:
  - `app/Support/FiscalNfceXmlService.php`
  - `tests/Unit/FiscalNfceXmlServiceTest.php`
  - `SYNC.md`

- observacoes para sincronizar em `pec1`:
  - sincronizar exatamente:
    - `app/Support/FiscalNfceXmlService.php`
    - `tests/Unit/FiscalNfceXmlServiceTest.php`
  - esta correcao nao altera banco;
  - esta correcao nao altera rotas nem frontend;
  - ela e obrigatoria para a `NF Consumidor` ser aceita pelo schema da SEFAZ.

## 20/04/26 - Ajuste do `urlChave` de GO para eliminar `cStat 878` na NFC-e

- Arquivos alterados:
  - `app/Support/FiscalWebserviceResolverService.php`
  - `tests/Unit/FiscalWebserviceResolverServiceTest.php`
  - `SYNC.md`

- Causa identificada:
  - a `NF Consumidor` estava alternando entre erros, mas o retorno atual confirmado da SEFAZ passou a ser:
    - `cStat 878 - Rejeicao: Endereco do site da UF da Consulta por chave de acesso diverge do previsto`
  - isso aponta diretamente para o valor preenchido em `infNFeSupl/urlChave`;
  - o resolver de GO estava entregando `consulta-completa`, mas esse endereco nao foi aceito pela SEFAZ neste fluxo real.

- O que foi feito:
  - o `FiscalWebserviceResolverService` voltou a mapear `urlChave` de GO para:
    - `http://www.sefaz.go.gov.br/nfce/consulta`
  - o ajuste foi aplicado em homologacao e producao;
  - os testes unitarios do resolver foram atualizados para refletir o endereco esperado.

- Efeito esperado:
  - novas NFC-e deixam de sair com `urlChave` rejeitada pela SEFAZ-GO;
  - a `NF Consumidor` regenerada passa a carregar a URL de consulta por chave alinhada com o retorno real do ambiente.

- Observacoes para sincronizar em `pec1`:
  - levar juntos:
    - `app/Support/FiscalWebserviceResolverService.php`
    - `tests/Unit/FiscalWebserviceResolverServiceTest.php`
  - nao depende de migration;
  - reprocessar as NFC-e ja geradas com `urlChave` anterior, porque o XML antigo continua salvo com o endereco rejeitado;
  - esta etapa corrige apenas a `urlChave`; nao altera `tpImp`, assinatura, `qrCode` nem a separacao funcional entre `Nota fiscal balcao`, `NF Consumidor` e futuro `cupom fiscal`.

## 20/04/26 - Reversao do `tpImp` da NFC-e para `4` apos rejeicao real `709`

- Arquivos alterados:
  - `app/Support/FiscalNfceXmlService.php`
  - `app/Support/FiscalNfceTransmissionService.php`
  - `tests/Unit/FiscalNfceTransmissionServiceTest.php`
  - `tests/Unit/FiscalNfceXmlServiceTest.php`
  - `SYNC.md`

- Causa identificada:
  - uma correcao anterior tinha alterado o fluxo da NFC-e para `tpImp = 1`;
  - porem a evidencia real da nota `520` (`chave 52260462074417000156650010000005201023178152`) mostrou retorno oficial da SEFAZ em `20/04/26` com:
    - `cStat 709 - Rejeicao: NFC-e com formato de DANFE invalido`
  - o XML salvo dessa nota foi transmitido com `mod = 65` e `tpImp = 1`;
  - isso confirmou, no ambiente real deste projeto, que a NFC-e precisa continuar com `tpImp = 4`.

- O que foi feito:
  - `FiscalNfceXmlService` voltou a gerar NFC-e com `tpImp = 4`;
  - `FiscalNfceTransmissionService` voltou a validar a estrutura exigindo `tpImp = 4`;
  - os testes foram atualizados para refletir novamente a regra correta.

- Efeito esperado:
  - novas NFC-e deixam de sair com `tpImp = 1`, que estava provocando `709`;
  - o gerador e a transmissao voltam a ficar coerentes com o retorno real da SEFAZ;
  - as notas de `balcao`, `consumidor` e a representacao de `cupom fiscal` continuam cobertas por esta mesma regra sempre que o modelo fiscal gerado for `65` (NFC-e).

- Observacoes para sincronizar em `pec1`:
  - levar juntos:
    - `app/Support/FiscalNfceXmlService.php`
    - `app/Support/FiscalNfceTransmissionService.php`
    - `tests/Unit/FiscalNfceTransmissionServiceTest.php`
    - `tests/Unit/FiscalNfceXmlServiceTest.php`
  - nao depende de migration;
  - reprocessar as NFC-e geradas com `tpImp = 1`, porque o XML antigo continua salvo com a regra errada;
  - esta etapa nao muda a distincao visual entre `Nota fiscal balcao`, `Nota fiscal Consumidor` e `cupom fiscal`; ela corrige apenas o `tpImp` do XML quando o documento emitido e `NFC-e`.

## 20/04/26 - Correcao do `tpImp` da NFC-e para eliminar a rejeicao `710`

- Arquivos alterados:
  - `app/Support/FiscalNfceXmlService.php`
  - `app/Support/FiscalNfceTransmissionService.php`
  - `tests/Unit/FiscalNfceTransmissionServiceTest.php`
  - `tests/Unit/FiscalNfceXmlServiceTest.php`
  - `SYNC.md`

- Causa identificada:
  - o fluxo da NFC-e estava inteiro baseado na premissa errada de que o DANFE deveria sair com `tpImp = 4`;
  - com isso, o gerador do XML criava a tag `<tpImp>4</tpImp>`;
  - em seguida, a transmissao interna ainda validava que a NFC-e "correta" desta etapa precisava continuar com `tpImp 4`;
  - isso mantinha o sistema preso a um formato que a SEFAZ rejeita com `710 - NF-e com formato de DANFE invalido`.

- O que foi feito:
  - `FiscalNfceXmlService` passou a gerar a NFC-e com `tpImp = 1`;
  - `FiscalNfceTransmissionService` foi alinhado para exigir `tpImp = 1` antes do envio, removendo a regra antiga que obrigava `4`;
  - os testes unitarios foram atualizados para refletir a regra correta e evitar regressao futura.

- Efeito esperado:
  - novas NFC-e deixam de nascer com o formato de DANFE rejeitado pela SEFAZ;
  - o proprio fluxo interno de transmissao deixa de bloquear XML valido por esperar o valor antigo;
  - a rejeicao `710` deixa de ocorrer nas notas regeneradas com esse ajuste.

- Observacoes para sincronizar em `pec1`:
  - levar juntos:
    - `app/Support/FiscalNfceXmlService.php`
    - `app/Support/FiscalNfceTransmissionService.php`
    - `tests/Unit/FiscalNfceTransmissionServiceTest.php`
    - `tests/Unit/FiscalNfceXmlServiceTest.php`
  - nao depende de migration;
  - reprocessar qualquer NFC-e gerada antes desta correcao, porque o XML antigo continua salvo com `tpImp = 4`;
  - manter os demais ajustes ja feitos para `urlChave`, `qrCode` e assinatura, pois esta etapa corrige especificamente o formato do DANFE no `ide`.

## 20/04/26 - Correcao da assinatura fiscal: NFC-e deste ambiente permanece em SHA1

- causa do problema:
  - a tentativa de migrar a assinatura da NFC-e para `SHA-256` gerou rejeicao de schema;
  - a SEFAZ deste ambiente respondeu explicitamente que o XML esperado continua com:
    - `SignatureMethod = rsa-sha1`
    - `DigestMethod = sha1`
  - com isso, o XML assinado em `rsa-sha256` e `sha256` passou a ser rejeitado por incompatibilidade com o schema carregado neste fluxo.

- o que foi ajustado:
  - a geracao da assinatura foi revertida para:
    - `XMLSecurityKey::RSA_SHA1`
    - `XMLSecurityDSig::SHA1`
  - o teste unitario voltou a validar explicitamente:
    - `SignatureMethod = rsa-sha1`
    - `DigestMethod = sha1`
  - notas que tinham sido reprocessadas com `SHA-256` precisam ser reprocessadas novamente para voltar ao formato aceito por este ambiente.

- arquivos alterados:
  - `app/Support/FiscalNfceXmlService.php`
  - `tests/Unit/FiscalNfceXmlServiceTest.php`
  - `SYNC.md`

- observacoes para sincronizar em `pec1`:
  - sincronizar exatamente:
    - `app/Support/FiscalNfceXmlService.php`
    - `tests/Unit/FiscalNfceXmlServiceTest.php`
  - nao depende de migration;
  - neste ambiente, a NFC-e deve permanecer em `SHA1`;
  - se alguma nota tiver sido regenerada com `SHA-256`, ela precisa ser reprocessada para voltar a `SHA1`.

## 20/04/26 - Correcao do `urlChave` da NFC-e em GO

- causa do problema:
  - o XML da NFC-e em GO estava sendo gerado com `urlChave` legado:
    - `http://www.sefaz.go.gov.br/nfce/consulta`
  - esse valor nao seguia o mesmo padrao atual do portal NFC-e usado no `qrCode`;
  - como o erro reportado pela SEFAZ seguia apontando `formato de DANFE invalido` mesmo com `tpImp = 4`, assinatura em `SHA1` e estrutura do `dest` correta, o bloco `infNFeSupl` passou a ser o principal suspeito.

- o que foi ajustado:
  - atualizado o mapeamento dos endpoints de GO para usar `consulta-completa`;
  - agora os valores ficaram:
    - homologacao:
      - `https://nfewebhomolog.sefaz.go.gov.br/nfeweb/sites/nfe/consulta-completa`
    - producao:
      - `https://nfeweb.sefaz.go.gov.br/nfeweb/sites/nfe/consulta-completa`

- arquivos alterados:
  - `app/Support/FiscalWebserviceResolverService.php`
  - `tests/Unit/FiscalWebserviceResolverServiceTest.php`
  - `SYNC.md`

- observacoes para sincronizar em `pec1`:
  - sincronizar exatamente:
    - `app/Support/FiscalWebserviceResolverService.php`
    - `tests/Unit/FiscalWebserviceResolverServiceTest.php`
  - nao depende de migration;
  - depois de sincronizar, reprocessar as notas que estiverem com `urlChave` antiga para regenerar o `infNFeSupl`.

## 16/04/26 - Nomes de produtos convertidos e protegidos em maiusculo

- causa do problema:
  - o campo `tb1_nome` era salvo exatamente como digitado;
  - isso permitia cadastro e alteracao com letras minusculas, deixando a base inconsistente.

- o que foi ajustado:
  - o backend passou a normalizar `tb1_nome` para maiusculo antes de salvar;
  - as telas de cadastro e edicao passaram a converter o nome para maiusculo enquanto o usuario digita;
  - os registros ja existentes em `tb1_produto` tambem foram convertidos para maiusculo.

- backend envolvido:
  - `app/Http/Controllers/ProductController.php`
    - adicionada normalizacao central do nome com `mb_strtoupper`;
    - `store` e `update` passam a salvar o nome sempre em maiusculo via `prepareProductData`.

- frontend envolvido:
  - `resources/js/Pages/Products/ProductCreate.jsx`
    - o campo `tb1_nome` agora converte para maiusculo no `onChange`.
  - `resources/js/Pages/Products/ProductEdit.jsx`
    - o campo `tb1_nome` agora converte para maiusculo no `onChange`.

- arquivos alterados:
  - `app/Http/Controllers/ProductController.php`
  - `resources/js/Pages/Products/ProductCreate.jsx`
  - `resources/js/Pages/Products/ProductEdit.jsx`
  - `SYNC.md`

- observacoes para sincronizar em `pec1`:
  - sincronizar exatamente:
    - `app/Http/Controllers/ProductController.php`
    - `resources/js/Pages/Products/ProductCreate.jsx`
    - `resources/js/Pages/Products/ProductEdit.jsx`
  - nao depende de migration;
  - nao altera estrutura de banco;
  - e importante replicar tambem a conversao dos registros ja existentes no banco de `pec1`.

## 16/04/26 - Nome do produto limitado a 25 caracteres em `products`

- causa do problema:
  - na listagem `products`, nomes longos de produtos estavam sendo exibidos por completo;
  - isso aumentava visualmente a largura da coluna e prejudicava a leitura rapida da tabela.

- o que foi ajustado:
  - o nome do produto passou a exibir no maximo `25` caracteres;
  - quando o nome ultrapassa esse limite, a listagem mostra reticencias;
  - adicionado `title` com o nome completo para consulta no hover.

- frontend envolvido:
  - `resources/js/Pages/Products/ProductIndex.jsx`
    - adicionada regra de truncamento visual do nome;
    - mantido o valor completo no `title`.

- arquivos alterados:
  - `resources/js/Pages/Products/ProductIndex.jsx`
  - `SYNC.md`

- observacoes para sincronizar em `pec1`:
  - sincronizar exatamente:
    - `resources/js/Pages/Products/ProductIndex.jsx`
  - nao depende de migration;
  - nao altera banco;
  - nao altera backend.

## 16/04/26 - Codigo de barras movido para baixo do nome em `products`

- causa do problema:
  - na listagem `products`, o codigo de barras aparecia em uma coluna propria;
  - isso deixava a tabela mais larga e dava destaque excessivo ao codigo, quando o desejado era um visual mais compacto.

- o que foi ajustado:
  - removida a coluna exclusiva `Codigo de barras` da tabela;
  - o codigo de barras passou a ser exibido logo abaixo do nome do produto;
  - o codigo agora usa fonte menor e aparencia mais discreta.

- frontend envolvido:
  - `resources/js/Pages/Products/ProductIndex.jsx`
    - removido o header da coluna de codigo de barras;
    - removida a celula separada do codigo de barras;
    - o nome do produto passou a renderizar o codigo abaixo dele em `text-xs`.

- arquivos alterados:
  - `resources/js/Pages/Products/ProductIndex.jsx`
  - `SYNC.md`

- observacoes para sincronizar em `pec1`:
  - sincronizar exatamente:
    - `resources/js/Pages/Products/ProductIndex.jsx`
  - nao depende de migration;
  - nao altera banco;
  - nao altera backend.

## 16/04/26 - Correcao de IDs de produtos fora do padrao e bloqueio preventivo no cadastro

- causa do problema:
  - a tabela `tb1_produto` estava com `AUTO_INCREMENT` contaminado por inclusoes com IDs muito altos;
  - tambem existiam produtos cadastrados na faixa `3000` a `3100`, que e reservada para comandas;
  - no backend de produtos, o cadastro do tipo `Balanca` aceitava `tb1_id` manual apenas com `min:1`, sem bloquear a faixa reservada nem limitar o tamanho do ID;
  - para produtos nao-balanca, o sistema dependia do `AUTO_INCREMENT` do banco, entao depois que um ID gigante entrou, os proximos produtos continuaram sendo criados fora do padrao.

- diagnostico confirmado no banco antes da correcao:
  - `23` produtos estavam com IDs entre `3000` e `3100`;
  - `64` produtos estavam com IDs acima de `9999`;
  - total de `87` produtos afetados;
  - havia `182` registros relacionados em `tb3_vendas`;
  - havia `2` registros relacionados em `product_discards`;
  - o `AUTO_INCREMENT` da `tb1_produto` estava em `2191800009062`.

- regra aplicada na correcao:
  - os produtos validos continuam ate `2999`;
  - a faixa `3000` a `3100` continua reservada exclusivamente para comandas;
  - todos os produtos afetados foram remapeados em ordem de criacao (`created_at ASC`, `tb1_id ASC`);
  - o novo de/para passou a ocupar a faixa `3101` ate `3187`;
  - o proximo ID seguro ficou `3188`.

- o que foi corrigido no banco:
  - remapeados `87` produtos de `tb1_produto`;
  - atualizados os IDs vinculados em `tb3_vendas`;
  - atualizados os IDs vinculados em `product_discards`;
  - verificada a tabela `tb25_produto_movimentacoes` sem registros afetados no momento;
  - ajustado o `AUTO_INCREMENT` de `tb1_produto` para `3188`;
  - apos a correcao:
    - `0` produtos ficaram na faixa reservada;
    - `0` produtos ficaram com IDs acima de `9999`;
    - `0` referencias invalidas permaneceram em `tb3_vendas`, `product_discards` e `tb25_produto_movimentacoes`.

- o que foi ajustado no codigo para nao voltar a acontecer:
  - `app/Http/Controllers/ProductController.php`
    - criadas constantes para:
      - inicio da faixa reservada (`3000`);
      - fim da faixa reservada (`3100`);
      - limite maximo seguro (`9999`);
    - cadastro de `Balanca` agora bloqueia IDs na faixa reservada;
    - cadastro de `Balanca` agora bloqueia IDs acima de `9999`;
    - novos produtos nao-balanca deixaram de depender do `AUTO_INCREMENT` contaminado;
    - novos produtos nao-balanca passam a receber o proximo ID seguro gerado pelo metodo `nextSafeProductId()`;
    - o metodo pula automaticamente a faixa `3000` a `3100`.
  - `resources/js/Pages/Products/ProductCreate.jsx`
    - adicionada orientacao visual informando que IDs `3000` a `3100` sao reservados para comandas.

- arquivos alterados:
  - `app/Http/Controllers/ProductController.php`
  - `resources/js/Pages/Products/ProductCreate.jsx`
  - `SYNC.md`

- observacoes para sincronizar em `pec1`:
  - sincronizar exatamente:
    - `app/Http/Controllers/ProductController.php`
    - `resources/js/Pages/Products/ProductCreate.jsx`
  - esta correcao teve duas partes:
    - parte de codigo, que deve ser sincronizada;
    - parte de banco/dados, que foi executada diretamente neste ambiente;
  - se o banco do `pec1` apresentar o mesmo problema, repetir a auditoria antes de aplicar qualquer remapeamento;
  - criterio usado no de/para:
    - selecionar produtos com `tb1_id` entre `3000` e `3100` ou acima de `9999`;
    - ordenar por `created_at ASC`, depois `tb1_id ASC`;
    - reatribuir sequencialmente de `3101` em diante;
    - atualizar referencias em `tb3_vendas`, `product_discards` e `tb25_produto_movimentacoes`;
    - ajustar o `AUTO_INCREMENT` para o proximo ID seguro;
  - este ajuste nao criou migration porque a correcao foi de dados existentes no banco, nao de estrutura.

## 15/04/26 - Novo tipo `Producao` com estoque e tela de movimentacao

- causa do problema:
  - o cadastro de produtos reconhecia apenas os tipos `Industria`, `Balanca` e `Servico`;
  - o campo `tb1_qtd` nao participava do model, validacao, create, edit, listagem nem visualizacao;
  - tambem nao existia tela propria para registrar entrada e saida dos produtos de `Producao`;
  - como a regra definida foi nao controlar estoque pela venda, faltava um fluxo administrativo dedicado para essas movimentacoes.

- regra adotada neste ajuste:
  - o novo tipo de produto ficou como valor `3` com label `Producao`;
  - produtos `Producao` usam `codigo de barras` como os tipos nao-balanca;
  - o estoque nao e debitado na venda;
  - entradas e saidas sao registradas manualmente em tela propria;
  - a nova tabela seguiu o proximo sequencial livre identificado no projeto: `tb25`.

- o que foi ajustado:
  - adicionado o tipo `Producao` nas opcoes e labels de produtos;
  - integrado o campo `tb1_qtd` ao CRUD de produtos;
  - criado um fluxo de estoque com tela de movimentacao e historico;
  - criada a tabela `tb25_produto_movimentacoes` para auditar entrada e saida;
  - adicionados atalhos para a tela de estoque na listagem, criacao, edicao e visualizacao de produtos;
  - a migration de `tb1_qtd` foi feita com protecao por `Schema::hasColumn`, porque neste projeto o campo ja havia sido criado manualmente.

- backend envolvido:
  - `app/Http/Controllers/ProductController.php`
    - passou a reconhecer `tb1_tipo = 3` como `Producao`;
    - passou a validar e preparar `tb1_qtd`;
    - passou a permitir ordenacao por `tb1_qtd`;
  - `app/Http/Controllers/ProductStockController.php`
    - criado para listar produtos de `Producao`, mostrar historico e registrar `Entrada` e `Saida`;
    - faz travamento da linha do produto com `lockForUpdate`;
    - impede saida acima do estoque atual;
    - atualiza `tb1_qtd` e grava o historico em `tb25_produto_movimentacoes`;
  - `app/Models/Produto.php`
    - recebeu `tb1_qtd` em `fillable` e `casts`;
    - recebeu relacionamento `stockMovements`;
  - `app/Models/ProductStockMovement.php`
    - criado para a nova tabela de movimentacoes;
  - `routes/web.php`
    - adicionadas as rotas:
      - `products.production-stock`
      - `products.production-stock.store`

- migrations envolvidas:
  - `database/migrations/2026_04_15_170000_add_tb1_qtd_to_tb1_produto_table.php`
    - adiciona `tb1_qtd` em `tb1_produto` somente se a coluna ainda nao existir;
  - `database/migrations/2026_04_15_171000_create_tb25_produto_movimentacoes_table.php`
    - cria `tb25_produto_movimentacoes`;
    - grava produto, usuario, tipo da movimentacao, quantidade, saldo anterior, saldo posterior e observacao.

- frontend envolvido:
  - `resources/js/Pages/Products/ProductCreate.jsx`
    - passou a exibir `Quantidade inicial` quando o tipo selecionado for `Producao`;
    - recebeu atalho para a tela de estoque;
  - `resources/js/Pages/Products/ProductEdit.jsx`
    - passou a exibir `Quantidade atual` quando o tipo for `Producao`;
    - orienta o uso da tela de estoque para novas entradas e saidas;
    - recebeu atalho para a tela de estoque;
  - `resources/js/Pages/Products/ProductIndex.jsx`
    - ganhou a coluna `Estoque`;
    - mostra o valor apenas para produtos de `Producao`;
    - ganhou botao superior de estoque e atalho por linha;
  - `resources/js/Pages/Products/ProductShow.jsx`
    - passou a mostrar `Estoque atual` para produtos de `Producao`;
    - recebeu atalho para a tela de estoque;
  - `resources/js/Pages/Products/ProductionStock.jsx`
    - criada a nova tela de estoque;
    - possui formulario de `Entrada` e `Saida`;
    - mostra saldo atual do produto selecionado;
    - lista produtos de `Producao`;
    - lista historico recente das movimentacoes.

- arquivos alterados:
  - `app/Http/Controllers/ProductController.php`
  - `app/Models/Produto.php`
  - `resources/js/Pages/Products/ProductCreate.jsx`
  - `resources/js/Pages/Products/ProductEdit.jsx`
  - `resources/js/Pages/Products/ProductIndex.jsx`
  - `resources/js/Pages/Products/ProductShow.jsx`
  - `routes/web.php`
  - `SYNC.md`

- arquivos criados:
  - `app/Http/Controllers/ProductStockController.php`
  - `app/Models/ProductStockMovement.php`
  - `database/migrations/2026_04_15_170000_add_tb1_qtd_to_tb1_produto_table.php`
  - `database/migrations/2026_04_15_171000_create_tb25_produto_movimentacoes_table.php`
  - `resources/js/Pages/Products/ProductionStock.jsx`

- observacoes para sincronizar em `pec1`:
  - sincronizar exatamente:
    - `app/Http/Controllers/ProductController.php`
    - `app/Http/Controllers/ProductStockController.php`
    - `app/Models/Produto.php`
    - `app/Models/ProductStockMovement.php`
    - `resources/js/Pages/Products/ProductCreate.jsx`
    - `resources/js/Pages/Products/ProductEdit.jsx`
    - `resources/js/Pages/Products/ProductIndex.jsx`
    - `resources/js/Pages/Products/ProductShow.jsx`
    - `resources/js/Pages/Products/ProductionStock.jsx`
    - `routes/web.php`
    - `database/migrations/2026_04_15_170000_add_tb1_qtd_to_tb1_produto_table.php`
    - `database/migrations/2026_04_15_171000_create_tb25_produto_movimentacoes_table.php`
  - depois de sincronizar o codigo no `pec1`, executar as migrations;
  - a migration de `tb1_qtd` foi preparada para nao quebrar se a coluna ja existir manualmente;
  - a nova tabela criada neste ajuste usa o sequencial `tb25`, entao nao reutilizar esse numero para outra tabela;
  - este ajuste nao altera o fluxo de venda nem baixa estoque automaticamente por venda.

## 15/04/26 - Nome do fornecedor abre a tela de resposta das disputas em `settings/suppliers`

- causa do problema:
  - na listagem `Fornecedores cadastrados` o nome do fornecedor era apenas texto;
  - a tela onde o fornecedor responde as disputas existe em `/supplier/disputes`, mas depende da sessao `supplier_access` criada pelo login com codigo;
  - por isso nao era possivel, a partir do painel administrativo, abrir diretamente a tela de respostas de um fornecedor especifico.

- o que foi ajustado:
  - criado um acesso administrativo para visualizar a tela de disputas de um fornecedor especifico;
  - o nome do fornecedor passou a ser clicavel na tabela de `settings/suppliers`;
  - o clique abre a mesma pagina `Supplier/Disputes`, agora em modo administrativo;
  - nesse modo a tela mostra os dados que o fornecedor enxerga, mas sem depender da sessao dele e sem permitir envio de resposta ou faturamento.

- backend envolvido:
  - `routes/web.php`
    - adicionada a rota nomeada `settings.suppliers.disputes`;
  - `app/Http/Controllers/SupplierController.php`
    - criado o metodo `showDisputes(Request $request, Supplier $supplier)`;
    - criado o helper privado `buildSupplierBids(Supplier $supplier)` para montar os lances exibidos na tela administrativa.

- frontend envolvido:
  - `resources/js/Pages/Settings/Suppliers.jsx`
    - o nome do fornecedor passou a usar `Link` para a nova rota administrativa;
  - `resources/js/Pages/Supplier/Disputes.jsx`
    - passou a aceitar `portalMode` e `backUrl`;
    - no modo `admin`, exibe mensagem de visualizacao administrativa;
    - no modo `admin`, substitui `Sair` por `Voltar`;
    - no modo `admin`, desabilita edicao de custo, upload e acoes de envio/faturamento.

- arquivos alterados:
  - `routes/web.php`
  - `app/Http/Controllers/SupplierController.php`
  - `resources/js/Pages/Settings/Suppliers.jsx`
  - `resources/js/Pages/Supplier/Disputes.jsx`
  - `SYNC.md`

- observacoes para sincronizar em `pec1`:
  - sincronizar exatamente:
    - `routes/web.php`
    - `app/Http/Controllers/SupplierController.php`
    - `resources/js/Pages/Settings/Suppliers.jsx`
    - `resources/js/Pages/Supplier/Disputes.jsx`
  - manter a rota publica `/supplier/disputes` como esta;
  - a nova rota administrativa nao substitui o acesso por codigo do fornecedor, ela apenas permite consulta pelo master;
  - nao depende de migration;
  - nao altera estrutura do banco.

## 15/04/26 - Botao de alternancia na coluna `Disputa` em `settings/suppliers`

- causa do problema:
  - a tela `settings/suppliers` ja listava o campo `dispute` de cada fornecedor;
  - porem a coluna `Disputa` era apenas informativa, mostrando somente `Sim` ou `Nao`;
  - com isso, qualquer alteracao exigia ajuste indireto no cadastro ou intervencao manual, porque nao existia rota nem acao especifica para alternar esse booleano diretamente na listagem.

- o que foi ajustado:
  - adicionada uma rota `PUT` dedicada para alternar o campo `dispute` do fornecedor;
  - criado no controller o metodo que faz o switch do valor atual no banco;
  - a coluna `Disputa` da tabela `Fornecedores cadastrados` passou a exibir um botao clicavel;
  - o botao agora alterna entre `Sim` e `Nao` sem sair da pagina, mantendo o scroll.

- backend envolvido:
  - `routes/web.php`
    - adicionada a rota nomeada `settings.suppliers.toggle-dispute`;
  - `app/Http/Controllers/SupplierController.php`
    - criado o metodo `toggleDispute(Request $request, Supplier $supplier)`;
    - o metodo valida permissao de `MASTER`, inverte o booleano `dispute` e redireciona com mensagem de sucesso.

- frontend envolvido:
  - `resources/js/Pages/Settings/Suppliers.jsx`
    - importado `PrimaryButton`, `router` e `useState`;
    - criada a funcao `handleDisputeToggle`;
    - a celula `Disputa` deixou de renderizar texto fixo e passou a renderizar um botao;
    - o botao mostra `...` enquanto a requisicao daquele fornecedor estiver em andamento;
    - o update usa `preserveScroll: true`.

- arquivos alterados:
  - `routes/web.php`
  - `app/Http/Controllers/SupplierController.php`
  - `resources/js/Pages/Settings/Suppliers.jsx`
  - `SYNC.md`

- observacoes para sincronizar em `pec1`:
  - sincronizar exatamente:
    - `routes/web.php`
    - `app/Http/Controllers/SupplierController.php`
    - `resources/js/Pages/Settings/Suppliers.jsx`
  - atualizar tambem o `SYNC.md` do outro projeto se voces mantiverem historico la;
  - nao depende de migration;
  - nao cria tabela;
  - nao altera estrutura do banco;
  - depende de o model `App\\Models\\Supplier` continuar com `dispute` em `fillable` e `casts`, como ja esta neste projeto.

## 14/04/26 - Totais de `Vale` e `Adiantamento` no card `Periodo considerado` em `settings/contra-cheque`

- causa do problema:
  - o backend da tela `settings/contra-cheque` ja enviava `summary.advances_total` e `summary.vales_total`;
  - porem o frontend do card `Periodo considerado` montava apenas `Colaboradores`, `Salarios` e `Saldo a receber`;
  - com isso, os totais de `Vale` e `Adiantamento` nao apareciam no resumo superior, mesmo estando disponiveis nos dados.

- o que foi ajustado:
  - adicionado o card `Adiantamento` no resumo superior;
  - adicionado o card `Vale` no resumo superior;
  - ajustada a grade do bloco para acomodar os 5 cards sem quebrar o layout.

- frontend envolvido:
  - `resources/js/Pages/Settings/ContraCheque.jsx`
    - `summaryCards` agora inclui `summary.advances_total`;
    - `summaryCards` agora inclui `summary.vales_total`;
    - a grade do bloco `Periodo considerado` passou de 3 cards para 5 cards.

- arquivos alterados:
  - `resources/js/Pages/Settings/ContraCheque.jsx`

- observacoes para sincronizar em `pec1`:
  - sincronizar exatamente:
    - `resources/js/Pages/Settings/ContraCheque.jsx`
  - nao depende de migration;
  - nao depende de rota nova;
  - nao altera banco;
  - o backend nao precisou ser alterado porque os totais ja eram enviados por `app/Http/Controllers/PayrollController.php`.

## 13/04/26 - Filtros adicionados em `settings/contra-cheque`

- causa do problema:
  - a tela `settings/contra-cheque` foi criada com os cards e a impressao, mas sem o mesmo formulario de filtros da tela `settings/folha-pagamento`;
  - com isso, o usuario nao conseguia refinar a visualizacao por periodo, unidade ou funcao diretamente nessa tela.

- o que foi ajustado:
  - adicionados em `settings/contra-cheque` os mesmos filtros da folha:
    - `Inicio`;
    - `Fim`;
    - `Unidade`;
    - `Funcao`;
  - o formulario agora envia os filtros para a propria rota `settings.contra-cheque`;
  - as datas continuam usando o padrao `DD/MM/AA`;
  - os cards e a impressao em 80mm continuam funcionando sobre o resultado filtrado.

- frontend envolvido:
  - `resources/js/Pages/Settings/ContraCheque.jsx`
    - passou a usar `useForm`;
    - recebeu os campos de filtro com o mesmo comportamento da folha;
    - converte as datas com `shortBrazilDateInputToIso`;
    - reaproveita `filterUnits`, `roleOptions`, `selectedUnitId` e `selectedRole` enviados pelo backend.

- arquivos alterados:
  - `resources/js/Pages/Settings/ContraCheque.jsx`

- observacoes para sincronizar em `pec1`:
  - sincronizar exatamente:
    - `resources/js/Pages/Settings/ContraCheque.jsx`
  - nao depende de migration;
  - nao depende de rota nova;
  - nao altera banco;
  - o backend ja estava preparado para receber esses filtros, por isso neste ajuste foi necessario alterar apenas o frontend.

## 13/04/26 - Nova opcao `Contra-Cheque` em Ferramentas com cards e impressao 80mm

- causa do problema:
  - no menu `Ferramentas` nao existia uma entrada dedicada chamada `Contra-Cheque`;
  - o sistema possuia a tela `Folha de Pagamento`, mas ela era uma visao mais ampla, em tabela, e nao uma tela direta em cards por colaborador;
  - tambem nao existia uma tela simplificada focada apenas em colaboradores com salario informado e com impressao resumida em 80mm.

- regra adotada para este ajuste:
  - como o sistema nao possui um campo explicito de `usuario ativo` no model `User`, foi considerada como base de exibicao:
    - usuarios dentro do escopo de gerenciamento permitido;
    - usuarios diferentes de `Cliente`;
    - usuarios com `salario > 0`.

- o que foi ajustado:
  - criada a opcao `Contra-Cheque` dentro de `Ferramentas`;
  - criada uma tela nova em cards, exibindo um resumo por colaborador;
  - cada card exibe:
    - `Salario`;
    - `Vale em compras`;
    - `Adiantamento`;
    - `Saldo`;
  - cada card possui botao `Imprimir 80mm`;
  - a impressao foi configurada para mostrar um resumo dos valores e o `Saldo a receber`;
  - a logica de impressao foi extraida para uma utilidade compartilhada;
  - a tela `Folha de Pagamento` passou a reutilizar a mesma utilidade de impressao.

- backend envolvido:
  - `app/Http/Controllers/PayrollController.php`
    - criada a action `contraCheque`;
    - a montagem dos dados da folha foi centralizada em `buildPayrollPayload`;
    - a nova tela reutiliza a mesma base de calculo de salario, vale, adiantamento e saldo;
    - quando a tela e `Contra-Cheque`, a consulta filtra apenas usuarios com `salario > 0`.
  - `routes/web.php`
    - adicionada a rota `settings.contra-cheque` em `/settings/contra-cheque`.

- frontend envolvido:
  - `resources/js/Pages/Settings/ContraCheque.jsx`
    - nova tela em cards para resumo individual por colaborador;
    - imprime contra-cheque resumido em 80mm.
  - `resources/js/Utils/contraChequePrint.js`
    - nova utilidade compartilhada para gerar e imprimir o HTML do contra-cheque;
    - suporta impressao resumida e detalhada.
  - `resources/js/Pages/Settings/FolhaPagamento.jsx`
    - passou a usar a utilidade compartilhada de impressao.
  - `resources/js/Pages/Settings/Menu.jsx`
    - adicionada a entrada `Contra-Cheque` no bloco `Ferramentas`.
  - `resources/js/Pages/Settings/Config.jsx`
    - adicionada a opcao `Contra-Cheque` na grade principal de `Ferramentas`.

- arquivos alterados:
  - `app/Http/Controllers/PayrollController.php`
  - `routes/web.php`
  - `resources/js/Pages/Settings/FolhaPagamento.jsx`
  - `resources/js/Pages/Settings/Menu.jsx`
  - `resources/js/Pages/Settings/Config.jsx`
  - `resources/js/Pages/Settings/ContraCheque.jsx`
  - `resources/js/Utils/contraChequePrint.js`

- observacoes para sincronizar em `pec1`:
  - sincronizar exatamente:
    - `app/Http/Controllers/PayrollController.php`
    - `routes/web.php`
    - `resources/js/Pages/Settings/FolhaPagamento.jsx`
    - `resources/js/Pages/Settings/Menu.jsx`
    - `resources/js/Pages/Settings/Config.jsx`
    - `resources/js/Pages/Settings/ContraCheque.jsx`
    - `resources/js/Utils/contraChequePrint.js`
  - nao depende de migration;
  - nao depende de alteracao no banco;
  - a regra de `ativo` nesta implementacao esta baseada em `salario > 0` e exclusao do perfil `Cliente`, porque nao existe um campo especifico de ativo em `users`;
  - a tela nova usa o mes corrente por padrao, seguindo a mesma regra de datas ja usada em `Folha de Pagamento`.

## 13/04/26 - Impressao individual e em lote em `salary-advances/create`

- causa do problema:
  - a tela `salary-advances/create` listava os adiantamentos do mes corrente apenas com a acao de exclusao;
  - nao existia botao para imprimir um adiantamento individual;
  - nao existia botao para imprimir todos os adiantamentos exibidos do usuario selecionado;
  - a tela tambem nao recebia o nome da loja de cada lancamento nem o periodo completo do mes corrente para montar a impressao corretamente.

- o que foi ajustado:
  - adicionados botoes `Imprimir` em cada linha da lista de adiantamentos;
  - adicionado botao `Imprimir todos` no topo do bloco `Vales do mes corrente`;
  - a impressao individual gera um comprovante contendo apenas o adiantamento da linha clicada;
  - a impressao em lote gera um comprovante com todos os adiantamentos exibidos do usuario no mes corrente;
  - o botao `Gravar` da tela passou a usar o componente central `PrimaryButton`;
  - o botao `Excluir` da grade passou a usar o componente central `DangerButton`;
  - o layout de impressao foi extraido para uma utilidade compartilhada, evitando duplicacao com o relatorio de adiantamentos.

- backend envolvido:
  - `app/Http/Controllers/SalaryAdvanceController.php`
    - a tela `create` agora envia `currentMonthStart` e `currentMonthEnd`;
    - a consulta do mes corrente agora carrega a relacao da loja;
    - cada item de `currentMonthAdvances` agora inclui `unit_name`;
    - o metodo `currentMonthAdvancesQuery` passou a aceitar intervalo opcional para reutilizar o mesmo periodo resolvido na action.

- frontend envolvido:
  - `resources/js/Pages/Finance/SalaryAdvanceCreate.jsx`
    - adicionados os botoes `Imprimir` e `Imprimir todos`;
    - adicionada mensagem visual quando o navegador bloqueia pop-up de impressao;
    - montado o detalhe individual e o detalhe consolidado do mes para envio ao helper de impressao.
  - `resources/js/Utils/salaryAdvancePrint.js`
    - nova utilidade compartilhada para gerar o HTML de impressao de adiantamentos;
    - centraliza a abertura da janela e o `window.print`.
  - `resources/js/Pages/Reports/Advances.jsx`
    - passou a reutilizar a nova utilidade compartilhada de impressao.

- arquivos alterados:
  - `app/Http/Controllers/SalaryAdvanceController.php`
  - `resources/js/Pages/Finance/SalaryAdvanceCreate.jsx`
  - `resources/js/Pages/Reports/Advances.jsx`
  - `resources/js/Utils/salaryAdvancePrint.js`

- observacoes para sincronizar em `pec1`:
  - sincronizar exatamente:
    - `app/Http/Controllers/SalaryAdvanceController.php`
    - `resources/js/Pages/Finance/SalaryAdvanceCreate.jsx`
    - `resources/js/Pages/Reports/Advances.jsx`
    - `resources/js/Utils/salaryAdvancePrint.js`
  - nao depende de migration;
  - nao depende de rota nova;
  - nao altera banco;
  - a impressao continua 100% no frontend;
  - se a IA de `pec1` encontrar o codigo antigo de impressao dentro de `resources/js/Pages/Reports/Advances.jsx`, deve remover aquela duplicacao e usar a utilidade `resources/js/Utils/salaryAdvancePrint.js`.

## 11/04/26 - Configuracao do Discarte com filtro mensal e consolidado por loja

- causa do problema:
  - a tela `settings/discard-config` mostrava apenas o escopo ativo da sessao;
  - nao existia filtro de mes;
  - nao havia consolidado real de todas as lojas permitidas;
  - tambem nao existia detalhamento individual por loja com `Hoje` e `Acumulado`.

- o que foi ajustado:
  - removido o card `Escopo atual` da tela;
  - adicionado filtro de mes para controlar o bloco de `Acumulado`;
  - mantido o bloco `Hoje` usando a data atual;
  - o `Acumulado` agora considera o mes selecionado e exibe o periodo visual em `DD/MM/AA a DD/MM/AA`;
  - a tela passou a exibir:
    - consolidado de hoje de todas as lojas permitidas;
    - consolidado acumulado de todas as lojas permitidas;
    - tabela com cada loja individual exibindo `Hoje` e `Acumulado`.

- backend envolvido:
  - `app/Http/Controllers/DiscardSettingsController.php`
    - passou a ler `month` via query string;
    - resolve o mes com fallback para o mes atual;
    - monta a lista de lojas permitidas via `ManagementScope::managedUnits`;
    - envia para a Inertia um `monitoring` novo com consolidado geral e detalhamento por loja.
  - `app/Support/DiscardAlertService.php`
    - ganhou o metodo `dashboardForAdmin`;
    - calcula consolidado geral `today`;
    - calcula consolidado geral `month`;
    - monta a lista `stores` com `today` e `month` por loja;
    - reutiliza a mesma regra de calculo de descarte, faturamento e percentual que ja existia.

- arquivos alterados:
  - `app/Http/Controllers/DiscardSettingsController.php`
  - `app/Support/DiscardAlertService.php`
  - `resources/js/Pages/Settings/DiscardConfig.jsx`

- observacoes para sincronizar em `pec1`:
  - sincronizar exatamente:
    - `app/Http/Controllers/DiscardSettingsController.php`
    - `app/Support/DiscardAlertService.php`
    - `resources/js/Pages/Settings/DiscardConfig.jsx`
  - nao depende de migration;
  - nao depende de rota nova;
  - nao altera banco;
  - a tela continua administrativa;
  - o filtro mensal desta tela nao troca a loja ativa da sessao;
  - o monitoramento global usado em outras partes do sistema nao foi alterado.

## 11/04/26 - Ajuste visual do topo em `settings/discard-config`

- causa do problema:
  - o topo da tela estava montado em blocos verticais simples;
  - o input e o botao do percentual ficavam separados;
  - o filtro mensal tambem estava mais alto e espaçado que o layout desejado;
  - visualmente a composicao nao seguia a referencia enviada.

- o que foi ajustado:
  - `resources/js/Pages/Settings/DiscardConfig.jsx`
    - o card `Percentual aceitavel` agora usa input e botao na mesma linha;
    - o botao `Salvar configuracao` passou a ficar dentro de uma faixa horizontal ao lado do campo;
    - o card `Filtro do acumulado` agora usa campo `month` e botao `Aplicar mes` lado a lado;
    - ajustadas proporcoes da grid superior, paddings, larguras e arredondamentos para aproximar o visual da imagem;
    - a logica da tela nao foi alterada, somente a organizacao visual.

- arquivos alterados:
  - `resources/js/Pages/Settings/DiscardConfig.jsx`

- validacao:
  - `npm run build`: ok.

## 16/04/26 - Produto sem codigo de barras usando o proprio `tb1_id`

- causa do problema:
  - o cadastro de produto nao permitia indicar explicitamente que um item nao possui codigo de barras;
  - quando isso acontecia, o sistema acabava usando codigos improvisados ou o usuario precisava informar um valor ficticio;
  - na base atual existiam varios `tb1_codbar` curtos, inclusive casos no padrao `SEM-*`, o que deixava o comportamento inconsistente entre cadastro novo e produtos antigos.

- regra aplicada:
  - quando o usuario marcar que o produto nao possui codigo de barras, o sistema grava o proprio `tb1_id` no campo `tb1_codbar`;
  - produtos de balanca continuam sem codigo de barras proprio, mas agora tambem usam o proprio `tb1_id` em `tb1_codbar`;
  - na correcao do banco, todo produto com `tb1_codbar` menor que `7` caracteres passa a receber `CAST(tb1_id AS CHAR)`.

- backend envolvido:
  - `app/Http/Controllers/ProductController.php`
    - adicionada a flag de request `sem_codigo_barras`;
    - `tb1_codbar` deixa de ser obrigatorio quando essa flag estiver marcada;
    - a geracao final do codigo passou a ser centralizada em `resolveProductBarcode`;
    - quando o produto estiver sem codigo proprio, o controller grava `tb1_codbar` com o proprio `tb1_id`;
    - a validacao de colisao de codigo passou a considerar tambem esse codigo gerado com base no ID;
    - `nextSafeProductId` passou a ignorar IDs cujo valor textual ja esteja ocupado em `tb1_codbar`.
  - `database/migrations/2026_04_16_190000_sync_short_product_barcodes_with_id.php`
    - nova migration de saneamento;
    - atualiza `tb1_produto.tb1_codbar` para `CAST(tb1_id AS CHAR)` quando `CHAR_LENGTH(TRIM(tb1_codbar)) < 7`;
    - o `down` ficou sem reversao automatica porque os valores antigos nao sao recuperaveis.

- frontend envolvido:
  - `resources/js/Pages/Products/ProductCreate.jsx`
    - adicionado checkbox `Produto sem codigo de barras`;
    - quando marcado, o campo manual de codigo deixa de ser exibido;
    - a tela informa que o sistema copiara o `tb1_id` para `tb1_codbar`;
    - para produtos de balanca o checkbox fica sempre ativo e bloqueado.
  - `resources/js/Pages/Products/ProductEdit.jsx`
    - recebeu a mesma regra visual do cadastro;
    - quando o produto estiver sem codigo proprio, a tela exibe apenas a orientacao e nao mostra input manual para `tb1_codbar`;
    - para produtos de balanca, a mensagem agora deixa claro que `tb1_codbar` acompanha o proprio `tb1_id`.

- testes:
  - `tests/Feature/ProductManagementTest.php`
    - atualizado para refletir o fim do padrao `SEM-*`;
    - adicionado cenario cobrindo produto comum marcado como sem codigo de barras, validando `tb1_codbar = tb1_id`.

- arquivos alterados:
  - `app/Http/Controllers/ProductController.php`
  - `resources/js/Pages/Products/ProductCreate.jsx`
  - `resources/js/Pages/Products/ProductEdit.jsx`
  - `tests/Feature/ProductManagementTest.php`
  - `SYNC.md`
  - `database/migrations/2026_04_16_190000_sync_short_product_barcodes_with_id.php`

- observacoes para sincronizar em `pec1`:
  - sincronizar exatamente:
    - `app/Http/Controllers/ProductController.php`
    - `resources/js/Pages/Products/ProductCreate.jsx`
    - `resources/js/Pages/Products/ProductEdit.jsx`
    - `tests/Feature/ProductManagementTest.php`
    - `database/migrations/2026_04_16_190000_sync_short_product_barcodes_with_id.php`
  - aplicar a migration tambem em `pec1`;
  - a copia em `pec1` pode ter pequenas diferencas visuais nas telas `Welcome.jsx` e `Login.jsx`, mas isso nao interfere nesta sincronizacao;
  - o ponto critico da sincronizacao e manter a mesma regra no controller e no formulario: `sem_codigo_barras` precisa resultar em `tb1_codbar = tb1_id`.

## 16/04/26 - Base estrutural para emissao fiscal NF-e/NFC-e

- causa do problema:
  - o sistema fechava a venda normalmente, mas nao possuia nenhuma camada fiscal para transformar esse fechamento em nota eletronica;
  - faltavam tres blocos essenciais:
    - configuracao fiscal por unidade;
    - cadastro fiscal minimo de produto;
    - vinculo entre a venda concluida e um registro fiscal preparado para futura transmissao a SEFAZ;
  - sem isso, mesmo com certificado e API depois, a emissao ficaria travada por falta de estrutura e validacao interna.

- o que foi criado nesta etapa:
  - estrutura de banco para configuracao fiscal por unidade;
  - estrutura de banco para registrar a nota fiscal ligada ao fechamento da venda;
  - ampliacao do cadastro de produto com campos fiscais minimos;
  - tela administrativa de configuracao fiscal;
  - preparacao automatica de registro fiscal quando a venda e finalizada;
  - validacao interna para marcar se a nota esta:
    - `pendente_configuracao`;
    - `erro_validacao`;
    - `pendente_emissao`.

- banco de dados:
  - `database/migrations/2026_04_16_200000_add_fiscal_fields_to_tb1_produto_table.php`
    - adiciona em `tb1_produto`:
      - `tb1_ncm`
      - `tb1_cest`
      - `tb1_cfop`
      - `tb1_unidade_comercial`
      - `tb1_unidade_tributavel`
      - `tb1_origem`
      - `tb1_csosn`
      - `tb1_cst`
      - `tb1_aliquota_icms`
  - `database/migrations/2026_04_16_201000_create_tb26_configuracoes_fiscais_table.php`
    - cria `tb26_configuracoes_fiscais`;
    - tabela por unidade (`tb2_id` unico);
    - guarda ambiente, serie, proximo numero, CRT, CSC, tipo de certificado, caminho do certificado, senha criptografada e dados completos do emitente.
  - `database/migrations/2026_04_16_202000_create_tb27_notas_fiscais_table.php`
    - cria `tb27_notas_fiscais`;
    - liga uma nota ao fechamento (`tb4_id`);
    - guarda status, payload montado, erros de validacao, chave, protocolo, XML e datas operacionais.

- backend:
  - `app/Models/ConfiguracaoFiscal.php`
    - novo model da tabela `tb26_configuracoes_fiscais`;
    - senha do certificado com cast `encrypted`.
  - `app/Models/NotaFiscal.php`
    - novo model da tabela `tb27_notas_fiscais`.
  - `app/Support/FiscalInvoicePreparationService.php`
    - novo servico para preparar a nota a partir do fechamento;
    - monta payload interno da emissao;
    - valida configuracao da unidade;
    - valida se os produtos tem cadastro fiscal minimo;
    - define o status inicial da nota;
    - reserva numeracao somente quando a unidade ja tiver algum tipo de emissao habilitado.
  - `app/Http/Controllers/SaleController.php`
    - ao finalizar a venda, agora chama `FiscalInvoicePreparationService`;
    - o JSON de retorno da venda passou a trazer bloco `fiscal` com status, mensagem, modelo, ambiente, serie e numero.
  - `app/Http/Controllers/FiscalConfigurationController.php`
    - novo controller para tela de configuracao fiscal;
    - permite selecionar unidade;
    - salvar ambiente, serie, CRT, CSC, dados do emitente;
    - fazer upload do certificado `A1` (`.pfx`/`.p12`);
    - remover certificado atual;
    - listar as ultimas notas preparadas da unidade.
  - `app/Http/Controllers/ProductController.php`
    - validacao e persistencia dos novos campos fiscais do produto;
    - inclusao das opcoes de origem fiscal no formulario.
  - `app/Models/Produto.php`
    - fillable/casts atualizados com os novos campos fiscais.
  - `app/Models/VendaPagamento.php`
    - adicionada relacao `notaFiscal()`.
  - `app/Models/Unidade.php`
    - adicionada relacao `configuracaoFiscal()`.
  - `routes/web.php`
    - novas rotas:
      - `settings.fiscal`
      - `settings.fiscal.update`

- frontend:
  - `resources/js/Pages/Settings/Config.jsx`
    - adicionada entrada `Configuracao Fiscal` no menu de ferramentas.
  - `resources/js/Pages/Settings/FiscalConfig.jsx`
    - nova tela de configuracao fiscal;
    - selecao de unidade;
    - bloco de emissao e numeracao;
    - bloco de credenciais fiscais;
    - upload de certificado;
    - bloco de dados do emitente;
    - tabela com as ultimas notas preparadas.
  - `resources/js/Pages/Products/ProductCreate.jsx`
    - nova secao `Cadastro fiscal`.
  - `resources/js/Pages/Products/ProductEdit.jsx`
    - nova secao `Cadastro fiscal`.

- testes e validacao:
  - `tests/Unit/FiscalInvoicePreparationServiceTest.php`
    - novo teste unitario cobrindo:
      - falta de configuracao fiscal;
      - configuracao completa e produto valido;
      - produto sem cadastro fiscal minimo.
  - `php artisan test tests/Unit/FiscalInvoicePreparationServiceTest.php`: ok.
  - `npm run build`: ok.
  - `php artisan test`: nao validado integralmente por bloqueio de conexao com o MySQL externo do ambiente de teste.

- comportamento importante desta etapa:
  - isto ainda nao transmite nota para a SEFAZ;
  - esta entrega prepara a base para a futura integracao oficial;
  - a venda agora ja nasce com um registro fiscal interno;
  - se a unidade ou o produto estiverem incompletos, a nota fica sinalizada com pendencia em vez de falhar silenciosamente.

- observacoes para sincronizar em `pec1`:
  - sincronizar exatamente estes arquivos:
    - `app/Http/Controllers/FiscalConfigurationController.php`
    - `app/Http/Controllers/ProductController.php`
    - `app/Http/Controllers/SaleController.php`
    - `app/Models/ConfiguracaoFiscal.php`
    - `app/Models/NotaFiscal.php`
    - `app/Models/Produto.php`
    - `app/Models/Unidade.php`
    - `app/Models/VendaPagamento.php`
    - `app/Support/FiscalInvoicePreparationService.php`
    - `database/migrations/2026_04_16_200000_add_fiscal_fields_to_tb1_produto_table.php`
    - `database/migrations/2026_04_16_201000_create_tb26_configuracoes_fiscais_table.php`
    - `database/migrations/2026_04_16_202000_create_tb27_notas_fiscais_table.php`
    - `resources/js/Pages/Products/ProductCreate.jsx`
    - `resources/js/Pages/Products/ProductEdit.jsx`
    - `resources/js/Pages/Settings/Config.jsx`
    - `resources/js/Pages/Settings/FiscalConfig.jsx`
    - `routes/web.php`
    - `tests/Unit/FiscalInvoicePreparationServiceTest.php`
  - a sincronizacao desta etapa nao depende de `Welcome.jsx` nem `Login.jsx`;
  - ponto critico:
    - copiar o service, o controller fiscal e as tres migrations juntos;
    - se copiar so a tela ou so o controller, a venda vai referenciar estruturas que ainda nao existem;
    - a migration `tb27_notas_fiscais` depende de `tb26_configuracoes_fiscais`;
    - a regra nova da venda depende da relacao `notaFiscal()` em `VendaPagamento`.

## 16/04/26 - Associacao explicita entre loja e certificado fiscal

- causa do ajuste:
  - foi confirmado que cada loja possui CNPJ proprio;
  - consequentemente cada loja tambem possui certificado digital proprio;
  - embora a configuracao fiscal ja estivesse vinculada por `tb2_id`, ainda faltava deixar isso explicito no cadastro e na validacao:
    - qual certificado pertence a qual loja;
    - qual CNPJ esta vinculado ao certificado;
    - impedir que uma loja salve ou use o certificado de outra.

- o que foi ajustado:
  - `database/migrations/2026_04_16_203000_add_certificate_identity_to_tb26_configuracoes_fiscais_table.php`
    - adiciona em `tb26_configuracoes_fiscais`:
      - `tb26_certificado_nome`
      - `tb26_certificado_cnpj`
  - `app/Models/ConfiguracaoFiscal.php`
    - fillable atualizado com os novos campos.
  - `app/Http/Controllers/FiscalConfigurationController.php`
    - a tela agora recebe tambem o CNPJ normalizado da loja;
    - passou a salvar `nome do certificado` e `CNPJ do certificado`;
    - antes de gravar, valida se o CNPJ base do certificado e o mesmo CNPJ base da loja selecionada;
    - se nao for, retorna erro no campo `tb26_certificado_cnpj`.
  - `app/Support/FiscalInvoicePreparationService.php`
    - o payload interno da nota agora inclui os dados identificadores do certificado;
    - a validacao fiscal passou a exigir `nome do certificado` e `CNPJ do certificado`;
    - na preparacao da nota, tambem confere se o certificado pertence ao mesmo CNPJ base da loja da venda.
  - `resources/js/Pages/Settings/FiscalConfig.jsx`
    - adicionados os campos:
      - `Nome do certificado`
      - `CNPJ do certificado`
    - adicionada uma caixa de resumo visual com:
      - loja;
      - CNPJ da loja;
      - nome do certificado;
      - CNPJ do certificado;
      - arquivo vinculado.
  - `tests/Unit/FiscalInvoicePreparationServiceTest.php`
    - novo cenario cobrindo tentativa de usar certificado de outra loja.

- regra importante:
  - a associacao operacional correta agora e:
    - `loja/unidade -> configuracao fiscal -> certificado proprio`
  - a validacao considera o CNPJ base:
    - se o certificado nao pertencer ao mesmo CNPJ base da loja, a configuracao e bloqueada;
    - se por algum motivo isso escapar, a preparacao da nota tambem marca erro.

- validacao:
  - `php artisan test tests/Unit/FiscalInvoicePreparationServiceTest.php`: ok.
  - `npm run build`: ok.

- arquivos alterados:
  - `database/migrations/2026_04_16_203000_add_certificate_identity_to_tb26_configuracoes_fiscais_table.php`
  - `app/Models/ConfiguracaoFiscal.php`
  - `app/Http/Controllers/FiscalConfigurationController.php`
  - `app/Support/FiscalInvoicePreparationService.php`
  - `resources/js/Pages/Settings/FiscalConfig.jsx`
  - `tests/Unit/FiscalInvoicePreparationServiceTest.php`
  - `SYNC.md`

- observacoes para sincronizar em `pec1`:
  - copiar esses arquivos junto com a base fiscal criada anteriormente;
  - aplicar tambem a migration nova `2026_04_16_203000_add_certificate_identity_to_tb26_configuracoes_fiscais_table.php`;
  - ponto critico da sincronizacao:
    - nao copiar so a tela;
    - o controller e o service precisam ir juntos para manter a validacao consistente entre cadastro e preparo da nota.

## 16/04/26 - Inspecao automatica do A1 e XML assinado localmente para NFC-e

- causa do ajuste:
  - a base fiscal ja sabia qual loja e qual certificado deveriam ser usados;
  - faltava transformar isso em algo operacional:
    - abrir o certificado A1;
    - validar senha e identidade do certificado;
    - identificar nome, CNPJ e validade do certificado;
    - gerar XML fiscal assinado localmente;
    - permitir reprocessar notas que ficaram pendentes antes da configuracao completa.
  - tambem ficou claro que `NF-e` modelo `55` ainda nao pode ser emitida automaticamente com a estrutura atual, porque a venda nao armazena destinatario fiscal; por isso a assinatura local desta etapa foi focada em `NFC-e`.

- o que foi criado:
  - `app/Support/FiscalCertificateService.php`
    - novo servico para abrir certificado `A1` (`.pfx`/`.p12`);
    - le o PKCS12 com `openssl`;
    - extrai:
      - certificado publico;
      - chave privada;
      - nome do certificado;
      - CNPJ do certificado;
      - validade;
    - normaliza o certificado para uso em `XMLDSig`.
  - `app/Support/FiscalNfceXmlService.php`
    - novo servico para montar XML `NFC-e` modelo `65`;
    - gera:
      - chave de acesso;
      - bloco `ide`;
      - emitente;
      - itens;
      - impostos basicos;
      - totais;
      - pagamento;
      - informacao adicional;
    - assina o XML localmente com o certificado da loja.
  - `app/Support/FiscalInvoicePreparationService.php`
    - passou a carregar o certificado real da unidade;
    - passou a tentar gerar XML assinado quando a nota for `NFC-e` e estiver validada;
    - status novo:
      - `xml_assinado`
    - adicionada rotina de `reprocessamento` das notas pendentes da unidade;
    - validacao nova:
      - certificado vencido bloqueia;
      - `NF-e` modelo `55` fica com erro explicando que ainda falta destinatario fiscal;
      - nesta etapa a geracao automatica foi preparada para `CRT 1` com `CSOSN 102/103/300/400`.
  - `app/Http/Controllers/FiscalConfigurationController.php`
    - upload do certificado agora exige senha quando necessario;
    - ao enviar o arquivo, o sistema tenta abrir o `A1`;
    - se conseguir:
      - preenche automaticamente nome do certificado;
      - preenche automaticamente CNPJ do certificado;
      - grava a validade;
    - se falhar:
      - remove o arquivo salvo;
      - retorna erro no formulario;
    - nova acao para reprocessar notas pendentes da unidade;
    - nova acao para baixar o XML assinado da nota.
  - `resources/js/Pages/Settings/FiscalConfig.jsx`
    - mostra validade do certificado no resumo da associacao loja/certificado;
    - adiciona acao `Reprocessar notas pendentes`;
    - exibe botao `Baixar XML` quando a nota ja tem `tb27_xml_envio`.

- banco de dados:
  - `database/migrations/2026_04_16_204000_add_certificate_validity_to_tb26_configuracoes_fiscais_table.php`
    - adiciona `tb26_certificado_valido_ate` em `tb26_configuracoes_fiscais`.

- regras importantes desta etapa:
  - a assinatura local esta preparada para `NFC-e`;
  - `NF-e` modelo `55` continua bloqueada automaticamente ate existir destinatario fiscal na venda;
  - o certificado da loja agora nao e apenas um arquivo salvo:
    - ele e aberto;
    - identificado;
    - validado;
    - e usado para assinar o XML localmente.

- testes e validacao:
  - `tests/Unit/FiscalInvoicePreparationServiceTest.php`
    - ajustado para o novo fluxo de validacao;
    - continua cobrindo configuracao, produto fiscal e certificado de outra loja.
  - `tests/Unit/FiscalCertificateServiceTest.php`
    - novo teste unitario cobrindo extracao de nome e CNPJ do certificado.
  - `php artisan test tests/Unit/FiscalInvoicePreparationServiceTest.php tests/Unit/FiscalCertificateServiceTest.php`: ok.
  - `npm run build`: ok.
  - `php -l` nos novos servicos/controladores/migrations: ok.

- arquivos alterados:
  - `app/Http/Controllers/FiscalConfigurationController.php`
  - `app/Models/ConfiguracaoFiscal.php`
  - `app/Support/FiscalCertificateService.php`
  - `app/Support/FiscalInvoicePreparationService.php`
  - `app/Support/FiscalNfceXmlService.php`
  - `database/migrations/2026_04_16_204000_add_certificate_validity_to_tb26_configuracoes_fiscais_table.php`
  - `resources/js/Pages/Settings/FiscalConfig.jsx`
  - `routes/web.php`
  - `tests/Unit/FiscalInvoicePreparationServiceTest.php`
  - `tests/Unit/FiscalCertificateServiceTest.php`
  - `SYNC.md`

- observacoes para sincronizar em `pec1`:
  - sincronizar esta etapa junto com toda a base fiscal anterior;
  - aplicar tambem:
    - `2026_04_16_204000_add_certificate_validity_to_tb26_configuracoes_fiscais_table.php`
  - ponto critico:
    - `FiscalCertificateService`, `FiscalNfceXmlService` e `FiscalInvoicePreparationService` precisam ir juntos;
    - o controller fiscal tambem precisa ser sincronizado no mesmo pacote, porque agora ele depende da inspecao real do certificado;
    - sem isso, a tela vai salvar o arquivo, mas nao vai preencher identidade/validade nem conseguir assinar o XML.

## 16/04/26 - Transmissao inicial da NFC-e para a SEFAZ com endpoint por UF

- causa do ajuste:
  - a etapa anterior ja deixava a nota em `xml_assinado`, mas ainda faltava o envio real para a SEFAZ;
  - havia tambem dois riscos tecnicos que impediriam a transmissao funcionar corretamente:
    - usar o endpoint `?wsdl` como se fosse URL de envio;
    - montar o lote XML escapado como texto dentro do SOAP, em vez de enviar o XML como nodo valido.

- o que foi criado:
  - `app/Support/FiscalWebserviceResolverService.php`
    - novo servico para resolver os endpoints da NFC-e por `UF` e `ambiente`;
    - nesta etapa o mapeamento inicial foi preparado para `GO`;
    - agora diferencia:
      - URL real de envio;
      - URL do `WSDL`;
      - operacao SOAP de autorizacao.
  - `app/Support/FiscalNfceTransmissionService.php`
    - novo servico para transmissao da `NFC-e`;
    - carrega o certificado da loja;
    - monta o lote `enviNFe`;
    - monta o envelope SOAP com o XML anexado corretamente;
    - envia para o webservice da SEFAZ com `cURL` e certificado `PEM` temporario;
    - interpreta a resposta da autorizacao e atualiza:
      - status;
      - mensagem;
      - recibo;
      - protocolo;
      - chave;
      - XML de retorno;
      - data da emissao.

- ajustes no controller fiscal:
  - `app/Http/Controllers/FiscalConfigurationController.php`
    - a listagem da tela passa a receber os endpoints resolvidos da unidade;
    - a tela mostra o endpoint real de envio e tambem o `WSDL`;
    - nova acao para transmitir manualmente uma nota em `xml_assinado`;
    - se a transmissao falhar, o erro volta para a tela como feedback ao usuario em vez de estourar excecao bruta.

- ajustes no frontend:
  - `resources/js/Pages/Settings/FiscalConfig.jsx`
    - a tabela de notas agora mostra:
      - botao `Baixar XML`;
      - botao `Transmitir` para notas em `xml_assinado`;
    - o resumo da loja/certificado mostra:
      - endpoint de envio;
      - `WSDL` de autorizacao.

- ajustes de rota:
  - `routes/web.php`
    - adicionadas as rotas:
      - `settings.fiscal.reprocess`
      - `settings.fiscal.invoices.xml`
      - `settings.fiscal.invoices.transmit`

- testes e validacao:
  - `tests/Unit/FiscalWebserviceResolverServiceTest.php`
    - novo teste unitario validando:
      - URL de envio sem `?wsdl`;
      - URL do `WSDL` separada;
      - excecao para `UF` ainda nao mapeada.
  - os testes anteriores de certificado e preparacao fiscal continuam sendo executados em conjunto nesta etapa.

- regra importante desta etapa:
  - a transmissao automatica continua focada em `NFC-e`;
  - `NF-e` modelo `55` ainda permanece bloqueada no preparo automatico porque a venda atual nao guarda destinatario fiscal;
  - o mapeamento oficial de endpoint ainda nao cobre todas as `UFs`, entao lojas fora do mapeamento atual vao exibir erro explicito na tela ate que a `UF` seja adicionada no resolver.

- arquivos alterados:
  - `app/Http/Controllers/FiscalConfigurationController.php`
  - `app/Support/FiscalNfceTransmissionService.php`
  - `app/Support/FiscalWebserviceResolverService.php`
  - `resources/js/Pages/Settings/FiscalConfig.jsx`
  - `routes/web.php`
  - `tests/Unit/FiscalWebserviceResolverServiceTest.php`
  - `SYNC.md`

  - observacoes para sincronizar em `pec1`:
  - copiar este pacote junto com toda a base fiscal anterior;
  - ponto critico:
    - o servico de transmissao depende do servico de certificado e do XML assinado localmente;
    - nao adianta sincronizar apenas o botao `Transmitir` da tela;
    - o resolver de endpoint precisa ir junto, porque a transmissao agora nao usa mais a URL `?wsdl` para o `POST`;
    - se a loja do `pec1` estiver em outra `UF`, incluir o novo mapeamento oficial no `FiscalWebserviceResolverService.php` antes de tentar emitir em producao.

## 16/04/26 - Correcao do upload de certificado A1 com MIME generico no Windows

- causa do problema:
  - alguns certificados `A1` validos em `.pfx` estavam sendo enviados corretamente pelo navegador;
  - porem o PHP no Windows identificava esses arquivos com MIME generico `application/octet-stream`;
  - a validacao anterior usava `mimes:pfx,p12`, entao o Laravel rejeitava o upload antes mesmo da inspecao real com `openssl`.

- o que foi ajustado:
  - `app/Http/Controllers/FiscalConfigurationController.php`
    - a validacao do campo `tb26_certificado_arquivo_upload` deixou de depender do MIME detectado;
    - agora o backend aceita o upload pela extensao real do arquivo:
      - `.pfx`
      - `.p12`
    - a verificacao pesada do certificado continua sendo feita depois pela inspecao real do arquivo com `FiscalCertificateService`.

- efeito pratico:
  - certificados `A1` validos enviados em Windows nao ficam mais bloqueados por `application/octet-stream`;
  - se o arquivo tiver extensao invalida, continua sendo rejeitado;
  - se a extensao for valida, mas o certificado/senha estiver errado, a falha continua aparecendo na etapa de inspecao real.

- arquivos alterados:
  - `app/Http/Controllers/FiscalConfigurationController.php`
  - `SYNC.md`

- observacoes para sincronizar em `pec1`:
  - sincronizar junto com a base fiscal anterior;
  - este ajuste nao substitui a migration fiscal pendente `2026_04_16_204000_add_certificate_validity_to_tb26_configuracoes_fiscais_table.php`, que tambem precisa estar aplicada para o salvamento completo do certificado.

## 16/04/26 - Acoes por linha em `Ultimas notas preparadas`

- causa do ajuste:
  - a tabela `Ultimas notas preparadas` mostrava apenas o estado fiscal e duas acoes tecnicas:
    - `Baixar XML`
    - `Transmitir`
  - no uso diario faltavam duas operacoes importantes:
    - reimprimir o cupom da venda vinculada;
    - regenerar apenas uma nota que ficou com erro, sem precisar reprocessar todas da loja.

- o que foi ajustado:
  - `app/Http/Controllers/FiscalConfigurationController.php`
    - a listagem das notas agora monta tambem o payload do cupom por linha;
    - cada nota passa a enviar `receipt` para o frontend;
    - cada nota passa a informar `pode_regenerar` para os status que aceitam nova preparacao;
    - criada a acao `regenerateInvoice()` para reexecutar `prepareForPayment()` somente da nota selecionada.
  - `routes/web.php`
    - adicionada a rota:
      - `settings.fiscal.invoices.regenerate`
  - `resources/js/Pages/Settings/FiscalConfig.jsx`
    - adicionada a coluna `Cupom` com o botao `Imprimir cupom`;
    - adicionada a coluna `Regenerar` com o botao `Regenerar nota`;
    - a impressao reutiliza o HTML padrao de cupom de `resources/js/Utils/receipt.js`;
    - quando o navegador bloquear pop-up, a tela mostra erro visivel.

- regra pratica:
  - `Imprimir cupom` apenas reabre a representacao da venda ja concluida;
  - `Regenerar nota` refaz o preparo fiscal apenas daquela venda;
  - a regeneracao individual fica disponivel para notas com status:
    - `pendente_configuracao`
    - `erro_validacao`
    - `erro_transmissao`
    - `pendente_emissao`

- arquivos alterados:
  - `app/Http/Controllers/FiscalConfigurationController.php`
  - `resources/js/Pages/Settings/FiscalConfig.jsx`
  - `routes/web.php`
  - `SYNC.md`

  - observacoes para sincronizar em `pec1`:
  - sincronizar os tres arquivos acima em conjunto;
  - o botao de impressao depende do utilitario ja existente `resources/js/Utils/receipt.js`;
  - se copiar apenas a tela sem o controller, a tabela nao recebera o payload `receipt` e os botoes vao ficar incompletos.

## 16/04/26 - Cadastro de produto bloqueando falta de NCM, CFOP e CSOSN/CST

- causa do problema:
  - o cadastro de produto aceitava salvar campos fiscais essenciais como opcionais;
  - a cobranca desses dados so acontecia depois, na preparacao da nota fiscal;
  - com isso, produtos como `PAO DE SAL` podiam ser cadastrados normalmente e so falhavam no momento da emissao.

- o que foi ajustado:
  - `app/Http/Controllers/ProductController.php`
    - adicionada validacao complementar no backend para bloquear salvamento sem:
      - `NCM`;
      - `CFOP`;
      - pelo menos um entre `CSOSN` ou `CST`.
    - agora o produto nao segue para gravacao se esses dados fiscais minimos estiverem faltando.
  - `resources/js/Pages/Products/ProductCreate.jsx`
    - a secao `Cadastro fiscal` agora destaca visualmente que:
      - `NCM` e `CFOP` sao obrigatorios;
      - `CSOSN` ou `CST` precisam ser informados.
    - adicionados marcadores visuais `*` nos campos fiscais essenciais.
  - `resources/js/Pages/Products/ProductEdit.jsx`
    - recebeu o mesmo reforco visual e obrigatoriedade do cadastro.

- efeito pratico:
  - novos produtos nao poderao mais entrar no sistema sem os dados fiscais minimos exigidos pela emissao;
  - a falha deixa de aparecer so na nota e passa a ser tratada na origem, no cadastro do produto.

- arquivos alterados:
  - `app/Http/Controllers/ProductController.php`
  - `resources/js/Pages/Products/ProductCreate.jsx`
  - `resources/js/Pages/Products/ProductEdit.jsx`
  - `SYNC.md`

- observacoes para sincronizar em `pec1`:
  - sincronizar os tres arquivos juntos;
  - este ajuste nao depende de migration;
  - ele apenas endurece a validacao e o formulario para impedir novos produtos fiscalmente incompletos.

## 16/04/26 - NFC-e com `infNFeSupl`, `qrCode` e `urlChave` para corrigir rejeicao de schema

- causa do problema:
  - o gerador de XML da `NFC-e` montava `ide`, `emit`, `det`, `total`, `pag` e assinatura;
  - porem o XML estava saindo sem o bloco suplementar `infNFeSupl`;
  - para `NFC-e`, esse bloco carrega `qrCode` e `urlChave`, e sua ausencia deixava o documento incompleto para o leiaute esperado pela SEFAZ;
  - como efeito pratico, notas como a `#20145` podiam chegar ao envio e retornar `Falha no Schema XML do lote de NFe`.

- o que foi ajustado:
  - `app/Support/FiscalNfceXmlService.php`
    - passou a depender de `FiscalWebserviceResolverService`;
    - agora resolve os enderecos oficiais da `UF` antes de finalizar o XML;
    - adiciona o bloco `infNFeSupl` dentro da `NFe`;
    - preenche:
      - `qrCode`
      - `urlChave`
    - o `qrCode` foi montado no formato simplificado da versao `3` para emissao `on-line`:
      - `...?p=<chave_acesso>|3|<tpAmb>`
  - `app/Support/FiscalWebserviceResolverService.php`
    - manteve a URL de consulta por `QR Code` da `NFC-e`;
    - passou a diferenciar a URL de consulta por chave (`urlChave`) da URL do `qrCode`;
    - para `GO`, `urlChave` agora aponta para `.../sites/nfe/consulta-completa`.
  - `tests/Unit/FiscalWebserviceResolverServiceTest.php`
    - passou a validar tambem `qr_code_url` e `consultation_url`.
  - `tests/Unit/FiscalNfceXmlServiceTest.php`
    - novo teste cobrindo a criacao de `infNFeSupl` com `qrCode` e `urlChave`.
  - `tests/Unit/FiscalInvoicePreparationServiceTest.php`
    - ajustado para a nova dependencia do gerador de XML.

- efeito pratico:
  - a `NFC-e` agora sai com a parte suplementar esperada pelo leiaute;
  - o XML assinado fica estruturalmente mais proximo do padrao exigido para transmissao;
  - isso ataca diretamente a causa mais provavel da rejeicao de schema observada no envio.

- validacao:
  - `php -l app/Support/FiscalNfceXmlService.php`: ok.
  - `php -l app/Support/FiscalWebserviceResolverService.php`: ok.
  - `php artisan test tests/Unit/FiscalWebserviceResolverServiceTest.php tests/Unit/FiscalNfceXmlServiceTest.php tests/Unit/FiscalInvoicePreparationServiceTest.php`: ok.

- arquivos alterados:
  - `app/Support/FiscalNfceXmlService.php`
  - `app/Support/FiscalWebserviceResolverService.php`
  - `tests/Unit/FiscalWebserviceResolverServiceTest.php`
  - `tests/Unit/FiscalInvoicePreparationServiceTest.php`
  - `SYNC.md`

- arquivos criados:
  - `tests/Unit/FiscalNfceXmlServiceTest.php`

- observacoes para sincronizar em `pec1`:
  - sincronizar estes arquivos em conjunto;
  - ponto critico:
    - se copiar apenas o `FiscalNfceXmlService.php` sem o `FiscalWebserviceResolverService.php`, o XML vai tentar buscar chaves de endpoint que ainda nao existem;
    - se copiar apenas o resolver sem o XML service, o sistema continua transmitindo a `NFC-e` sem `infNFeSupl`;
  - depois da sincronizacao, reprocessar a nota que falhou para que um novo XML seja gerado com `qrCode` e `urlChave`.

## 16/04/26 - Endurecimento do XML da NFC-e contra rejeicao de schema

- causa do ajuste:
  - mesmo apos incluir `infNFeSupl`, a SEFAZ continuou retornando `Falha no Schema XML do lote de NFe`;
  - isso indicou que o XML ainda podia conter algum campo estruturalmente valido na interface, mas invalido no schema:
    - campos numericos com mascara em cadastros antigos;
    - `CEP`, `cMun`, `NCM`, `CEST` e `CFOP` enviados sem saneamento final no momento da geracao do XML;
    - retorno da SEFAZ ainda muito generico, sem destacar o `cStat`.

- o que foi ajustado:
  - `app/Support/FiscalNfceXmlService.php`
    - passou a normalizar novamente, na hora de gerar o XML, os campos sensiveis ao schema;
    - agora `cMunFG`, `cMun`, `CEP`, `NCM`, `CEST`, `CFOP` e `orig` sao tratados para sair sem mascara;
    - quando algum desses campos obrigatorios nao fecha exatamente no tamanho fiscal esperado, o service interrompe a geracao com erro explicito antes do envio;
    - isso protege tambem notas geradas a partir de cadastros antigos que ja estavam salvos com mascara.
  - `app/Support/FiscalNfceTransmissionService.php`
    - melhorado o parse da resposta para incluir o `cStat` na mensagem final;
    - quando existir `SOAP Fault`, a razao do fault passa a ser considerada no texto de erro.
  - `tests/Unit/FiscalNfceXmlServiceTest.php`
    - novo cenario cobrindo normalizacao de `cMun`, `CEP`, `NCM`, `CEST` e `CFOP`.
  - `tests/Unit/FiscalNfceTransmissionServiceTest.php`
    - novo teste cobrindo a mensagem de erro com prefixo `cStat`.

- efeito pratico:
  - o XML da `NFC-e` fica mais resistente a rejeicoes de schema causadas por mascara ou formato salvo em registros antigos;
  - quando a SEFAZ rejeitar novamente, a mensagem salva no sistema fica mais diagnostica.

- validacao:
  - `php -l app/Support/FiscalNfceXmlService.php`: ok.
  - `php -l app/Support/FiscalNfceTransmissionService.php`: ok.
  - `php -l tests/Unit/FiscalNfceXmlServiceTest.php`: ok.
  - `php -l tests/Unit/FiscalNfceTransmissionServiceTest.php`: ok.
  - `php artisan test tests/Unit/FiscalWebserviceResolverServiceTest.php tests/Unit/FiscalNfceXmlServiceTest.php tests/Unit/FiscalNfceTransmissionServiceTest.php tests/Unit/FiscalInvoicePreparationServiceTest.php`: ok.

- arquivos alterados:
  - `app/Support/FiscalNfceXmlService.php`
  - `app/Support/FiscalNfceTransmissionService.php`
  - `tests/Unit/FiscalNfceXmlServiceTest.php`
  - `SYNC.md`

- arquivos criados:
  - `tests/Unit/FiscalNfceTransmissionServiceTest.php`

- observacoes para sincronizar em `pec1`:
  - sincronizar estes arquivos junto com as etapas fiscais anteriores;
  - depois da sincronizacao, regenerar a nota com erro para que o XML seja montado novamente com os campos saneados;
  - se ainda houver rejeicao `225`, o sistema agora deve devolver uma mensagem mais informativa para a proxima investigacao.

## 17/04/26 - Travamento de UF x codigo IBGE e correcao pontual da configuracao fiscal da loja

- causa do problema:
  - ao inspecionar o XML real da nota `#20145`, foi confirmado que a emissao estava em `homologacao`, mas com dados geograficos inconsistentes;
  - a configuracao fiscal da loja estava assim:
    - `UF = GO`
    - `municipio = AGUAS LINDAS`
    - `codigo_municipio = 5300108`
  - o codigo `5300108` pertence a `Brasilia/DF`, nao a `Aguas Lindas de Goias/GO`;
  - isso fazia a nota sair com:
    - `cUF = 53`
    - `cMun = 5300108`
    - endpoint de `GO/homologacao`
  - essa mistura de `UF/IBGE` foi a primeira inconsistencia concreta confirmada no XML salvo da nota rejeitada.

- o que foi ajustado no codigo:
  - `app/Support/FiscalMunicipalityCodeService.php`
    - novo servico com o mapa de prefixos IBGE por `UF`;
    - usado para conferir se os 2 primeiros digitos do codigo do municipio pertencem a `UF` informada.
  - `app/Http/Controllers/FiscalConfigurationController.php`
    - antes de salvar a configuracao fiscal, agora valida a combinacao:
      - `tb26_uf`
      - `tb26_codigo_municipio`
    - se o codigo nao pertencer a `UF`, o salvamento e bloqueado com mensagem explicita.
  - `app/Support/FiscalInvoicePreparationService.php`
    - a preparacao da nota tambem passou a validar essa compatibilidade;
    - isso protege notas geradas a partir de configuracoes antigas que ja estavam erradas no banco.
  - `tests/Unit/FiscalMunicipalityCodeServiceTest.php`
    - novo teste cobrindo a regra do prefixo IBGE por `UF`.
  - `tests/Unit/FiscalInvoicePreparationServiceTest.php`
    - novo cenario cobrindo configuracao com `GO` + codigo de municipio de `DF`.

- correcao aplicada diretamente no banco deste projeto:
  - configuracao fiscal `tb26_id = 1` / loja `tb2_id = 3`
    - `tb26_codigo_municipio`: de `5300108` para `5200258`
    - `tb26_municipio`: de `AGUAS LINDAS` para `AGUAS LINDAS DE GOIAS`
  - esta foi uma correcao de dado, nao de estrutura;
  - por isso nao foi criada migration nova.

- efeito pratico:
  - a nota `#20145` foi reprocessada apos a correcao de dados;
  - ela voltou para status `xml_assinado`;
  - o novo XML ficou com:
    - `cUF = 52`
    - `cMunFG = 5200258`
    - `cMun = 5200258`
    - `xMun = AGUAS LINDAS DE GOIAS`

- validacao:
  - `php -l app/Support/FiscalMunicipalityCodeService.php`: ok.
  - `php -l app/Http/Controllers/FiscalConfigurationController.php`: ok.
  - `php -l app/Support/FiscalInvoicePreparationService.php`: ok.
  - `php -l tests/Unit/FiscalMunicipalityCodeServiceTest.php`: ok.
  - `php -l tests/Unit/FiscalInvoicePreparationServiceTest.php`: ok.
  - `php artisan test tests/Unit/FiscalMunicipalityCodeServiceTest.php tests/Unit/FiscalInvoicePreparationServiceTest.php tests/Unit/FiscalWebserviceResolverServiceTest.php tests/Unit/FiscalNfceXmlServiceTest.php tests/Unit/FiscalNfceTransmissionServiceTest.php`: ok.

- arquivos alterados:
  - `app/Http/Controllers/FiscalConfigurationController.php`
  - `app/Support/FiscalInvoicePreparationService.php`
  - `tests/Unit/FiscalInvoicePreparationServiceTest.php`
  - `SYNC.md`

- arquivos criados:
  - `app/Support/FiscalMunicipalityCodeService.php`
  - `tests/Unit/FiscalMunicipalityCodeServiceTest.php`

- observacoes para sincronizar em `pec1`:
  - sincronizar os arquivos de codigo acima;
  - no banco do `pec1`, verificar se existe configuracao fiscal com `UF` diferente do prefixo do codigo IBGE;
  - se houver loja equivalente a esta:
    - corrigir `tb26_codigo_municipio` para `5200258`
    - corrigir `tb26_municipio` para `AGUAS LINDAS DE GOIAS`
  - depois da sincronizacao, reprocessar as notas fiscais pendentes/erro da loja para gerar XML novo com o municipio correto.

## 17/04/26 - Normalizacao da inscricao estadual do emitente para evitar schema 225

- causa do problema:
  - mesmo apos corrigir `UF` e `codigo_municipio`, a nota `#20145` continuou retornando `cStat 225`;
  - ao inspecionar o XML real salvo da nota, foi confirmado que o campo `IE` do emitente ainda estava saindo com mascara:
    - `20.303.012-5`
  - a documentacao oficial da NF-e indica que a `IE` do emitente deve ser enviada apenas com algarismos, sem pontuacao, ou com a literal `ISENTO` quando aplicavel;
  - isso mantinha o XML sujeito a rejeicao por schema.

- o que foi ajustado no codigo:
  - `app/Http/Controllers/FiscalConfigurationController.php`
    - o campo `tb26_ie` agora e normalizado no salvamento;
    - se vier como `ISENTO`, preserva `ISENTO`;
    - se vier com mascara, salva apenas os digitos.
  - `app/Support/FiscalNfceXmlService.php`
    - o XML da `NFC-e` agora nunca envia `IE` com pontuacao;
    - se a inscricao estadual estiver invalida, a geracao para antes do envio com erro explicito.
  - `app/Support/FiscalInvoicePreparationService.php`
    - a preparacao da nota passou a validar a `IE` da unidade;
    - aceita:
      - apenas digitos com 2 a 14 posicoes;
      - ou `ISENTO`.
  - `tests/Unit/FiscalNfceXmlServiceTest.php`
    - passou a validar que `IE` com mascara e serializada no XML sem pontuacao.
  - `tests/Unit/FiscalInvoicePreparationServiceTest.php`
    - novo cenario cobrindo `IE` invalida na configuracao fiscal.

- correcao aplicada diretamente no banco deste projeto:
  - configuracao fiscal `tb26_id = 1` / loja `tb2_id = 3`
    - `tb26_ie`: de `20.303.012-5` para `203030125`
  - apos isso, a nota `#20145` foi reprocessada para gerar novo XML com `IE` sem mascara.

- efeito pratico:
  - a configuracao fiscal passa a armazenar `IE` em formato compativel com o schema;
  - notas novas e reprocessadas nao herdam mais pontuacao antiga no campo do emitente;
  - a investigacao da `#20145` ficou mais objetiva, porque o XML regenerado agora elimina mais uma causa concreta de schema.

- validacao:
  - `php -l app/Http/Controllers/FiscalConfigurationController.php`: ok.
  - `php -l app/Support/FiscalNfceXmlService.php`: ok.
  - `php -l app/Support/FiscalInvoicePreparationService.php`: ok.
  - `php -l tests/Unit/FiscalNfceXmlServiceTest.php`: ok.
  - `php -l tests/Unit/FiscalInvoicePreparationServiceTest.php`: ok.
  - `php artisan test tests/Unit/FiscalMunicipalityCodeServiceTest.php tests/Unit/FiscalInvoicePreparationServiceTest.php tests/Unit/FiscalWebserviceResolverServiceTest.php tests/Unit/FiscalNfceXmlServiceTest.php tests/Unit/FiscalNfceTransmissionServiceTest.php`: ok.

- arquivos alterados:
  - `app/Http/Controllers/FiscalConfigurationController.php`
  - `app/Support/FiscalNfceXmlService.php`
  - `app/Support/FiscalInvoicePreparationService.php`
  - `tests/Unit/FiscalNfceXmlServiceTest.php`
  - `tests/Unit/FiscalInvoicePreparationServiceTest.php`
  - `SYNC.md`

- observacoes para sincronizar em `pec1`:
  - sincronizar os arquivos acima junto com a blindagem de `UF x codigo IBGE`;
  - no banco do `pec1`, verificar se a `IE` da configuracao fiscal da loja equivalente tambem esta com mascara;
  - se estiver, remover a pontuacao antes de reprocessar as notas fiscais pendentes/erro.

## 17/04/26 - Ajuste do bloco de pagamento fiscal para `maquina`, `vale` e `refeicao`

- causa do problema:
  - depois de corrigir `UF`, `codigo IBGE` e `IE`, o `cStat 225` da nota `#20145` continuou;
  - ao inspecionar o XML real da nota, foi confirmado que:
    - a venda estava com `tipo_pagamento = maquina`;
    - o XML estava saindo com `<tPag>99</tPag>` e sem `<xPag>`;
  - alem disso, pagamentos `vale` tambem caiam no fallback generico `99`, em vez de usar o codigo fiscal proprio.

- o que foi ajustado:
  - `app/Support/FiscalNfceXmlService.php`
    - o bloco `pag/detPag` deixou de usar apenas um resolvedor de codigo simples;
    - agora existe um resolvedor completo de pagamento fiscal com:
      - codigo (`tPag`);
      - descricao (`xPag`) quando necessaria.
    - mapeamento aplicado:
      - `dinheiro` -> `01`
      - `vale` -> `10`
      - `refeicao` -> `11`
      - `faturar` -> `90`
      - `maquina` -> `99` com `xPag = MAQUINA`
      - tipos desconhecidos -> `99` com `xPag` gerado a partir do nome informado.
  - `tests/Unit/FiscalNfceXmlServiceTest.php`
    - novo cenario cobrindo:
      - `maquina` gerando `tPag 99` com `xPag`;
      - `vale` gerando `tPag 10` sem `xPag`.

- efeito pratico:
  - o XML fiscal deixa de usar fallback generico silencioso para meios de pagamento conhecidos;
  - o grupo de pagamento fica mais aderente ao leiaute fiscal atual, especialmente nos casos em que o sistema interno usa o label `maquina`.

- validacao:
  - `php -l app/Support/FiscalNfceXmlService.php`: ok.
  - `php -l tests/Unit/FiscalNfceXmlServiceTest.php`: ok.
  - `php artisan test tests/Unit/FiscalMunicipalityCodeServiceTest.php tests/Unit/FiscalInvoicePreparationServiceTest.php tests/Unit/FiscalWebserviceResolverServiceTest.php tests/Unit/FiscalNfceXmlServiceTest.php tests/Unit/FiscalNfceTransmissionServiceTest.php`: ok.

- arquivos alterados:
  - `app/Support/FiscalNfceXmlService.php`
  - `tests/Unit/FiscalNfceXmlServiceTest.php`
  - `SYNC.md`

- observacoes para sincronizar em `pec1`:
  - sincronizar este ajuste junto com as correcoes anteriores do XML fiscal;
  - depois da sincronizacao, reprocessar notas emitidas com `tipo_pagamento = maquina` para que o novo `detPag` seja reconstruido no XML.

## 17/04/26 - Correcao da ordem `Signature` x `infNFeSupl` na raiz da NFC-e

- causa do problema:
  - mesmo depois de corrigir `UF`, `codigo IBGE`, `IE` e o bloco de pagamento, a nota `#20145` continuou retornando `cStat 225`;
  - ao revisar o gerador do XML da `NFC-e`, foi identificado um detalhe estrutural na raiz do documento `NFe`;
  - o fluxo estava montando a raiz nesta ordem:
    - `infNFe`
    - `infNFeSupl`
    - `Signature`
  - para a `NFC-e`, esse tipo de inversao entre a assinatura digital e o bloco suplementar e um candidato forte a rejeicao de schema, porque a ordem dos nos faz parte do leiaute validado pela SEFAZ.

- o que foi ajustado:
  - `app/Support/FiscalNfceXmlService.php`
    - a assinatura digital agora e anexada antes do bloco suplementar;
    - a raiz do `NFe` passa a ser montada nesta ordem:
      - `infNFe`
      - `Signature`
      - `infNFeSupl`
  - `tests/Unit/FiscalNfceXmlServiceTest.php`
    - novo teste cobrindo explicitamente a ordem dos nos da raiz do `NFe`;
    - o teste garante que `infNFeSupl` nunca mais fique antes de `Signature`.

- efeito pratico:
  - o XML assinado da `NFC-e` fica mais aderente ao leiaute estrutural esperado;
  - isso ataca um ponto tipico de rejeicao `225` que nao aparece na interface e nem nos dados cadastrais.

- validacao:
  - pendente reprocessar e retransmitir a nota `#20145` assim que a conexao com o banco externo responder novamente;
  - o teste unitario passou a proteger a ordem correta do XML em nivel de codigo.

- arquivos alterados:
  - `app/Support/FiscalNfceXmlService.php`
  - `tests/Unit/FiscalNfceXmlServiceTest.php`
  - `SYNC.md`

- observacoes para sincronizar em `pec1`:
  - sincronizar estes arquivos junto com as etapas fiscais anteriores;
  - depois da sincronizacao, reprocessar qualquer nota `NFC-e` que tenha sido gerada antes desta correcao, porque o XML antigo continua com a ordem estrutural errada.

## 17/04/26 - Botao `Cupom R$0,00` com DANFE NFC-e 80mm em `settings/fiscal`

- causa do ajuste:
  - na lista `Ultimas notas preparadas`, o botao ainda usava o utilitario de recibo interno da venda;
  - isso fazia o clique abrir apenas um comprovante simples, sem estrutura fiscal, sem chave de acesso e sem `QR Code`;
  - alem disso, o rotulo do botao nao mostrava o valor da nota.

- o que foi ajustado:
  - `app/Http/Controllers/FiscalConfigurationController.php`
    - a listagem das notas passou a enviar:
      - `total` da nota;
      - `protocolo`;
      - `recibo`;
      - payload `fiscal_receipt` proprio da nota fiscal;
    - criado um montador especifico para cupom fiscal 80mm;
    - o payload fiscal agora inclui:
      - emitente;
      - endereco;
      - CNPJ;
      - IE;
      - serie e numero;
      - ambiente;
      - status;
      - itens;
      - total;
      - chave de acesso;
      - protocolo;
      - recibo;
      - `qrCode` e `urlChave` extraidos do XML assinado.
  - `resources/js/Utils/receipt.js`
    - mantido o recibo interno antigo para as outras telas;
    - criado `buildFiscalReceiptHtml()` para imprimir um cupom fiscal estilo `DANFE NFC-e` em largura `80mm`;
    - o HTML fiscal mostra:
      - cabecalho fiscal;
      - emitente;
      - itens;
      - pagamento;
      - total;
      - chave de acesso;
      - `QR Code`;
      - observacao de previa quando a nota ainda nao estiver autorizada.
  - `resources/js/Pages/Settings/FiscalConfig.jsx`
    - o botao deixou de se chamar `Imprimir cupom`;
    - agora o rotulo segue o formato:
      - `Cupom R$ 0,00`
    - o clique passou a abrir o cupom fiscal da nota, nao o recibo interno da venda;
    - a rotina de impressao foi ajustada para deixar o proprio HTML fiscal controlar o momento da impressao.

- efeito pratico:
  - a area fiscal passa a imprimir um cupom muito mais proximo do que se espera de uma `NFC-e` em impressora `80mm`;
  - o botao ja mostra o valor da nota na propria lista;
  - notas ainda nao autorizadas continuam imprimiveis como previa fiscal para conferencia.

- validacao:
  - validar com:
    - `php -l app/Http/Controllers/FiscalConfigurationController.php`
    - `cmd /c npm run build`
  - recomendacao pratica:
    - abrir `settings/fiscal`;
    - clicar em um `Cupom R$...`;
    - conferir impressao em navegador e impressora `80mm`.

- arquivos alterados:
  - `app/Http/Controllers/FiscalConfigurationController.php`
  - `resources/js/Pages/Settings/FiscalConfig.jsx`
  - `resources/js/Utils/receipt.js`
  - `SYNC.md`

- observacoes para sincronizar em `pec1`:
  - sincronizar os tres arquivos juntos;
  - se copiar apenas a tela sem o controller, o frontend nao recebera o payload `fiscal_receipt`;
  - se copiar apenas o controller sem o novo utilitario, o botao vai existir mas a impressao fiscal nao sera montada.

## 17/04/26 - Item `Transmitir` no dropdown do dashboard com modal de notas pendentes

- causa do ajuste:
  - o dropdown do dashboard mostrava apenas atalhos estaticos;
  - nao existia nenhum resumo imediato das notas prontas para transmissao;
  - para transmitir uma nota fiscal, o usuario precisava navegar manualmente ate `settings/fiscal` e localizar a nota na lista.

- o que foi ajustado:
  - `app/Http/Middleware/HandleInertiaRequests.php`
    - passou a compartilhar globalmente um resumo das notas com status `xml_assinado`;
    - o resumo e limitado ao escopo das lojas que o usuario administrador pode gerenciar;
    - cada item compartilhado inclui:
      - loja;
      - venda;
      - modelo;
      - serie/numero;
      - valor;
      - data;
      - status;
      - mensagem;
      - rota para `Transmitir`;
      - rota para abrir `settings/fiscal`.
  - `resources/js/Layouts/AuthenticatedLayout.jsx`
    - adicionado no dropdown desktop o item:
      - `Transmitir (N)`
    - adicionado o mesmo atalho no menu mobile;
    - ao clicar, abre uma modal global com a lista das notas pendentes para transmissao;
    - cada registro da modal possui:
      - dados resumidos da nota;
      - botao `Abrir fiscal`;
      - botao `Transmitir`.

- regra aplicada:
  - o resumo so aparece para perfis administrativos (`MASTER` e `GERENTE`);
  - entram na fila apenas notas com status `xml_assinado`, ou seja, ja prontas para envio;
  - `MASTER` enxerga todas as lojas;
  - `GERENTE` enxerga apenas as lojas dentro do proprio escopo de gestao.

- efeito pratico:
  - o dashboard passa a funcionar tambem como painel rapido de transmissao fiscal;
  - o usuario nao precisa mais sair do fluxo principal para descobrir quantas notas estao pendentes de envio;
  - a transmissao continua reaproveitando a mesma rota fiscal ja existente, sem duplicar logica.

- validacao:
  - validar com:
    - `php -l app/Http/Middleware/HandleInertiaRequests.php`
    - `cmd /c npm run build`
  - conferir visualmente:
    - dropdown desktop;
    - menu mobile;
    - abertura da modal;
    - clique em `Transmitir`.

- arquivos alterados:
  - `app/Http/Middleware/HandleInertiaRequests.php`
  - `resources/js/Layouts/AuthenticatedLayout.jsx`
  - `SYNC.md`

- observacoes para sincronizar em `pec1`:
  - sincronizar os dois arquivos de codigo juntos;
  - se copiar apenas o layout sem o middleware, o item `Transmitir` aparecera sem dados;
  - se copiar apenas o middleware sem o layout, o resumo global sera enviado mas nao sera exibido no menu.

## 17/04/26 - Correcao do caminho de armazenamento do certificado fiscal

- causa do problema:
  - ao regenerar a nota, o sistema retornava:
    - `Arquivo do certificado nao encontrado no armazenamento local.`
  - a investigacao mostrou que o disco `local` ja aponta para `storage/app/private`;
  - porem o upload do certificado estava sendo salvo com o prefixo:
    - `private/fiscal-certificados/...`
  - isso criava um caminho redundante dentro do proprio disco:
    - `storage/app/private/private/fiscal-certificados/...`
  - alem disso, lojas com configuracao antiga poderiam ficar com o path legado salvo no banco.

- o que foi ajustado:
  - `app/Http/Controllers/FiscalConfigurationController.php`
    - o upload do certificado passou a salvar no caminho correto do disco `local`:
      - `fiscal-certificados/<tb2_id>/...`
    - com isso, o arquivo deixa de cair no subdiretorio redundante `private/private`.
  - `app/Support/FiscalCertificateService.php`
    - a leitura do certificado passou a aceitar:
      - caminho novo: `fiscal-certificados/...`
      - caminho legado: `private/fiscal-certificados/...`
    - isso preserva compatibilidade com configuracoes antigas ja gravadas no banco;
    - a mensagem de erro de arquivo ausente agora inclui o caminho fiscal salvo para facilitar o diagnostico.
  - `tests/Unit/FiscalCertificateServiceTest.php`
    - novo teste cobrindo a resolucao do path atual e do legado para o mesmo arquivo no storage.

- efeito pratico:
  - novos uploads ficam salvos no lugar correto;
  - leituras antigas nao quebram por causa da mudanca de prefixo;
  - quando o arquivo realmente nao existir no storage, o erro fica mais objetivo.

- validacao:
  - validar com:
    - `php -l app/Http/Controllers/FiscalConfigurationController.php`
    - `php -l app/Support/FiscalCertificateService.php`
    - `php artisan test tests/Unit/FiscalCertificateServiceTest.php`
  - observacao operacional:
    - se a configuracao fiscal apontar para um arquivo inexistente de fato, sera necessario reenviar o certificado da loja para repor o arquivo fisico.

- arquivos alterados:
  - `app/Http/Controllers/FiscalConfigurationController.php`
  - `app/Support/FiscalCertificateService.php`
  - `tests/Unit/FiscalCertificateServiceTest.php`
  - `SYNC.md`

- observacoes para sincronizar em `pec1`:
  - sincronizar estes arquivos em conjunto;
  - se o `pec1` ja tiver configuracoes gravadas com prefixo `private/`, o novo service continua aceitando;
  - para novos uploads no `pec1`, o caminho salvo passara a ser o formato corrigido sem o prefixo duplicado.

## 17/04/26 - Reposicao do arquivo fisico do certificado da loja 3 e regeneracao da venda `20145`

- causa do problema:
  - mesmo apos a correcao de codigo do caminho fiscal, a venda `20145` continuava falhando ao regenerar;
  - a configuracao fiscal da loja `tb2_id = 3` apontava para:
    - `private/fiscal-certificados/3/certificado-20260416164839.pfx`
  - porem o arquivo fisico nao existia no storage local da aplicacao;
  - o unico `.pfx` disponivel no ambiente estava na raiz do projeto.

- o que foi corrigido diretamente neste ambiente:
  - copiado o arquivo:
    - `PAOECAFEBARRAGEM01LTDA_12345678.pfx`
  - para os caminhos:
    - `storage/app/private/fiscal-certificados/3/certificado-20260416164839.pfx`
    - `storage/app/private/private/fiscal-certificados/3/certificado-20260416164839.pfx`
  - o segundo caminho foi mantido apenas como compatibilidade imediata com o path legado salvo no banco.

- ajuste de dado aplicado no banco deste projeto:
  - configuracao fiscal da loja `tb2_id = 3`
    - `tb26_certificado_arquivo`:
      - de `private/fiscal-certificados/3/certificado-20260416164839.pfx`
      - para `fiscal-certificados/3/certificado-20260416164839.pfx`

- validacao executada:
  - a configuracao da loja voltou a abrir o certificado com sucesso;
  - leitura confirmada do certificado:
    - CNPJ: `62074417000156`
    - nome: `PAO E CAFE BARRAGEM 01 LTDA:62074417000156`
  - a venda `20145` foi reprocessada no fluxo real;
  - a nota fiscal vinculada voltou para:
    - `xml_assinado`
    - mensagem: `XML fiscal assinado localmente e aguardando transmissao para a SEFAZ.`

- efeito pratico:
  - o erro `Arquivo do certificado nao encontrado no armazenamento local` deixou de bloquear a regeneracao da nota desta loja;
  - a proxima etapa operacional volta a ser a transmissao da nota.

- arquivos alterados nesta correcao operacional:
  - `SYNC.md`

- arquivos criados/copied no ambiente:
  - `storage/app/private/fiscal-certificados/3/certificado-20260416164839.pfx`
  - `storage/app/private/private/fiscal-certificados/3/certificado-20260416164839.pfx`

- observacoes para sincronizar em `pec1`:
  - alem do codigo, verificar se o arquivo fisico do certificado tambem existe no storage do `pec1`;
  - se o banco do `pec1` apontar para um `.pfx` inexistente, repor o arquivo antes de tentar regenerar as notas;
  - se houver o mesmo certificado em local externo ao storage, copiar para `storage/app/private/fiscal-certificados/<tb2_id>/...` e alinhar `tb26_certificado_arquivo` para o caminho sem o prefixo legado `private/`.

## 17/04/26 - Blindagem de producao para `settings/fiscal` e rotas moveis ausentes

- causa do problema:
  - apos o deploy em producao, a tela `settings/fiscal` retornava `500 Server Error`;
  - havia dois pontos de risco:
    - o layout global passou a consultar `tb27_notas_fiscais` em toda requisicao autenticada para alimentar o item `Transmitir` do dropdown;
    - o proprio controller fiscal assume que as tabelas `tb26_configuracoes_fiscais` e `tb27_notas_fiscais` ja existem no banco;
  - se o deploy subir o codigo antes de aplicar as migrations fiscais, qualquer uma dessas consultas pode derrubar a aplicacao;
  - alem disso, `routes/web.php` referenciava `MobileRevenueController`, mas esse controller nao existe no projeto atual, o que pode quebrar `route:list`, cache de rotas e alguns processos de deploy.

- o que foi ajustado:
  - `app/Http/Middleware/HandleInertiaRequests.php`
    - a montagem de `pendingFiscalTransmissions` agora verifica antes se as tabelas fiscais existem;
    - se a base fiscal ainda nao estiver pronta, retorna `count = 0` e `items = []` sem derrubar a aplicacao;
    - tambem foi adicionada protecao com `try/catch` para evitar `500` em falhas de banco nessa etapa global.
  - `app/Http/Controllers/FiscalConfigurationController.php`
    - a acao `index()` agora verifica se as tabelas fiscais ja existem;
    - se nao existirem, a tela continua abrindo sem `500`;
    - nesse caso, ela renderiza a pagina com listas vazias e uma mensagem explicando que as migrations fiscais ainda nao foram aplicadas no ambiente.
  - `resources/js/Pages/Settings/FiscalConfig.jsx`
    - passou a exibir a mensagem amigavel de indisponibilidade da base fiscal quando o backend detectar ambiente sem as tabelas novas.
  - `routes/web.php`
    - as rotas de `MobileRevenueController` passaram a ser registradas apenas se a classe realmente existir no projeto;
    - isso impede que um controller ausente quebre `route:list` e etapas de deploy/caching.

- efeito pratico:
  - `settings/fiscal` deixa de cair com `500` quando o deploy sobe antes das migrations fiscais;
  - o dropdown global continua funcionando mesmo sem a base fiscal pronta;
  - o projeto deixa de depender da existencia do `MobileRevenueController` para montar as rotas.

- validacao:
  - validar com:
    - `php -l app/Http/Middleware/HandleInertiaRequests.php`
    - `php -l app/Http/Controllers/FiscalConfigurationController.php`
    - `php -l routes/web.php`
    - `cmd /c npm run build`

- arquivos alterados:
  - `app/Http/Middleware/HandleInertiaRequests.php`
  - `app/Http/Controllers/FiscalConfigurationController.php`
  - `resources/js/Pages/Settings/FiscalConfig.jsx`
  - `routes/web.php`
  - `SYNC.md`

- observacoes para sincronizar em `pec1`:
  - sincronizar estes arquivos em conjunto;
  - esta blindagem nao substitui a necessidade de aplicar as migrations fiscais em producao;
  - ela apenas evita que a interface inteira caia enquanto o banco ainda nao foi atualizado.

## 17/04/26 - Blindagem adicional de `settings/fiscal` contra XML invalido e ausencia de `dom/xml`

- causa do problema:
  - mesmo com a protecao para tabelas ausentes, `settings/fiscal` ainda podia retornar `500` em producao;
  - o controller passou a montar o cupom fiscal de cada nota ao abrir a tela;
  - isso depende de:
    - `DOMDocument`;
    - `DOMXPath`;
    - XML fiscal valido em `tb27_xml_envio`;
  - se o servidor estiver sem a extensao `dom/xml` ou se uma unica nota tiver XML corrompido, a tela inteira podia cair.

- o que foi ajustado:
  - `app/Http/Controllers/FiscalConfigurationController.php`
    - o carregamento da lista de notas passou a ficar dentro de `try/catch`;
    - se houver falha ao carregar a listagem fiscal, a tela continua abrindo e recebe um aviso amigavel;
    - cada nota agora e montada por um metodo isolado;
    - a construcao do `fiscal_receipt` de cada linha tambem fica protegida por `try/catch`, impedindo que uma nota quebrada derrube toda a tela;
    - `extractFiscalReceiptXmlData()` agora retorna vazio imediatamente quando `DOMDocument` ou `DOMXPath` nao existirem no ambiente.
  - `resources/js/Pages/Settings/FiscalConfig.jsx`
    - passou a exibir `invoiceLoadWarning` quando o backend conseguir abrir a tela, mas nao conseguir montar com seguranca a listagem fiscal completa.

- efeito pratico:
  - a tela `settings/fiscal` continua abrindo em producao mesmo se:
    - o PHP estiver sem `dom/xml`;
    - alguma nota tiver XML invalido;
    - a montagem do cupom fiscal falhar em uma ou mais linhas.
  - nesses casos, o impacto fica restrito ao cupom/listagem, sem derrubar a pagina toda.

- validacao:
  - validar com:
    - `php -l app/Http/Controllers/FiscalConfigurationController.php`
    - `cmd /c npm run build`

- arquivos alterados:
  - `app/Http/Controllers/FiscalConfigurationController.php`
  - `resources/js/Pages/Settings/FiscalConfig.jsx`
  - `SYNC.md`

- observacoes para sincronizar em `pec1`:
  - sincronizar estes arquivos junto com as blindagens anteriores de producao;
  - essa camada nao corrige o XML ruim em si, apenas impede que ele derrube a interface.
## 17/04/26 - Blindagem do salvamento fiscal em producao contra 500 no upload do certificado

- Arquivos alterados:
  - `app/Http/Controllers/FiscalConfigurationController.php`
  - `app/Support/FiscalCertificateService.php`

- Causa identificada:
  - em producao, o `settings/fiscal` ainda podia retornar `500` ao salvar a configuracao do certificado quando o ambiente falhasse em pontos como `OpenSSL`, escrita no storage local ou persistencia da configuracao fiscal;
  - a tela ja estava blindada no `GET`, mas o `POST` de salvamento ainda podia deixar a excecao escapar como erro interno;
  - se a extensao `OpenSSL` nao estiver disponivel no servidor, a leitura do `.pfx/.p12` via `openssl_pkcs12_read()` poderia falhar de forma estrutural no ambiente.

- O que foi feito:
  - `FiscalCertificateService` agora verifica explicitamente se as funcoes `openssl_pkcs12_read` e `openssl_x509_parse` existem antes de tentar ler o certificado;
  - quando `OpenSSL` nao estiver disponivel, o sistema passa a retornar uma mensagem controlada: `A extensao OpenSSL nao esta disponivel neste ambiente para ler o certificado digital.`;
  - `FiscalConfigurationController@update` foi blindado com `try/catch` amplo no fluxo de salvamento;
  - `ValidationException` continua subindo normalmente para manter mensagens de formulario;
  - falhas inesperadas de ambiente agora sao registradas com `report($exception)` e redirecionam de volta para `settings/fiscal` com mensagem amigavel, evitando `500` bruto na gravacao.

- Efeito esperado em producao:
  - salvar o certificado nao deve mais derrubar a rota com erro interno visivel ao usuario;
  - se o problema for `OpenSSL`, storage local ou divergencia de banco, a tela deve permanecer aberta e exibir erro amigavel;
  - a excecao continua registrada no log do Laravel/Azure para diagnostico fino do ambiente.

- Observacao para sincronizacao com `pec1`:
  - copiar exatamente essa blindagem do `update()` e da verificacao de `OpenSSL`, porque ela depende do mesmo fluxo de upload do certificado e reduz muito a dificuldade de diagnosticar falhas de producao.
## 17/04/26 - Diagnostico visual de banco, storage e leitura da senha do certificado em settings/fiscal

- Arquivos alterados:
  - `app/Http/Controllers/FiscalConfigurationController.php`
  - `resources/js/Pages/Settings/FiscalConfig.jsx`

- Causa identificada:
  - local e producao usam o mesmo banco, mas o comportamento fiscal pode divergir porque o certificado fisico fica no storage local de cada ambiente e a senha do certificado depende da `APP_KEY` para descriptografia;
  - a tela fiscal ainda lia `tb26_certificado_senha` pelo cast `encrypted`, o que pode falhar em producao se a `APP_KEY` nao coincidir com a do ambiente que gravou a senha;
  - isso podia fazer a pagina cair para o modo minimo, dando a impressao de que os dados nao foram gravados mesmo quando o registro existia no banco.

- O que foi feito:
  - `FiscalConfigurationController` agora envia um bloco `configurationDiagnostics` para a tela fiscal;
  - o diagnostico informa:
    - unidade selecionada;
    - se a configuracao foi encontrada no banco;
    - `tb26_id`;
    - caminho salvo do certificado;
    - se o arquivo existe no storage deste ambiente;
    - se existe no caminho legado `private/...`;
    - se ha senha criptografada salva no banco;
    - se a senha conseguiu ou nao ser lida neste ambiente;
  - `buildFiscalConfigurationPayload()` deixou de depender diretamente da leitura descriptografada para montar `has_certificate_password`;
  - foi criada uma leitura segura da senha criptografada usando `getRawOriginal('tb26_certificado_senha')` para evitar derrubar a tela so por divergencia de `APP_KEY`;
  - `FiscalConfig.jsx` agora mostra um card `Diagnostico do ambiente` logo abaixo do resumo do certificado.

- Efeito esperado:
  - em producao, a propria tela deve mostrar se o problema esta no banco, no storage do certificado ou na leitura da senha criptografada;
  - isso ajuda a diferenciar claramente:
    - registro salvo no banco mas arquivo ausente no Azure;
    - senha gravada no banco mas ilegivel por `APP_KEY` diferente;
    - configuracao sequer criada na tabela `tb26_configuracoes_fiscais`.

- Observacao para sincronizacao com `pec1`:
  - essa alteracao e importante para depuracao de ambientes compartilhando o mesmo banco;
  - sincronizar exatamente o card visual e a leitura segura da senha criptografada, porque isso reduz bastante o tempo de diagnostico quando local e servidor divergem.
## 17/04/26 - Resolucao definitiva da senha do certificado entre local e producao com mesmo banco

- Arquivos alterados:
  - `app/Models/ConfiguracaoFiscal.php`
  - `app/Http/Controllers/FiscalConfigurationController.php`
  - `app/Support/FiscalCertificateService.php`
  - `app/Support/FiscalInvoicePreparationService.php`
  - `resources/js/Pages/Settings/FiscalConfig.jsx`
  - `tests/Unit/FiscalCertificateServiceTest.php`

- Arquivos criados:
  - `database/migrations/2026_04_17_020000_add_shared_certificate_password_to_tb26_configuracoes_fiscais_table.php`

- Causa confirmada:
  - local e producao compartilham o mesmo banco, mas a senha do certificado estava presa ao cast `encrypted` de `tb26_certificado_senha`;
  - isso faz a leitura depender da `APP_KEY` do ambiente que gravou o dado;
  - com `APP_KEY` diferente entre local e Azure, a producao via o mesmo registro, mas nao conseguia descriptografar a senha;
  - resultado: a configuracao parecia inconsistente entre ambientes, mesmo com o mesmo banco.

- O que foi feito:
  - criada a coluna `tb26_certificado_senha_compartilhada` em `tb26_configuracoes_fiscais`;
  - a migration tenta migrar automaticamente para a nova coluna os valores antigos de `tb26_certificado_senha` quando estiver rodando em um ambiente capaz de descriptografar a senha antiga;
  - `FiscalCertificateService` agora:
    - prefere a senha compartilhada;
    - cai para a senha antiga criptografada somente quando necessario;
    - expõe detalhes da origem e legibilidade da senha;
  - `FiscalConfigurationController` passou a:
    - salvar a nova senha nos dois campos quando o usuario informar novamente;
    - preencher a senha compartilhada automaticamente quando a senha antiga ainda for legivel no ambiente atual;
    - usar o novo resolvedor de senha no upload e no diagnostico;
  - `FiscalInvoicePreparationService` agora valida a senha do certificado usando a senha efetivamente legivel no ambiente, e nao mais apenas o campo criptografado antigo;
  - `FiscalConfig.jsx` passou a mostrar no diagnostico:
    - se a senha compartilhada existe;
    - qual a fonte da senha lida (`shared` ou `legacy_encrypted`);
    - o status real da leitura no ambiente atual.

- Efeito esperado:
  - depois da migration rodar no banco compartilhado a partir do ambiente local, a senha legivel passa a ficar disponivel para a producao sem depender da `APP_KEY`;
  - o Azure deve conseguir abrir o certificado usando a senha compartilhada, mesmo que a senha antiga criptografada tenha sido gravada com outra `APP_KEY`.

- Observacao para sincronizacao com `pec1`:
  - sincronizar exatamente a migration e os ajustes do resolvedor de senha;
  - essa parte e critica porque resolve a divergencia mais traiçoeira entre ambientes com o mesmo banco, mas com `APP_KEY` diferentes.
## 17/04/26 - Exibicao do erro real de salvamento na tela fiscal

- Arquivos alterados:
  - `app/Http/Controllers/FiscalConfigurationController.php`
  - `resources/js/Pages/Settings/FiscalConfig.jsx`

- Causa identificada:
  - a gravacao da configuracao fiscal podia falhar antes de criar a linha em `tb26_configuracoes_fiscais`, mas a tela `settings/fiscal` nao exibia o `flash.error` retornado pelo backend;
  - isso fazia parecer que nada acontecia, mesmo quando o controller ja estava redirecionando com erro;
  - como o diagnostico depende da existencia da linha no banco, a ausencia desse feedback dificultava entender que o `save()` nem chegou a persistir.

- O que foi feito:
  - `FiscalConfig.jsx` agora mostra `flash.success` e `flash.error` usando o componente `AlertMessage`;
  - `FiscalConfigurationController@update` passou a devolver um `flash.error` com detalhe tecnico controlado, reaproveitando a mensagem da excecao quando existir;
  - com isso, a proxima tentativa de salvar em producao deve mostrar a causa real diretamente na interface.

- Efeito esperado:
  - quando a configuracao fiscal nao conseguir ser salva, o usuario deve ver o motivo na propria tela;
  - isso elimina o comportamento de “nao gravou e nao deu erro”.
## 17/04/26 - Neutralizacao definitiva do campo legado tb26_certificado_senha para parar o erro "The MAC is invalid."

- Arquivos alterados:
  - `app/Models/ConfiguracaoFiscal.php`
  - `app/Http/Controllers/FiscalConfigurationController.php`
  - `app/Support/FiscalCertificateService.php`

- Causa confirmada:
  - mesmo apos a criacao da senha compartilhada, o campo legado `tb26_certificado_senha` ainda permanecia no model com cast `encrypted`;
  - isso fazia o Laravel tentar interpretar um valor criptografado antigo com `APP_KEY` diferente, disparando `The MAC is invalid.` durante o fluxo de salvar a configuracao fiscal;
  - na pratica, o campo legado continuava contaminando o salvamento, apesar a senha compartilhada ja existir para resolver a divergencia entre ambientes.

- O que foi feito:
  - removido o cast `encrypted` de `tb26_certificado_senha` em `ConfiguracaoFiscal`;
  - `FiscalCertificateService` passou a descriptografar o campo legado manualmente via `Crypt::decryptString()` somente quando precisar tentar aproveita-lo;
  - `FiscalConfigurationController@update` passou a limpar `tb26_certificado_senha` sempre que a senha compartilhada estiver presente, deixando `tb26_certificado_senha_compartilhada` como fonte operacional principal;
  - quando uma nova senha e informada, ela agora fica apenas na senha compartilhada e o campo legado e neutralizado.

- Efeito esperado:
  - salvar a configuracao fiscal em producao nao deve mais falhar por causa de `The MAC is invalid.`;
  - a leitura operacional do certificado continua funcionando pela senha compartilhada;
  - o campo legado deixa de interferir no comportamento entre local e Azure.
## 17/04/26 - Instrumentacao do GET settings/fiscal para comparar leitura do model com leitura crua do banco

- Arquivos alterados:
  - `app/Http/Controllers/FiscalConfigurationController.php`
  - `resources/js/Pages/Settings/FiscalConfig.jsx`

- Causa investigada:
  - havia divergencia entre o comportamento do local e da producao ao recarregar `settings/fiscal`;
  - o `POST` ja retornava sucesso, mas o `GET` seguinte ainda caia no bloco de protecao, dando a impressao de que nada tinha sido salvo;
  - era necessario descobrir se a linha existia no banco e em qual etapa do carregamento a producao quebrava.

- O que foi feito:
  - `FiscalConfigurationController@index` agora registra a etapa atual do carregamento da tela (`carregar configuracao`, `carregar unidade`, `resolver endpoints`, `montar payload`, etc.);
  - quando o `GET` falhar, a mensagem exibida na propria tela agora informa:
    - a etapa em que a excecao ocorreu;
    - o detalhe tecnico da excecao;
  - foi criado um fallback de diagnostico por consulta crua via `DB::table('tb26_configuracoes_fiscais')`, sem passar pelo model;
  - o card `Diagnostico do ambiente` agora mostra tambem:
    - se a configuracao crua foi encontrada no banco;
    - o `tb26_id` cru;
    - a falha especifica de carregamento, quando existir.

- Efeito esperado:
  - em producao, depois do deploy, a tela deve revelar se:
    - o registro realmente existe no banco;
    - o model esta falhando ao hidratar/carregar;
    - ou se a excecao acontece em outra etapa do `GET /settings/fiscal`.
## 17/04/26 - Correcao do diagnostico da tela fiscal por chave de array divergente

- Arquivos alterados:
  - `app/Http/Controllers/FiscalConfigurationController.php`

- Causa identificada:
  - o `FiscalCertificateService` passou a devolver o status de leitura da senha na chave `readable`;
  - o controller da tela fiscal ainda tentava ler `decryptable`;
  - isso derrubava o `GET /settings/fiscal` na etapa `montar payload da configuracao fiscal para a tela` com o erro `Undefined array key "decryptable"`.

- O que foi feito:
  - `FiscalConfigurationController` passou a usar a chave correta `readable`;
  - a prop exibida na interface continua sendo `password_decryptable` apenas como nome visual legado, mas agora preenchida com o valor certo retornado pelo servico.

- Efeito esperado:
  - `settings/fiscal` deve voltar a abrir em producao sem cair por esse erro de diagnostico;
  - a partir disso, a tela passa a mostrar o estado real da configuracao fiscal da unidade.
## 17/04/26 - Remocao de CNAE solto no emitente da NFC-e para reduzir rejeicao de schema 225

- Arquivos alterados:
  - `app/Support/FiscalNfceXmlService.php`
  - `tests/Unit/FiscalNfceXmlServiceTest.php`

- Causa identificada:
  - o XML da NFC-e ainda podia sair com `CNAE` no bloco `emit` mesmo quando a configuracao fiscal da loja nao tinha `IM`;
  - no layout fiscal, esse grupo do emitente nao deve ir incompleto;
  - isso deixava o XML da nota estruturalmente suspeito para o schema e mantinha o `cStat 225 - Rejeicao: Falha no Schema XML do lote de NFe`.

- O que foi feito:
  - `FiscalNfceXmlService` passou a enviar `IM` e `CNAE` somente quando a `IM` estiver preenchida;
  - se a loja nao tiver `IM`, o XML deixa de mandar `CNAE` isolado no emitente;
  - os testes unitarios passaram a cobrir os dois cenarios:
    - sem `IM`, `CNAE` nao aparece;
    - com `IM`, `CNAE` continua sendo enviado junto.

- Validacao:
  - `php artisan test tests/Unit/FiscalNfceXmlServiceTest.php`

- Efeito esperado:
  - as notas NFC-e geradas para lojas sem `IM` deixam de levar um grupo parcial no emitente;
  - isso reduz mais uma causa concreta de schema invalido antes da transmissao para a SEFAZ.

- Observacoes para sincronizar em `pec1`:
  - sincronizar exatamente `app/Support/FiscalNfceXmlService.php` e `tests/Unit/FiscalNfceXmlServiceTest.php`;
  - nao depende de migration;
  - depois da sincronizacao, regenerar as notas pendentes que estavam com XML assinado antigo para que o novo XML seja reconstruido sem `CNAE` solto.

## 17/04/26 - Ajuste operacional do storage do certificado para reprocessar a nota 20145

- Arquivos alterados:
  - `SYNC.md`

- Causa identificada:
  - depois que a configuracao fiscal da loja `tb2_id = 3` foi salva novamente em outro ambiente, o banco compartilhado passou a apontar para o caminho `fiscal-certificados/3/certificado-20260417020907.pfx`;
  - neste ambiente local, o storage ainda tinha apenas o arquivo anterior `certificado-20260416164839.pfx`;
  - com isso, a nota `20145` nao conseguia nem regenerar o XML, porque parava em `Arquivo do certificado nao encontrado no armazenamento local`.

- O que foi feito neste ambiente:
  - copiado o certificado fisico existente para o novo nome esperado pelo banco compartilhado:
    - `storage/app/private/fiscal-certificados/3/certificado-20260417020907.pfx`
  - mantida tambem a compatibilidade no caminho legado:
    - `storage/app/private/private/fiscal-certificados/3/certificado-20260417020907.pfx`
  - apos esse alinhamento, a `20145` voltou a regenerar normalmente e o XML novo foi assinado sem `CNAE`.

- Observacoes para sincronizar em `pec1`:
  - se o banco compartilhado apontar para um nome de certificado que nao exista fisicamente no storage do ambiente, a regeneracao da nota vai falhar antes da transmissao;
  - nesses casos, conferir o valor atual de `tb26_certificado_arquivo` no banco e alinhar o arquivo fisico correspondente em:
    - `storage/app/private/fiscal-certificados/<tb2_id>/...`
  - esse ajuste foi operacional, nao estrutural; nao depende de migration nem de alteracao adicional de codigo.

## 17/04/26 - Exclusao segura de notas preparadas nao transmitidas em settings/fiscal

- Arquivos alterados:
  - `app/Http/Controllers/FiscalConfigurationController.php`
  - `resources/js/Pages/Settings/FiscalConfig.jsx`
  - `routes/web.php`

- Causa identificada:
  - a lista `Ultimas notas preparadas` nao tinha acao para descartar registros fiscais internos que ainda nao foram transmitidos;
  - isso deixava notas preparadas antigas acumuladas na unidade e confundia a operacao;
  - ao mesmo tempo, apagar nota ja transmitida seria risco fiscal e precisava continuar proibido.

- O que foi feito:
  - adicionada a rota `settings.fiscal.invoices.destroy` com metodo `DELETE`;
  - criado no `FiscalConfigurationController` o metodo `destroyInvoice()`;
  - a exclusao agora so e permitida para notas que:
    - nao estao com status `emitida` ou `cancelada`;
    - nao possuem `tb27_protocolo`;
    - nao possuem `tb27_recibo`;
  - a listagem fiscal passou a receber a flag `pode_excluir`;
  - a tela `FiscalConfig.jsx` ganhou uma nova coluna `Excluir` com botao de lixeira;
  - ao clicar, a interface pede confirmacao e remove apenas notas ainda nao transmitidas.

- Validacao:
  - `php -l app/Http/Controllers/FiscalConfigurationController.php`
  - `php -l routes/web.php`
  - `cmd /c npm run build`

- Efeito esperado:
  - notas preparadas internas que ainda nao foram transmitidas podem ser descartadas pela tela fiscal;
  - notas ja transmitidas ou com indicio de protocolo/recibo continuam protegidas contra exclusao.

- Observacoes para sincronizar em `pec1`:
  - sincronizar exatamente os tres arquivos listados acima;
  - nao depende de migration;
  - a regra de bloqueio no backend e obrigatoria e nao deve ser removida no outro projeto.

## 17/04/26 - Correcao da ordem da raiz da NFC-e conforme o leiaute oficial

- Arquivos alterados:
  - `app/Support/FiscalNfceXmlService.php`
  - `tests/Unit/FiscalNfceXmlServiceTest.php`

- Causa confirmada:
  - a NFC-e estava sendo gerada com a raiz em ordem:
    - `infNFe`
    - `Signature`
    - `infNFeSupl`
  - pelo leiaute oficial consultado no MOC 7.0, o grupo `infNFeSupl` deve vir antes da `Signature`;
  - isso era a causa real do `cStat 225 - Rejeicao: Falha no Schema XML do lote de NFe`.

- O que foi feito:
  - `FiscalNfceXmlService` passou a montar a NFC-e nesta ordem:
    - `infNFe`
    - `infNFeSupl`
    - `Signature`
  - o teste unitario do XML fiscal foi ajustado para validar a ordem oficial;
  - como o ambiente de teste local nao conseguiu gerar uma chave RSA temporaria com OpenSSL, o teste de assinatura completa ficou preparado para `skip` quando a capacidade de assinatura nao estiver disponivel.

- Validacao:
  - `php artisan test tests/Unit/FiscalNfceXmlServiceTest.php`

- Resultado operacional confirmado:
  - depois de reprocessar a venda `20145` com a nova ordem, o erro mudou de:
    - `cStat 225 - Rejeicao: Falha no Schema XML do lote de NFe`
  - para:
    - `cStat 202 - Rejeicao: Falha no reconhecimento da autoria ou integridade do arquivo digital`
  - isso confirma que o problema de schema foi resolvido e o proximo bloqueio passou a ser a assinatura/autenticidade do XML.

- Observacoes para sincronizar em `pec1`:
  - sincronizar exatamente os arquivos listados acima;
  - depois da sincronizacao, regenerar as notas antigas para que o XML seja refeito com a ordem correta;
  - se o banco compartilhado estiver apontando para um nome de certificado inexistente no storage local, alinhar tambem o arquivo fisico antes de testar transmissao.

## 17/04/26 - Tentativa de migracao da assinatura da NFC-e para SHA-256

- Arquivos alterados:
  - `app/Support/FiscalNfceXmlService.php`
  - `tests/Unit/FiscalNfceXmlServiceTest.php`

- Causa investigada:
  - depois da correcao do schema, a transmissao passou de `cStat 225` para `cStat 202 - Rejeicao: Falha no reconhecimento da autoria ou integridade do arquivo digital`;
  - o candidato mais forte no codigo era que a assinatura ainda usava `SHA-1`.

- O que foi feito:
  - `FiscalNfceXmlService` foi ajustado para:
    - `DigestValue` com `sha256`;
    - `SignatureMethod` com `rsa-sha256`;
    - `DigestMethod` com `sha256`;
    - `openssl_sign(..., OPENSSL_ALGO_SHA256)`;
  - o teste unitario do XML foi mantido consistente com a nova montagem.

- Validacao:
  - `php -l app/Support/FiscalNfceXmlService.php`
  - `php artisan test tests/Unit/FiscalNfceXmlServiceTest.php`

- Resultado operacional observado:
  - o XML novo passou a sair com referencias claras de `sha256` na assinatura e no digest;
  - porem, ao retransmitir a venda `20145`, o retorno voltou de `cStat 202` para:
    - `cStat 225 - Rejeicao: Falha no Schema XML do lote de NFe`
  - isso indica que a mudanca para SHA-256, do jeito atual, nao e a correcao final e introduziu nova incompatibilidade com o schema/assinatura aceitos neste fluxo.

- Observacoes para sincronizar em `pec1`:
  - sincronizar esta etapa somente se o outro projeto tambem estiver seguindo a mesma investigacao;
  - esta mudanca ainda nao resolveu a transmissao final;
  - o ponto util desta etapa foi diagnostico: confirmou que o problema nao era apenas "usar SHA-1", porque a troca direta para SHA-256 reintroduziu erro de schema.

## 17/04/26 - Reversao da assinatura em SHA-256 para retornar ao ponto correto de diagnostico

- Arquivos alterados:
  - `app/Support/FiscalNfceXmlService.php`
  - `SYNC.md`

- Causa identificada:
  - a troca direta da assinatura para `SHA-256` nao resolveu o problema final e ainda trouxe de volta o `cStat 225`;
  - isso atrapalhava o diagnostico porque reabria o erro de schema, que ja tinha sido superado quando a raiz da NFC-e foi corrigida.

- O que foi feito:
  - revertida a parte da assinatura que havia sido trocada para `SHA-256`;
  - mantida a ordem correta da raiz da NFC-e:
    - `infNFe`
    - `infNFeSupl`
    - `Signature`
  - a assinatura voltou a sair em `SHA-1`, apenas para restaurar o ponto de comparacao que ja havia eliminado o erro de schema.

- Validacao:
  - `php -l app/Support/FiscalNfceXmlService.php`
  - `php artisan test tests/Unit/FiscalNfceXmlServiceTest.php`
  - retransmissao real da venda `20145`

- Resultado operacional confirmado:
  - com a reversao, a venda `20145` voltou a transmitir com:
    - `cStat 202 - Rejeicao: Falha no reconhecimento da autoria ou integridade do arquivo digital`
  - isso confirma novamente que:
    - o problema de schema foi resolvido pela correcao da ordem da raiz;
    - o proximo bloqueio real continua sendo a assinatura/autenticidade do XML.

- Observacoes para sincronizar em `pec1`:
  - sincronizar esta reversao junto com a correcao anterior da ordem da raiz;
  - nao manter a versao `SHA-256` isolada no outro projeto, porque ela trouxe de volta o `225` neste fluxo atual.

## 17/04/26 - Assinatura XMLDSig da NFC-e corrigida para usar namespace em todos os nos internos

- Arquivos alterados:
  - `app/Support/FiscalNfceXmlService.php`
  - `tests/Unit/FiscalNfceXmlServiceTest.php`

- Causa identificada:
  - o bloco `Signature` da NFC-e nascia com namespace XMLDSig, mas os filhos internos (`SignedInfo`, `Reference`, `DigestValue`, `SignatureValue`, `KeyInfo`, `X509Certificate`, etc.) ainda eram criados com `createElement()` sem namespace;
  - isso pode passar na verificacao matematica local da assinatura, mas faz o autorizador nao reconhecer corretamente a autoria/integridade do arquivo;
  - como o erro atual ja estava em `cStat 202`, o ponto mais forte restante era justamente a montagem XMLDSig manual.

- O que foi feito:
  - `FiscalNfceXmlService` passou a criar todos os nos do bloco de assinatura com `createElementNS('http://www.w3.org/2000/09/xmldsig#', ...)`;
  - `DigestValue`, `SignatureValue` e `X509Certificate` deixaram de reutilizar o helper generico sem namespace e agora usam helper especifico da assinatura;
  - o teste unitario passou a verificar explicitamente se:
    - `Signature` permanece na ordem oficial apos `infNFeSupl`;
    - `SignedInfo`, `Reference`, `SignatureValue` e `X509Certificate` existem com namespace XMLDSig.

- Efeito esperado:
  - a assinatura gerada fica mais aderente ao padrao XMLDSig esperado pela SEFAZ;
  - isso ataca diretamente a causa mais forte restante do `cStat 202 - Rejeicao: Falha no reconhecimento da autoria ou integridade do arquivo digital`.

- Observacoes para sincronizar em `pec1`:
  - sincronizar exatamente os dois arquivos listados acima;
  - nao misturar esta correcao com a tentativa anterior de `SHA-256` que foi revertida;
  - depois da sincronizacao, regenerar a nota para que o XML assinado seja recriado com o novo bloco `Signature` totalmente namespace-aware.

## 17/04/26 - Substituicao da assinatura manual da NFC-e por xmlseclibs

- Arquivos alterados:
  - `app/Support/FiscalNfceXmlService.php`
  - `composer.json`
  - `composer.lock`

- Causa identificada:
  - mesmo com o schema corrigido e o namespace XMLDSig ajustado, a SEFAZ continuava retornando `cStat 202`;
  - a assinatura manual ja estava matematicamente consistente localmente, mas esse tipo de implementacao ainda e sensivel a detalhes finos de XMLDSig que o autorizador valida com mais rigor;
  - por isso o ponto mais forte restante passou a ser a propria rotina artesanal de assinatura.

- O que foi feito:
  - adicionada a dependencia `robrichards/xmlseclibs:^3.1`;
  - `FiscalNfceXmlService` deixou de montar `SignedInfo`, `Reference`, `DigestValue`, `SignatureValue` e `KeyInfo` manualmente;
  - a assinatura agora usa `XMLSecurityDSig` com:
    - canonicalizacao `C14N`;
    - referencia ao `infNFe` existente sem sobrescrever o `Id`;
    - transforms de `enveloped-signature` e `C14N`;
    - chave privada `RSA_SHA1`, preservando o algoritmo que ja mantinha o schema aceito;
    - inclusao do certificado via `add509Cert()`.

- Efeito esperado:
  - reduzir o risco de incompatibilidades sutis de XMLDSig que passavam localmente, mas ainda eram rejeitadas pela SEFAZ como falha de autoria/integridade;
  - manter intacta a correcao anterior da ordem da raiz da NFC-e.

- Observacoes para sincronizar em `pec1`:
  - sincronizar obrigatoriamente:
    - `app/Support/FiscalNfceXmlService.php`
    - `composer.json`
    - `composer.lock`
  - depois de sincronizar o codigo, executar `composer install` no `pec1` para instalar `xmlseclibs`;
  - nao remover a correcao anterior da ordem `infNFe -> infNFeSupl -> Signature`, porque ela continua sendo necessaria para evitar o `225`.

## 17/04/26 - Nova tentativa de autorizacao com xmlseclibs em RSA-SHA256

- Arquivos alterados:
  - `app/Support/FiscalNfceXmlService.php`

- Causa investigada:
  - mesmo apos migrar a assinatura para `xmlseclibs`, a SEFAZ continuou retornando `cStat 202`;
  - isso reduziu o foco para algoritmo de assinatura/digest ou formato do `KeyInfo`;
  - a tentativa anterior de `SHA-256` tinha sido feita ainda na assinatura manual, entao ela nao era conclusiva depois da troca para `xmlseclibs`.

- O que foi feito:
  - `FiscalNfceXmlService` passou a assinar com:
    - `XMLSecurityKey::RSA_SHA256`
    - `XMLSecurityDSig::SHA256`
  - foram mantidos:
    - a ordem correta da raiz `infNFe -> infNFeSupl -> Signature`;
    - a canonicalizacao `C14N`;
    - o uso de `xmlseclibs` para montagem do bloco XMLDSig.

- Efeito esperado:
  - verificar se o autorizador reconhece melhor a autoria/integridade quando a mesma assinatura padrao da biblioteca usa `RSA-SHA256` e `Digest SHA256`;
  - esse teste e mais fiel do que a tentativa antiga em `SHA-256`, porque agora o XMLDSig nao esta mais sendo montado manualmente.

- Observacoes para sincronizar em `pec1`:
  - sincronizar este ajuste apenas junto com a etapa anterior da migracao para `xmlseclibs`;
  - nao aplicar este trecho isoladamente em cima da assinatura manual antiga.

## 17/04/26 - Reversao do xmlseclibs em RSA-SHA256 para voltar ao ponto correto do cStat 202

- Arquivos alterados:
  - `app/Support/FiscalNfceXmlService.php`
  - `SYNC.md`

- Causa identificada:
  - a tentativa de usar `RSA-SHA256` com `Digest SHA256` dentro do `xmlseclibs` fez a transmissao real da NFC-e voltar de `cStat 202` para `cStat 225`;
  - isso mostrou que, neste fluxo atual, o `SHA-256` reabre incompatibilidade de schema e atrapalha o diagnostico do problema real de autoria/integridade.

- O que foi feito:
  - `FiscalNfceXmlService` voltou a usar:
    - `XMLSecurityKey::RSA_SHA1`
    - `XMLSecurityDSig::SHA1`
  - foi mantida a assinatura via `xmlseclibs`;
  - foi mantida a ordem correta da raiz da NFC-e:
    - `infNFe`
    - `infNFeSupl`
    - `Signature`

- Efeito esperado:
  - restaurar o estado estavel em que a NFC-e volta a passar do schema e a resposta da SEFAZ fica em `cStat 202`;
  - a partir desse ponto, a investigacao segue no bloco `KeyInfo/X509Data` e nao mais no algoritmo de assinatura.

- Observacoes para sincronizar em `pec1`:
  - nao manter a variante `RSA-SHA256` ativa no outro projeto neste momento;
  - sincronizar esta reversao junto com a etapa da migracao para `xmlseclibs`.

## 17/04/26 - Botao Transmitir NF no cupom pos-venda do Dashboard

- Arquivos alterados:
  - `app/Http/Controllers/SaleController.php`
  - `resources/js/Pages/Dashboard.jsx`
  - `routes/web.php`

- Causa identificada:
  - o fechamento da venda no `Dashboard` ja retornava o resumo fiscal em `sale.fiscal`, mas o modal do cupom so permitia imprimir o comprovante interno;
  - para transmitir a nota, o operador precisava sair do fluxo do caixa e ir ate `settings/fiscal`;
  - alem disso, a rota de transmissao existente retornava `redirect`, adequada para a tela fiscal administrativa, mas nao para o fluxo em AJAX do cupom pos-venda.

- O que foi feito:
  - adicionada a rota autenticada `sales.fiscal.transmit` em `POST /sales/fiscal/{notaFiscal}/transmit`;
  - criado no `SaleController` o metodo `transmitFiscalInvoice()`:
    - responde em JSON;
    - exige usuario autenticado;
    - restringe a transmissao para a loja ativa do caixa;
    - permite perfis `0`, `1` e `3`, alinhando com o fluxo operacional do Dashboard;
    - reutiliza `FiscalNfceTransmissionService`;
    - devolve no JSON o resumo fiscal atualizado da nota.
  - `SaleController@store` passou a reutilizar o helper `buildFiscalSummary()` para manter o payload fiscal consistente entre venda e transmissao;
  - o modal `Cupom pronto para impressao` em `Dashboard.jsx` ganhou:
    - bloco visual com status da nota, serie/numero, protocolo e recibo;
    - botao `Transmitir NF`;
    - o botao aparece apenas quando a nota estiver em `xml_assinado` ou `erro_transmissao`;
    - ao transmitir, o modal atualiza `receiptData.fiscal` sem fechar o cupom.

- Efeito esperado:
  - logo apos fechar a venda, o operador consegue transmitir a nota fiscal da propria venda sem sair do `Dashboard`;
  - quando a transmissao falhar, o modal continua aberto e o status fiscal e atualizado ali mesmo;
  - quando a nota mudar para `emitida`, o botao deixa de aparecer automaticamente.

- Observacoes para sincronizar em `pec1`:
  - sincronizar exatamente os tres arquivos listados acima;
  - esta mudanca nao depende de migration;
  - o `Dashboard.jsx` depende de o payload `sale.fiscal` continuar vindo do backend no fechamento da venda;
  - importante manter a regra de unidade ativa no endpoint JSON, para o caixa nao transmitir nota de outra loja.

## 17/04/26 - Reducao do lock fiscal na preparacao da nota para evitar timeout 1205

- Arquivos alterados:
  - `app/Support/FiscalInvoicePreparationService.php`

- Causa identificada:
  - o metodo `prepareForPayment()` sempre fazia `lockForUpdate()` em `tb26_configuracoes_fiscais` da loja;
  - isso acontecia ate quando a nota fiscal ja existia e a operacao so precisava reler ou regenerar o XML;
  - em operacoes concorrentes da mesma loja, esse lock desnecessario aumentava o risco de `SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded`.

- O que foi feito:
  - a preparacao da nota agora tenta travar primeiro apenas a propria `tb27_notas_fiscais` da venda;
  - a configuracao fiscal da loja so recebe `lockForUpdate()` quando realmente nao existe nota e precisamos reservar nova numeracao fiscal;
  - depois de obter o lock da configuracao, o codigo revalida se outra transacao criou a nota nesse intervalo, evitando consumir numeracao duplicada;
  - foi adicionado tratamento especifico para `lock wait timeout` e `deadlock`, convertendo essas falhas em mensagem operacional amigavel.

- Efeito esperado:
  - reduzir muito a disputa de lock na linha da configuracao fiscal da loja;
  - evitar timeout quando o caixa tenta transmitir logo apos a venda ou quando ha outra operacao fiscal concorrente na mesma unidade;
  - quando ainda houver disputa real, a aplicacao passa a devolver mensagem clara em vez de erro bruto do banco.

- Observacoes para sincronizar em `pec1`:
  - sincronizar exatamente este arquivo;
  - nao depende de migration;
  - importante manter a revalidacao da nota apos o lock da configuracao, porque ela protege a numeracao fiscal contra concorrencia.

## 17/04/26 - Enriquecimento do KeyInfo da assinatura XML com SubjectName e IssuerSerial

- Arquivos alterados:
  - `app/Support/FiscalNfceXmlService.php`

- Causa identificada:
  - com o schema ja resolvido e a assinatura estabilizada novamente em `SHA-1`, a NFC-e continuava em `cStat 202`;
  - o bloco `KeyInfo/X509Data` estava indo apenas com `X509Certificate`;
  - o proximo suspeito forte passou a ser a identificacao do certificado no XML, nao mais a estrutura geral da assinatura.

- O que foi feito:
  - mantida a assinatura via `xmlseclibs` em `RSA-SHA1`;
  - o `add509Cert()` passou a incluir tambem:
    - `X509SubjectName`
    - `X509IssuerSerial`
      - `X509IssuerName`
      - `X509SerialNumber`
  - isso deixa o `KeyInfo` mais completo para o autorizador reconhecer a autoria do certificado usado na assinatura.

- Efeito esperado:
  - aumentar a compatibilidade do bloco `Signature` com a validacao de autoria/integridade da SEFAZ sem reabrir o `225`.

- Observacoes para sincronizar em `pec1`:
  - sincronizar exatamente este arquivo;
  - nao misturar esta etapa com a variante `SHA-256`, que deve continuar desativada;
  - depois da sincronizacao, regenerar a nota para que a assinatura seja refeita com o `KeyInfo` enriquecido.

## 17/04/26 - Switch para desligar a geracao automatica de notas fiscais e atalho fixo Abrir fiscal na modal de transmissao

- Arquivos alterados:
  - `app/Models/ConfiguracaoFiscal.php`
  - `app/Http/Controllers/FiscalConfigurationController.php`
  - `app/Support/FiscalInvoicePreparationService.php`
  - `resources/js/Pages/Settings/FiscalConfig.jsx`
  - `resources/js/Layouts/AuthenticatedLayout.jsx`
  - `SYNC.md`

- Arquivos criados:
  - `database/migrations/2026_04_17_030000_add_tb26_geracao_automatica_ativa_to_tb26_configuracoes_fiscais_table.php`

- Causa identificada:
  - a configuracao fiscal da loja nao tinha um interruptor geral para suspender a preparacao automatica de notas no fechamento da venda;
  - quando a operacao queria apenas parar de gerar nota temporariamente, era preciso desmontar configuracao ou desmarcar tipos de emissao;
  - na modal `Transmitir notas pendentes`, o botao `Abrir fiscal` aparecia somente nas linhas da lista, entao sumia quando nao havia registros pendentes.

- O que foi feito:
  - criada a coluna booleana `tb26_geracao_automatica_ativa` em `tb26_configuracoes_fiscais`, com `default true`;
  - `ConfiguracaoFiscal` passou a tratar esse campo em `fillable` e `casts`;
  - `FiscalConfigurationController` passou a:
    - validar o novo campo;
    - salvar o estado do switch;
    - carregar o valor para a tela fiscal;
    - assumir `true` por padrao quando ainda nao houver configuracao salva;
  - `FiscalConfig.jsx` ganhou um switch visual de liga/desliga na secao `Emissao e numeracao`, com status textual `Ativa` ou `Desligada`;
  - `FiscalInvoicePreparationService` agora interrompe a criacao automatica da nota quando:
    - a configuracao fiscal existe;
    - a nota da venda ainda nao existe;
    - `tb26_geracao_automatica_ativa = false`;
  - se a nota ja existir, o servico continua permitindo regeneracao/reprocessamento da nota existente;
  - `AuthenticatedLayout.jsx` passou a exibir `Abrir fiscal` fixo no cabecalho da modal `Transmitir notas pendentes`, ao lado de `Fechar`;
  - quando existir nota pendente, o atalho usa a loja da primeira nota da lista; quando nao existir nenhuma, ele aponta para `settings/fiscal`.

- Efeito esperado:
  - a loja pode desligar temporariamente a geracao automatica de notas sem apagar certificado, CSC ou numeracao fiscal;
  - vendas fechadas com a geracao desligada nao criam nova `tb27_notas_fiscais`;
  - notas ja existentes continuam podendo ser regeneradas ou transmitidas;
  - a modal de transmissao sempre oferece o atalho `Abrir fiscal`, inclusive quando a fila estiver vazia.

- Observacoes para sincronizar em `pec1`:
  - sincronizar todos os arquivos listados acima;
  - executar a migration nova no `pec1` depois de sincronizar o codigo;
  - o comportamento foi desenhado para nao quebrar notas antigas: o bloqueio vale apenas para novas vendas sem nota fiscal criada;
  - manter o botao `Abrir fiscal` no cabecalho da modal, mesmo sem registros, porque essa e justamente a situacao em que o atalho deixava de aparecer.

## 17/04/26 - Coluna NF na listagem de unidades com atalho para settings/fiscal

- Arquivos alterados:
  - `app/Http/Controllers/UnitController.php`
  - `resources/js/Pages/Units/UnitIndex.jsx`
  - `SYNC.md`

- Causa identificada:
  - a listagem `units` ainda ocupava uma coluna com `Endereco`, mas nao mostrava nenhum indicador financeiro das notas fiscais por loja;
  - tambem faltava um atalho direto da unidade para a tela fiscal da propria loja;
  - como a tela `units` ja e restrita a `MASTER` e `GERENTE`, fazia sentido aproveitar esse mesmo escopo para expor o total fiscal.

- O que foi feito:
  - removida a coluna `Endereco` da tabela de unidades;
  - adicionada a coluna `NF`;
  - `UnitController@index` agora calcula `tb2_nf_total` por unidade via subquery:
    - soma `tb4_vendas_pg.valor_total`;
    - apenas para registros em `tb27_notas_fiscais` com `tb27_status = emitida`;
  - o valor fiscal foi arredondado no backend e enviado junto com a paginacao;
  - `UnitIndex.jsx` passou a renderizar esse valor como botao/link;
  - ao clicar, o usuario vai direto para `settings/fiscal?unit_id=<loja>`.

- Efeito esperado:
  - `MASTER` e `GERENTE` visualizam rapidamente quanto cada loja ja gerou em notas fiscais autorizadas;
  - a tela de unidades deixa de exibir a coluna de endereco e passa a ter um acesso direto para a configuracao/operacao fiscal da unidade.

- Observacoes para sincronizar em `pec1`:
  - sincronizar exatamente os arquivos listados acima;
  - nao depende de migration;
  - manter o criterio da soma apenas para `tb27_status = emitida`, para nao inflar o total com notas pendentes, com erro ou apenas assinadas localmente.

## 17/04/26 - Botao NF da listagem de unidades sinaliza geracao automatica ativa ou desligada

- Arquivos alterados:
  - `app/Http/Controllers/UnitController.php`
  - `resources/js/Pages/Units/UnitIndex.jsx`
  - `SYNC.md`

- Causa identificada:
  - a nova coluna `NF` mostrava o valor fiscal da unidade, mas usava sempre o mesmo estilo visual;
  - como a configuracao fiscal agora possui o switch `tb26_geracao_automatica_ativa`, faltava refletir esse estado no proprio botao da listagem.

- O que foi feito:
  - `UnitController@index` passou a carregar tambem `configuracaoFiscal` com o campo `tb26_geracao_automatica_ativa`;
  - cada unidade enviada para a tela agora leva o booleano `tb26_geracao_automatica_ativa`;
  - `UnitIndex.jsx` passou a pintar o botao `NF`:
    - verde quando a geracao automatica estiver ativa;
    - vermelho quando a geracao automatica estiver desligada;
  - o clique continua levando para `settings/fiscal` da unidade;
  - o botao tambem ganhou `title` explicando o estado atual da geracao automatica.

- Efeito esperado:
  - a listagem de unidades passa a mostrar nao apenas o total fiscal da loja, mas tambem o estado operacional da geracao automatica de notas;
  - isso facilita bater o olho e identificar rapidamente quais lojas estao com a emissao automatica ligada ou desligada.

- Observacoes para sincronizar em `pec1`:
  - sincronizar exatamente os arquivos listados acima;
  - nao depende de migration nova;
  - este ajuste depende de a migration `2026_04_17_030000_add_tb26_geracao_automatica_ativa_to_tb26_configuracoes_fiscais_table.php` ja estar aplicada no outro projeto.

## 17/04/26 - Geracao parcial da nota fiscal apenas com itens que possuem dados fiscais minimos

- Arquivos alterados:
  - `app/Support/FiscalInvoicePreparationService.php`
  - `app/Support/FiscalNfceXmlService.php`
  - `app/Http/Controllers/FiscalConfigurationController.php`
  - `tests/Unit/FiscalInvoicePreparationServiceTest.php`
  - `SYNC.md`

- Causa identificada:
  - o fluxo fiscal anterior bloqueava a nota inteira quando qualquer item da venda estivesse sem `NCM`, `CFOP` ou `CSOSN/CST`;
  - na pratica isso impedia a emissao mesmo quando parte da venda ja tinha cadastro fiscal suficiente;
  - a nova regra operacional definida e gerar a nota apenas com os itens aptos, deixando os itens sem cadastro minimo fora do documento.

- O que foi feito:
  - `FiscalInvoicePreparationService` passou a separar os itens da venda em dois grupos:
    - `itens` aptos para a nota;
    - `itens_excluidos` por falta de dados fiscais minimos;
  - o criterio minimo para entrar na nota passou a ser:
    - `NCM`;
    - `CFOP`;
    - `CSOSN/CST`;
  - se ao menos um item estiver apto:
    - a nota e gerada apenas com esse subconjunto;
    - os itens excluidos ficam registrados em `tb27_payload['itens_excluidos']`;
    - `tb27_payload['valor_total_documento']` passa a guardar o total real da nota;
  - se nenhum item estiver apto:
    - a nota continua em `erro_validacao`;
    - a mensagem informa que nenhum item da venda possui dados fiscais minimos;
  - `FiscalNfceXmlService` passou a montar o XML apenas com os itens aptos recebidos do service;
  - o grupo `pag` da NFC-e passou a usar o total do documento fiscal gerado, e nao mais o total completo da venda;
  - `FiscalConfigurationController` passou a usar o payload fiscal salvo para:
    - mostrar o total correto da nota na listagem;
    - imprimir o cupom fiscal com apenas os itens incluidos;
    - expor tambem a lista de itens excluidos quando existir.

- Efeito esperado:
  - uma venda com itens mistos agora pode gerar nota parcial;
  - exemplo:
    - se a venda tiver 10 itens e apenas 1 tiver `NCM`, `CFOP` e `CSOSN/CST`, a nota fiscal sera gerada apenas com esse 1 item;
  - os itens sem cadastro minimo nao bloqueiam mais a nota inteira;
  - o valor mostrado na area fiscal passa a refletir o total real da nota emitida, e nao o total bruto da venda.

- Observacoes para sincronizar em `pec1`:
  - sincronizar exatamente os arquivos listados acima;
  - nao depende de migration;
  - depois de sincronizar, regenerar notas antigas que estavam em `erro_validacao` apenas por itens mistos, para que a nova regra parcial seja aplicada;
  - importante manter o uso de `valor_total_documento` e `itens_excluidos` no payload fiscal, porque eles passam a ser a referencia correta do documento quando a nota for parcial.

## 19/04/26 - Clonagem da parte de nota fiscal do `pec1` para este projeto

- Arquivos alterados:
  - `app/Http/Controllers/FiscalConfigurationController.php`
  - `app/Http/Controllers/SaleController.php`
  - `app/Support/FiscalInvoicePreparationService.php`
  - `resources/js/Pages/Settings/FiscalConfig.jsx`
  - `SYNC.md`

- Causa identificada:
  - a parte fiscal deste projeto estava divergente da copia em `C:\xampp\htdocs\pec1`;
  - o fluxo de nota fiscal daqui tinha ficado misturado, com trechos mais novos locais e outros comportamentos diferentes do `pec1`;
  - isso afetava principalmente:
    - preparacao automatica da nota conforme tipo de pagamento;
    - reprocessamento de notas;
    - transmissao manual da nota;
    - resumo/debug mostrado na tela fiscal;
    - fluxo de venda com pagamentos em cartao e pagamento misto dinheiro + cartao.

- O que foi feito:
  - `FiscalInvoicePreparationService` foi alinhado ao `pec1`:
    - voltou a bloquear geracao automatica para pagamentos que sao apenas controle interno;
    - voltou a registrar mensagem especifica para `vale`, `refeicao` e `faturar` quando nao geram nota automatica;
    - o reprocessamento voltou a incluir tambem notas `xml_assinado` e `erro_transmissao`;
  - `FiscalConfigurationController` foi alinhado ao `pec1`:
    - restaurado o fluxo de transmitir nota revalidando o pagamento e regenerando a nota antes da transmissao;
    - restaurados os limites de tamanho dos campos do emitente conforme estavam no `pec1`;
    - restaurado o enriquecimento da mensagem fiscal quando houver `cStat 462`, incluindo referencia ao `CSC ID`;
    - restaurado o bloco `xml_debug` na listagem das notas;
    - restaurado o suporte aos labels de pagamento com `cartao_credito`, `cartao_debito`, `dinheiro_cartao_credito` e `dinheiro_cartao_debito`;
  - `resources/js/Pages/Settings/FiscalConfig.jsx` foi alinhado ao `pec1`:
    - voltou a mostrar a coluna `XML debug`;
    - voltou a sincronizar `tb2_id` e o formulario de reprocessamento com `useEffect`;
    - voltou a exibir o erro de validacao de `tb2_id`;
  - `SaleController` foi alinhado ao `pec1`:
    - restaurado o aceite dos tipos `cartao_credito` e `cartao_debito`;
    - restaurado o fluxo de pagamento misto `dinheiro + cartao`;
    - restaurada a transmissao fiscal via caixa com regeneracao previa da nota;
    - restaurado o `xml_debug` no resumo fiscal retornado pela API de venda/transmissao.

- Efeito esperado:
  - este projeto volta a ter o mesmo comportamento fiscal do `pec1` nos arquivos centrais comparados;
  - a tela fiscal administrativa e o fluxo fiscal do caixa passam a responder do mesmo jeito da copia;
  - diagnostico de XML, mensagens fiscais e regras de transmissao ficam padronizados entre os dois ambientes.

- Observacoes para sincronizar em `pec1`:
  - esta entrada documenta uma sincronizacao no sentido `pec1 -> pec-rodrigo`;
  - se futuramente precisar repetir esta mesma clonagem em outro ambiente, usar exatamente os arquivos listados acima;
  - nao houve migration nova nesta clonagem;
  - os arquivos centrais comparados e alinhados contra o `pec1` foram:
    - `app/Http/Controllers/FiscalConfigurationController.php`
    - `app/Http/Controllers/SaleController.php`
    - `app/Support/FiscalInvoicePreparationService.php`
    - `resources/js/Pages/Settings/FiscalConfig.jsx`

## 19/04/26 - Sincronizacao da branch `23` do `pec1` para este projeto

- Arquivos alterados:
  - `app/Http/Controllers/FiscalConfigurationController.php`
  - `app/Http/Controllers/SaleController.php`
  - `app/Http/Controllers/SalesReportController.php`
  - `app/Http/Controllers/UnitController.php`
  - `app/Support/FiscalInvoicePreparationService.php`
  - `app/Support/FiscalNfceTransmissionService.php`
  - `app/Support/FiscalNfceXmlService.php`
  - `resources/js/Pages/Dashboard.jsx`
  - `resources/js/Pages/Settings/FiscalConfig.jsx`
  - `resources/js/Pages/Units/UnitIndex.jsx`
  - `resources/js/Utils/receipt.js`
  - `routes/web.php`
  - `tests/Feature/SaleCardPaymentTypesTest.php`
  - `tests/Unit/FiscalInvoicePreparationServiceTest.php`
  - `tests/Unit/FiscalNfceTransmissionServiceTest.php`
  - `tests/Unit/FiscalNfceXmlServiceTest.php`
  - `SYNC.md`

- Causa identificada:
  - a branch `23` do projeto `C:\xampp\htdocs\pec1` continha um pacote maior de ajustes fiscais e de fluxo de venda ainda nao refletido aqui;
  - como este projeto ja tinha alteracoes locais recentes na area fiscal, era necessario trazer o conteudo da branch com selecao cuidadosa para nao copiar artefatos sensiveis de ambiente.

- O que foi feito:
  - foram trazidos para este projeto os arquivos de aplicacao da branch `23` relacionados a:
    - configuracao fiscal;
    - fluxo de venda e transmissao da nota;
    - dashboard;
    - listagem de unidades;
    - utilitarios de recibo;
    - testes da area fiscal e de pagamentos com cartao;
  - a sincronizacao foi feita preservando a decisao de nao copiar:
    - certificados `.p12` e `.pfx`;
    - arquivos de debug em `storage/app/private`;
    - arquivos sensiveis/temporarios do `storage`;
  - com isso, o comportamento funcional da branch `23` foi trazido sem contaminar este ambiente com credenciais ou artefatos locais do outro projeto.

- Efeito esperado:
  - este projeto passa a refletir os ajustes funcionais da branch `23` do `pec1` nos arquivos de aplicacao sincronizados;
  - os fluxos de nota fiscal e de pagamento com cartao ficam alinhados com o que estava sendo desenvolvido naquela branch;
  - os testes especificos dessa area tambem passam a existir aqui para apoiar validacao futura.

- Observacoes para sincronizar em `pec1`:
  - esta entrada documenta a sincronizacao no sentido `pec1/branch 23 -> pec-rodrigo`;
  - o commit de referencia da branch analisada foi `7f6c070` (`23.1`);
  - os arquivos sensiveis de `storage` e certificados foram deliberadamente excluidos da copia;
  - se futuramente for necessario repetir esta sincronizacao em outro ambiente, manter a mesma regra: copiar apenas arquivos de aplicacao e nunca certificados/debug do `storage`.

## 19/04/26 - Correcao de cadeia SSL na transmissao com a SEFAZ

- Arquivos alterados:
  - `app/Support/FiscalNfceTransmissionService.php`
  - `config/services.php`
  - `SYNC.md`

- Causa identificada:
  - a transmissao fiscal via cURL usava o certificado cliente da empresa, mas nao informava explicitamente um CA bundle para validar a cadeia SSL do servidor da SEFAZ;
  - o `php.ini` deste ambiente tambem nao tinha `curl.cainfo` nem `openssl.cafile` configurados;
  - por isso o ambiente podia falhar com `SSL certificate problem: unable to get local issuer certificate` mesmo com o certificado A1 da empresa valido.

- O que foi feito:
  - `FiscalNfceTransmissionService` passou a localizar um CA bundle confiavel antes de abrir a conexao SSL;
  - a ordem de busca ficou:
    - `services.fiscal.ca_bundle`;
    - `curl.cainfo` do `php.ini`;
    - `openssl.cafile` do `php.ini`;
    - `storage/app/private/cacert.pem`;
    - `cacert.pem` na raiz do projeto;
    - `C:\xampp\php\extras\ssl\cacert.pem`;
    - `C:\php\extras\ssl\cacert.pem`;
  - a transmissao agora define explicitamente:
    - `CURLOPT_CAINFO`;
    - `CURLOPT_SSL_VERIFYPEER = true`;
    - `CURLOPT_SSL_VERIFYHOST = 2`;
  - quando o erro for de cadeia SSL nao confiavel, a mensagem agora informa tambem qual CA bundle foi usado;
  - quando nenhum bundle for encontrado, a falha passa a ser explicita antes mesmo do `curl_exec`.

- Efeito esperado:
  - o sistema deixa de depender apenas da configuracao global do PHP/OpenSSL para confiar na SEFAZ;
  - ambientes XAMPP sem `curl.cainfo` configurado passam a funcionar desde que exista um `cacert.pem` em um dos caminhos suportados;
  - o diagnostico de erro fica mais objetivo quando o problema for cadeia SSL.

- Observacoes para sincronizar em `pec1`:
  - levar junto `app/Support/FiscalNfceTransmissionService.php` e `config/services.php`;
  - nao depende de migration;
  - se o outro ambiente tambem nao tiver `curl.cainfo`/`openssl.cafile`, manter um `cacert.pem` atualizado em um dos caminhos aceitos pela rotina.

## 19/04/26 - Prioridade para `cacert.pem` local e diagnostico expandido da cadeia SSL

- Arquivos alterados:
  - `app/Support/FiscalNfceTransmissionService.php`
  - `SYNC.md`

- Causa identificada:
  - mesmo apos a primeira correcao de SSL, ainda apareceu a mensagem dizendo que nenhuma cadeia confiavel foi encontrada;
  - a verificacao do ambiente mostrou que o projeto ja tinha bundles acessiveis, entao o melhor ajuste era priorizar o `cacert.pem` local recem-baixado e ampliar o diagnostico da resolucao.

- O que foi feito:
  - `FiscalNfceTransmissionService` passou a priorizar primeiro `storage/app/private/cacert.pem`;
  - quando nenhum bundle for aceito, a excecao agora informa tambem todos os caminhos que foram avaliados pela rotina.

- Efeito esperado:
  - o projeto passa a usar primeiro o `cacert.pem` local que foi baixado especificamente para esta correcao;
  - se ainda houver falha, a mensagem mostrara exatamente os caminhos considerados, facilitando localizar diferencas entre processo web, CLI e ambiente carregado.

- Observacoes para sincronizar em `pec1`:
  - levar junto o ajuste em `app/Support/FiscalNfceTransmissionService.php`;
  - nao depende de migration;
  - manter o `cacert.pem` local atualizado caso a maquina tenha bundles globais antigos.

## 19/04/26 - Inclusao da cadeia intermediaria do certificado cliente na transmissao SSL

- Arquivos alterados:
  - `app/Support/FiscalCertificateService.php`
  - `app/Support/FiscalNfceTransmissionService.php`
  - `SYNC.md`

- Causa identificada:
  - ao inspecionar o fluxo de transmissao, foi identificado que o PEM temporario usado no cURL levava apenas:
    - certificado final da empresa;
    - chave privada;
  - se o endpoint da SEFAZ exigir a cadeia intermediaria do certificado cliente durante a renegociacao/autenticacao mutua TLS, a transmissao pode falhar mesmo com A1 valido e CA bundle configurado.

- O que foi feito:
  - `FiscalCertificateService` passou a extrair tambem `extracerts` do `.pfx/.p12` quando existirem;
  - o retorno do service agora inclui:
    - `certificate_chain_pem`;
    - `extra_ca_pem`;
  - `FiscalNfceTransmissionService` passou a montar o PEM temporario com:
    - certificado cliente;
    - cadeia intermediaria do cliente;
    - chave privada;
  - o arquivo PEM temporario tambem passou a ser gravado com separacao mais segura entre blocos.

- Efeito esperado:
  - a autenticacao mutua TLS com a SEFAZ passa a enviar a cadeia completa do certificado cliente quando ela estiver presente no `.pfx`;
  - isso reduz falhas de handshake/renegociacao em ambientes em que o servidor espera a cadeia intermediaria do certificado do emitente.

- Observacoes para sincronizar em `pec1`:
  - levar junto `app/Support/FiscalCertificateService.php` e `app/Support/FiscalNfceTransmissionService.php`;
  - nao depende de migration;
  - manter o `.pfx/.p12` importado com a cadeia completa sempre que possivel.

## 19/04/26 - Separacao entre arquivo temporario do certificado e da chave privada no handshake mutual TLS

- Arquivos alterados:
  - `app/Support/FiscalNfceTransmissionService.php`
  - `SYNC.md`

- Causa identificada:
  - mesmo com bundle CA configurado e cadeia intermediaria do cliente incluida, a transmissao ainda falhava;
  - a implementacao anterior usava o mesmo arquivo PEM para:
    - `CURLOPT_SSLCERT`;
    - `CURLOPT_SSLKEY`;
  - em autenticacao mutua TLS isso pode gerar incompatibilidade de handshake/renegociacao com servidores mais sensiveis.

- O que foi feito:
  - a transmissao passou a gerar dois arquivos temporarios separados:
    - um apenas para certificado + cadeia;
    - outro apenas para chave privada;
  - `CURLOPT_SSLCERT` agora aponta somente para o arquivo do certificado;
  - `CURLOPT_SSLKEY` agora aponta somente para o arquivo da chave privada;
  - a mensagem de erro para `unable to get local issuer certificate` foi ajustada para deixar claro que a falha ocorre durante o handshake TLS/renegociacao com a SEFAZ.

- Efeito esperado:
  - a autenticacao mutua fica mais compativel com o servidor da SEFAZ;
  - reduz o risco de falha causada pela combinacao de certificado/cadeia/chave em um unico PEM durante a renegociacao TLS.

- Observacoes para sincronizar em `pec1`:
  - levar junto `app/Support/FiscalNfceTransmissionService.php`;
  - nao depende de migration;
  - manter tambem o ajuste anterior de inclusao da cadeia intermediaria do cliente.

## 19/04/26 - Configuracao OpenSSL local para renegociacao legada da SEFAZ

- Arquivos alterados:
  - `app/Support/FiscalNfceTransmissionService.php`
  - `config/services.php`
  - `config/openssl-sefaz-legacy.cnf`
  - `SYNC.md`

- Causa identificada:
  - a investigacao do endpoint homolog da SEFAZ de Goias mostrou que:
    - a conexao TLS inicial era estabelecida;
    - o servidor pedia renegociacao;
    - a falha ocorria depois, no handshake/renegociacao;
  - isso indica incompatibilidade de renegociacao TLS entre o ambiente OpenSSL do PHP e o endpoint da SEFAZ, e nao simples ausencia de CA publica.

- O que foi feito:
  - foi criado `config/openssl-sefaz-legacy.cnf` com `UnsafeLegacyRenegotiation`;
  - `services.fiscal.openssl_legacy_config` passou a expor esse caminho de forma configuravel;
  - antes da chamada cURL da transmissao fiscal, a rotina agora injeta temporariamente:
    - `OPENSSL_CONF` apontando para o arquivo legacy;
    - `SSL_CERT_FILE` apontando para o CA bundle resolvido;
  - ao final da requisicao, essas variaveis de ambiente sao restauradas.

- Efeito esperado:
  - o processo de transmissao passa a usar uma politica OpenSSL mais compativel com renegociacao legada exigida por alguns endpoints da SEFAZ;
  - isso aumenta a chance de o handshake mutual TLS concluir sem desativar verificacao SSL.

- Observacoes para sincronizar em `pec1`:
  - levar junto:
    - `app/Support/FiscalNfceTransmissionService.php`
    - `config/services.php`
    - `config/openssl-sefaz-legacy.cnf`
  - nao depende de migration;
  - manter esse arquivo de configuracao no projeto para o mesmo comportamento de transmissao.

## 19/04/26 - Refino do OpenSSL legacy e do diagnostico cURL da transmissao SEFAZ

- Arquivos alterados:
  - `app/Support/FiscalNfceTransmissionService.php`
  - `config/openssl-sefaz-legacy.cnf`
  - `SYNC.md`

- Causa identificada:
  - ainda era necessario melhorar dois pontos do ajuste anterior:
    - o arquivo `openssl-sefaz-legacy.cnf` podia ficar mais compativel com OpenSSL 3.x;
    - o `sendSoapRequest()` ainda nao registrava `curl_errno` nem a saida verbose do cURL, o que dificultava diagnosticar a etapa exata da falha com a SEFAZ.

- O que foi feito:
  - `config/openssl-sefaz-legacy.cnf` foi ajustado para:
    - `openssl_conf = openssl_init`;
    - `Options = UnsafeLegacyRenegotiation`;
    - `CipherString = DEFAULT@SECLEVEL=0`;
  - `FiscalNfceTransmissionService` passou a usar:
    - `CURLOPT_TIMEOUT = 90`;
    - `CURLOPT_CONNECTTIMEOUT = 30`;
    - `CURLOPT_VERBOSE`;
    - captura de `curl_errno`;
    - leitura do stream verbose do cURL;
  - em erro, a rotina agora grava log estruturado com:
    - `errno`;
    - `error`;
    - `url`;
    - `ca_bundle`;
    - `openssl_conf`;
    - `verbose`.

- Efeito esperado:
  - aumenta a compatibilidade com endpoints antigos da SEFAZ em OpenSSL 3.x;
  - o proximo erro de transmissao passa a deixar um rastro tecnico muito mais util no log para identificar se a falha esta em handshake, renegociacao, certificado cliente ou transporte HTTP.

- Observacoes para sincronizar em `pec1`:
  - levar junto:
    - `app/Support/FiscalNfceTransmissionService.php`
    - `config/openssl-sefaz-legacy.cnf`
  - nao depende de migration;
  - importante manter o log verbose apenas para diagnostico; se no futuro isso ficar ruidoso demais, revisar depois de estabilizar a transmissao.

## 19/04/26 - Reforco do cURL com `CAPATH`, senha explicita do certificado e legacy mais agressivo

- Arquivos alterados:
  - `app/Support/FiscalCertificateService.php`
  - `app/Support/FiscalNfceTransmissionService.php`
  - `config/openssl-sefaz-legacy.cnf`
  - `SYNC.md`

- Causa identificada:
  - mesmo apos os ajustes anteriores, a falha SSL permanecia identica;
  - faltavam ainda tres reforcos no transporte:
    - passar a senha do certificado explicitamente ao cURL;
    - informar tambem `CAPATH` alem de `CAINFO`;
    - tornar a configuracao legacy do OpenSSL ainda mais agressiva para testes com endpoint antigo da SEFAZ.

- O que foi feito:
  - `FiscalCertificateService` passou a devolver tambem a `password` do certificado no payload carregado;
  - `FiscalNfceTransmissionService` passou a configurar:
    - `CURLOPT_SSLCERTPASSWD`;
    - `CURLOPT_CAPATH` quando o diretorio do bundle existir;
    - log com `http_code` e `ca_path`;
  - `config/openssl-sefaz-legacy.cnf` foi ajustado para:
    - `Options = UnsafeLegacyServerConnect,UnsafeLegacyRenegotiation`;
    - `CipherString = DEFAULT@SECLEVEL=0`.

- Efeito esperado:
  - o cURL passa a receber um conjunto mais completo de parametros SSL/TLS para o handshake mutual TLS;
  - se o endpoint exigir comportamento mais antigo de renegociacao, a chance de compatibilidade aumenta;
  - o log tecnico fica ainda mais util caso a falha persista.

- Observacoes para sincronizar em `pec1`:
  - levar junto:
    - `app/Support/FiscalCertificateService.php`
    - `app/Support/FiscalNfceTransmissionService.php`
    - `config/openssl-sefaz-legacy.cnf`
  - nao depende de migration;
  - estes ajustes sao especificamente para endurecer o diagnostico e flexibilizar o handshake com a SEFAZ sem desligar verificacao SSL.

## 19/04/26 - Bundle fiscal reforcado com cadeia explicita da SEFAZ-GO

- Arquivos alterados/criados:
  - `app/Support/FiscalNfceTransmissionService.php`
  - `config/services.php`
  - `storage/app/private/letsencrypt-r12.pem`
  - `storage/app/private/isrg-root-x1.pem`
  - `storage/app/private/fiscal-ca-bundle.pem`
  - `SYNC.md`

- Causa identificada:
  - o log verbose continuava mostrando `SSL certificate problem: unable to get local issuer certificate` antes de qualquer resposta HTTP;
  - mesmo com `cacert.pem` valido, o OpenSSL/cURL do PHP nao estava conseguindo completar sozinho a cadeia publica entregue pelo endpoint da SEFAZ-GO;
  - por isso foi necessario reforcar localmente o bundle com a cadeia publica explicita vista no endpoint.

- O que foi feito:
  - foram baixados os certificados publicos oficiais:
    - `Let's Encrypt R12`;
    - `ISRG Root X1`;
  - foi criado `storage/app/private/fiscal-ca-bundle.pem` concatenando:
    - `cacert.pem`;
    - `letsencrypt-r12.pem`;
    - `isrg-root-x1.pem`;
  - `config/services.php` passou a apontar por padrao para `storage/app/private/fiscal-ca-bundle.pem`;
  - `FiscalNfceTransmissionService` passou a priorizar:
    - caminho configurado em `services.fiscal.ca_bundle`;
    - `storage/app/private/fiscal-ca-bundle.pem`;
    - `storage/app/private/cacert.pem`;
    - demais fallbacks ja existentes.

- Efeito esperado:
  - o cURL passa a receber um bundle reforcado com a cadeia publica explicita do endpoint;
  - reduz a dependencia do comportamento automatico de chain-building do OpenSSL do PHP/XAMPP;
  - facilita sincronizar esse mesmo diagnostico/ajuste no `pec1`.

- Observacoes para sincronizar em `pec1`:
  - levar junto:
    - `app/Support/FiscalNfceTransmissionService.php`
    - `config/services.php`
    - `storage/app/private/letsencrypt-r12.pem`
    - `storage/app/private/isrg-root-x1.pem`
    - `storage/app/private/fiscal-ca-bundle.pem`
  - manter `config/openssl-sefaz-legacy.cnf` como ja estava na etapa anterior;
  - nao depende de migration.

## 19/04/26 - Correcao da cadeia real do endpoint de producao da SEFAZ-GO

- Arquivos alterados/criados:
  - `storage/app/private/ac-soluti-ssl-ev-g4.crt`
  - `storage/app/private/ac-soluti-ssl-ev-g4.pem`
  - `storage/app/private/icp-brasil-raiz-v10.crt`
  - `storage/app/private/icp-brasil-raiz-v10.pem`
  - `storage/app/private/fiscal-ca-bundle.pem`
  - `SYNC.md`

- Causa identificada:
  - a investigacao anterior estava enviesada por endpoint/cadeia errados;
  - ao validar diretamente `https://nfe.sefaz.go.gov.br/nfe/services/NFeAutorizacao4`, foi confirmado que a producao da SEFAZ-GO nao usa `Let's Encrypt`;
  - a cadeia real do servidor e:
    - certificado `nfe.sefaz.go.gov.br`;
    - intermediaria `AC SOLUTI SSL EV G4`;
    - raiz `Autoridade Certificadora Raiz Brasileira v10`;
  - por isso o bundle baseado em `Let's Encrypt` nao resolveria o `unable to get local issuer certificate` em producao.

- O que foi feito:
  - foram baixados os certificados publicos corretos da cadeia ICP-Brasil/SOLUTI v10;
  - foi reconstruido `storage/app/private/fiscal-ca-bundle.pem` concatenando:
    - `cacert.pem`;
    - `ac-soluti-ssl-ev-g4.pem`;
    - `icp-brasil-raiz-v10.pem`;
  - foi validado com `openssl s_client -CAfile fiscal-ca-bundle.pem` que a verificacao do certificado do servidor passou para:
    - `Verification: OK`;
    - `Verify return code: 0 (ok)`.

- Efeito observado:
  - a falha de cadeia publica do servidor foi efetivamente corrigida no teste direto com OpenSSL;
  - o erro residual passou a ser `sslv3 alert certificate unknown`, o que indica que o proximo suspeito deixou de ser a cadeia do servidor e passou a ser a aceitacao do certificado cliente no mutual TLS.

- Observacoes para sincronizar em `pec1`:
  - levar junto:
    - `storage/app/private/ac-soluti-ssl-ev-g4.crt`
    - `storage/app/private/ac-soluti-ssl-ev-g4.pem`
    - `storage/app/private/icp-brasil-raiz-v10.crt`
    - `storage/app/private/icp-brasil-raiz-v10.pem`
    - `storage/app/private/fiscal-ca-bundle.pem`
  - o bundle antigo baseado em `Let's Encrypt` nao representa a cadeia real da producao da SEFAZ-GO;
  - nao depende de migration.

## 19/04/26 - Correcao do hash do QR Code da NFC-e (`cStat 464`)

- Arquivos alterados:
  - `app/Support/FiscalNfceXmlService.php`
  - `SYNC.md`

- Causa identificada:
  - o calculo do `hashQRCode` da NFC-e online estava divergindo do padrao oficial da versao 2.00;
  - a string enviada ao `SHA-1` estava errada em dois pontos:
    - incluia um separador `|` extra antes do CSC;
    - usava o `cscId` sem normalizar a remocao dos zeros nao significativos;
  - com isso, a URL do QR Code era montada com parametros validos, mas o hash final nao batia com o recalculo da SEFAZ, gerando `cStat 464 - Rejeicao: Codigo de Hash no QR-Code difere do calculado`.

- O que foi feito:
  - `FiscalNfceXmlService::buildQrCodeUrl()` passou a:
    - normalizar o `cscId` usando apenas digitos e removendo zeros a esquerda;
    - montar a base do hash como:
      - `<chave>|2|<tpAmb>|<cscId><CSC>`
      - ou seja, sem `|` entre `cscId` e `CSC`, conforme o manual;
  - a URL final continuou no formato:
    - `?p=<chave>|2|<tpAmb>|<cscId>|<hash>`

- Efeito esperado:
  - a SEFAZ passa a recalcular exatamente o mesmo `hashQRCode` gerado pelo sistema;
  - elimina a rejeicao `464` quando o CSC e o CSC ID cadastrados estiverem corretos para o ambiente da loja.

- Observacoes para sincronizar em `pec1`:
  - levar junto:
    - `app/Support/FiscalNfceXmlService.php`
  - nao depende de migration;
  - esta etapa nao altera a URL base da consulta, apenas a forma de calcular o `hashQRCode`.

## 19/04/26 - Correcao da `urlChave` de Goiás para eliminar `cStat 878`

- Arquivos alterados:
  - `app/Support/FiscalWebserviceResolverService.php`
  - `SYNC.md`

- Causa identificada:
  - a rejeicao `878 - Endereco do site da UF da Consulta por chave de acesso diverge do previsto` nao tinha relacao com localizacao fisica do usuario;
  - o problema estava no mapeamento da tag `urlChave` para Goiás;
  - o sistema estava preenchendo `urlChave` com:
    - `https://nfeweb.sefaz.go.gov.br/nfeweb/sites/nfe/consulta-completa`
    - `https://nfewebhomolog.sefaz.go.gov.br/nfeweb/sites/nfe/consulta-completa`
  - mas a SEFAZ-GO espera o endereco oficial de consulta por chave no portal publico do estado.

- O que foi feito:
  - `FiscalWebserviceResolverService` passou a usar para Goiás, em producao e homologacao:
    - `http://www.sefaz.go.gov.br/nfce/consulta`
  - a URL do QR Code foi mantida separadamente, sem alterar o mapeamento de `qr_code_url`.

- Efeito esperado:
  - a tag `urlChave` passa a refletir o endereco oficial esperado pela SEFAZ-GO;
  - elimina a rejeicao `878` quando a nota for retransmitida com o novo XML.

- Observacoes para sincronizar em `pec1`:
  - levar junto:
    - `app/Support/FiscalWebserviceResolverService.php`
  - nao depende de migration;
  - esta etapa corrige apenas `urlChave`; nao altera webservice de autorizacao nem a URL do QR Code.

## 19/04/26 - Ajuste visual na tela fiscal e botao de cupom fiscal na modal do terminal

- Arquivos alterados:
  - `resources/js/Pages/Settings/FiscalConfig.jsx`
  - `resources/js/Pages/Dashboard.jsx`
  - `SYNC.md`

- Causa identificada:
  - a grade de configuracao fiscal ainda exibia a coluna `XML debug`, o que poluia a leitura das notas preparadas;
  - a modal `Cupom pronto para impressao` do dashboard tinha botao apenas para o cupom comum, mesmo quando a venda ja possuia informacoes fiscais suficientes para gerar um cupom fiscal.

- O que foi feito:
  - foi removida a coluna visual `XML debug` da tabela de notas em `FiscalConfig.jsx`;
  - foi removido da tabela o card visual que renderizava esse bloco de debug;
  - `Dashboard.jsx` passou a importar `buildFiscalReceiptHtml`;
  - foi criado o fluxo `handlePrintFiscalReceipt()` para montar um payload fiscal a partir dos dados ja presentes na modal da venda;
  - foi adicionado o botao `Cupom Fiscal` na modal `Cupom pronto para impressao` sempre que existir bloco fiscal na venda.

- Efeito esperado:
  - a tabela fiscal fica mais limpa e focada nas acoes operacionais;
  - o operador consegue imprimir o cupom fiscal diretamente da modal da venda, sem depender de outra tela.

- Observacoes para sincronizar em `pec1`:
  - levar junto:
    - `resources/js/Pages/Settings/FiscalConfig.jsx`
    - `resources/js/Pages/Dashboard.jsx`
  - nao depende de migration;
  - o botao novo gera o DANFE/NFC-e no frontend usando os dados fiscais ja retornados na venda.

## 19/04/26 - Botao `Cupom Fiscal` exibido somente apos autorizacao da NFC-e

- Arquivos alterados:
  - `resources/js/Pages/Dashboard.jsx`
  - `SYNC.md`

- Causa identificada:
  - o botao `Cupom Fiscal` estava aparecendo cedo demais, apenas pela existencia do bloco fiscal na venda;
  - antes da transmissao/autorizacao, o recibo fiscal ainda pode nao ter `chave_acesso`, `protocolo` e QR Code finais, o que gera impressao incompleta.

- O que foi feito:
  - a regra da modal `Cupom pronto para impressao` passou a exibir o botao `Cupom Fiscal` somente quando:
    - `receiptData.fiscal.status === 'emitida'`

- Efeito esperado:
  - evita impressao do cupom fiscal sem QR Code e chave de acesso definitivos;
  - o operador continua com o botao normal `Imprimir` disponivel em qualquer momento da venda.

- Observacoes para sincronizar em `pec1`:
  - levar junto:
    - `resources/js/Pages/Dashboard.jsx`
  - nao depende de migration;
  - a mudanca e apenas de regra visual/operacional da modal.

## 19/04/26 - Impressao do cupom fiscal aguardando o carregamento do QR Code

- Arquivos alterados:
  - `resources/js/Pages/Dashboard.jsx`
  - `SYNC.md`

- Causa identificada:
  - o QR Code continuava saindo em branco porque `Dashboard.jsx` ainda chamava `printWindow.print()` imediatamente apos escrever o HTML fiscal;
  - isso atropelava o script interno de `buildFiscalReceiptHtml()`, que ja estava preparado para esperar o `load` da imagem `#qrCodeImage` antes de imprimir.

- O que foi feito:
  - foi removido o `printWindow.print()` imediato do fluxo `handlePrintFiscalReceipt()`;
  - a janela fiscal passou a depender do script interno do HTML fiscal para decidir o momento correto do `print()`;
  - `Dashboard.jsx` ficou responsavel apenas por fechar a janela no `afterprint`.

- Efeito esperado:
  - o QR Code tem tempo para carregar antes da impressao;
  - o cupom fiscal deixa de sair com o quadro em branco quando a imagem externa responder normalmente.

- Observacoes para sincronizar em `pec1`:
  - levar junto:
    - `resources/js/Pages/Dashboard.jsx`
  - nao depende de migration;
  - esta etapa depende do `buildFiscalReceiptHtml()` que ja estava com o script de espera do QR Code.

## 19/04/26 - Remocao do `XML debug` da modal `Cupom pronto para impressao`

- Arquivos alterados:
  - `resources/js/Pages/Dashboard.jsx`
  - `SYNC.md`

- Causa identificada:
  - mesmo apos limpar a tela de configuracao fiscal, a modal de fechamento da venda ainda exibia o bloco `XML debug`;
  - isso acontecia porque `Dashboard.jsx` continuava renderizando `renderFiscalXmlDebug(receiptData.fiscal.xml_debug)` dentro do card azul da nota fiscal.

- O que foi feito:
  - foi removida a funcao `renderFiscalXmlDebug` de `Dashboard.jsx`;
  - foi removida a chamada desse bloco dentro da modal `Cupom pronto para impressao`.

- Efeito esperado:
  - a modal passa a mostrar apenas o resumo fiscal operacional:
    - numero;
    - status;
    - protocolo;
    - recibo;
    - mensagem;
  - elimina a poluicao visual do debug tecnico na experiencia do caixa.

- Observacoes para sincronizar em `pec1`:
  - levar junto:
    - `resources/js/Pages/Dashboard.jsx`
  - nao depende de migration;
  - esta etapa e apenas de limpeza visual.

## 19/04/26 - Fallback do CA bundle para producao com `APP_STORAGE` externo

- Arquivos alterados:
  - `app/Support/FiscalNfceTransmissionService.php`
  - `SYNC.md`

- Causa identificada:
  - em producao, o projeto usa `APP_STORAGE=/home/site/storage`, entao `storage_path()` passa a apontar para fora de `/home/site/wwwroot`;
  - o erro mostrou que a transmissao procurou o bundle apenas no storage externo e nos fallbacks globais, sem considerar o bundle que pode estar presente dentro do codigo publicado;
  - por isso o local funcionava, mas a producao falhava com `nenhuma cadeia de certificados confiaveis foi encontrada no ambiente` quando o arquivo estava em `wwwroot/storage/app/private/...` e nao em `/home/site/storage/app/private/...`.

- O que foi feito:
  - a rotina `resolveCaBundlePath()` passou a considerar tambem estes fallbacks:
    - `base_path('storage/app/private/fiscal-ca-bundle.pem')`;
    - `base_path('storage/app/private/cacert.pem')`;
  - os caminhos antigos continuam com prioridade:
    - `services.fiscal.ca_bundle`;
    - `storage_path('app/private/fiscal-ca-bundle.pem')`;
    - `storage_path('app/private/cacert.pem')`;
    - `php.ini`;
    - `cacert.pem` globais.

- Efeito esperado:
  - em producao, a transmissao consegue usar o bundle que veio junto no deploy mesmo quando o `storage` real do Laravel esta fora do `wwwroot`;
  - reduz falhas de ambiente causadas por diferenca entre layout local XAMPP e layout Linux/Azure.

- Observacoes para sincronizar em `pec1`:
  - levar junto:
    - `app/Support/FiscalNfceTransmissionService.php`
  - nao depende de migration;
  - se o outro ambiente tambem usar `APP_STORAGE` externo, esse fallback ajuda a localizar o bundle publicado dentro do projeto.

## 19/04/26 - Cadastro fiscal opcional em `products/create`

- Arquivos alterados:
  - `app/Http/Controllers/ProductController.php`
  - `resources/js/Pages/Products/ProductCreate.jsx`
  - `SYNC.md`

- Causa identificada:
  - a tela `products/create` mostrava o bloco `Cadastro fiscal` como obrigatorio, com asteriscos e atributos `required`;
  - alem disso, o backend ainda executava `ensureRequiredFiscalFields()`, bloqueando o salvamento sem `NCM`, `CFOP` e sem ao menos um entre `CSOSN` ou `CST`;
  - por isso, mesmo quando a intencao era cadastrar o produto sem configuracao fiscal naquele momento, o formulario era recusado.

- O que foi feito:
  - a validacao obrigatoria dos campos fiscais foi removida de `ProductController`;
  - o formulario `products/create` deixou de marcar `NCM`, `CFOP`, `CSOSN` e `CST` como obrigatorios;
  - o texto explicativo da tela passou a informar que todos os campos fiscais sao opcionais no cadastro.

- Efeito esperado:
  - o produto pode ser cadastrado sem preencher a secao `Cadastro fiscal`;
  - quando algum campo fiscal for informado, as validacoes de formato continuam ativas;
  - produtos sem cadastro fiscal continuam podendo ficar de fora da preparacao fiscal automatica ate serem completados depois.

- Observacoes para sincronizar em `pec1`:
  - levar junto:
    - `app/Http/Controllers/ProductController.php`
    - `resources/js/Pages/Products/ProductCreate.jsx`
  - nao depende de migration.

## 19/04/26 - Padronizacao fiscal dos produtos com `tb1_tipo = 1`

- Arquivos alterados:
  - `SYNC.md`

- Causa identificada:
  - os produtos do tipo `1` precisavam ficar com o mesmo cadastro fiscal para evitar divergencia de emissao e de preparacao fiscal entre itens equivalentes.

- O que foi feito:
  - foi executado update direto no banco na tabela `tb1_produto` para todos os registros com `tb1_tipo = 1`;
  - os seguintes campos foram padronizados:
    - `tb1_ncm = 19059090`
    - `tb1_cfop = 5102`
    - `tb1_csosn = 102`
    - `tb1_cst = 040`
    - `tb1_origem = 0`

- Resultado observado:
  - total de produtos com `tb1_tipo = 1`: `315`
  - linhas efetivamente alteradas pelo `UPDATE`: `314`
  - depois da execucao, `315` de `315` produtos desse tipo ficaram com o padrao solicitado.

- Observacoes para sincronizar em `pec1`:
  - replicar o mesmo `UPDATE` diretamente no banco do outro projeto;
  - nao depende de migration;
  - SQL aplicado:

```sql
UPDATE tb1_produto
SET
    tb1_ncm = '19059090',
    tb1_cfop = '5102',
    tb1_csosn = '102',
    tb1_cst = '040',
    tb1_origem = 0
WHERE tb1_tipo = 1;
```

## 19/04/26 - Padronizacao fiscal de toda a `tb1_produto`

- Arquivos alterados:
  - `SYNC.md`

- Causa identificada:
  - foi solicitado aplicar o mesmo cadastro fiscal para todos os itens da tabela de produtos, sem restringir por tipo;
  - antes disso, apenas os produtos com `tb1_tipo = 1` tinham recebido esse padrao.

- O que foi feito:
  - foi executado update direto no banco na tabela `tb1_produto` para todos os registros;
  - os seguintes campos foram padronizados em toda a tabela:
    - `tb1_ncm = 19059090`
    - `tb1_cfop = 5102`
    - `tb1_csosn = 102`
    - `tb1_cst = 040`
    - `tb1_origem = 0`

- Resultado observado:
  - total de produtos na tabela `tb1_produto`: `1904`
  - linhas efetivamente alteradas pelo `UPDATE`: `1589`
  - depois da execucao, `1904` de `1904` produtos ficaram com o padrao solicitado.

- Observacoes para sincronizar em `pec1`:
  - replicar o mesmo `UPDATE` diretamente no banco do outro projeto;
  - nao depende de migration;
  - SQL aplicado:

```sql
UPDATE tb1_produto
SET
    tb1_ncm = '19059090',
    tb1_cfop = '5102',
    tb1_csosn = '102',
    tb1_cst = '040',
    tb1_origem = 0;
```

## 19/04/26 - Limpeza dos dados fiscais dos produtos que nao sao de balanca

- Arquivos alterados:
  - `SYNC.md`

- Causa identificada:
  - depois do update que aplicou o mesmo padrao fiscal em toda a `tb1_produto`, os produtos que nao sao de balanca tambem ficaram com os dados do pao;
  - como esses itens nao deveriam herdar esse padrao, foi necessario desfazer o impacto neles limpando o cadastro fiscal.

- O que foi feito:
  - foi executado update direto no banco para todos os produtos com `tb1_tipo <> 1`;
  - os seguintes campos fiscais foram limpos:
    - `tb1_ncm = NULL`
    - `tb1_cest = NULL`
    - `tb1_cfop = NULL`
    - `tb1_csosn = NULL`
    - `tb1_cst = NULL`
  - os campos numericos/default foram redefinidos para:
    - `tb1_origem = 0`
    - `tb1_aliquota_icms = 0`

- Resultado observado:
  - total de produtos com `tb1_tipo <> 1`: `1589`
  - linhas efetivamente alteradas pelo `UPDATE`: `1589`
  - depois da execucao, `1589` de `1589` produtos nao-balanca ficaram com os campos fiscais limpos.

- Observacoes para sincronizar em `pec1`:
  - replicar o mesmo `UPDATE` diretamente no banco do outro projeto apenas se la tambem tiver ocorrido a aplicacao indevida do padrao fiscal em todos os produtos;
  - nao depende de migration;
  - SQL aplicado:

```sql
UPDATE tb1_produto
SET
    tb1_ncm = NULL,
    tb1_cest = NULL,
    tb1_cfop = NULL,
    tb1_csosn = NULL,
    tb1_cst = NULL,
    tb1_origem = 0,
    tb1_aliquota_icms = 0
WHERE tb1_tipo <> 1;
```

## 19/04/26 - Correcao do erro em venda mista `dinheiro + cartao`

- Arquivos alterados:
  - `database/migrations/2026_04_19_120000_expand_tipo_pagamento_length_on_tb4_vendas_pg.php`
  - `SYNC.md`

- Causa identificada:
  - apos o desmembramento de `maquina` em `cartao_credito` e `cartao_debito`, o sistema passou a salvar em `tb4_vendas_pg.tipo_pagamento` os tipos mistos:
    - `dinheiro_cartao_credito`
    - `dinheiro_cartao_debito`
  - a coluna `tb4_vendas_pg.tipo_pagamento` ainda estava definida como `VARCHAR(20)`;
  - esses novos valores ultrapassam 20 caracteres, causando erro de gravacao quando a venda era parte em dinheiro e o restante no cartao.

- O que foi feito:
  - a coluna `tb4_vendas_pg.tipo_pagamento` foi ampliada de `VARCHAR(20)` para `VARCHAR(40)`;
  - foi criada migration para manter esse ajuste versionado no projeto;
  - o `ALTER TABLE` tambem foi aplicado no banco atual para remover o erro imediatamente.

- Efeito esperado:
  - vendas mistas em `dinheiro + cartao credito` e `dinheiro + cartao debito` deixam de gerar `Server Error`;
  - o backend passa a persistir corretamente os novos tipos longos em `tb4_vendas_pg`.

- Observacoes para sincronizar em `pec1`:
  - levar junto:
    - `database/migrations/2026_04_19_120000_expand_tipo_pagamento_length_on_tb4_vendas_pg.php`
  - aplicar tambem o ajuste no banco do outro projeto;
  - nao depende de mudanca em tela;
  - SQL aplicado:

```sql
ALTER TABLE tb4_vendas_pg
MODIFY tipo_pagamento VARCHAR(40) NOT NULL;
```

## 19/04/26 - Atualizacao fiscal por GTIN para produtos com codigo de barras

- Arquivos alterados:
  - `SYNC.md`

- Causa identificada:
  - foi solicitado atualizar os dados fiscais apenas dos produtos com codigo de barras;
  - como `CFOP`, `CSOSN` e `CST` nao podem ser inferidos com seguranca apenas pelo GTIN, a atualizacao segura ficou restrita a:
    - `tb1_ncm`
    - `tb1_cest`

- Fonte utilizada:
  - consulta publica por GTIN no endpoint `https://ean.blendo.com.br/ean_consulta.php`;
  - o endpoint retornou JSON com campos como:
    - `ncm`
    - `cest`
    - `nome`
    - `categoria`
  - exemplo validado durante a execucao:
    - GTIN `7894900027013` (`COCA COLA 2L`) retornou `ncm = 22021000`.

- O que foi feito:
  - foram considerados apenas produtos com `tb1_codbar` em formato numerico valido de `8` a `14` digitos;
  - para cada GTIN valido, foi feita consulta externa;
  - quando a resposta trouxe `ncm` valido com `8` digitos, o produto foi atualizado;
  - quando a resposta trouxe `cest` valido com `7` digitos, ele tambem foi salvo;
  - nenhum ajuste foi feito em:
    - `tb1_cfop`
    - `tb1_csosn`
    - `tb1_cst`
    - `tb1_origem`

- Resultado observado:
  - total de produtos com GTIN valido considerado no lote: `1528`
  - produtos com `NCM` resolvido automaticamente pela consulta: `267`
  - produtos com `CEST` resolvido automaticamente: `31`
  - atualizacoes efetivamente aplicadas no banco durante o lote: `267`
  - consultas sem retorno fiscal util para preenchimento automatico: `1261`
  - erros tecnicos durante o lote: `0`

- Observacoes para sincronizar em `pec1`:
  - nao foi criada migration;
  - a atualizacao foi operacional, via consulta externa por GTIN;
  - repetir apenas se o outro ambiente tambem puder consultar a mesma fonte externa;
  - manter o mesmo criterio de seguranca:
    - atualizar somente `NCM`;
    - atualizar `CEST` apenas quando a fonte retornar valor valido;
    - nao preencher `CFOP/CSOSN/CST` automaticamente por GTIN.

## 19/04/26 - Filtros fiscais e botoes em icone na tela `products`

- Arquivos alterados:
  - `app/Http/Controllers/ProductController.php`
  - `resources/js/Pages/Products/ProductIndex.jsx`
  - `SYNC.md`

- Causa identificada:
  - a barra de acoes da tela `products` ainda mostrava os botoes `VR Credito` e `Estoque` com texto, ocupando mais espaco do que o necessario;
  - alem disso, nao existia atalho para listar produtos com cadastro fiscal completo ou incompleto considerando os quatro campos obrigatorios operacionais:
    - `NCM`
    - `CFOP`
    - `CSOSN`
    - `CST`

- O que foi feito:
  - os botoes `VR Credito` e `Estoque` passaram a exibir apenas icones, mantendo `title` e `aria-label`;
  - foram adicionados dois novos botoes com icone:
    - um para listar produtos com cadastro fiscal incompleto;
    - outro para listar produtos com cadastro fiscal completo;
  - o backend de `products.index` passou a aceitar o filtro `fiscal_status` com os valores:
    - `complete`
    - `incomplete`
  - produto fiscal completo foi definido como aquele que possui simultaneamente:
    - `tb1_ncm`
    - `tb1_cfop`
    - `tb1_csosn`
    - `tb1_cst`
  - produto fiscal incompleto foi definido como aquele em que qualquer um desses quatro campos esteja vazio ou nulo.

- Efeito esperado:
  - a barra superior da listagem fica mais compacta;
  - o usuario consegue localizar rapidamente produtos com cadastro fiscal faltando ou completo;
  - o filtro fiscal pode ser combinado com busca, ordenacao e filtro de `VR Credito`.

- Observacoes para sincronizar em `pec1`:
  - levar junto:
    - `app/Http/Controllers/ProductController.php`
    - `resources/js/Pages/Products/ProductIndex.jsx`
  - nao depende de migration.

## 19/04/26 - Fila de atualizacao fiscal em lote na tela `products`

- Arquivos alterados:
  - `app/Http/Controllers/ProductController.php`
  - `resources/js/Pages/Products/ProductIndex.jsx`
  - `resources/js/Pages/Products/ProductFiscalQueue.jsx`
  - `routes/web.php`
  - `SYNC.md`

- Causa identificada:
  - a atualizacao manual dos dados fiscais produto por produto exigia abrir formulario individual, salvar e esperar a tela recarregar;
  - isso tornava o preenchimento de `NCM`, `CFOP`, `CSOSN` e `CST` lento e repetitivo para muitos itens pendentes.

- O que foi feito:
  - foi criado um novo icone na tela `products` para abrir a fila de atualizacao fiscal;
  - foi criada a tela `ProductFiscalQueue.jsx`;
  - a fila carrega `20` produtos por vez com cadastro fiscal incompleto;
  - cada linha mostra:
    - nome do produto;
    - codigo de barras;
    - campos `NCM`, `CFOP`, `CSOSN`, `CST`;
    - botao para gravar;
  - a gravacao e assincrona por linha, sem submit/reload da pagina;
  - ao gravar com sucesso:
    - a linha some imediatamente;
    - a contagem de pendentes e atualizada;
  - quando os `20` itens terminam:
    - a proxima lista e carregada sem recarregar a tela.

- Rotas adicionadas:
  - `products.fiscal-queue`
  - `products.fiscal-queue.items`
  - `products.fiscal-queue.update`

- Efeito esperado:
  - o usuario consegue preencher dados fiscais em fluxo continuo;
  - nao precisa voltar ao indice nem reabrir formulario individual a cada produto;
  - a fila sempre apresenta o proximo bloco de itens pendentes.

- Observacoes para sincronizar em `pec1`:
  - levar junto:
    - `app/Http/Controllers/ProductController.php`
    - `resources/js/Pages/Products/ProductIndex.jsx`
    - `resources/js/Pages/Products/ProductFiscalQueue.jsx`
    - `routes/web.php`
  - nao depende de migration.

## 20/04/26 - Filtros por tipo e placeholders na fila fiscal

- Arquivos alterados:
  - `app/Http/Controllers/ProductController.php`
  - `resources/js/Pages/Products/ProductFiscalQueue.jsx`
  - `SYNC.md`

- Causa identificada:
  - a fila fiscal trazia todos os produtos pendentes misturados, sem separar `Industria`, `Balanca`, `Servico` e `Producao`;
  - isso dificultava o preenchimento por categoria;
  - os campos fiscais tambem nao tinham placeholders de referencia, deixando a digitacao menos guiada.

- O que foi feito:
  - a tela `products/fiscal-queue` passou a exibir filtros por tipo:
    - `Industria`
    - `Balanca`
    - `Servico`
    - `Producao`
    - `Todos`
  - o backend agora aceita o parametro `type` na fila fiscal;
  - tanto a carga inicial quanto a busca dos proximos `20` itens respeitam o filtro selecionado;
  - foi adicionado resumo visual do tipo selecionado na tela;
  - os campos passaram a exibir placeholders:
    - `NCM`: `19059090`
    - `CFOP`: `5102`
    - `CSOSN`: `102`
    - `CST`: `040`

- Efeito esperado:
  - o usuario consegue trabalhar a fila fiscal por categoria de produto;
  - ao concluir um lote, os proximos `20` continuam sendo carregados dentro do mesmo tipo escolhido;
  - os placeholders servem como referencia visual rapida durante o preenchimento.

- Observacoes para sincronizar em `pec1`:
  - levar junto:
    - `app/Http/Controllers/ProductController.php`
    - `resources/js/Pages/Products/ProductFiscalQueue.jsx`
  - nao depende de migration.

## 20/04/26 - Novo tipo fiscal `cupom fiscal` com CPF apenas

- causa do problema:
  - o caixa so tratava dois cenarios fiscais:
    - `NF Balcao` sem destinatario;
    - `NF Consumidor` com nome e endereco completos;
  - qualquer consumidor informado era validado como se fosse obrigatoriamente `NF Consumidor`;
  - por isso nao existia o terceiro fluxo pedido agora:
    - `cupom fiscal` identificado apenas por CPF.

- o que foi ajustado:
  - a identificacao fiscal passou a ter tres modos funcionais:
    - `balcao`
    - `cupom_fiscal`
    - `consumidor`
  - a modal final da venda agora permite escolher:
    - `Cupom fiscal` com CPF apenas;
    - `NF Consumidor` com nome e endereco completos;
  - o payload fiscal salvo em `tb27_payload['consumer']` agora guarda tambem `type`;
  - a validacao fiscal passou a tratar `cupom_fiscal` como CPF-only;
  - a `NF Consumidor` continua exigindo nome e endereco completos;
  - o XML da NFC-e passou a montar o grupo `dest` em dois formatos:
    - `cupom_fiscal`: somente `CPF` + `indIEDest`;
    - `consumidor`: `CPF/CNPJ` + `xNome` + `enderDest` + `indIEDest`;
  - a modal e a impressao passaram a mostrar corretamente:
    - `NF Balcao`
    - `Cupom Fiscal`
    - `NF Consumidor`

- backend envolvido:
  - `app/Http/Controllers/SaleController.php`
    - `updateConsumerFiscalInvoice` agora recebe `consumer.type`;
    - `normalizeFiscalConsumerInput` passou a sanitizar separadamente `cupom_fiscal` e `NF Consumidor`;
    - `buildFiscalSummary` passou a devolver `consumer_type`;
    - `extractFiscalConsumer` passou a inferir e preservar o tipo fiscal salvo.
  - `app/Support/FiscalInvoicePreparationService.php`
    - o payload do consumidor agora e normalizado com `type`;
    - `cupom_fiscal` limpa campos de endereco e preserva apenas o CPF;
    - `validatePayload` passou a validar CPF-only para cupom e endereco completo apenas para `NF Consumidor`.
  - `app/Support/FiscalNfceXmlService.php`
    - `appendDestination` passou a gerar o `dest` reduzido quando o tipo for `cupom_fiscal`.

- frontend envolvido:
  - `resources/js/Pages/Dashboard.jsx`
    - a modal `Identificacao fiscal` agora permite alternar entre `Cupom fiscal` e `NF Consumidor`;
    - ao selecionar `Cupom fiscal`, a tela exige apenas CPF;
    - os rotulos da nota e da impressao passaram a refletir o tipo fiscal real.
  - `resources/js/Utils/receipt.js`
    - o DANFE 80mm passou a exibir o tipo fiscal impresso.

- testes/validacao executados:
  - `php -l app/Http/Controllers/SaleController.php`
  - `php -l app/Support/FiscalInvoicePreparationService.php`
  - `php -l app/Support/FiscalNfceXmlService.php`
  - `php -l tests/Unit/FiscalInvoicePreparationServiceTest.php`
  - `php -l tests/Unit/FiscalNfceXmlServiceTest.php`
  - `php artisan test --filter=FiscalInvoicePreparationServiceTest`
  - `php artisan test --filter=FiscalNfceXmlServiceTest`
  - `npm run build`

- observacoes importantes para sincronizar em `pec1`:
  - sincronizar exatamente:
    - `app/Http/Controllers/SaleController.php`
    - `app/Support/FiscalInvoicePreparationService.php`
    - `app/Support/FiscalNfceXmlService.php`
    - `resources/js/Pages/Dashboard.jsx`
    - `resources/js/Utils/receipt.js`
    - `tests/Unit/FiscalInvoicePreparationServiceTest.php`
    - `tests/Unit/FiscalNfceXmlServiceTest.php`
  - nao depende de migration;
  - nao cria tabela nova;
  - o tipo fiscal do consumidor fica salvo em `tb27_payload['consumer']['type']`;
  - notas antigas sem `type` continuam funcionando porque o sistema infere:
    - sem documento: `balcao`
    - com documento e sem nome: `cupom_fiscal`
    - com documento e nome: `consumidor`

## 20/04/26 - Correcao da transmissao da NFC-e para aceitar `cupom fiscal` com CPF-only

- causa do problema:
  - o gerador do XML da NFC-e foi ajustado para o novo modo `cupom_fiscal`, abrindo o grupo `dest` apenas com:
    - `CPF`
    - `indIEDest`
  - porem a camada de transmissao ainda estava presa a regra antiga de `NF Consumidor`;
  - ao validar o XML antes do SOAP, `FiscalNfceTransmissionService` exigia sempre:
    - nome do destinatario (`xNome`)
    - endereco completo (`enderDest`)
  - por isso o `cupom fiscal` falhava com a mensagem:
    - `O XML fiscal abriu o grupo dest, mas nao informou o nome do destinatario.`

- o que foi ajustado:
  - `FiscalNfceTransmissionService` passou a reconhecer o caso de `cupom fiscal`;
  - quando o `dest` vier somente com CPF, sem `xNome` e sem `enderDest`, a transmissao passa a aceitar;
  - a exigencia de nome e endereco completo continua valendo para destinatario completo (`NF Consumidor`).

- arquivos alterados:
  - `app/Support/FiscalNfceTransmissionService.php`
  - `tests/Unit/FiscalNfceTransmissionServiceTest.php`
  - `SYNC.md`

- testes/validacao executados:
  - `php -l app/Support/FiscalNfceTransmissionService.php`
  - `php -l tests/Unit/FiscalNfceTransmissionServiceTest.php`
  - `php artisan test --filter=FiscalNfceTransmissionServiceTest`

- observacoes importantes para sincronizar em `pec1`:
  - sincronizar exatamente:
    - `app/Support/FiscalNfceTransmissionService.php`
    - `tests/Unit/FiscalNfceTransmissionServiceTest.php`
  - nao depende de migration;
  - sem essa etapa, o outro projeto vai continuar rejeitando `cupom fiscal` mesmo com o XML correto.

## 20/04/26 - DANFE fiscal passou a imprimir os itens reais do payload da nota

- causa do problema:
  - o DANFE 80mm estava montando a secao `ITENS` a partir de `receiptData.items`, que representa o estado da venda no frontend;
  - porem os itens fiscais efetivos da NFC-e ficam salvos no payload da nota em `tb27_payload['itens']`;
  - com isso, em cenarios onde o estado do frontend nao refletia corretamente o conteudo fiscal, o DANFE podia sair com:
    - `Nenhum item fiscal disponivel para impressao`
  - isso acontecia mesmo quando a nota ja tinha itens fiscais validos salvos no payload.

- o que foi ajustado:
  - `SaleController` passou a expor no resumo fiscal um array `fiscal.items`, montado diretamente de `tb27_payload['itens']`;
  - a impressao fiscal em `Dashboard.jsx` passou a priorizar `receiptData.fiscal.items`;
  - `receiptData.items` ficou apenas como fallback.

- efeito esperado:
  - o DANFE passa a listar exatamente os itens fiscais que entraram na NFC-e;
  - itens excluidos do payload fiscal nao aparecem mais por engano na impressao;
  - a impressao fica coerente com a nota realmente gerada.

- arquivos alterados:
  - `app/Http/Controllers/SaleController.php`
  - `resources/js/Pages/Dashboard.jsx`
  - `SYNC.md`

- testes/validacao executados:
  - `php -l app/Http/Controllers/SaleController.php`
  - `php -l resources/js/Pages/Dashboard.jsx`
  - `npm run build`
  - validacao direta do resumo fiscal da nota `23925`, confirmando `1` item em `fiscal.items`

- observacoes importantes para sincronizar em `pec1`:
  - sincronizar exatamente:
    - `app/Http/Controllers/SaleController.php`
    - `resources/js/Pages/Dashboard.jsx`
  - nao depende de migration;
  - esta etapa e importante para o outro projeto imprimir os itens corretos da NFC-e, mesmo quando houver diferenca entre o estado da venda no frontend e o payload fiscal salvo.

## 20/04/26 - Busca automatica na fila fiscal

- Arquivos alterados:
  - `app/Http/Controllers/ProductController.php`
  - `resources/js/Pages/Products/ProductFiscalQueue.jsx`
  - `SYNC.md`

- Causa identificada:
  - a fila fiscal tinha filtro por tipo, mas nao permitia localizar rapidamente um item especifico por nome, ID ou codigo de barras;
  - isso obrigava o usuario a depender apenas do lote visivel de `20` itens.

- O que foi feito:
  - foi adicionado um campo de busca ao lado dos botoes de filtro em `products/fiscal-queue`;
  - a busca dispara automaticamente ao digitar `4` caracteres ou mais;
  - quando o campo fica vazio, a fila volta automaticamente para a listagem sem busca;
  - o backend passou a aceitar o parametro `search` na fila fiscal;
  - a busca funciona em:
    - `tb1_nome`
    - `tb1_id`
    - `tb1_codbar`
  - a busca foi integrada com o filtro por tipo;
  - a carga dos proximos `20` itens continua respeitando:
    - tipo selecionado
    - termo pesquisado

- Efeito esperado:
  - o usuario consegue localizar e preencher rapidamente produtos especificos dentro da fila fiscal;
  - o comportamento assincrono da fila continua igual, sem recarregar a tela inteira.

- Observacoes para sincronizar em `pec1`:
  - levar junto:
    - `app/Http/Controllers/ProductController.php`
    - `resources/js/Pages/Products/ProductFiscalQueue.jsx`
  - nao depende de migration.

## 20/04/26 - Atalhos cruzados entre folha e contra-cheque

- Arquivos alterados:
  - `resources/js/Pages/Settings/FolhaPagamento.jsx`
  - `resources/js/Pages/Settings/ContraCheque.jsx`
  - `SYNC.md`

- Causa identificada:
  - as telas `settings/folha-pagamento` e `settings/contra-cheque` nao tinham navegacao direta entre si;
  - para alternar entre elas, o usuario precisava voltar para o menu de configuracoes, o que deixava o fluxo mais lento.

- O que foi feito:
  - em `settings/folha-pagamento` foi adicionado um botao de atalho `Ir para Contra-Cheque` no cabecalho da tela;
  - em `settings/contra-cheque` foi adicionado um botao de atalho `Ir para Folha de Pagamento` no cabecalho da tela;
  - os atalhos usam as rotas ja existentes:
    - `settings.contra-cheque`
    - `settings.payroll`

- Efeito esperado:
  - agora o usuario pode alternar diretamente entre as duas telas sem voltar ao menu principal de configuracoes.

- Observacoes para sincronizar em `pec1`:
  - sincronizar exatamente:
    - `resources/js/Pages/Settings/FolhaPagamento.jsx`
    - `resources/js/Pages/Settings/ContraCheque.jsx`
  - manter tambem este registro no `SYNC.md`;
  - nao depende de migration;
  - a alteracao e apenas de navegacao/interface.

## 20/04/26 - Filtro de usuario em folha/contra-cheque e impressao unificada

- Arquivos alterados:
  - `app/Http/Controllers/PayrollController.php`
  - `resources/js/Pages/Settings/FolhaPagamento.jsx`
  - `resources/js/Pages/Settings/ContraCheque.jsx`
  - `SYNC.md`

- Causa identificada:
  - as telas `settings/folha-pagamento` e `settings/contra-cheque` so permitiam filtrar por periodo, unidade e funcao;
  - o backend em `PayrollController` nao aceitava `user_id`, entao nao havia como restringir a listagem para um colaborador especifico;
  - alem disso, o botao `Imprimir 80mm` em `settings/contra-cheque` usava `printContraCheque(..., { showDetails: false })`, enquanto o botao `Contra-Cheque` da folha usava `showDetails: true`;
  - por isso os dois botoes imprimiam conteudos diferentes mesmo usando o mesmo utilitario de impressao.

- O que foi feito:
  - `PayrollController` passou a:
    - aceitar o parametro `user_id`;
    - resolver o usuario selecionado;
    - aplicar o filtro na consulta principal das duas telas;
    - expor `filterUsers` e `selectedUserId` no payload enviado ao frontend;
  - `settings/folha-pagamento` recebeu um novo seletor `Usuario` no formulario de filtros;
  - `settings/contra-cheque` recebeu o mesmo seletor `Usuario`;
  - `settings/contra-cheque` passou a usar `showDetails: true`, deixando a impressao `80mm` identica ao conteudo aberto pelo botao `Contra-Cheque` da folha.

- Efeito esperado:
  - agora e possivel filtrar um colaborador especifico nas telas:
    - `settings/folha-pagamento`
    - `settings/contra-cheque`
  - o botao `Imprimir 80mm` de `settings/contra-cheque` passa a imprimir o mesmo detalhamento de adiantamentos e vales do botao `Contra-Cheque` da folha.

- Observacoes para sincronizar em `pec1`:
  - sincronizar exatamente:
    - `app/Http/Controllers/PayrollController.php`
    - `resources/js/Pages/Settings/FolhaPagamento.jsx`
    - `resources/js/Pages/Settings/ContraCheque.jsx`
  - manter tambem este registro no `SYNC.md`;
  - nao depende de migration;
  - a alteracao e funcional e de interface, sem mudar estrutura de banco.

## 20/04/26 - Otimizacao do relatorio Controle para evitar erro 500 em producao

- Arquivos alterados:
  - `app/Http/Controllers/SalesReportController.php`
  - `SYNC.md`

- Causa identificada:
  - a tela `Controle` (`GET /reports/control`) montava os totais por loja carregando todos os registros de `tb4_vendas_pg` do periodo com `with('vendas')`;
  - depois disso, o backend percorria todos os pagamentos em PHP para descobrir a unidade e separar os valores entre `dinheiro` e `cartao`;
  - em producao, esse volume cresce bastante e aumenta muito consumo de memoria e tempo de execucao, o que pode resultar em erro `500` ao abrir o menu `Controle` no dashboard;
  - o problema ficava concentrado no metodo `buildControlStoreTotals()` de `SalesReportController`.

- O que foi feito:
  - a apuracao de `dinheiro`, `cartao` e `all` no relatorio `Controle` foi reescrita para consolidar os totais diretamente no banco;
  - foi criado um fluxo com subquery para localizar a primeira venda de cada pagamento dentro das unidades permitidas e, a partir dela, agrupar os totais por `id_unidade`;
  - a separacao entre parte em dinheiro e parte em cartao continua respeitando a mesma regra anterior:
    - `cartao_credito`, `cartao_debito` e `maquina` contam como cartao;
    - `dinheiro`, `dinheiro_cartao_credito` e `dinheiro_cartao_debito` usam `dois_pgto` para separar cartao de dinheiro;
  - os filtros `vale` e `refeicao` continuaram com a agregacao ja existente em `tb3_vendas`.

- Efeito esperado:
  - o menu `Controle` passa a abrir com bem menos carga de memoria no backend;
  - a chance de erro `500` por volume de dados no dashboard cai significativamente;
  - o resultado visual do relatorio permanece o mesmo, inclusive nos filtros por forma de pagamento.

- Observacoes para sincronizar em `pec1`:
  - sincronizar exatamente:
    - `app/Http/Controllers/SalesReportController.php`
  - manter tambem este registro no `SYNC.md`;
  - nao depende de migration;
  - a alteracao foi feita para performance e estabilidade da rota `reports.control`, sem mudar layout da tela.

## 20/04/26 - Correcao de carregamento antigo do frontend fiscal

- Arquivos alterados:
  - `public/hot` (removido)
  - `SYNC.md`

- Causa identificada:
  - o Laravel estava priorizando o modo Vite dev por causa da existencia do arquivo `public/hot`;
  - esse arquivo apontava para `http://[::1]:4000`, entao o projeto ignorava o build novo em `public/build`;
  - com isso, a tela `settings/fiscal` podia continuar carregando um bundle JS antigo em memoria, mesmo apos o backend e o build ja estarem corretos;
  - esse estado explica o caso em que o botao `Cupom` ainda mostrava "Nenhum item fiscal disponivel para impressao", embora o endpoint ja estivesse retornando `receipt.items`.

- O que foi feito:
  - foi removido o arquivo `public/hot`;
  - com isso, o Laravel volta a servir os assets compilados de `public/build`;
  - o build atual ja continha os arquivos novos de `FiscalConfig` e `receipt`, entao a correcao necessaria aqui era impedir o uso do servidor Vite antigo.

- Efeito esperado:
  - a tela `settings/fiscal` passa a usar o bundle atualizado salvo em `public/build`;
  - o clique em `Cupom` deixa de depender de uma versao antiga do frontend em memoria;
  - o comportamento da impressao fiscal fica alinhado com o payload atual retornado pelo backend.

- Observacoes para sincronizar em `pec1`:
  - verificar se tambem existe `public/hot` no projeto `C:\xampp\htdocs\pec1`;
  - se existir e o ambiente nao depender intencionalmente de Vite em modo dev, remover esse arquivo la tambem;
  - nao copiar o arquivo `public/hot` entre ambientes;
  - nao depende de migration;
  - esta correcao e de entrega/carregamento de frontend, nao de regra de negocio fiscal.

## 20/04/26 - Cupom fiscal sem itens em settings/fiscal

- Arquivos alterados:
  - `app/Http/Controllers/FiscalConfigurationController.php`
  - `SYNC.md`

- Causa identificada:
  - a grade `Ultimas notas preparadas` carregava as notas fiscais com uma selecao parcial de colunas;
  - nessa consulta, o campo `tb27_payload` nao era trazido do banco;
  - o botao `Cupom` depende de `buildInvoiceListPayload()`, que monta `invoice.fiscal_receipt.items` a partir de `tb27_payload['itens']`;
  - como o payload fiscal nao vinha nessa query da tela, o cupom era montado com `items` vazio;
  - por isso a venda `23925` mostrava "Nenhum item fiscal disponivel para impressao" mesmo com a nota `tb27_id = 20` contendo `1` item fiscal salvo.

- O que foi feito:
  - a query da listagem em `settings/fiscal` passou a incluir `tb27_payload`;
  - tambem foram incluidos `tb2_id` e `tb26_id` para manter o model da nota coerente com os relacionamentos usados na montagem do payload fiscal;
  - com isso, `buildInvoiceListPayload()` volta a receber os dados completos necessarios para popular `invoice.fiscal_receipt.items`.

- Efeito esperado:
  - o botao `Cupom` da tabela `Ultimas notas preparadas` passa a abrir o DANFE NFC-e com os itens fiscais corretos;
  - a venda `23925` deve voltar a exibir o item `PAO DE SAL`, quantidade `1`, valor unitario `R$ 2,62`.

- Observacoes para sincronizar em `pec1`:
  - sincronizar exatamente:
    - `app/Http/Controllers/FiscalConfigurationController.php`
  - manter tambem o registro desta etapa em `SYNC.md`;
  - nao depende de migration;
  - esta correcao afeta especificamente a listagem de notas em `settings/fiscal`, nao a geracao do payload fiscal no banco.
## 23/04/26 - Preparacao de migracao do MySQL KingHost para Azure

- Arquivos criados/alterados:
  - `scripts/export-mysql-dump.php`
  - `storage/certs/DigiCertGlobalRootG2.crt.pem`
  - `bkp/paoecafe83_23-04-26_azure.sql`
  - `SYNC.md`

- Causa identificada:
  - a aplicacao esta hospedada na Azure, mas o banco MySQL ainda estava na KingHost;
  - essa separacao aumenta dependencia de rede entre provedores e pode gerar instabilidade/latencia;
  - o `Azure Database for MySQL Flexible Server` exige transporte seguro/TLS, entao o Laravel precisa usar `MYSQL_ATTR_SSL_CA` apontando para o certificado publico;
  - o MySQL 8 da Azure tambem estava gerando chave primaria invisivel automatica em tabelas sem PK durante importacao de dump antigo, causando erro `Multiple primary key defined` quando o dump aplicava `ALTER TABLE ... ADD PRIMARY KEY`.

- O que foi feito:
  - foi criado o servidor MySQL na Azure com endpoint `pdv.mysql.database.azure.com`;
  - foi criada/recriada a base `paoecafe83` na Azure para testes de migracao;
  - foi baixado o certificado publico `DigiCertGlobalRootG2.crt.pem` para permitir conexao TLS via PDO/Laravel;
  - como `mysqldump.exe` do XAMPP/MariaDB falhou no handshake com a KingHost, foi criado o script `scripts/export-mysql-dump.php` para gerar dump via PDO;
  - o dump gerado inclui `SET SESSION sql_generate_invisible_primary_key=OFF;` para evitar chaves primarias invisiveis automáticas no MySQL da Azure;
  - o dump atualizado foi importado na Azure e validado contra a KingHost.

- Validacoes realizadas:
  - Azure e KingHost ficaram com as mesmas contagens principais apos a importacao:
    - `tb1_produto`: `1921`
    - `tb3_vendas`: `56736`
    - `tb4_vendas_pg`: `26680`
    - `users`: `42`
    - total de tabelas: `35`
  - `php artisan migrate:status` funcionou apontando temporariamente para a Azure com TLS;
  - permanece pendente a migration `2026_04_19_120000_expand_tipo_pagamento_length_on_tb4_vendas_pg`, que ja aparecia como `Pending` no teste.

- Observacoes para sincronizar em `pec1`:
  - nao trocar automaticamente a conexao de `pec1`, porque dominio, banco e telas especificas sao diferentes;
  - copiar o script `scripts/export-mysql-dump.php` apenas se tambem for necessario exportar/importar banco no outro projeto;
  - copiar `storage/certs/DigiCertGlobalRootG2.crt.pem` caso `pec1` tambem passe a conectar em MySQL da Azure com TLS obrigatorio;
  - quando a virada definitiva for feita, configurar no ambiente da Azure:
    - `DB_HOST=pdv.mysql.database.azure.com`
    - `DB_PORT=3306`
    - `DB_DATABASE=paoecafe83`
    - `DB_USERNAME=pdv`
    - `MYSQL_ATTR_SSL_CA` apontando para o certificado publico no servidor;
  - a senha nao foi registrada neste arquivo por seguranca.

## 23/04/26 - Relatorio Notas Fiscais Emitidas

- Arquivos criados/alterados em `C:\xampp\htdocs\pec-rodrigo`:
  - `routes/web.php`
  - `app/Http/Controllers/SalesReportController.php`
  - `resources/js/Pages/Reports/Index.jsx`
  - `resources/js/Pages/Reports/FiscalInvoices.jsx`
  - `resources/js/Layouts/AuthenticatedLayout.jsx`
  - `resources/js/Pages/Settings/ProfileAccess.jsx`
  - `resources/js/Pages/Settings/MenuOrder.jsx`
  - `SYNC.md`

- Causa identificada:
  - a area `reports` ainda nao tinha um item/tela para listar as notas da tabela `tb27_notas_fiscais`;
  - os status fiscais ja existem no sistema, entao nao foi necessario criar tabela nova nem alterar dados no banco.

- O que foi feito:
  - criada a rota `reports.notas-fiscais-emitidas` em `/reports/notas-fiscais-emitidas`;
  - criado o metodo `notasFiscaisEmitidas` em `SalesReportController`;
  - o relatorio respeita o mesmo escopo de lojas dos outros relatorios via `resolveReportUnit` e `reportUnitIds`;
  - filtros adicionados: Loja, Situacao e Data inicial/final;
  - as datas da tela usam entrada/visualizacao no padrao `DD/MM/AA`;
  - a Situacao usa o mapeamento:
    - `Assinada`: status interno `xml_assinado`;
    - `Erro`: status internos `erro_validacao` e `erro_transmissao`;
    - `Emitida`: status interno `emitida`;
  - adicionada a tela `resources/js/Pages/Reports/FiscalInvoices.jsx` com listagem de venda, loja, situacao, modelo, serie/numero, datas, valor, pagamento, caixa, chave, protocolo e mensagem;
  - adicionado o item "Notas Fiscais Emitidas" no indice de relatorios, menu lateral/dropdown, permissoes de menu e ordenacao.

- Observacoes para sincronizar em `C:\xampp\htdocs\pec1`:
  - replicar os mesmos arquivos, exceto se houver divergencias locais nos arquivos de login/pagina inicial que nao se aplicam aqui;
  - nao executar migration nova, pois o relatorio usa a tabela existente `tb27_notas_fiscais`;
  - apos sincronizar, executar `php artisan route:clear` se a rota nao aparecer e `cmd /c npm run build` para regenerar os assets.

## 23/04/26 - Performance da tela de venda

- Arquivos criados/alterados em `C:\xampp\htdocs\pec-rodrigo`:
  - `database/migrations/2026_04_23_090000_add_dashboard_sale_performance_indexes.php`
  - `app/Http/Controllers/ProductController.php`
  - `app/Http/Controllers/SaleController.php`
  - `SYNC.md`

- Causa identificada:
  - a busca de produtos na tela de venda usava `LIKE '%termo%'` em `tb1_produto.tb1_nome`, mas a tabela so tinha indice na chave primaria e no codigo de barras;
  - a busca por codigo de barras numerico longo tambem usava `LIKE`, mesmo existindo indice unico em `tb1_codbar`;
  - a insercao de vendas sem comanda carregava produtos item a item, gerando uma consulta por produto agrupado;
  - as consultas de comandas abertas, restricao de caixa e limites de vale/refeicao usavam filtros combinados em `tb3_vendas`, mas o banco tinha apenas indices simples separados.

- O que foi feito:
  - criada migration de indices de performance:
    - `tb1_produto_nome_fulltext` em `tb1_produto(tb1_nome)`;
    - `tb1_produto_fav_status_nome_idx` em `tb1_produto(tb1_favorito, tb1_status, tb1_nome)`;
    - `tb1_produto_status_nome_idx` em `tb1_produto(tb1_status, tb1_nome)`;
    - `tb3_vendas_unit_status_comanda_idx` em `tb3_vendas(id_unidade, status, id_comanda)`;
    - `tb3_vendas_unit_status_data_idx` em `tb3_vendas(id_unidade, status, data_hora)`;
    - `tb3_vendas_caixa_unit_status_data_idx` em `tb3_vendas(id_user_caixa, id_unidade, status, data_hora)`;
    - `tb3_vendas_tipo_vale_data_idx` em `tb3_vendas(tipo_pago, id_user_vale, data_hora)`;
    - `tb4_vendas_pg_created_idx` em `tb4_vendas_pg(created_at)`;
  - `ProductController::search` passou a buscar codigo de barras por igualdade em vez de `LIKE`;
  - `ProductController::search` passou a usar `FULLTEXT ... IN BOOLEAN MODE` para busca por nome com prefixo;
  - `SaleController::store` passou a carregar todos os produtos da venda sem comanda em uma consulta unica.

- Observacoes para sincronizar em `C:\xampp\htdocs\pec1`:
  - replicar os arquivos acima;
  - executar `php artisan migrate --force` para criar os indices no banco do outro sistema;
  - se a busca por nome nao retornar termos muito curtos, lembrar que o front ja evita busca automatica com menos de 3 caracteres e o FULLTEXT segue as regras do MySQL para tokens.

## 23/04/26 - Total diario de notas fiscais em Unidades

- Arquivos criados/alterados em `C:\xampp\htdocs\pec-rodrigo`:
  - `app/Http/Controllers/UnitController.php`
  - `SYNC.md`

- Causa identificada:
  - na tela `units`, o campo `tb2_nf_total` somava todas as notas fiscais com status `emitida`, sem filtro de data;
  - com isso, o valor exibido era acumulado historico da loja, e nao o total do dia corrente.

- O que foi feito:
  - o `selectSub` de `UnitController@index` agora filtra `tb27_notas_fiscais.tb27_emitida_em` entre o inicio e o fim do dia atual;
  - a soma continua usando `tb4_vendas_pg.valor_total`, mas somente para notas com `tb27_status = emitida` emitidas hoje.

- Observacoes para sincronizar em `C:\xampp\htdocs\pec1`:
  - replicar `app/Http/Controllers/UnitController.php`;
  - nao ha migration nova nesta alteracao.

## 23/04/26 - Calendario no Controle Financeiro

- Arquivos criados/alterados em `C:\xampp\htdocs\pec-rodrigo`:
  - `resources/js/Pages/Reports/ControlPanel.jsx`
  - `SYNC.md`

- Causa identificada:
  - em `reports/control`, os campos `Inicio` e `Fim` estavam como `type="text"` com mascara manual `DD/MM/AA`;
  - por serem campos de texto, o navegador nao abria o seletor/calendario nativo.

- O que foi feito:
  - os inputs de `start_date` e `end_date` foram alterados para `type="date"`;
  - a mascara manual local `normalizeDateInput` foi removida desta tela;
  - o backend ja aceita datas no formato `Y-m-d`, que e o formato enviado por inputs `date`.

- Observacoes para sincronizar em `C:\xampp\htdocs\pec1`:
  - replicar `resources/js/Pages/Reports/ControlPanel.jsx`;
  - executar `cmd /c npm run build` apos sincronizar os assets.

## 23/04/26 - Ajuste visual do relatorio Notas Fiscais Emitidas

- Arquivos criados/alterados em `C:\xampp\htdocs\pec-rodrigo`:
  - `resources/js/Pages/Reports/FiscalInvoices.jsx`
  - `SYNC.md`

- Causa identificada:
  - o relatorio `reports/notas-fiscais-emitidas` exibia muitos dados fiscais em colunas separadas, deixando a tabela larga;
  - as datas exibiam o ano, mas foi solicitado remover o ano da visualizacao;
  - a coluna Pagamento exibia texto e foi solicitado usar icones.

- O que foi feito:
  - as colunas `Chave`, `Protocolo` e `Mensagem` foram consolidadas em uma unica coluna `SEFAZ`;
  - as colunas `Modelo` e `Serie/Numero` foram consolidadas em uma unica coluna `M.S.N`;
  - as colunas `Venda` e `Loja` foram consolidadas em uma unica coluna `I.D`;
  - os filtros de data `Inicio` e `Fim` foram alterados para `type="date"` para abrir calendario nativo;
  - as datas de `Criada em` e `Emitida em` passaram a exibir `DD/MM HH:mm`, sem ano;
  - a coluna `Pagamento` passou a exibir icone com `title` e `aria-label` contendo o texto do pagamento.

- Observacoes para sincronizar em `C:\xampp\htdocs\pec1`:
  - replicar `resources/js/Pages/Reports/FiscalInvoices.jsx`;
  - executar `cmd /c npm run build` apos sincronizar os assets.

## 23/04/26 - Botao de impressao no valor do relatorio Notas Fiscais Emitidas

- Arquivos criados/alterados em `C:\xampp\htdocs\pec-rodrigo`:
  - `app/Http/Controllers/SalesReportController.php`
  - `resources/js/Pages/Reports/FiscalInvoices.jsx`
  - `SYNC.md`

- Causa identificada:
  - o campo `Valor` no relatorio `reports/notas-fiscais-emitidas` era apenas texto;
  - para imprimir o cupom fiscal era necessario abrir outro fluxo, mesmo a linha ja representando uma nota fiscal.

- O que foi feito:
  - `SalesReportController@notasFiscaisEmitidas` passou a enviar `fiscal_receipt` em cada linha, com dados da nota, venda, loja, pagamento, consumidor, itens fiscais, QR Code e URL de consulta quando houver XML fiscal;
  - a tela `FiscalInvoices.jsx` passou a importar `buildFiscalReceiptHtml`;
  - o valor da linha foi transformado em botao com icone de impressora;
  - ao clicar no valor, o sistema abre uma janela de impressao do cupom fiscal daquela nota;
  - se o navegador bloquear pop-up ou faltar payload fiscal, a tela mostra uma mensagem de erro controlada.

- Observacoes para sincronizar em `C:\xampp\htdocs\pec1`:
  - replicar `app/Http/Controllers/SalesReportController.php`;
  - replicar `resources/js/Pages/Reports/FiscalInvoices.jsx`;
  - executar `cmd /c npm run build` apos sincronizar os assets.

## 23/04/26 - Correcao do banco local do projeto `pec-rodrigo`

- Arquivos criados/alterados em `C:\xampp\htdocs\pec-rodrigo`:
  - `.env`
  - `SYNC.md`

- Causa identificada:
  - o servidor local iniciado por `php artisan serve` estava rodando a partir de `C:\xampp\htdocs\pec-rodrigo`, mas o `.env` apontava `DB_DATABASE=paoecafe8302`;
  - o banco correto para este projeto local e `paoecafe83`.

- O que foi feito:
  - alterado `DB_DATABASE` de `paoecafe8302` para `paoecafe83` no `.env`;
  - nao houve criacao de tabela, migration, update ou delete em dados do banco.

- Observacoes para sincronizar em `C:\xampp\htdocs\pec1`:
  - nao copiar automaticamente esta alteracao de `.env`, pois `pec1` possui conexao de banco propria;
  - se for necessario validar o ambiente apos sincronizar, conferir com `php artisan config:show database.connections.mysql.database`.
