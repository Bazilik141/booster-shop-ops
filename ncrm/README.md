# Booster Shop NCRM

Локальна Supabase-схема нової CRM + мінімальний Next.js скелет.
Міграції `0001`–`0004` відтворюють Stage 1–4 з
`plans/crm-schema-v1_2026-06-26.md`.

## Передумови

- Docker Desktop;
- Node.js 20+;
- Supabase CLI 2.x (`npx supabase` також підтримується);
- npm.

Не додавайте `project ref`, паролі або ключі до репозиторію.
`.env.local` і Supabase temp-файли мають лишатися локальними.

## Локальна Supabase база

```bash
cd ncrm
npx supabase start
npx supabase db reset
```

Перевірка відсутності локального schema drift:

```bash
npx supabase db diff --local
```

## Next.js застосунок

Скопіюйте локальні Supabase URL/key у `.env.local` за прикладом:

```bash
cp .env.example .env.local
```

Беріть значення з:

```bash
npx supabase status -o env
```

Потрібні змінні:

```bash
NEXT_PUBLIC_SUPABASE_URL=
NEXT_PUBLIC_SUPABASE_ANON_KEY=
SUPABASE_SERVICE_ROLE_KEY=
```

Мапінг:

- `API_URL` → `NEXT_PUBLIC_SUPABASE_URL`
- `ANON_KEY` → `NEXT_PUBLIC_SUPABASE_ANON_KEY`
- `SERVICE_ROLE_KEY` → `SUPABASE_SERVICE_ROLE_KEY`

Не використовуйте `PUBLISHABLE_KEY` / `SECRET_KEY` для цього локального
read-demo. Не комітьте `.env.local`.

Запуск:

```bash
npm install
npm run dev
```

Root route читає Supabase тільки через `lib/repositories/analytics.repo.ts`.
UI/сторінки не мають робити прямі Supabase query calls напряму.

Production build:

```bash
npm run build
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

Це впливає лише на локальний Docker-emulator. Хмарні міграції з NCRM-01
не запускаються.
