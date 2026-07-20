"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import type { MysteryBoxType, MysteryEligibleComponent, MysteryQueueItem } from "@/lib/domain/mystery";
import { commitMysteryAction, releaseMysteryAction, reserveMysteryAction } from "../actions";

type Props = {
  fulfillment: MysteryQueueItem;
  boxType: MysteryBoxType;
  components: MysteryEligibleComponent[];
};

export function MysteryForm({ fulfillment, boxType, components }: Props) {
  const router = useRouter();
  const [quantities, setQuantities] = useState<Record<string, number>>({});
  const [message, setMessage] = useState<string | null>(null);
  const [pending, setPending] = useState(false);
  const expected = boxType.expectedPackCount * fulfillment.saleQty;
  const selected = Object.values(quantities).reduce((total, quantity) => total + quantity, 0);
  const canCommit = fulfillment.orderStatusCode === "shipped";
  const canRelease = fulfillment.orderStatusCode === "cancelled" || fulfillment.orderStatusCode === "refund";

  async function reserve(formData: FormData) {
    setPending(true); setMessage(null);
    formData.set("componentsJson", JSON.stringify(Object.entries(quantities).filter(([, qty]) => qty > 0).map(([productId, qty]) => ({ productId, qty }))));
    const result = await reserveMysteryAction(formData);
    setMessage(result.message); setPending(false);
    if (result.ok) router.refresh();
  }

  async function run(formData: FormData, action: typeof commitMysteryAction) {
    setPending(true); setMessage(null);
    const result = await action(formData);
    setMessage(result.message); setPending(false);
    if (result.ok) router.refresh();
  }

  return <div className="stack">
    <div className="card subtle"><strong>{fulfillment.mysterySku}</strong> · {fulfillment.mysteryName}<br /><span className="muted">Продаж: {fulfillment.saleQty} шт. · Потрібно компонентів: {expected} · Статус замовлення: {fulfillment.orderStatusName ?? fulfillment.orderStatusCode ?? "—"}</span></div>
    {fulfillment.state === "needs_assembly" ? <form action={reserve} className="stack">
      <input type="hidden" name="saleItemId" value={fulfillment.saleItemId} />
      <input type="hidden" name="componentsJson" value="[]" />
      <p>Обрано: <strong>{selected} / {expected}</strong>. Остаточну перевірку кількості, придатності та доступного залишку виконує база даних.</p>
      <div className="table-wrap"><table><thead><tr><th>SKU</th><th>Компонент</th><th>Фізично</th><th>У резерві</th><th>Доступно</th><th>До резерву</th></tr></thead><tbody>{components.map((component) => <tr key={component.productId}><td>{component.sku}</td><td>{component.name}</td><td>{component.physicalQty}</td><td>{component.reservedQty}</td><td>{component.availableQty}</td><td><input aria-label={`Кількість ${component.sku}`} type="number" min="0" max={component.availableQty} step="1" value={quantities[component.productId] ?? 0} onChange={(event) => setQuantities((current) => ({ ...current, [component.productId]: Math.max(0, Number(event.target.value) || 0) }))} /></td></tr>)}</tbody></table></div>
      <button type="submit" disabled={pending}>{pending ? "Збереження…" : "Зарезервувати склад"}</button>
    </form> : null}
    {fulfillment.state === "reserved" ? <div className="stack">
      <p>Компоненти зарезервовано. Підтвердження створює фактичне списання MBOX та оновлює COGS.</p>
      <form action={(formData) => run(formData, commitMysteryAction)}><input type="hidden" name="saleItemId" value={fulfillment.saleItemId} /><button type="submit" disabled={pending || !canCommit}>Підтвердити збірку</button>{!canCommit ? <span className="muted"> Підтвердження доступне лише після статусу <code>shipped</code>.</span> : null}</form>
      <form action={(formData) => run(formData, releaseMysteryAction)}><input type="hidden" name="saleItemId" value={fulfillment.saleItemId} /><button type="submit" disabled={pending || !canRelease}>Звільнити резерв</button>{!canRelease ? <span className="muted"> Резерв звільняється лише при <code>cancelled</code> або <code>refund</code>; зазвичай це робить тригер зміни статусу.</span> : null}</form>
    </div> : null}
    {fulfillment.state === "committed" ? <p className="muted">Збірку вже підтверджено.</p> : null}
    {fulfillment.state === "released" ? <p className="muted">Резерв уже звільнено.</p> : null}
    {message ? <p role="status">{message}</p> : null}
  </div>;
}
