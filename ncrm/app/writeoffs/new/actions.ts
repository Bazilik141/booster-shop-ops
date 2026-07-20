"use server";

import { revalidatePath } from "next/cache";
import { getCurrentStaff } from "@/lib/auth/session";
import { addWriteoff } from "@/lib/repositories/writeoffs.repo";

type FormResult = { ok: boolean; message: string };
const ALLOWED_WRITEOFF_TYPES = ["Власне відкриття", "Маркетинг", "Інше"] as const;

function requiredText(formData: FormData, name: string): string {
  const value = formData.get(name);
  if (typeof value !== "string" || !value.trim()) throw new Error(`Заповніть поле «${name}».`);
  return value.trim();
}

function numberValue(value: unknown, name: string, minimum = 0): number {
  const number = Number(value);
  if (!Number.isFinite(number) || number < minimum) throw new Error(`Поле «${name}» має містити коректне число.`);
  return number;
}

export async function createWriteoffAction(formData: FormData): Promise<FormResult> {
  try {
    const staff = await getCurrentStaff();
    if (!staff) throw new Error("Доступ owner/admin не підтверджено.");
    const type = requiredText(formData, "type");
    if (!(ALLOWED_WRITEOFF_TYPES as readonly string[]).includes(type)) {
      throw new Error("Оберіть дозволений тип списання.");
    }
    const writtenOffAt = new Date(requiredText(formData, "writtenOffAt"));
    if (Number.isNaN(writtenOffAt.valueOf())) throw new Error("Некоректна дата списання.");
    const rawItems = JSON.parse(requiredText(formData, "itemsJson")) as unknown;
    if (!Array.isArray(rawItems) || rawItems.length === 0) throw new Error("Додайте принаймні одну позицію списання.");
    const rawExpectedQty = (formData.get("expectedQty") as string)?.trim();

    const writeoff = await addWriteoff({
      createdBy: staff.id,
      writeoffNo: requiredText(formData, "writeoffNo"),
      type,
      reason: (formData.get("reason") as string)?.trim() || null,
      expectedQty: rawExpectedQty ? numberValue(rawExpectedQty, "Очікувана кількість") : null,
      writtenOffAt: writtenOffAt.toISOString(),
      note: (formData.get("note") as string)?.trim() || null,
      items: rawItems.map((item, index) => {
        if (!item || typeof item !== "object") throw new Error(`Некоректна позиція ${index + 1}.`);
        const line = item as Record<string, unknown>;
        if (typeof line.productId !== "string" || !line.productId) throw new Error(`Оберіть SKU у позиції ${index + 1}.`);
        return {
          productId: line.productId,
          qty: numberValue(line.qty, `Кількість у позиції ${index + 1}`, Number.EPSILON),
          note: typeof line.note === "string" && line.note.trim() ? line.note.trim() : null
        };
      })
    });

    revalidatePath("/writeoffs");
    revalidatePath("/stock");
    return { ok: true, message: `Списання ${writeoff.writeoffNo} створено.` };
  } catch (error) {
    return { ok: false, message: error instanceof Error ? error.message : "Не вдалося створити списання." };
  }
}
