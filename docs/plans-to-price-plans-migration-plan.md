# Plans -> Price Plans Migration Plan (compatível, sem quebra)

## Status atual

- Runtime principal usa `monetization_price_plans` como catálogo comercial.
- Tabela `plans` e colunas legadas `plan_id` foram removidas.
- `tenant_users` usa `price_plan_id` para vínculo direto com plano comercial.

## Etapa 1 (aplicada) — runtime canônico

- Admin de clientes e subscriptions prioriza `price_plan_id`.
- Formulários de clientes usam seleção de plano comercial (`price_plan_id`).
- `plan_id` legado deixou de ser usado no runtime.

## Etapa 2 (aplicada) — ponte de dados

Migration aplicada:
- `database/migrations/2026_02_13_173500_add_price_plan_id_to_tenant_users_table.php`

A migration:
- adiciona `tenant_users.price_plan_id` + índice/FK;
- backfill por assinatura ativa (`tenant_subscriptions.price_plan_id`);
- fallback de backfill por `plan_id` legado quando necessário;
- sincroniza `tenant_users.plan_id` a partir do `price_plan_id` quando houver mapeamento.

## Etapa 3 (aplicada) — cleanup final de legado

Migration aplicada:
- `database/migrations/2026_02_13_175500_drop_legacy_plans_table_and_plan_id_columns.php`

Removido:
- `tenant_users.plan_id`
- `tenant_subscriptions.plan_id`
- `monetization_price_plans.plan_id`
- tabela `plans`

Além disso:
- removidos artefatos legados `app/Models/Plan.php` e `database/seeders/PlanSeeder.php`.
- `DatabaseSeeder` não referencia mais `PlanSeeder`.
