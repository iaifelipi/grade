# Grade - Migracao Hostinger (Etapa 1)

## 1) Inventario atual (origem)
- Projeto: `Grade` (Laravel 12.49.0)
- PHP CLI: `8.2.30`
- Composer: `2.9.4`
- Node/NPM: `22.22.0 / 10.9.4`
- Queue connection: `database`
- Session driver: `database`
- Cache store: `database`
- Storage local: `storage/`

## 2) Tamanho atual (origem local)
- `storage/`: ~`77M`
- `security/`: ~`95M`
- Projeto total: ~`366M`

## 3) Rotas criticas validadas
- Sources/Importacao: 14 rotas (`sources/*`, `vault/sources/*`, cancel/reprocess)
- Monitoramento admin: 6 rotas (`admin/monitoring/*`, incluindo ACK incidente)

## 4) Dependencias de runtime (producao)
- PHP extensoes Laravel padrao + `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `zip`
- Biblioteca de planilha: `phpoffice/phpspreadsheet` (importacao XLSX)
- Banco MySQL
- Cron ativo para scheduler
- Worker de fila (preferencialmente supervisor; fallback por cron quando indisponivel)

## 5) Riscos identificados antes do cutover
- Conexao local MySQL esta falhando no momento (senha alterada recentemente no ambiente local).
- Nao bloqueia migracao para Hostinger, mas impede validacoes locais com `artisan migrate:status`.
- Necessario validar credenciais do banco Hostinger antes do deploy final.

## 6) Escopo do pacote de deploy (gerado por script)
Inclui:
- codigo fonte da aplicacao
- `vendor/` (opcional com `--with-vendor`)
- `public/build` (assets compilados, opcional com `--with-build`)

Exclui:
- `.env`, `.env.*` sensiveis
- `node_modules/`, `tests/`, `security/`, `storage/app/private/`
- logs, cache temporario, arquivos CSV
- `.git/`

## 7) Entregaveis desta etapa
- `scripts/package-hostinger.sh` (gera ZIP limpo)
- `docs/migration/hostinger-cutover-checklist.md`
- `docs/migration/hostinger-stage1-inventory.md` (este arquivo)
- `.env.hostinger.example` (template de producao)

## 8) Proximo passo (Etapa 2)
- Provisionar Hostinger (PHP + MySQL + dominio)
- Subir pacote
- Configurar `.env` producao
- Rodar `php artisan migrate --force`
- Configurar cron/scheduler + fila
- Smoke test e cutover DNS/Cloudflare

