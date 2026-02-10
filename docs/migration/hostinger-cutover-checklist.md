# Pixip - Checklist de Cutover Hostinger

## Pre-deploy
- [ ] Repositorio atualizado e tag criado (`pre-migracao-hostinger`)
- [ ] Backup DB exportado (dump SQL)
- [ ] Backup de arquivos (`storage/app`, uploads)
- [ ] Confirmar versoes PHP/MySQL no Hostinger
- [ ] Confirmar extensoes PHP necessarias

## Deploy tecnico
- [ ] Upload pacote gerado por `scripts/package-hostinger.sh`
- [ ] Extrair em diretório da app
- [ ] Ajustar permissao de `storage/` e `bootstrap/cache`
- [ ] Configurar `.env` de producao
- [ ] `composer install --no-dev --optimize-autoloader` (se vendor nao enviado)
- [ ] `php artisan key:generate` (se app nova)
- [ ] `php artisan migrate --force`
- [ ] `php artisan config:cache`
- [ ] `php artisan route:cache`
- [ ] `php artisan view:cache`

## Scheduler e filas
- [ ] Cron: `* * * * * php /caminho/artisan schedule:run >> /dev/null 2>&1`
- [ ] Worker em execução (supervisor/cron fallback)
- [ ] Validar jobs `imports`, `normalize`, `extras`

## Validacao funcional
- [ ] Login admin
- [ ] Importacao de CSV/XLSX
- [ ] Evolucao de status (`queued -> uploading/importing -> normalizing -> done`)
- [ ] Reprocessar e excluir arquivo
- [ ] Automacao (run/cancel/eventos)
- [ ] Monitoramento admin carregando sem erro

## Cutover
- [ ] Cloudflare SSL: `Full (strict)`
- [ ] DNS apontando para Hostinger
- [ ] WAF/rate limit basico habilitado
- [ ] Verificar acesso origin direto (bloqueado quando possivel)

## Pos-cutover
- [ ] Monitorar 24h erros 5xx/queue backlog
- [ ] Confirmar backups automaticos
- [ ] Registrar data/hora de virada + responsavel

