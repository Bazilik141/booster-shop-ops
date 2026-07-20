import Link from "next/link";
import { listWriteoffs } from "@/lib/repositories/writeoffs.repo";

export const dynamic = "force-dynamic";

export default async function WriteoffsPage() {
  const writeoffs = await listWriteoffs({ limit: 100 });
  return <main className="page"><section className="hero compact"><div className="eyebrow">NCRM-09c · write</div><h1>Списання</h1><p>Операційні списання без inventory adjustments та Mystery Box flow.</p><p><Link href="/writeoffs/new">Створити списання →</Link></p></section><section className="card table-card"><div className="table-wrap"><table><thead><tr><th>Дата / №</th><th>Тип</th><th>Причина</th><th>Позиції</th></tr></thead><tbody>{writeoffs.map((writeoff) => <tr key={writeoff.id}><td>{writeoff.writeoffNo}<br /><span className="muted">{writeoff.writtenOffAt}</span></td><td>{writeoff.type}</td><td>{writeoff.reason ?? "—"}</td><td>{writeoff.items?.length ?? 0}</td></tr>)}</tbody></table></div></section></main>;
}
