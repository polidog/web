"use server";

import { desc, eq, sql } from "drizzle-orm";
import { revalidatePath } from "next/cache";
import { db } from "@/db";
import { postTags, posts, tags } from "@/db/schema";
import { getCurrentUser } from "@/features/auth/lib/auth-helpers";

/**
 * Create a new tag
 */
export async function createTag(formData: FormData) {
  try {
    const user = await getCurrentUser();
    if (!user) {
      return { success: false, error: "認証が必要です" };
    }

    const name = formData.get("name") as string;
    const slug = formData.get("slug") as string;

    if (!name || !slug) {
      return { success: false, error: "必須項目を入力してください" };
    }

    const [tag] = await db
      .insert(tags)
      .values({
        name,
        slug,
      })
      .returning();

    revalidatePath("/admin/tags");
    return { success: true, tag };
  } catch (error) {
    console.error("Failed to create tag:", error);
    return {
      success: false,
      error:
        error instanceof Error ? error.message : "タグの作成に失敗しました",
    };
  }
}

/**
 * Update an existing tag
 */
export async function updateTag(id: number, formData: FormData) {
  try {
    const user = await getCurrentUser();
    if (!user) {
      return { success: false, error: "認証が必要です" };
    }

    const name = formData.get("name") as string;
    const slug = formData.get("slug") as string;

    if (!name || !slug) {
      return { success: false, error: "必須項目を入力してください" };
    }

    const [updatedTag] = await db
      .update(tags)
      .set({
        name,
        slug,
      })
      .where(eq(tags.id, id))
      .returning();

    if (!updatedTag) {
      return { success: false, error: "タグが見つかりません" };
    }

    revalidatePath("/admin/tags");
    return { success: true, tag: updatedTag };
  } catch (error) {
    console.error("Failed to update tag:", error);
    return {
      success: false,
      error:
        error instanceof Error ? error.message : "タグの更新に失敗しました",
    };
  }
}

/**
 * Delete a tag
 */
export async function deleteTag(id: number) {
  try {
    const user = await getCurrentUser();
    if (!user) {
      return { success: false, error: "認証が必要です" };
    }

    await db.delete(tags).where(eq(tags.id, id));

    revalidatePath("/admin/tags");
    return { success: true };
  } catch (error) {
    console.error("Failed to delete tag:", error);
    return {
      success: false,
      error:
        error instanceof Error ? error.message : "タグの削除に失敗しました",
    };
  }
}

/**
 * Get all tags
 */
export async function getAllTags() {
  try {
    const allTags = await db.select().from(tags).orderBy(tags.name);
    return { success: true, tags: allTags };
  } catch (error) {
    console.error("Failed to fetch tags:", error);
    return {
      success: false,
      error:
        error instanceof Error ? error.message : "タグの取得に失敗しました",
      tags: [],
    };
  }
}

/**
 * Get a tag with its associated posts (with pagination)
 */
export async function getTagWithPosts(
  id: number,
  page = 1,
  perPage = 50,
) {
  try {
    // Get the tag
    const [tag] = await db.select().from(tags).where(eq(tags.id, id));

    if (!tag) {
      return { success: false, error: "タグが見つかりません" };
    }

    // Get total count of posts associated with this tag
    const [{ count }] = await db
      .select({ count: sql<number>`count(*)` })
      .from(posts)
      .innerJoin(postTags, eq(posts.id, postTags.postId))
      .where(eq(postTags.tagId, id));

    const totalPages = Math.ceil(count / perPage);
    const offset = (page - 1) * perPage;

    // Get paginated posts associated with this tag
    const tagPosts = await db
      .select({
        id: posts.id,
        title: posts.title,
        slug: posts.slug,
        status: posts.status,
        publishedAt: posts.publishedAt,
        createdAt: posts.createdAt,
      })
      .from(posts)
      .innerJoin(postTags, eq(posts.id, postTags.postId))
      .where(eq(postTags.tagId, id))
      .orderBy(desc(posts.createdAt))
      .limit(perPage)
      .offset(offset);

    return {
      success: true,
      tag,
      posts: tagPosts,
      totalCount: count,
      totalPages,
    };
  } catch (error) {
    console.error("Failed to fetch tag with posts:", error);
    return {
      success: false,
      error:
        error instanceof Error
          ? error.message
          : "タグと記事の取得に失敗しました",
    };
  }
}
