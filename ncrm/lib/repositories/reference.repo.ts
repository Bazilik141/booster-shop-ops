import type {
  PurchaseFormReferences,
  ReferenceOption,
  SaleFormReferences,
  SupplierRegionOption
} from "@/lib/domain";
import { createRepositoryClient } from "@/lib/supabase/client";
import type { Tables } from "@/lib/types/database";
import { repositoryError } from "./_utils";

type NamedReference = Tables<"sale_channels">;
type SupplierRegion = Tables<"supplier_regions">;

function mapReference(row: NamedReference): ReferenceOption {
  return { id: row.id, code: row.code, name: row.name_uk };
}

async function listNamedReference(
  table: "sale_channels" | "payment_types" | "payment_statuses" | "order_statuses" | "post_methods"
): Promise<ReferenceOption[]> {
  const supabase = createRepositoryClient();
  const { data, error } = await supabase
    .from(table)
    .select("id, code, name_uk")
    .eq("is_active", true)
    .order("name_uk", { ascending: true });

  if (error) {
    throw repositoryError(`listReference.${table}`, error.message);
  }

  return ((data ?? []) as NamedReference[]).map(mapReference);
}

export async function getSaleFormReferences(): Promise<SaleFormReferences> {
  const [channels, paymentTypes, paymentStatuses, orderStatuses, postMethods] = await Promise.all([
    listNamedReference("sale_channels"),
    listNamedReference("payment_types"),
    listNamedReference("payment_statuses"),
    listNamedReference("order_statuses"),
    listNamedReference("post_methods")
  ]);

  return { channels, paymentTypes, paymentStatuses, orderStatuses, postMethods };
}

export async function getPurchaseFormReferences(): Promise<PurchaseFormReferences> {
  const supabase = createRepositoryClient();
  const [{ data: regions, error: regionsError }, { data: lotStatuses, error: statusesError }] = await Promise.all([
    supabase
      .from("supplier_regions")
      .select("id, code, name_uk, default_goods_currency, default_forwarding_currency, default_intl_shipping_currency, default_local_currency")
      .eq("is_active", true)
      .order("name_uk", { ascending: true }),
    supabase
      .from("purchase_lot_statuses")
      .select("code, name_uk")
      .eq("is_active", true)
      .order("name_uk", { ascending: true })
  ]);

  if (regionsError) {
    throw repositoryError("getPurchaseFormReferences.regions", regionsError.message);
  }
  if (statusesError) {
    throw repositoryError("getPurchaseFormReferences.lotStatuses", statusesError.message);
  }

  return {
    regions: ((regions ?? []) as SupplierRegion[]).map((region) => ({
      id: region.id,
      code: region.code,
      name: region.name_uk,
      defaultGoodsCurrency: region.default_goods_currency,
      defaultForwardingCurrency: region.default_forwarding_currency,
      defaultIntlShippingCurrency: region.default_intl_shipping_currency,
      defaultLocalCurrency: region.default_local_currency
    })),
    lotStatuses: (lotStatuses ?? []).map((status) => ({
      id: status.code,
      code: status.code,
      name: status.name_uk
    }))
  };
}
