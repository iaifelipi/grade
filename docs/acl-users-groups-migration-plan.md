# ACL Users Groups Migration Plan (3 etapas)

Objetivo: consolidar ACL no padrão `users_groups` e pivots `users_groups_*`, mantendo compatibilidade temporária com nomes legados.

## Etapa 1 — Rename (estrutural)

Migration aplicada: `database/migrations/2026_02_13_171000_rename_roles_table_to_users_groups.php`

Rename:
- `roles` -> `users_groups`

## Etapa 2 — Compat (legado)

Migration aplicada: `database/migrations/2026_02_13_172000_rename_acl_pivots_to_users_groups_and_add_legacy_views.php`

Renames:
- `user_role` -> `users_groups_user`
- `role_permission` -> `users_groups_permission`

Views legadas criadas para manter compatibilidade:
- `user_role`
- `role_permission`

## Etapa 3 — Cleanup final (quando encerrar compatibilidade)

Condição para executar:
- nenhum código interno/externo depende mais de `user_role` ou `role_permission`.

Checklist rápido:
1. Validar buscas no código (`rg "\buser_role\b|\brole_permission\b" app routes config tests`).
2. Rodar testes de autorização/ACL.
3. Executar remoção das views legadas.

SQL de cleanup:

```sql
DROP VIEW IF EXISTS user_role;
DROP VIEW IF EXISTS role_permission;
```

Validação pós-cleanup:
- tabelas canônicas devem existir: `users_groups`, `users_groups_user`, `users_groups_permission`.
- rotas/telas de grupos de usuários continuam operando normalmente.

## Notas de migration histórica

As migrations antigas de ACL foram ajustadas para instalações novas criarem o padrão canônico por default:
- `2026_02_05_000011_create_acl_tables.php`
- `2026_02_05_000003_add_tenant_uuid_to_permission_tables.php`
- `2026_02_05_000012_migrate_spatie_acl_to_internal.php`

Elas ainda preservam fallback legado para bases antigas durante a transição.
