ALTER TABLE tb4_vendas_pg
MODIFY tipo_pagamento VARCHAR(40) NOT NULL;

UPDATE tb1_produto
SET tb1_nome = UPPER(tb1_nome)
WHERE BINARY tb1_nome <> BINARY UPPER(tb1_nome);
