import type { AddWriteoffPayload, Writeoff, WriteoffItem } from "@/lib/domain";
import { createRepositoryClient } from "@/lib/supabase/client";
import type { Tables, TablesInsert } from "@/lib/types/database";
import { repositoryError, toNumber, toNullableNumber } from "./_utils";

type ProductMini = {
  sku?: string | null;
  name?: string | null;
  full_name?: string | null;
};

type WriteoffItemRecord = Tables<"writeoff_items"> & {
  products?: ProductMini | ProductMini[] | null;
};

type WriteoffRecord = Tables<"writeoffs"> & {
  writeoff_items?: WriteoffItemRecord[] | null;
};

const WRITEOFF_SELECT = "*, writeoff_items(*, products(sku, name, full_name))";

function firstProduct(raw: WriteoffItemRecord["products"]): ProductMini | null {
  if (!raw) {
    return null;
  }

  return Array.isArray(raw) ? (raw[0] ?? null) : raw;
}

function mapWriteoffItem(row: WriteoffItemRecord): WriteoffItem {
  const product = firstProduct(row.products);

  return {
    id: row.id,
    writeoffId: row.writeoff_id,
    productId: row.product_id,
    productSku: product?.sku ?? null,
    productName: product?.full_name ?? product?.name ?? null,
    qty: toNumber(row.qty),
    note: row.note,
    createdAt: row.created_at,
    updatedAt: row.updated_at
  };
}

function mapWriteoff(row: WriteoffRecord): Writeoff {
  return {
    id: row.id,
    writeoffNo: row.writeoff_no,
    type: row.type,
    reason: row.reason,
    expectedQty: toNullableNumber(row.expected_qty),
    writtenOffAt: row.written_off_at,
    mysterySaleId: row.mystery_sale_id,
    note: row.note,
    createdAt: row.created_at,
    updatedAt: row.updated_at,
    items: row.writeoff_items?.map(mapWriteoffItem) ?? []
  };
}

export async function listWriteoffs(options: { limit?: number } = {}): Promise<Writeoff[]> {
  const supabase = createRepositoryClient();
  const { data, error } = await supabase
    .from("writeoffs")
    .select(WRITEOFF_SELECT)
    .order("written_off_at", { ascending: false })
    .limit(options.limit ?? 100);

  if (error) {
    throw repositoryError("listWriteoffs", error.message);
  }

  return ((data ?? []) as WriteoffRecord[]).map(mapWriteoff);
}

export async function getWriteoff(id: string): Promise<Writeoff | null> {
  const supabase = createRepositoryClient();
  const { data, error } = await supabase
    .from("writeoffs")
    .select(WRITEOFF_SELECT)
    .eq("id", id)
    .maybeSingle();

  if (error) {
    throw repositoryError("getWriteoff", error.message);
  }

  return data ? mapWriteoff(data as WriteoffRecord) : null;
}

export async function addWriteoff(payload: AddWriteoffPayload): Promise<Writeoff> {
  const supabase = createRepositoryClient();
  const { data: writeoff, error: writeoffError } = await supabase
    .from("writeoffs")
    .insert({
      created_by: payload.createdBy,
      writeoff_no: payload.writeoffNo,
      type: payload.type,
      reason: payload.reason ?? null,
      expected_qty: payload.expectedQty ?? null,
      written_off_at: payload.writtenOffAt,
      mystery_sale_id: payload.mysterySaleId ?? null,
      note: payload.note ?? null
    })
    .select("*")
    .single();

  if (writeoffError) {
    throw repositoryError("addWriteoff", writeoffError.message);
  }

  if (payload.items?.length) {
    const itemRows: TablesInsert<"writeoff_items">[] = payload.items.map((item) => ({
      writeoff_id: writeoff.id,
      product_id: item.productId,
      qty: item.qty,
      note: item.note ?? null
    }));

    const { error: itemsError } = await supabase.from("writeoff_items").insert(itemRows);

    if (itemsError) {
      throw repositoryError("addWriteoffItems", itemsError.message);
    }
  }

  const inserted = await getWriteoff(writeoff.id);
  if (!inserted) {
    throw repositoryError("addWriteoff", "inserted writeoff was not found after insert");
  }

  return inserted;
}
