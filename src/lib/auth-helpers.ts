import { auth } from "@/lib/auth";
import { headers } from "next/headers";
import { redirect } from "next/navigation";
import type { Session, User } from "better-auth";

/**
 * Get current session from server components
 * @returns Session and user data or null if not authenticated
 */
export async function getSession(): Promise<{
  session: Session;
  user: User;
} | null> {
  const session = await auth.api.getSession({
    headers: await headers(),
  });

  return session;
}

/**
 * Require authentication in server components
 * Redirects to login if not authenticated
 * @returns Session and user data
 */
export async function requireAuth() {
  const session = await getSession();

  if (!session) {
    redirect("/login");
  }

  return session;
}

/**
 * Get current user from server components
 * @returns User data or null if not authenticated
 */
export async function getCurrentUser() {
  const session = await getSession();
  return session?.user ?? null;
}
