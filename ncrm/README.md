# Booster Shop NCRM

Локальна Supabase-схема нової CRM. Міграції `0001`–`0004` відтворюють
Stage 1–4 з `plans/crm-schema-v1_2026-06-26.md`.

## Передумови

- Docker Desktop;
- Node.js 20+;
- Supabase CLI 2.x (`npx supabase` також підтримується).

Хмарний Supabase-проєкт для локальної роботи не потрібен. Не додавайте
`project ref`, паролі або ключі до репозиторію.

## Локальний запуск

```bash
cd ncrm
npx supabase start
npx supabase db reset
```

Перевірка відсутності локального schema drift:

```bash
npx supabase db diff --local
```

## TypeScript-типи

Після успішного `db reset`:

```bash
mkdir -p lib/types
npx supabase gen types typescript --local --schema public > lib/types/database.ts
```

Файл `lib/types/database.ts` є згенерованим. Не редагуйте його вручну.

## Зупинка та локальний rollback

```bash
npx supabase stop --no-backup
```

Це впливає лише на локальний Docker-emulator. Хмарні міграції в NCRM-01
не запускаються.
