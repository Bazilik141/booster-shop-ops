"use client";

import { useRouter } from "next/navigation";
import { createSupabaseBrowserAppClient } from "@/lib/supabase/client";

export function SignOutButton() {
  const router = useRouter();

  async function signOut() {
    const supabase = createSupabaseBrowserAppClient();
    await supabase.auth.signOut();
    router.replace("/login");
    router.refresh();
  }

  return <button type="button" onClick={signOut}>Вийти</button>;
}
