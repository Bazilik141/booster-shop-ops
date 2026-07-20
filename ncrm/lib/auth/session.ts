import { cookies } from "next/headers";
import { createRepositoryClient, createSupabaseSessionClient } from "@/lib/supabase/client";

const ALLOWED_ROLES = ["owner", "admin"] as const;

export type AllowedStaffRole = (typeof ALLOWED_ROLES)[number];

export type CurrentStaff = {
  id: string;
  displayName: string | null;
  role: AllowedStaffRole;
};

type StaffRow = {
  id: string;
  display_name: string | null;
  role: string;
};

export function isAllowedStaffRole(role: string): role is AllowedStaffRole {
  return (ALLOWED_ROLES as readonly string[]).includes(role);
}

/**
 * Reads only the identity/role record. public.staff has deny-by-default RLS,
 * therefore this narrow server helper deliberately uses the repository client;
 * the browser and session clients never receive service-role access.
 */
export async function getStaffByUserId(userId: string): Promise<CurrentStaff | null> {
  const supabase = createRepositoryClient();
  const { data, error } = await supabase
    .from("staff")
    .select("id, display_name, role")
    .eq("id", userId)
    .maybeSingle();

  if (error) {
    throw new Error(`getStaffByUserId: ${error.message}`);
  }

  if (!data || !isAllowedStaffRole(data.role)) {
    return null;
  }

  const staff = data as StaffRow;
  return {
    id: staff.id,
    displayName: staff.display_name,
    role: staff.role as AllowedStaffRole
  };
}

export async function getCurrentStaff(): Promise<CurrentStaff | null> {
  const cookieStore = await cookies();
  const supabase = createSupabaseSessionClient({
    getAll: () => cookieStore.getAll(),
    // Token refresh is handled in middleware, where response cookies are writable.
    setAll: () => undefined
  });
  const {
    data: { user },
    error
  } = await supabase.auth.getUser();

  if (error || !user) {
    return null;
  }

  return getStaffByUserId(user.id);
}
