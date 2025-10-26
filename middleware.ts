import { eq } from "drizzle-orm";
import type { NextRequest } from "next/server";
import { NextResponse } from "next/server";
import { db } from "@/db";
import { posts } from "@/db/schema";
import { auth } from "@/features/auth/lib/auth";

export async function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;

  // Redirect old blog URL (/blog/[slug]) to new format (/blog/[year]/[month]/[slug])
  const oldBlogPattern = /^\/blog\/([^/]+)$/;
  const match = pathname.match(oldBlogPattern);

  if (match) {
    const slug = match[1];

    try {
      // Fetch post from database to get publishedAt date
      const [post] = await db
        .select()
        .from(posts)
        .where(eq(posts.slug, slug))
        .limit(1);

      if (post?.publishedAt) {
        const date = new Date(post.publishedAt);
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, "0");
        const newUrl = new URL(`/blog/${year}/${month}/${slug}`, request.url);
        return NextResponse.redirect(newUrl, 301);
      }
    } catch (error) {
      console.error("Error redirecting old blog URL:", error);
    }
  }

  // Redirect legacy polidog.jp URLs (/:year/:month/:day/:slug) to new format
  const legacyPattern = /^\/(\d{4})\/(\d{2})\/(\d{2})\/([^/]+)\/?$/;
  const legacyMatch = pathname.match(legacyPattern);

  if (legacyMatch) {
    const [, year, month, , slug] = legacyMatch;
    const newUrl = new URL(`/blog/${year}/${month}/${slug}`, request.url);
    return NextResponse.redirect(newUrl, 301);
  }

  // Protected admin routes
  if (pathname.startsWith("/admin")) {
    const session = await auth.api.getSession({
      headers: request.headers,
    });

    if (!session) {
      // Redirect to login if not authenticated
      const url = new URL("/login", request.url);
      return NextResponse.redirect(url);
    }
  }

  return NextResponse.next();
}

export const config = {
  matcher: [
    /*
     * Match all request paths except:
     * - api (API routes)
     * - _next/static (static files)
     * - _next/image (image optimization files)
     * - favicon.ico (favicon file)
     * - public folder
     */
    "/((?!api|_next/static|_next/image|favicon.ico|.*\\.(?:svg|png|jpg|jpeg|gif|webp)$).*)",
  ],
};
