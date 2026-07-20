import { createBrowserClient, createServerClient } from "@supabase/ssr";
import { createClient, type SupabaseClient } from "@supabase/supabase-js";
import type { Database } from "@/lib/types/database";

type SupabaseEnv = {
  url: string;
  key: string;
  usingServiceRole: boolean;
};

export const SUPABASE_ENV_VARS = [
  "NEXT_PUBLIC_SUPABASE_URL",
  "NEXT_PUBLIC_SUPABASE_ANON_KEY",
  "SUPABASE_SERVICE_ROLE_KEY"
] as const;

export class SupabaseEnvError extends Error {
  constructor(message: string) {
    super(message);
    this.name = "SupabaseEnvError";
  }
}

function readSupabaseEnv(options: { useServiceRole?: boolean } = {}): SupabaseEnv {
  const url = process.env.NEXT_PUBLIC_SUPABASE_URL;
  const anonKey = process.env.NEXT_PUBLIC_SUPABASE_ANON_KEY;
  const serviceRoleKey = process.env.SUPABASE_SERVICE_ROLE_KEY;
  const useServiceRole = Boolean(options.useServiceRole);
  const key = useServiceRole ? serviceRoleKey : anonKey;

  const missing = [
    !url ? "NEXT_PUBLIC_SUPABASE_URL" : null,
    !key
      ? useServiceRole
        ? "SUPABASE_SERVICE_ROLE_KEY"
        : "NEXT_PUBLIC_SUPABASE_ANON_KEY"
      : null
  ].filter(Boolean);

  if (!url || !key) {
    throw new SupabaseEnvError(
      `Supabase env is not configured: missing ${missing.join(", ")}.`
    );
  }

  return {
    url,
    key,
    usingServiceRole: useServiceRole
  };
}

export function createSupabaseServerClient(
  options: { useServiceRole?: boolean } = {}
): SupabaseClient<Database> {
  const env = readSupabaseEnv(options);

  return createClient<Database>(env.url, env.key, {
    auth: {
      autoRefreshToken: false,
      detectSessionInUrl: false,
      persistSession: false
    }
  });
}

export function createSupabaseBrowserAppClient(): SupabaseClient<Database> {
  const env = readSupabaseEnv({ useServiceRole: false });

  return createBrowserClient<Database>(env.url, env.key);
}

type SessionCookie = {
  name: string;
  value: string;
  options?: Record<string, unknown>;
};

type SessionCookieStore = {
  getAll: () => { name: string; value: string }[];
  setAll: (cookies: SessionCookie[]) => void;
};

/**
 * Session/identity client only. Business-table access remains in repository
 * modules through createRepositoryClient(), which uses the service-role key.
 */
export function createSupabaseSessionClient(
  cookieStore: SessionCookieStore
): SupabaseClient<Database> {
  const env = readSupabaseEnv({ useServiceRole: false });

  return createServerClient<Database>(env.url, env.key, {
    cookies: cookieStore
  });
}

export function createRepositoryClient(): SupabaseClient<Database> {
  return createSupabaseServerClient({ useServiceRole: true });
}
