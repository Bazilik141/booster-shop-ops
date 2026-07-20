import Link from "next/link";
import { getSkuCatalog } from "@/lib/repositories/analytics.repo";

export const dynamic = "force-dynamic";

const money = (value: number | null) =>
  value === null ? "—" : new Intl.NumberFormat("uk-UA", { style: "currency", currency: "UAH", maximumFractionDigits: 0 }).format(value);

export default async function SkuPage() {
  const catalogue = await getSkuCatalog({ limit: 1000 });
  return (
    <main className="page">
      <section className="hero compact"><div className="eyebrow">NCRM-08 · read-only</div><h1>SKU-каталог</h1><p>{catalogue.total} активних продуктів. Нульова виручка за 30 днів — валідне значення, не приховується.</p></section>
      <section className="card table-card"><div className="table-wrap"><table><thead><tr><th>SKU</th><th>Назва</th><th>РРЦ</th><th>Склад</th><th>Продано 30 днів</th><th>Виручка 30 днів</th><th>FIFO mgmt</th><th>Сигнал</th></tr></thead><tbody>
        {catalogue.rows.map((row) => <tr key={row.productId}><td>{row.sku}</td><td>{row.fullName ?? row.name ?? "—"}</td><td>{money(row.currentRrc)}<br /><Link href={`/sku/${row.productId}/rrc`}>Оновити РРЦ</Link></td><td>{row.stockQty ?? "—"}</td><td>{row.units30d}</td><td>{money(row.revenue30d)}</td><td>{money(row.warehouseMgmtCost)}</td><td>{row.alert ?? "—"}</td></tr>)}
      </tbody></table></div></section>
    </main>
  );
}
