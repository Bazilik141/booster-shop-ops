import { NextResponse, type NextRequest } from "next/server";
import { getStaffByUserId } from "@/lib/auth/session";
import { createSupabaseSessionClient } from "@/lib/supabase/client";

const LOGIN_PATH = "/login";
const ACCESS_PENDING_PATH = "/access-pending";

function redirect(request: NextRequest, pathname: string): NextResponse {
  const url = request.nextUrl.clone();
  url.pathname = pathname;
  url.search = "";
  return NextResponse.redirect(url);
}

export async function middleware(request: NextRequest) {
  let sessionCookies: { name: string; value: string; options?: Record<string, unknown> }[] = [];
  const supabase = createSupabaseSessionClient({
    getAll: () => request.cookies.getAll(),
    setAll: (cookies) => {
      sessionCookies = cookies;
    }
  });
  const {
    data: { user }
  } = await supabase.auth.getUser();
  const pathname = request.nextUrl.pathname;
  let response: NextResponse;

  if (!user) {
    response = pathname === LOGIN_PATH ? NextResponse.next() : redirect(request, LOGIN_PATH);
  } else {
    const staff = await getStaffByUserId(user.id);

    if (!staff) {
      response = pathname === ACCESS_PENDING_PATH
        ? NextResponse.next()
        : redirect(request, ACCESS_PENDING_PATH);
    } else if (pathname === LOGIN_PATH || pathname === ACCESS_PENDING_PATH) {
      response = redirect(request, "/");
    } else {
      response = NextResponse.next();
    }
  }

  for (const cookie of sessionCookies) {
    response.cookies.set(cookie.name, cookie.value, cookie.options);
  }

  return response;
}

export const config = {
  matcher: ["/((?!_next/static|_next/image|favicon.ico).*)"]
};
