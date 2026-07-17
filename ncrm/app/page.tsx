import {
  getCostQualityExposure,
  getDashboardGuardrails,
  getForecastMargin,
  getSummary,
  getUnpricedInventory
} from "@/lib/repositories/analytics.repo";

export const dynamic = "force-dynamic";

function formatMoney(value: number | null | undefined) {
  return new Intl.NumberFormat("uk-UA", {
    style: "currency",
    currency: "UAH",
    maximumFractionDigits: 0
  }).format(value ?? 0);
}

function formatNumber(value: number | null | undefined) {
  return new Intl.NumberFormat("uk-UA", { maximumFractionDigits: 1 }).format(value ?? 0);
}

export default async function Home() {
  try {
    const [summary, guardrails, unpricedInventory, costQuality, forecast] = await Promise.all([
      getSummary(),
      getDashboardGuardrails(),
      getUnpricedInventory(),
      getCostQualityExposure(),
      getForecastMargin()
    ]);
    const currentMonth = summary.salesPeriods.find(
      (period) => period.periodCode === "current_month"
    );
    const latestPnl = summary.latestPnlMonth;
    const nonActualCostLines = costQuality.filter((row) => row.costState !== "actual");
    const nonActualCostRevenue = nonActualCostLines.reduce(
      (total, row) => total + row.revenue,
      0
    );
    const forecastMargin = forecast.reduce((total, row) => total + row.forecastMargin, 0);

    return (
      <main className="page">
        <section className="hero">
          <div className="eyebrow">NCRM-08 · локальний read-only огляд</div>
          <h1>Booster Shop NCRM</h1>
          <p>
            Зведення фінансів, запасів і якості собівартості. Дані читаються тільки
            через repository layer; write-шляхів на цій картці немає.
          </p>
        </section>

        <section className="grid" aria-label="Основні показники">
          <article className="card">
            <h2>Виручка місяця</h2>
            <p className="metric">{formatMoney(currentMonth?.revenue)}</p>
            <p className="muted">{formatNumber(currentMonth?.orders)} замовлень</p>
          </article>
          <article className="card">
            <h2>Чистий прибуток</h2>
            <p className="metric">{formatMoney(latestPnl?.trueNetProfit)}</p>
            <p className="muted">net revenue: {formatMoney(latestPnl?.netRevenue)}</p>
          </article>
          <article className="card">
            <h2>Доступний склад</h2>
            <p className="metric">{formatNumber(guardrails?.availableQty)}</p>
            <p className="muted">
              фізично {formatNumber(guardrails?.physicalQty)} · резерв {formatNumber(guardrails?.reservedQty)}
            </p>
          </article>
        </section>

        <section className="grid" style={{ marginTop: 16 }} aria-label="Контрольні показники">
          <article className="card">
            <h2>SKU у каталозі</h2>
            <p className="metric">{formatNumber(summary.productCount)}</p>
            <p className="muted">Активний каталог дивіться на екрані SKU.</p>
          </article>
          <article className="card">
            <h2>Без ручної РРЦ</h2>
            <p className="metric">{formatNumber(unpricedInventory.length)}</p>
            <p className="muted">
              активів на {formatMoney(unpricedInventory.reduce((total, row) => total + row.assetMgmtCost, 0))}
            </p>
          </article>
          <article className="card">
            <h2>Прогнозна маржа</h2>
            <p className="metric">{formatMoney(forecastMargin)}</p>
            <p className="muted">По товарах з доступним залишком і ручною РРЦ.</p>
          </article>
        </section>

        <section className="grid" style={{ marginTop: 16 }} aria-label="Якість даних">
          <article className="card stack">
            <h3>Якість COGS</h3>
            <p className="metric">{formatNumber(nonActualCostLines.reduce((total, row) => total + row.saleItemCount, 0))}</p>
            <p className="muted">
              рядків із provisional/estimated COGS на {formatMoney(nonActualCostRevenue)}.
            </p>
          </article>
          <article className="card stack">
            <h3>P&amp;L v2</h3>
            <p className="muted">PRRO gross profit: {formatMoney(latestPnl?.prroGrossProfit)}</p>
            <p className="muted">COGS reversals: {formatMoney(latestPnl?.cogsReversals)}</p>
            <p className="muted">Inventory adjustments: {formatMoney(latestPnl?.inventoryAdjustmentImpact)}</p>
          </article>
          <article className="card stack">
            <h3>Локальна безпека</h3>
            <p className="muted">
              Застосунок поки без логіну та працює через service role. Тримайте його лише на localhost.
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
          <div className="eyebrow">NCRM-08 · локальне налаштування</div>
          <h1>Supabase ще не підключений</h1>
          <p>Додайте локальні значення до <code>.env.local</code> і запустіть emulator.</p>
        </section>
        <article className="card warning">
          <h2>Read-шлях не пройшов</h2>
          <p className="muted">{message}</p>
        </article>
      </main>
    );
  }
}
