import { getStock } from "@/lib/repositories/analytics.repo";

export const dynamic = "force-dynamic";

export default async function StockPage() {
  const stock = await getStock({ limit: 1000 });
  return (
    <main className="page">
      <section className="hero compact"><div className="eyebrow">NCRM-08 · read-only</div><h1>Склад</h1><p>Повний список активних продуктів із <code>v_stock_alerts</code>, без home-page teaser limit.</p></section>
      <section className="card table-card"><div className="table-wrap"><table><thead><tr><th>SKU</th><th>Назва</th><th>Залишок</th><th>Продано 30 днів</th><th>Покриття</th><th>Сигнал</th></tr></thead><tbody>
        {stock.map((row) => <tr key={row.productId ?? row.sku}><td>{row.sku ?? "—"}</td><td>{row.name ?? "—"}</td><td>{row.stockQty}</td><td>{row.soldQty30d}</td><td>{row.coverageDays ?? "—"}</td><td>{row.alert ?? "—"}</td></tr>)}
      </tbody></table></div></section>
    </main>
  );
}
