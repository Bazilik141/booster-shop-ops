import { getCustomers } from "@/lib/repositories/customers.repo";

export const dynamic = "force-dynamic";

const money = (value: number) =>
  new Intl.NumberFormat("uk-UA", { style: "currency", currency: "UAH", maximumFractionDigits: 0 }).format(value);

export default async function CustomersPage() {
  const customers = await getCustomers({ limit: 1000 });
  return (
    <main className="page">
      <section className="hero compact"><div className="eyebrow">NCRM-08 · read-only</div><h1>Клієнти</h1><p>{customers.total} нормалізованих телефонних профілів; одноразові покупці включені.</p></section>
      <section className="card table-card"><div className="table-wrap"><table><thead><tr><th>Клієнт</th><th>Телефон</th><th>Замовлень</th><th>Перший</th><th>Останній</th><th>LTV</th><th>Повторний</th></tr></thead><tbody>
        {customers.rows.map((row) => <tr key={row.customerPhone ?? `unknown-${row.firstOrderAt}`}><td>{row.customerName ?? "Не вказано"}</td><td>{row.customerPhone ?? "Не вказано"}</td><td>{row.orderCount}</td><td>{row.firstOrderAt}</td><td>{row.lastOrderAt}</td><td>{money(row.lifetimeRevenue)}</td><td>{row.isRepeat ? "Так" : "Ні"}</td></tr>)}
      </tbody></table></div></section>
    </main>
  );
}
