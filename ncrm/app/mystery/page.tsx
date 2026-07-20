import Link from "next/link";
import { listMysteryQueue } from "@/lib/repositories/mystery.repo";

export const dynamic = "force-dynamic";

const stateLabel: Record<string, string> = {
  needs_assembly: "Потрібна збірка",
  reserved: "Зарезервовано",
  committed: "Підтверджено",
  released: "Звільнено",
  reversed: "Сторновано"
};

export default async function MysteryQueuePage() {
  const items = await listMysteryQueue();

  return <main className="page">
    <section className="hero compact">
      <div className="eyebrow">NCRM-09e · fulfillment</div>
      <h1>Mystery Box</h1>
      <p>Черга позицій у станах «потрібна збірка» та «зарезервовано».</p>
    </section>
    <section className="card">
      {items.length === 0 ? <p className="muted">Немає Mystery Box, що очікують дії.</p> : <div className="table-wrap"><table><thead><tr><th>Замовлення</th><th>Товар</th><th>Кількість</th><th>Статус замовлення</th><th>Fulfillment</th><th></th></tr></thead><tbody>{items.map((item) => <tr key={item.fulfillmentId}><td>{item.orderNo ?? "—"}<br /><span className="muted">{item.customerName ?? item.customerPhone ?? "—"}</span></td><td>{item.mysterySku}<br /><span className="muted">{item.mysteryName}</span></td><td>{item.saleQty}</td><td>{item.orderStatusName ?? item.orderStatusCode ?? "—"}</td><td>{stateLabel[item.state] ?? item.state}</td><td><Link href={`/mystery/${item.saleItemId}`}>Відкрити</Link></td></tr>)}</tbody></table></div>}
    </section>
  </main>;
}
