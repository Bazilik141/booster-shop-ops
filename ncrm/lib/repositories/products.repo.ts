import type { Product, SkuOption, UpdateRrcPayload } from "@/lib/domain";
import { createRepositoryClient } from "@/lib/supabase/client";
import type { Tables } from "@/lib/types/database";
import { repositoryError, toNullableNumber } from "./_utils";

type ProductRow = Tables<"products">;
type CurrentRrcRow = Tables<"v_current_rrc">;

function mapProduct(row: ProductRow, currentRrc?: number | null): Product {
  return {
    id: row.id,
    sku: row.sku,
    name: row.name,
    fullName: row.full_name,
    brandCode: row.brand_code,
    categoryCode: row.category_code,
    gameCode: row.game_code,
    languageCode: row.language_code,
    gtin: row.gtin,
    legacySku: row.legacy_sku,
    isActive: row.is_active,
    archivedAt: row.archived_at,
    createdAt: row.created_at,
    updatedAt: row.updated_at,
    currentRrc: currentRrc ?? null
  };
}

async function getCurrentRrcMap(productIds: string[]) {
  if (productIds.length === 0) {
    return new Map<string, number | null>();
  }

  const supabase = createRepositoryClient();
  const { data, error } = await supabase
    .from("v_current_rrc")
    .select("product_id, rrc")
    .in("product_id", productIds);

  if (error) {
    throw repositoryError("getCurrentRrcMap", error.message);
  }

  return new Map(
    ((data ?? []) as CurrentRrcRow[])
      .filter((row) => row.product_id)
      .map((row) => [row.product_id as string, toNullableNumber(row.rrc)])
  );
}

export async function listProducts(
  options: { limit?: number; activeOnly?: boolean } = {}
): Promise<Product[]> {
  const supabase = createRepositoryClient();
  let query = supabase
    .from("products")
    .select("*")
    .order("sku", { ascending: true })
    .limit(options.limit ?? 250);

  if (options.activeOnly ?? true) {
    query = query.eq("is_active", true).is("archived_at", null);
  }

  const { data, error } = await query;

  if (error) {
    throw repositoryError("listProducts", error.message);
  }

  const rows = (data ?? []) as ProductRow[];
  const rrc = await getCurrentRrcMap(rows.map((row) => row.id));
  return rows.map((row) => mapProduct(row, rrc.get(row.id)));
}

export async function listSku(
  options: { limit?: number; activeOnly?: boolean } = {}
): Promise<SkuOption[]> {
  const products = await listProducts(options);

  return products.map((product) => ({
    productId: product.id,
    sku: product.sku,
    name: product.fullName ?? product.name,
    isActive: product.isActive,
    currentRrc: product.currentRrc ?? null
  }));
}

export async function updateRrc(payload: UpdateRrcPayload): Promise<Product | null> {
  const supabase = createRepositoryClient();
  const effectiveFrom = payload.effectiveFrom ?? new Date().toISOString().slice(0, 10);
  const { error } = await supabase.from("product_prices").upsert(
    {
      product_id: payload.productId,
      rrc: payload.rrc,
      source: payload.source ?? "manual",
      note: payload.note ?? null,
      effective_from: effectiveFrom
    },
    {
      onConflict: "product_id,effective_from"
    }
  );

  if (error) {
    throw repositoryError("updateRrc", error.message);
  }

  const { data, error: productError } = await supabase
    .from("products")
    .select("*")
    .eq("id", payload.productId)
    .maybeSingle();

  if (productError) {
    throw repositoryError("updateRrcReadback", productError.message);
  }

  return data ? mapProduct(data as ProductRow, payload.rrc) : null;
}
