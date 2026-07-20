import Link from "next/link";
import { getOrders } from "@/lib/repositories/orders.repo";

export const dynamic = "force-dynamic";

const money = (value: number) =>
  new Intl.NumberFormat("uk-UA", { style: "currency", currency: "UAH", maximumFractionDigits: 0 }).format(value);

export default async function OrdersPage() {
  const orders = await getOrders({ limit: 100 });

  return (
    <main className="page">
      <section className="hero compact">
        <div className="eyebrow">NCRM-08 · read-only</div>
        <h1>Замовлення</h1>
        <p>{orders.total} записів. Статуси, оплата й канал показані людськими назвами.</p>
        <p><Link href="/orders/new">Створити продаж →</Link></p>
      </section>
      <section className="card table-card">
        <div className="table-wrap">
          <table>
            <thead><tr><th>Дата / №</th><th>Клієнт</th><th>Статус</th><th>Канал</th><th>Оплата</th><th>Позиції</th><th>Виручка</th></tr></thead>
            <tbody>
              {orders.rows.map((order) => (
                <tr key={order.id}>
                  <td><Link href={`/orders/${order.id}`}>{order.orderNo}</Link><br /><span className="muted">{order.soldAt}</span></td>
                  <td>{order.customerName ?? "Не вказано"}<br /><span className="muted">{order.customerPhone ?? "—"}</span></td>
                  <td>{order.orderStatus?.name ?? "—"}</td>
                  <td>{order.channel?.name ?? "—"}</td>
                  <td>{order.paymentStatus?.name ?? order.paymentType?.name ?? "—"}</td>
                  <td>{order.itemsCount} / {order.units} шт.</td>
                  <td>{money(order.revenue)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
    </main>
  );
}
