"use server";

import { getCurrentStaff } from "@/lib/auth/session";
import {
  commitMysteryFulfillment,
  releaseMysteryFulfillment,
  reserveMysteryFulfillment
} from "@/lib/repositories/mystery.repo";
import { revalidatePath } from "next/cache";

export type MysteryActionResult = { ok: boolean; message: string };

function fail(error: unknown): MysteryActionResult {
  return { ok: false, message: error instanceof Error ? error.message : "Невідома помилка Mystery Box." };
}

function refresh(saleItemId: string) {
  revalidatePath("/mystery");
  revalidatePath(`/mystery/${saleItemId}`);
}

export async function reserveMysteryAction(formData: FormData): Promise<MysteryActionResult> {
  try {
    const staff = await getCurrentStaff();
    if (!staff) throw new Error("Потрібен активний сеанс співробітника.");

    const saleItemId = String(formData.get("saleItemId") ?? "");
    const rawComponents = JSON.parse(String(formData.get("componentsJson") ?? "[]")) as unknown;
    if (!saleItemId || !Array.isArray(rawComponents) || rawComponents.length === 0) {
      throw new Error("Додайте щонайменше один компонент для резерву.");
    }

    const components = rawComponents.map((component) => {
      if (!component || typeof component !== "object") throw new Error("Некоректний склад Mystery Box.");
      const productId = String((component as { productId?: unknown }).productId ?? "");
      const qty = Number((component as { qty?: unknown }).qty);
      if (!productId || !Number.isInteger(qty) || qty <= 0) {
        throw new Error("Некоректна кількість компонента Mystery Box.");
      }
      return { productId, qty };
    });

    await reserveMysteryFulfillment(saleItemId, components, staff.id);
    refresh(saleItemId);
    return { ok: true, message: "Склад Mystery Box зарезервовано." };
  } catch (error) {
    return fail(error);
  }
}

export async function commitMysteryAction(formData: FormData): Promise<MysteryActionResult> {
  try {
    const staff = await getCurrentStaff();
    if (!staff) throw new Error("Потрібен активний сеанс співробітника.");
    const saleItemId = String(formData.get("saleItemId") ?? "");
    if (!saleItemId) throw new Error("Не вказано позицію продажу.");
    await commitMysteryFulfillment(saleItemId);
    refresh(saleItemId);
    return { ok: true, message: "Mystery Box зібрано та списано." };
  } catch (error) {
    return fail(error);
  }
}

export async function releaseMysteryAction(formData: FormData): Promise<MysteryActionResult> {
  try {
    const staff = await getCurrentStaff();
    if (!staff) throw new Error("Потрібен активний сеанс співробітника.");
    const saleItemId = String(formData.get("saleItemId") ?? "");
    if (!saleItemId) throw new Error("Не вказано позицію продажу.");
    await releaseMysteryFulfillment(saleItemId);
    refresh(saleItemId);
    return { ok: true, message: "Резерв Mystery Box звільнено." };
  } catch (error) {
    return fail(error);
  }
}
