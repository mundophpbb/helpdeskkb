# mundophpbb/helpdeskkb

Extensão phpBB de Base de Conhecimento integrada ao Help Desk.

## Entregue neste pacote
- Estrutura completa da extensão
- Migração com tabelas de categorias e artigos
- ACP com abas de configurações, categorias e artigos
- Frontend com índice e visualização do artigo
- Sugestões automáticas de artigos no viewtopic com base no título do tópico
- Suporte multilíngue (`pt_br` e `en`)
- Link opcional na navegação

## Rotas
- `/helpdesk/kb`
- `/helpdesk/kb/article/{article_id}`

## Observações
- O cadastro de artigos aceita BBCode no conteúdo.
- O vínculo com `forum_id` e `department_key` já foi preparado para aprofundar a integração com o Help Desk no próximo passo.
- Este pacote é um MVP funcional para servir de base ao desenvolvimento incremental.


## Modos de operação

- KB somente (standalone)
- KB + Help Desk (integrado)
