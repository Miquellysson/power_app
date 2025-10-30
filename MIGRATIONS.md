
# Migrações aplicadas (2025-09-18)
- Adicionada categoria padrão: **Anticoncepcionais** (seed em `install.php`).
- Adicionado campo `orders.track_token` (create/migrate em `install.php`) para link público de acompanhamento de pedido.
- Frete fixo definido: **$ 7.00** (checkout e total).
- Moeda padrão alterada para **USD** (símbolo `$`) — `config.php` e textos hardcoded atualizados.
- `install.php` agora cria também as tabelas `order_items` (compatível com `diag.php`) e `settings` (preferências chave/valor usadas pelo painel).
- Campo `products.square_payment_link` criado para suportar links diretos do Square por produto.
- Criada tabela `page_layouts` para armazenar rascunhos/publicações do editor visual da home.
- Criada tabela `payment_methods` para gerenciar métodos de pagamento dinâmicos via painel.

## Como aplicar no ambiente existente
1. Faça backup do banco.
2. Suba os arquivos alterados.
3. Rode `/install.php` no navegador (idempotente). Ele criará o campo `track_token` se não existir e fará o seed da categoria **Anticoncepcionais** (não duplica).
   - Esse passo também garante a criação das tabelas `order_items` e `settings` quando ausentes.
   - Inclui o campo `square_payment_link` na tabela `products` sem impactar cadastros existentes.
   - Cria/atualiza a tabela `page_layouts` com a coluna `meta` (se ausente).
   - Provisiona a tabela `payment_methods` e popula com Pix/Zelle/Venmo/PayPal/Square caso esteja vazia.

## Link de acompanhamento
- Depois do pedido, o cliente verá o link.
- Por e-mail, o link é enviado (usa `cfg()['store']['base_url']` se definido; senão, link relativo `/index.php?route=track&code=...`).
