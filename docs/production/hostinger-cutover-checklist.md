# Grade - Pacote de Cutover (Hostinger)

Objetivo: minimizar surpresa no momento de trocar/ativar o ambiente Hostinger, garantindo que o deploy subiu com DB, assets, rotas e módulos admin funcionando.

Este pacote tem:
- Checklist final (antes/durante/depois)
- Comando unico de validacao pos-deploy

## 0) Pre-requisitos

- DNS/SSL do dominio ativo (Cloudflare opcional)
- Acesso SSH no Hostinger funcionando
- Repo no Hostinger atualizado (git clone/pull) ou upload feito
- `.env` configurado em producao:
  - `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY`, `APP_URL`
  - `DB_*` corretos
  - `SESSION_DRIVER=database`, `CACHE_STORE=database` (conforme padrao do app)
  - `MONITORING_QUEUE_WORKER_MODE=cron` (Hostinger)
  - `SECURITY_QUEUE_WORKER_MODE=cron` (Hostinger)
  - Cloudflare (opcional): `CLOUDFLARE_API_TOKEN`, `CLOUDFLARE_ZONE_ID`

## 1) Checklist - Antes do Cutover (Hostinger)

No Hostinger (pasta do app, ex `~/grade-app`):

1. Checar PHP + extensoes basicas:
```bash
php -v
php -m | rg -n 'pdo_mysql|mbstring|openssl|curl|json' || true
```

2. Banco acessivel:
```bash
php artisan tinker --execute='DB::connection()->getPdo(); echo "DB_OK name=".DB::connection()->getDatabaseName().PHP_EOL;'
```

3. Migrations em dia:
```bash
php artisan migrate --force
```

4. Assets (Vite) presentes:
```bash
test -f public/build/manifest.json && echo "manifest ok"
```

5. Permissoes de escrita:
```bash
test -w storage && test -w storage/framework && test -w storage/logs && test -w bootstrap/cache && echo "writable ok"
```

6. Rotas criticas (cadastro desativado):
```bash
php artisan route:list --no-ansi | rg '(^|\\s)register(\\s|$)' && echo "ERROR register still on" || echo "register off ok"
```

## 2) Cutover (quando for virar para producao)

1. Rodar deploy (se estiver usando `deploy.sh`):
```bash
./deploy.sh
```

2. Rodar validacao unica:
```bash
chmod +x scripts/grade-postdeploy-validate.sh
./scripts/grade-postdeploy-validate.sh --url https://grade.com.br --apply-migrations
```

Se voce nao quiser aplicar migrations automaticamente, rode sem `--apply-migrations` (vai falhar se tiver pendente):
```bash
./scripts/grade-postdeploy-validate.sh --url https://grade.com.br
```

## 3) Checklist - Cron (Hostinger)

No hPanel (Cron Jobs), garantir:

- `* * * * * /usr/bin/php /home/SEU_USER/grade-app/artisan schedule:run >> /home/SEU_USER/grade-app/storage/logs/cron.log 2>&1`
- (se usar filas via cron) os `queue:work ... --stop-when-empty` como ja configurado
- Security Access (recomendado):
  - `*/2 * * * * /bin/bash -lc '/home/SEU_USER/grade-app/scripts/grade-security-cron.sh run --minutes=15 --limit=250 >> /home/SEU_USER/grade-app/storage/logs/security/cron-security-run.log 2>&1'`
  - `20 4 * * * /bin/bash -lc '/home/SEU_USER/grade-app/scripts/grade-security-cron.sh prune >> /home/SEU_USER/grade-app/storage/logs/security/cron-security-prune.log 2>&1'`

## 4) Checklist - Depois do Cutover

1. Homepage responde:
```bash
curl -fsSI https://grade.com.br | head -n 5
curl -fsSI https://grade.com.br/up | head -n 5
```

2. Logs sem erros novos:
```bash
tail -n 200 storage/logs/laravel.log
```

3. Admin > Monitoramento abre sem 500.
4. Admin > Seguranca abre e atualiza KPIs.
5. Upload: botao "Registrar e Importar" faz POST e cria jobs (mesmo que fila esteja vazia).

## 5) Rollback rapido (se der ruim)

- Voltar para o commit anterior (se deploy via git):
```bash
git log --oneline -n 10
git checkout <COMMIT_ANTERIOR>
./deploy.sh
./scripts/grade-postdeploy-validate.sh --url https://grade.com.br
```

- Se rollback for via backup/zip: restaurar pasta do app + `.env` + rodar `./deploy.sh`.

