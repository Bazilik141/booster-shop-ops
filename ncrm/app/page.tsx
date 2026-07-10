import { getSkuMetrics, getStock, getSummary } from "@/lib/repositories/analytics.repo";

export const dynamic = "force-dynamic";

function formatMoney(value: number | null | undefined) {
  return new Intl.NumberFormat("uk-UA", {
    style: "currency",
    currency: "UAH",
    maximumFractionDigits: 0
  }).format(value ?? 0);
}

function formatNumber(value: number | null | undefined) {
  return new Intl.NumberFormat("uk-UA", {
    maximumFractionDigits: 1
  }).format(value ?? 0);
}

export default async function Home() {
  try {
    const [summary, stock, skuMetrics] = await Promise.all([
      getSummary(),
      getStock({ limit: 5 }),
      getSkuMetrics({ limit: 5 })
    ]);

    const currentMonth = summary.salesPeriods.find(
      (period) => period.periodCode === "current_month"
    );

    return (
      <main className="page">
        <section className="hero">
          <div className="eyebrow">NCRM-02 · Supabase read demo</div>
          <h1>Booster Shop NCRM</h1>
          <p>
            Root route читає локальний Supabase emulator через repository layer.
            Це технічний скелет без фінального дизайну й без прямого доступу UI до
            Supabase.
          </p>
        </section>

        <section className="grid" aria-label="CRM summary">
          <article className="card">
            <h2>Поточний місяць</h2>
            <p className="metric">{formatMoney(currentMonth?.revenue)}</p>
            <p className="muted">
              {formatNumber(currentMonth?.orders)} замовлень · прибуток{" "}
              {formatMoney(currentMonth?.trueNetProfit)}
            </p>
          </article>

          <article className="card">
            <h2>SKU у базі</h2>
            <p className="metric">{formatNumber(summary.productCount)}</p>
            <p className="muted">
              Значення приходить із таблиці products через analytics repository.
            </p>
          </article>

          <article className="card">
            <h2>Останній P&amp;L місяць</h2>
            <p className="metric">{formatMoney(summary.latestPnlMonth?.trueNetProfit)}</p>
            <p className="muted">
              {summary.latestPnlMonth?.month ?? "Поки немає продажів у view"}
            </p>
          </article>
        </section>

        <section className="grid" style={{ marginTop: 16 }} aria-label="CRM lists">
          <article className="card stack">
            <h3>Stock alerts</h3>
            {stock.length === 0 ? (
              <p className="muted">View `v_stock_alerts` повернула 0 рядків.</p>
            ) : (
              <ul className="list">
                {stock.map((item) => (
                  <li key={item.productId ?? item.sku}>
                    <span>{item.sku ?? "SKU без коду"}</span>
                    <span>{formatNumber(item.stockQty)} шт.</span>
                  </li>
                ))}
              </ul>
            )}
          </article>

          <article className="card stack">
            <h3>Top SKU</h3>
            {skuMetrics.length === 0 ? (
              <p className="muted">View `v_top_skus` повернула 0 рядків.</p>
            ) : (
              <ul className="list">
                {skuMetrics.map((item) => (
                  <li key={item.productId ?? item.sku}>
                    <span>{item.sku ?? "SKU без коду"}</span>
                    <span>{formatMoney(item.revenue)}</span>
                  </li>
                ))}
              </ul>
            )}
          </article>

          <article className="card stack">
            <h3>Repository boundary</h3>
            <p className="muted">
              Ця сторінка імпортує тільки `analytics.repo.ts`. Прямі Supabase
              query calls дозволені лише в `lib/repositories/*` та
              `lib/supabase/client.ts`.
            </p>
          </article>
        </section>
      </main>
    );
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);

    return (
      <main className="page">
        <section className="hero">
          <div className="eyebrow">NCRM-02 · setup needed</div>
          <h1>Supabase ще не підключений</h1>
          <p>
            Додай локальні значення в <code>.env.local</code> і запусти emulator.
            Ця сторінка не використовує mock-дані; помилка нижче — з реального
            read-шляху.
          </p>
        </section>

        <article className="card warning">
          <h2>Read demo не пройшов</h2>
          <p className="muted">{message}</p>
          <p className="muted">
            Потрібні змінні: <code>NEXT_PUBLIC_SUPABASE_URL</code>,{" "}
            <code>NEXT_PUBLIC_SUPABASE_ANON_KEY</code>. Опційно для локального
            server-side read: <code>SUPABASE_SERVICE_ROLE_KEY</code>.
          </p>
        </article>
      </main>
    );
  }
}
