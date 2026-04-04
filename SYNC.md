# 2026-04-04

## Master Confere no endpoint reports/cash-closure

- Arquivos alterados:
  - `app/Http/Controllers/SalesReportController.php`
  - `app/Models/CashierClosure.php`
  - `database/migrations/2026_04_04_120000_add_master_review_fields_to_cashier_closures_table.php`
  - `resources/js/Pages/Reports/CashClosure.jsx`
  - `routes/web.php`
  - `tests/Feature/CashClosureMasterReviewTest.php`
- Problema corrigido:
  - o relatorio `reports/cash-closure` mostrava apenas os valores originais fechados pelo caixa e nao permitia uma segunda conferencia do `Master`;
  - o card `Base da conferencia` ficava misturado no bloco de resumo, em vez de acompanhar os filtros no topo.
- Causa real:
  - a tabela `cashier_closures` nao tinha campos separados para a revisao do `Master`;
  - o backend so carregava `cash_amount` e `card_amount` originais;
  - o frontend nao tinha acao de edicao nem modal para a segunda conferencia.
- Comportamento novo:
  - quando o fechamento existir, o `Master (0)` passa a ver o botao `Master Confere` ao lado do status;
  - esse botao abre um modal para editar dinheiro e cartao na segunda conferencia;
  - o relatorio passa a usar os valores revisados pelo `Master` como base efetiva de conferencia, preservando os valores originais do caixa;
  - a tela mostra quem fez a conferencia do `Master` e quando ela ocorreu;
  - o card `Base da conferencia` foi movido para o topo, ao lado dos filtros, como no layout solicitado.
- Testes adicionados:
  - cobertura para atualizar a conferencia do `Master`;
  - cobertura para garantir que o relatorio usa os valores revisados quando existirem.
- Impacto na sincronizacao com `pec1`:
  - criar os campos de segunda conferencia na tabela `cashier_closures`;
  - replicar a nova rota de atualizacao da conferencia;
  - replicar a logica do `Master Confere` no `SalesReportController` e na tela `CashClosure.jsx`.

## Regra diaria para Refeicao no Dashboard

- Arquivos alterados:
  - `app/Http/Controllers/SaleController.php`
  - `app/Http/Controllers/UserController.php`
  - `resources/js/Pages/Dashboard.jsx`
  - `tests/Feature/SaleRefeicaoRulesTest.php`
- Problema corrigido:
  - o fluxo de lancamentos no `Dashboard` aceitava pagamento em `Refeicao` apenas com base no saldo mensal, sem limite diario e exibindo o saldo do colaborador o tempo todo.
- Causa real:
  - o `SaleController` validava somente o saldo disponivel no periodo mensal;
  - o `users.search` devolvia apenas saldo/uso mensal;
  - a tela do `Dashboard` sempre mostrava o saldo no modo `Refeicao`, mesmo quando a compra era permitida.
- Comportamento novo:
  - compras em `Refeicao` agora respeitam limite diario de `R$ 12,00` de segunda a sabado e `R$ 24,00` no domingo;
  - se a compra ultrapassar o limite diario, a conclusao e bloqueada com mensagem personalizada;
  - se o saldo de refeicao for insuficiente, a mensagem passa a mostrar o saldo disponivel do colaborador;
  - na lista de usuarios do modal, o saldo so aparece quando ele for insuficiente para a compra atual;
  - quando o bloqueio for pelo limite diario, a lista mostra apenas o valor ainda disponivel no dia.
- Testes adicionados:
  - bloqueio de venda em `Refeicao` acima do limite diario em dia util;
  - validacao de venda permitida no domingo com limite maior;
  - cobertura da busca de usuarios com campos de uso diario de refeicao.
- Impacto na sincronizacao com `pec1`:
  - replicar a validacao do limite diario no `SaleController`;
  - replicar os novos campos de uso diario em `UserController::search`;
  - replicar o ajuste visual e a validacao previa no `resources/js/Pages/Dashboard.jsx`.

## Busca por comanda no endpoint reports/hoje

- Arquivos alterados:
  - `app/Http/Controllers/SalesReportController.php`
  - `resources/js/Pages/Reports/Hoje.jsx`
  - `tests/Feature/SalesReportHojeTest.php`
- Problema corrigido:
  - a tela `reports/hoje` ja filtrava por cupom, valor e horario, mas ainda nao permitia buscar diretamente pelo numero da comanda.
- Causa real:
  - o backend nao aceitava nenhum parametro `comanda` na consulta;
  - o frontend nao tinha campo para enviar esse filtro.
- Comportamento novo:
  - a busca de `reports/hoje` agora tambem aceita o numero da comanda;
  - o filtro funciona em conjunto com os demais criterios, mantendo sempre a base fixa no dia atual da loja e o limite de 10 registros.
- Teste adicionado:
  - cobertura para garantir o filtro por numero da comanda.
- Impacto na sincronizacao com `pec1`:
  - replicar o filtro `comanda` no metodo `hoje()` do `SalesReportController`;
  - replicar o novo campo no formulario de `resources/js/Pages/Reports/Hoje.jsx`;
  - copiar a cobertura de teste correspondente, se o `pec1` estiver com a mesma base de testes.

## Busca limitada no endpoint reports/hoje

- Arquivos alterados:
  - `app/Http/Controllers/SalesReportController.php`
  - `resources/js/Pages/Reports/Hoje.jsx`
  - `tests/Feature/SalesReportHojeTest.php`
- Problema corrigido:
  - o endpoint `reports/hoje` listava todos os cupons do dia da loja atual de uma vez, sem busca dedicada e sem limite por consulta.
- Causa real:
  - o metodo `hoje()` carregava todos os pagamentos do dia com `get()` e sem filtros especificos;
  - a tela `Hoje.jsx` apenas renderizava a lista recebida, sem formulario para pesquisar por cupom, valor ou horario.
- Comportamento novo:
  - a tela `reports/hoje` agora exibe uma busca com filtros por numero do cupom, valor e hora;
  - a base da consulta continua sempre fixa nos cupons do dia atual da loja ativa;
  - ao informar `hora:minuto`, o backend retorna os cupons entre 10 minutos antes e 10 minutos depois do horario informado;
  - cada busca retorna no maximo 10 registros, ordenados do mais recente para o mais antigo;
  - a tela ganhou botao para limpar os filtros e mensagem explicando o limite da pesquisa.
- Teste adicionado:
  - cobertura para garantir o limite de 10 resultados no dia/unidade atual;
  - cobertura para filtro por valor + janela de horario;
  - cobertura para filtro por numero do cupom.
- Impacto na sincronizacao com `pec1`:
  - replicar no metodo `hoje()` do `SalesReportController` os filtros `cupom`, `valor` e `hora`, mantendo o dia atual como filtro fixo;
  - limitar o retorno da consulta a 10 registros;
  - replicar o formulario de busca em `resources/js/Pages/Reports/Hoje.jsx`;
  - copiar tambem o teste `tests/Feature/SalesReportHojeTest.php` se o projeto `pec1` ja estiver com a estrutura de testes alinhada.

## Botao para listar produtos disponiveis para VR Credito

- Arquivos alterados:
  - `app/Http/Controllers/ProductController.php`
  - `resources/js/Pages/Products/ProductIndex.jsx`
- Problema corrigido:
  - a listagem do endpoint `products` nao tinha um atalho para mostrar apenas os itens marcados como disponiveis para `VR Credito`.
- Causa real:
  - o campo `tb1_vr_credit` existia no cadastro e na edicao do produto, mas o metodo `index()` do `ProductController` nao aceitava nenhum filtro para esse campo;
  - no frontend, a tela `ProductIndex` tinha apenas busca e botao de cadastro, sem acao dedicada para filtrar os produtos com `VR Credito`.
- Comportamento novo:
  - foi adicionado um botao `VR Credito` no topo da listagem de produtos;
  - o botao fica no lado oposto ao botao de cadastro;
  - ao clicar, a tela passa a mostrar apenas os produtos com `tb1_vr_credit = 1`;
  - o filtro continua convivendo com busca e ordenacao.
- Impacto na sincronizacao com `pec1`:
  - replicar o filtro `vr_credit` no metodo `index()` do `ProductController`;
  - replicar o botao de filtro no topo da `ProductIndex.jsx`, mantendo-o no lado oposto ao botao de cadastro;
  - nao alterar `resources/js/Pages/Welcome.jsx` nem `resources/js/Pages/Auth/Login.jsx`, porque fazem parte das diferencas conhecidas entre os projetos.

## Correcao da permissao do reports/hoje para manter acesso do Caixa

- Arquivos alterados:
  - `app/Http/Controllers/SalesReportController.php`
  - `resources/js/Layouts/AuthenticatedLayout.jsx`
- Problema corrigido:
  - apos a ampliacao anterior do endpoint `reports/hoje`, o perfil `Caixa` deixou de ver a opcao no menu e de acessar a tela.
- Causa real:
  - a regra anterior foi substituida em vez de ampliada;
  - o backend passou a aceitar apenas `Master (0)`, `Gerente (1)` e `Sub-Gerente (2)`;
  - o frontend tambem ficou limitado a esses tres perfis, removendo o comportamento antigo do `Caixa (3)`.
- Comportamento novo:
  - o endpoint `reports/hoje` agora fica acessivel para `Master (0)`, `Gerente (1)`, `Sub-Gerente (2)` e `Caixa (3)`;
  - o item `Hoje` volta a aparecer no menu para o perfil `Caixa`, alem dos perfis de gestao.
- Impacto na sincronizacao com `pec1`:
  - ajustar a validacao do `SalesReportController` para aceitar `[0, 1, 2, 3]`;
  - ajustar a condicao de exibicao do item `reports_hoje` no `AuthenticatedLayout.jsx` para aceitar `[0, 1, 2, 3]`;
  - esta correcao complementa a alteracao anterior e evita regressao para o perfil `Caixa`.

## Ajuste do scroll do chat no On-Line para preservar o historico

- Arquivos alterados:
  - `resources/js/Pages/Online/Index.jsx`
- Problema corrigido:
  - depois da mudanca anterior, a conversa do endpoint `on-line` descia automaticamente em toda atualizacao, atrapalhando quem estivesse lendo mensagens antigas.
- Causa real:
  - o auto-scroll ficou configurado para acontecer a cada atualizacao da lista de mensagens;
  - isso fazia o refresh do chat puxar a barra para baixo mesmo quando o usuario estava consultando o historico.
- Comportamento novo:
  - o scroll automatico acontece apenas uma vez ao abrir/trocar de conversa;
  - depois disso, novas atualizacoes nao forcao a barra para o final;
  - se o usuario subir para ler o historico, a posicao manual passa a ser respeitada.
- Impacto na sincronizacao com `pec1`:
  - replicar o ajuste da funcao `loadSnapshot()` e do `applySnapshot()` em `resources/js/Pages/Online/Index.jsx`;
  - manter o scroll automatico apenas na abertura efetiva da conversa;
  - nao alterar `resources/js/Pages/Welcome.jsx` nem `resources/js/Pages/Auth/Login.jsx`, porque fazem parte das diferencas conhecidas entre os projetos.

## Conversa do On-Line sempre posicionada no final

- Arquivos alterados:
  - `resources/js/Pages/Online/Index.jsx`
- Problema corrigido:
  - a barra de rolagem da conversa no endpoint `on-line` nem sempre permanecia no fim da conversa.
- Causa real:
  - a logica de auto-scroll era condicional;
  - o sistema so rolava automaticamente em algumas situacoes, como troca de usuario ou chegada de mensagem quando a barra ja estava perto do final;
  - se a atualizacao acontecesse fora dessas condicoes, a conversa podia permanecer acima do rodape.
- Comportamento novo:
  - toda atualizacao da conversa agora reposiciona a barra no final;
  - ao trocar de usuario, o scroll vai direto para baixo;
  - ao chegar nova mensagem, a conversa continua sempre ancorada no fim.
- Impacto na sincronizacao com `pec1`:
  - replicar a simplificacao do `useEffect` de scroll em `resources/js/Pages/Online/Index.jsx`;
  - nao alterar `resources/js/Pages/Welcome.jsx` nem `resources/js/Pages/Auth/Login.jsx`, porque fazem parte das diferencas conhecidas entre os projetos.

## Padronizacao da impressao de cupons e exibicao obrigatoria da comanda

- Arquivos alterados:
  - `app/Http/Controllers/SaleController.php`
  - `app/Http/Controllers/SalesReportController.php`
  - `resources/js/Pages/Dashboard.jsx`
  - `resources/js/Pages/Reports/Faturar.jsx`
  - `resources/js/Pages/Reports/Hoje.jsx`
  - `resources/js/Pages/Reports/SalesToday.jsx`
  - `resources/js/Pages/Reports/Vale.jsx`
  - `resources/js/Utils/receipt.js`
- Problema corrigido:
  - a impressao do cupom nao era padronizada entre os pontos de venda e reimpressao;
  - em alguns fluxos o numero do cupom aparecia, em outros nao;
  - quando a venda vinha de comanda, o numero dela nem sempre era mostrado no cupom.
- Causa real:
  - existiam varios layouts diferentes de impressao, copiados em arquivos separados do frontend;
  - o `Dashboard` usava um HTML proprio de impressao e nao mostrava a identificacao do cupom;
  - no fluxo de venda normal, o backend nao enviava o `id` do cupom no topo do objeto retornado;
  - no fluxo de comanda, a comanda existia nos dados internos, mas nao era promovida de forma padronizada para o objeto usado na impressao.
- Comportamento novo:
  - todos os cupons de venda e reimpressao agora usam o mesmo gerador em `resources/js/Utils/receipt.js`;
  - o cupom passa a imprimir o numero do cupom de forma padronizada sempre que houver identificador;
  - quando a venda for de comanda, o numero da comanda passa a ser impresso no cabecalho do cupom;
  - o modal de cupom do `Dashboard` tambem passou a exibir o numero do cupom e da comanda, quando existirem.
- Backend:
  - `SaleController` agora retorna `sale.id` e `sale.comanda` no payload da venda finalizada;
  - os itens do cupom de venda por comanda passam a carregar `comanda` no retorno;
  - `SalesReportController` passou a incluir `comanda` explicitamente no objeto `receipt` dos relatorios de cupom.
- Impacto na sincronizacao com `pec1`:
  - copiar o novo utilitario `resources/js/Utils/receipt.js`;
  - substituir os HTMLs locais de impressao nos arquivos de relatorio e no `Dashboard` para usar o utilitario compartilhado;
  - replicar no backend a inclusao de `sale.id`, `sale.comanda` e `receipt.comanda`;
  - nao alterar `resources/js/Pages/Welcome.jsx` nem `resources/js/Pages/Auth/Login.jsx`, porque fazem parte das diferencas conhecidas entre os projetos.

## Liberacao do endpoint reports/hoje para perfis de gestao

- Arquivos alterados:
  - `app/Http/Controllers/SalesReportController.php`
  - `resources/js/Layouts/AuthenticatedLayout.jsx`
- Problema corrigido:
  - o endpoint `reports/hoje` nao aparecia no menu para `Master`, `Gerente` e `Sub-Gerente`, e o backend tambem bloqueava o acesso direto para esses perfis.
- Causa real:
  - a permissao dessa tela estava implementada como exclusiva de `Caixa`;
  - no frontend, o item `reports_hoje` ficava visivel apenas quando `isCashier` era verdadeiro;
  - no backend, o metodo `hoje()` chamava `ensureCashier($request)`, retornando `403` para perfis `0`, `1` e `2`.
- Comportamento novo:
  - o endpoint `reports/hoje` agora pode ser acessado por `Master (0)`, `Gerente (1)` e `Sub-Gerente (2)`;
  - o item `Hoje` passa a aparecer no menu para esses tres perfis, respeitando a permissao `reports_hoje` ja existente.
- Impacto na sincronizacao com `pec1`:
  - replicar a mudanca no metodo `hoje()` do `SalesReportController`, substituindo a regra de `Caixa` por uma regra especifica para `Master`, `Gerente` e `Sub-Gerente`;
  - replicar a condicao de exibicao do item `reports_hoje` no `AuthenticatedLayout.jsx`;
  - nao alterar `resources/js/Pages/Welcome.jsx` nem `resources/js/Pages/Auth/Login.jsx`, porque fazem parte das diferencas conhecidas entre os projetos.

## Rolagem propria na conversa do On-Line

- Arquivos alterados:
  - `resources/js/Pages/Online/Index.jsx`
- Problema corrigido:
  - a coluna da conversa no endpoint `on-line` crescia junto com as mensagens e nao mantinha uma barra de rolagem interna no mesmo padrao da lista de usuarios.
- Causa real:
  - o container principal da conversa usava `min-h-[68vh]`, que define apenas altura minima;
  - com isso, a coluna podia aumentar conforme o volume de mensagens, em vez de ficar limitada a uma altura fixa para que o `overflow-y-auto` da lista de mensagens assumisse a rolagem.
- Comportamento novo:
  - a conversa agora usa altura fixa de `68vh` com `min-h-0`;
  - a barra de rolagem fica na area das mensagens, como acontece na lista de usuarios on-line;
  - o bloco de digitacao continua fixo na parte inferior da conversa.
- Impacto na sincronizacao com `pec1`:
  - replicar a mesma troca de classe CSS no arquivo `resources/js/Pages/Online/Index.jsx`;
  - nao alterar `resources/js/Pages/Welcome.jsx` nem `resources/js/Pages/Auth/Login.jsx`, porque fazem parte das diferencas conhecidas entre os projetos.

## Correcao do erro 500 ao editar produto de balanca

- Arquivo alterado: `app/Http/Controllers/ProductController.php`
- Problema corrigido: ao salvar a edicao de um produto com `tb1_tipo = 1`, o metodo `prepareProductData()` sobrescrevia `tb1_codbar` com string vazia.
- Causa real do 500: a tabela `tb1_produto` tem `tb1_codbar` unico, e ja existia pelo menos um outro registro com `tb1_codbar = ''`. Ao editar o produto `30`, o sistema tentava gravar `''` e o banco rejeitava a operacao.
- Comportamento novo:
  - produto de balanca nao perde mais o `tb1_codbar` atual durante a edicao;
  - se o produto de balanca ainda nao tiver `tb1_codbar`, o sistema gera automaticamente `SEM-{tb1_id}`;
  - foi adicionada validacao preventiva para evitar colisao desse codigo interno e retornar erro de validacao em vez de `500`.
- Impacto na sincronizacao com `pec1`:
  - replicar a mesma logica no `ProductController`;
  - nao alterar `resources/js/Pages/Welcome.jsx` nem `resources/js/Pages/Auth/Login.jsx`, porque fazem parte das diferencas conhecidas entre os projetos;
  - nao e necessario alterar banco ou criar migration para esta correcao.

## Teste de regressao

- Arquivo criado: `tests/Feature/ProductManagementTest.php`
- Cobertura adicionada:
  - cadastro de produto de balanca agora gera `tb1_codbar` no formato `SEM-{tb1_id}`;
  - edicao de produto de balanca continua funcionando mesmo se ja existir outro produto com `tb1_codbar` vazio no banco.

## Permissao para alterar valores de produto

- Arquivos alterados:
  - `app/Http/Controllers/ProductController.php`
  - `resources/js/Pages/Products/ProductEdit.jsx`
  - `tests/Feature/ProductManagementTest.php`
- Regra nova:
  - apenas `Master (0)`, `Gerente (1)` e `Sub-gerente (2)` podem alterar `tb1_vlr_custo` e `tb1_vlr_venda` na edicao de produto;
  - outros perfis ainda podem editar os demais campos do produto, desde que nao tentem mudar os valores.
- Backend:
  - a validacao agora compara os valores enviados com os valores atuais do produto;
  - se um perfil sem permissao tentar alterar custo ou venda, o sistema retorna erro de validacao e nao deixa salvar.
- Frontend:
  - na tela `ProductEdit`, os campos de custo e venda ficam desabilitados para perfis sem permissao;
  - foi exibido um aviso explicando quais perfis podem alterar os valores.
- Observacao importante para sincronizar no `pec1`:
  - manter o bloqueio no backend mesmo com o campo desabilitado no frontend, porque o frontend sozinho nao protege contra envio manual da requisicao;
  - esta alteracao usa a `funcao` ativa da sessao, entao continua respeitando o comportamento de troca de funcao ja existente no sistema.
