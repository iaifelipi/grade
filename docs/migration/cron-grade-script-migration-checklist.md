# Grade - Checklist de Migracao de Scripts Cron

## Objetivo
Migrar cronjobs de nomes legados `pixip-*` para `grade-*` sem interrupcao.

## 1) Auditoria (somente leitura)
```bash
crontab -l 2>/dev/null | sed -n '1,200p'
crontab -l 2>/dev/null | grep -E 'scripts/(pixip|grade)-(security|guest)' || true
```

## 2) Verificar scripts no deploy atual
```bash
cd /var/www/painel1
ls -lah scripts/grade-*.sh scripts/pixip-*.sh
```

## 3) Migracao segura (troca de referencias no crontab)
```bash
TMP_CRON="$(mktemp)"
crontab -l 2>/dev/null > "$TMP_CRON" || true
sed -i 's#/scripts/pixip-security-copy.sh#/scripts/grade-security-copy.sh#g' "$TMP_CRON"
sed -i 's#/scripts/pixip-security-sync-missing.sh#/scripts/grade-security-sync-missing.sh#g' "$TMP_CRON"
sed -i 's#/scripts/pixip-guest-retention.sh#/scripts/grade-guest-retention.sh#g' "$TMP_CRON"
sed -i 's#/scripts/pixip-security-lock.sh#/scripts/grade-security-lock.sh#g' "$TMP_CRON"
sed -i 's#/scripts/pixip-security-unlock.sh#/scripts/grade-security-unlock.sh#g' "$TMP_CRON"
crontab "$TMP_CRON"
rm -f "$TMP_CRON"
```

## 4) Validacao
```bash
crontab -l 2>/dev/null | grep -E 'scripts/(pixip|grade)-(security|guest)' || true
```

Resultado esperado:
- cron deve apontar para `scripts/grade-*.sh`
- `scripts/pixip-*.sh` continuam como wrappers de compatibilidade

## 5) Rollback rapido
Caso necessario, reverta apenas os caminhos no crontab:
```bash
TMP_CRON="$(mktemp)"
crontab -l 2>/dev/null > "$TMP_CRON" || true
sed -i 's#/scripts/grade-security-copy.sh#/scripts/pixip-security-copy.sh#g' "$TMP_CRON"
sed -i 's#/scripts/grade-security-sync-missing.sh#/scripts/pixip-security-sync-missing.sh#g' "$TMP_CRON"
sed -i 's#/scripts/grade-guest-retention.sh#/scripts/pixip-guest-retention.sh#g' "$TMP_CRON"
sed -i 's#/scripts/grade-security-lock.sh#/scripts/pixip-security-lock.sh#g' "$TMP_CRON"
sed -i 's#/scripts/grade-security-unlock.sh#/scripts/pixip-security-unlock.sh#g' "$TMP_CRON"
crontab "$TMP_CRON"
rm -f "$TMP_CRON"
```

## 6) Limpeza futura (apos janela de estabilidade)
- remover wrappers `scripts/pixip-*.sh`
- manter apenas `scripts/grade-*.sh`
