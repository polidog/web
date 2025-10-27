"use server";

import { eq } from "drizzle-orm";
import { revalidatePath } from "next/cache";
import { db } from "@/db";
import { postCategories, postTags, posts } from "@/db/schema";
import { getCurrentUser } from "@/features/auth/lib/auth-helpers";

/**
 * Create a new post
 */
export async function createPost(formData: FormData) {
  try {
    const user = await getCurrentUser();
    if (!user) {
      return { success: false, error: "認証が必要です" };
    }

    const title = formData.get("title") as string;
    const slug = formData.get("slug") as string;
    const content = formData.get("content") as string;
    const excerpt = formData.get("excerpt") as string;
    const status = (formData.get("status") as "draft" | "published") || "draft";
    const publishedAtInput = formData.get("publishedAt") as string;
    const categoryIds = formData
      .getAll("categoryIds")
      .map((id) => Number(id))
      .filter((id) => !Number.isNaN(id));
    const tagIds = formData
      .getAll("tagIds")
      .map((id) => Number(id))
      .filter((id) => !Number.isNaN(id));

    if (!title || !slug || !content) {
      return { success: false, error: "必須項目を入力してください" };
    }

    const [post] = await db
      .insert(posts)
      .values({
        title,
        slug,
        content,
        excerpt: excerpt || null,
        status,
        authorId: user.id,
        publishedAt:
          status === "published"
            ? publishedAtInput
              ? new Date(publishedAtInput)
              : new Date()
            : null,
      })
      .returning();

    // Insert category relations
    if (categoryIds.length > 0) {
      await db.insert(postCategories).values(
        categoryIds.map((categoryId) => ({
          postId: post.id,
          categoryId,
        })),
      );
    }

    // Insert tag relations
    if (tagIds.length > 0) {
      await db.insert(postTags).values(
        tagIds.map((tagId) => ({
          postId: post.id,
          tagId,
        })),
      );
    }

    revalidatePath("/admin/posts");
    return { success: true, post };
  } catch (error) {
    console.error("Failed to create post:", error);
    return {
      success: false,
      error:
        error instanceof Error ? error.message : "記事の作成に失敗しました",
    };
  }
}

/**
 * Update an existing post
 */
export async function updatePost(id: number, formData: FormData) {
  try {
    const user = await getCurrentUser();
    if (!user) {
      return { success: false, error: "認証が必要です" };
    }

    const title = formData.get("title") as string;
    const slug = formData.get("slug") as string;
    const content = formData.get("content") as string;
    const excerpt = formData.get("excerpt") as string;
    const status = (formData.get("status") as "draft" | "published") || "draft";
    const publishedAtInput = formData.get("publishedAt") as string;
    const categoryIds = formData
      .getAll("categoryIds")
      .map((id) => Number(id))
      .filter((id) => !Number.isNaN(id));
    const tagIds = formData
      .getAll("tagIds")
      .map((id) => Number(id))
      .filter((id) => !Number.isNaN(id));

    if (!title || !slug || !content) {
      return { success: false, error: "必須項目を入力してください" };
    }

    // Get current post to check if status changed
    const [currentPost] = await db.select().from(posts).where(eq(posts.id, id));

    if (!currentPost) {
      return { success: false, error: "記事が見つかりません" };
    }

    const [updatedPost] = await db
      .update(posts)
      .set({
        title,
        slug,
        content,
        excerpt: excerpt || null,
        status,
        publishedAt:
          status === "published"
            ? publishedAtInput
              ? new Date(publishedAtInput)
              : currentPost.status === "draft"
                ? new Date()
                : currentPost.publishedAt
            : null,
      })
      .where(eq(posts.id, id))
      .returning();

    // Update category relations
    await db.delete(postCategories).where(eq(postCategories.postId, id));
    if (categoryIds.length > 0) {
      await db.insert(postCategories).values(
        categoryIds.map((categoryId) => ({
          postId: id,
          categoryId,
        })),
      );
    }

    // Update tag relations
    await db.delete(postTags).where(eq(postTags.postId, id));
    if (tagIds.length > 0) {
      await db.insert(postTags).values(
        tagIds.map((tagId) => ({
          postId: id,
          tagId,
        })),
      );
    }

    revalidatePath("/admin/posts");
    revalidatePath(`/admin/posts/${id}/edit`);
    return { success: true, post: updatedPost };
  } catch (error) {
    console.error("Failed to update post:", error);
    return {
      success: false,
      error:
        error instanceof Error ? error.message : "記事の更新に失敗しました",
    };
  }
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
