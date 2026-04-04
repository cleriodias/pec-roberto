# 2026-04-04

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
