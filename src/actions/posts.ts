"use server";

import { db } from "@/db";
import { posts } from "@/db/schema";
import { getCurrentUser } from "@/lib/auth-helpers";
import { eq } from "drizzle-orm";
import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";

/**
 * Create a new post
 */
export async function createPost(formData: FormData) {
  const user = await getCurrentUser();
  if (!user) {
    throw new Error("認証が必要です");
  }

  const title = formData.get("title") as string;
  const slug = formData.get("slug") as string;
  const content = formData.get("content") as string;
  const excerpt = formData.get("excerpt") as string;
  const status = formData.get("status") as "draft" | "published";

  if (!title || !slug || !content) {
    throw new Error("必須項目を入力してください");
  }

  const [post] = await db
    .insert(posts)
    .values({
      title,
      slug,
      content,
      excerpt: excerpt || null,
      status: status || "draft",
      authorId: user.id,
      publishedAt: status === "published" ? new Date() : null,
    })
    .returning();

  revalidatePath("/admin/posts");
  redirect("/admin/posts");
}

/**
 * Update an existing post
 */
export async function updatePost(id: number, formData: FormData) {
  const user = await getCurrentUser();
  if (!user) {
    throw new Error("認証が必要です");
  }

  const title = formData.get("title") as string;
  const slug = formData.get("slug") as string;
  const content = formData.get("content") as string;
  const excerpt = formData.get("excerpt") as string;
  const status = formData.get("status") as "draft" | "published";

  if (!title || !slug || !content) {
    throw new Error("必須項目を入力してください");
  }

  // Get current post to check if status changed
  const [currentPost] = await db.select().from(posts).where(eq(posts.id, id));

  await db
    .update(posts)
    .set({
      title,
      slug,
      content,
      excerpt: excerpt || null,
      status: status || "draft",
      publishedAt:
        status === "published" && currentPost.status === "draft"
          ? new Date()
          : currentPost.publishedAt,
    })
    .where(eq(posts.id, id));

  revalidatePath("/admin/posts");
  revalidatePath(`/admin/posts/${id}/edit`);
  redirect("/admin/posts");
}

/**
 * Delete a post
 */
export async function deletePost(id: number) {
  const user = await getCurrentUser();
  if (!user) {
    throw new Error("認証が必要です");
  }

  await db.delete(posts).where(eq(posts.id, id));

  revalidatePath("/admin/posts");
}

/**
 * Publish a draft post
 */
export async function publishPost(id: number) {
  const user = await getCurrentUser();
  if (!user) {
    throw new Error("認証が必要です");
  }

  await db
    .update(posts)
    .set({
      status: "published",
      publishedAt: new Date(),
    })
    .where(eq(posts.id, id));

  revalidatePath("/admin/posts");
  revalidatePath(`/admin/posts/${id}/edit`);
}

/**
 * Unpublish a post (revert to draft)
 */
export async function unpublishPost(id: number) {
  const user = await getCurrentUser();
  if (!user) {
    throw new Error("認証が必要です");
  }

  await db
    .update(posts)
    .set({
      status: "draft",
    })
    .where(eq(posts.id, id));

  revalidatePath("/admin/posts");
  revalidatePath(`/admin/posts/${id}/edit`);
}
