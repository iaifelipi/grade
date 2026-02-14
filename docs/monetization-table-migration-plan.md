# Monetization Table Migration Plan (3 etapas)

Objetivo: padronizar tabelas com prefixo de domínio (`monetization_*`) sem quebrar o módulo atual.

## Etapa 1 — Rename (estrutural)

Migration aplicada: `database/migrations/2026_02_13_130000_rename_monetization_tables_with_prefix.php`

Renames:
- `payment_gateways` -> `monetization_payment_gateways`
- `currencies` -> `monetization_currencies`
- `tax_rates` -> `monetization_tax_rates`
- `price_plans` -> `monetization_price_plans`
- `promo_codes` -> `monetization_promo_codes`
- `orders` -> `monetization_orders`

A migration remove/recria FKs de `orders` durante o rename para garantir integridade.

## Etapa 2 — Compat (legado)

Migration aplicada: `database/migrations/2026_02_13_130100_create_legacy_monetization_compat_views.php`

Cria views legadas com os nomes antigos apontando para as tabelas novas:
- `payment_gateways`
- `currencies`
- `tax_rates`
- `price_plans`
- `promo_codes`
- `orders`

Isso mantém compatibilidade para consultas/scripts antigos durante a transição.

## Etapa 3 — Cleanup (final)

Comando operacional:

```bash
php artisan monetization:compat:cleanup --dry-run
php artisan monetization:compat:cleanup --force
```

Definição:
- `--dry-run`: mostra o que será removido.
- `--force`: remove as views legadas.

Execute somente quando confirmar que nenhum código externo usa os nomes antigos.

## Rollback

Para rollback de migrations:
- a migration de compat remove as views no `down`.
- a migration de rename restaura os nomes antigos no `down`.

Recomendação: sempre validar com testes HTTP/integrados antes e depois.

