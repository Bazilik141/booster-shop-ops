import type { LookupLabel } from "@/lib/domain";

type LookupRecord = {
  id?: string | null;
  code?: string | null;
  name_uk?: string | null;
};

export function toNumber(value: number | string | null | undefined, fallback = 0) {
  if (value === null || value === undefined || value === "") {
    return fallback;
  }

  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : fallback;
}

export function toNullableNumber(value: number | string | null | undefined) {
  if (value === null || value === undefined || value === "") {
    return null;
  }

  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : null;
}

export function mapLookup(raw: unknown): LookupLabel | null {
  const value = Array.isArray(raw) ? raw[0] : raw;

  if (!value || typeof value !== "object") {
    return null;
  }

  const record = value as LookupRecord;

  return {
    id: record.id ?? null,
    code: record.code ?? null,
    name: record.name_uk ?? null
  };
}

export function repositoryError(action: string, message: string) {
  return new Error(`${action} failed: ${message}`);
}
