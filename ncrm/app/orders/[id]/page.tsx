import Link from "next/link";
import { notFound } from "next/navigation";
import { getOrderById } from "@/lib/repositories/orders.repo";

export const dynamic = "force-dynamic";

const money = (value: number) =>
  new Intl.NumberFormat("uk-UA", { style: "currency", currency: "UAH", maximumFractionDigits: 0 }).format(value);

export default async function OrderDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const order = await getOrderById(id);
  if (!order) notFound();

  return (
    <main className="page">
      <section className="hero compact">
        <div className="eyebrow"><Link href="/orders">← Замовлення</Link></div>
        <h1>{order.orderNo}</h1>
        <p>{order.soldAt} · {order.customerName ?? "Клієнт не вказаний"} · {order.orderStatus?.name ?? "Статус не вказаний"}</p>
      </section>
      <section className="card table-card">
        <h2>Позиції</h2>
        <div className="table-wrap"><table><thead><tr><th>SKU</th><th>Назва</th><th>К-сть</th><th>Виручка</th><th>COGS</th><th>Маржа</th><th>Стан COGS</th></tr></thead><tbody>
          {order.lines.map((line) => <tr key={line.id}><td>{line.sku ?? "—"}</td><td>{line.name ?? "—"}</td><td>{line.qty}</td><td>{money(line.revenue)}</td><td>{money(line.managementCogs)}</td><td>{money(line.contributionMargin)}</td><td>{line.costState}</td></tr>)}
        </tbody></table></div>
      </section>
      <section className="card stack" style={{ marginTop: 16 }}>
        <h2>Повернення</h2>
        {order.refunds.length === 0 ? <p className="muted">Повернень немає.</p> : order.refunds.map((refund) => <p key={refund.id}>{refund.refundedAt}: {refund.refundType} — {money(refund.amount)}{refund.restock ? " · повернення на склад" : ""}</p>)}
      </section>
    </main>
  );
}
