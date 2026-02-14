# Grade - Runbook Producao (Local + Hostinger)

Este documento descreve o minimo para rodar o Grade em duas instancias:

- Local (rede interna): com liberdade de services/daemon
- Hostinger (cloud): foco em `cron` (sem depender de supervisor)

## Variaveis de ambiente (Security Access)

Obrigatorio (seguranca do app):
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY=...`
- `APP_URL=...`

Banco:
- `DB_*` conforme ambiente

Security module:
- `SECURITY_QUEUE_WORKER_MODE=cron` (Hostinger recomendado)
- `SECURITY_QUEUE_NAME=maintenance`
- `SECURITY_RISK_WINDOW_MINUTES=15`
- `SECURITY_RISK_LOGIN_FAILED_PER_IP=20`
- `SECURITY_RISK_FORBIDDEN_PER_IP=50`
- `SECURITY_RISK_FIREWALL_EVENTS_PER_IP=30`
- `SECURITY_RETENTION_EVENTS_DAYS=90`
- `SECURITY_RETENTION_ACTIONS_DAYS=180`
- `SECURITY_RETENTION_INCIDENTS_DAYS=365`

Cloudflare (opcional):
- `CLOUDFLARE_API_TOKEN=...`
- `CLOUDFLARE_ZONE_ID=...`
- `CLOUDFLARE_INGEST_LIMIT=250`

## Cron (Hostinger)

O wrapper `scripts/grade-security-cron.sh` roda os comandos em modo sync.

Recomendado:
```cron
*/2 * * * * /bin/bash -lc '/var/www/painel1/scripts/grade-security-cron.sh run --minutes=15 --limit=250 >> /var/www/painel1/storage/logs/security/cron-security-run.log 2>&1'
20 4 * * * /bin/bash -lc '/var/www/painel1/scripts/grade-security-cron.sh prune >> /var/www/painel1/storage/logs/security/cron-security-prune.log 2>&1'
```

Se preferir separar ingest/evaluate:
```cron
*/5 * * * * /bin/bash -lc '/var/www/painel1/scripts/grade-security-cron.sh ingest --minutes=15 --limit=250 >> /var/www/painel1/storage/logs/security/cron-security-ingest.log 2>&1'
*/2 * * * * /bin/bash -lc '/var/www/painel1/scripts/grade-security-cron.sh evaluate --minutes=15 >> /var/www/painel1/storage/logs/security/cron-security-evaluate.log 2>&1'
```

## Local (rede interna)

Opcoes:
- `SECURITY_QUEUE_WORKER_MODE=process` e rodar workers (supervisor)
- `SECURITY_QUEUE_WORKER_MODE=cron` e rodar os mesmos crons acima

## Teste rapido (sem UI)

1) Gerar eventos (exemplo):
```bash
php artisan tinker --execute='use Illuminate\\Support\\Facades\\DB; $now=now(); DB::table("security_access_events")->insert(["source"=>"app","event_type"=>"login_failed","ip_address"=>"1.2.3.4","request_path"=>"/login","request_method"=>"POST","http_status"=>401,"payload_json"=>json_encode(["test"=>1]),"occurred_at"=>$now]); echo "ok\\n";'
```

2) Avaliar e criar incidentes:
```bash
php artisan security:access:evaluate --minutes=15
```

