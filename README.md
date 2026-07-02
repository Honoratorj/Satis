# Pesquisa de Satisfação (Satis)

Aplicação web em PHP para coletar e consultar pesquisas de satisfação dos
chamados de TI. É integrada ao banco do GLPI (`CMMJ_TI`): a partir do
`ticket_id` de um chamado, identifica automaticamente o **técnico** e o
**requerente** e registra a avaliação do usuário.

## Stack

- **PHP 8.4** (extensão `mysqli`)
- **MySQL/MariaDB** — banco `CMMJ_TI` em `10.0.0.5`
- **Nginx + php-fpm** — servido na porta **8096** (`/etc/nginx/sites-enabled/pesquisa`, root `/var/www/html/Satis`)

## Configuração

As credenciais e helpers ficam em `config.php`, que **não é versionado**
(contém a senha do banco). Para configurar em um novo ambiente:

```bash
cp config.php.example config.php
# edite config.php e preencha DB_PASS com a senha real
```

`config.php` define:

- `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME` — conexão com o banco
- `COMPANY_LOGO` — caminho do logo
- `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)` — modo de exceção do mysqli (tratado via `try/catch`)
- `db()` — retorna uma conexão `mysqli` (usado por `export.php`)
- `e()` — escapa valores para saída HTML

O usuário do banco precisa ter grant para conectar a partir do host do
servidor web (ex.: `pesquisa_user@10.0.0.7`).

## Estrutura

| Arquivo | Função |
|---|---|
| `index.php` | Formulário da pesquisa. Recebe `ticket_id`, `ticket_name`, `ticket_createdate`, `ticket_solvedate` via query string e busca técnico/requerente no GLPI. |
| `salvar_pesquisa.php` | Recebe o POST do formulário, valida e grava em `pesquisa_satisfacao`. Redireciona para `sucesso.php`. Se o chamado já foi avaliado, exibe uma página amigável (ver _Resposta única_). |
| `sucesso.php` | Página de confirmação de envio (com animação e confete). |
| `export.php` | Relatório/consulta das pesquisas com filtros (técnico, requerente, período, nota, etc.). |
| `identificar_tecnico.php` | Script de manutenção: preenche o técnico dos registros com `tecnico IS NULL`, consultando o GLPI. |
| `identificar_tecnico.sh` | Wrapper para rodar o script acima via cron/CLI. |
| `config.php` | Credenciais e funções auxiliares (não versionado). |
| `config.php.example` | Template de configuração. |
| `ALTER_TABLE.sql` | Migração das colunas/índices da tabela `pesquisa_satisfacao`. |
| `assets/` | Recursos estáticos (logo). |
| `fotos_tecnicos/` | Fotos dos técnicos, nomeadas por `users_id` (ex.: `10.jpeg`). |

## Fluxo de uso

1. O chamado é resolvido no GLPI e o usuário recebe o link para a pesquisa,
   contendo o `ticket_id` na URL.
2. `index.php` renderiza o formulário já preenchido com técnico/requerente.
3. O usuário avalia e envia; `salvar_pesquisa.php` grava a resposta.
4. As respostas podem ser consultadas em `export.php`.

## Resposta única

A coluna `ticket_id` da tabela `pesquisa_satisfacao` tem um índice **UNIQUE**,
portanto **cada chamado só pode ser avaliado uma vez**. Se o usuário reenviar a
pesquisa de um chamado já respondido, o `INSERT` falha com o erro MySQL `1062`
(_Duplicate entry_).

`salvar_pesquisa.php` trata esse caso especificamente: em vez da mensagem
genérica de erro, exibe uma página amigável **"Este chamado já foi avaliado"**
(HTTP `409`). Qualquer outra falha de banco continua caindo na mensagem de erro
padrão, e o detalhe técnico é sempre registrado via `error_log`.

## Interface / animações

`index.php` e `sucesso.php` usam CSS + JavaScript (sem dependências externas)
para uma experiência mais dinâmica:

- Fundo animado com rede de partículas (canvas) e _aurora_ de gradientes.
- Revelação escalonada dos blocos e leve _tilt_ 3D no card ao mover o mouse.
- Avatar do técnico com anel giratório; barra de progresso do preenchimento.
- Emojis de avaliação reativos, com banner de feedback dinâmico por nota.
- Confete ao dar nota alta e na página de sucesso; botão com brilho e _ripple_.

Todas as animações respeitam `prefers-reduced-motion` (usuários com essa
preferência veem a versão estática).

## Banco de dados

A tabela principal é `pesquisa_satisfacao`. Para criar/atualizar as colunas
e índices, aplique:

```bash
mysql -h 10.0.0.5 -u root -p < ALTER_TABLE.sql
```

## Tratamento de erros

Todos os acessos ao banco usam `try/catch (mysqli_sql_exception)`:

- `index.php` — se o banco falhar, a página **ainda carrega** (técnico/requerente em branco) e o erro é registrado via `error_log`.
- `salvar_pesquisa.php` — falha de banco resulta em mensagem amigável ao usuário; o erro técnico vai para o `error_log`.
- `identificar_tecnico.php` — falha de conexão encerra o script com mensagem clara.

Isso evita que falhas de conexão/consulta virem **HTTP 500**.
