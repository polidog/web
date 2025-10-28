"use server";

import { desc, eq, sql } from "drizzle-orm";
import { revalidatePath } from "next/cache";
import { db } from "@/db";
import { categories, postCategories, posts } from "@/db/schema";
import { getCurrentUser } from "@/features/auth/lib/auth-helpers";

/**
 * Create a new category
 */
export async function createCategory(formData: FormData) {
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

    const [category] = await db
      .insert(categories)
      .values({
        name,
        slug,
      })
      .returning();

    revalidatePath("/admin/categories");
    return { success: true, category };
  } catch (error) {
    console.error("Failed to create category:", error);
    return {
      success: false,
      error:
        error instanceof Error ? error.message : "カテゴリの作成に失敗しました",
    };
  }
}

/**
 * Update an existing category
 */
export async function updateCategory(id: number, formData: FormData) {
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

    const [updatedCategory] = await db
      .update(categories)
      .set({
        name,
        slug,
      })
      .where(eq(categories.id, id))
      .returning();

    if (!updatedCategory) {
      return { success: false, error: "カテゴリが見つかりません" };
    }

    revalidatePath("/admin/categories");
    return { success: true, category: updatedCategory };
  } catch (error) {
    console.error("Failed to update category:", error);
    return {
      success: false,
      error:
        error instanceof Error ? error.message : "カテゴリの更新に失敗しました",
    };
  }
}

/**
 * Delete a category
 */
export async function deleteCategory(id: number) {
  try {
    const user = await getCurrentUser();
    if (!user) {
      return { success: false, error: "認証が必要です" };
    }

    await db.delete(categories).where(eq(categories.id, id));

    revalidatePath("/admin/categories");
    return { success: true };
  } catch (error) {
    console.error("Failed to delete category:", error);
    return {
      success: false,
      error:
        error instanceof Error ? error.message : "カテゴリの削除に失敗しました",
    };
  }
}

/**
 * Get all categories
 */
export async function getAllCategories() {
  try {
    const allCategories = await db
      .select()
      .from(categories)
      .orderBy(categories.name);
    return { success: true, categories: allCategories };
  } catch (error) {
    console.error("Failed to fetch categories:", error);
    return {
      success: false,
      error:
        error instanceof Error ? error.message : "カテゴリの取得に失敗しました",
      categories: [],
    };
  }
}

/**
 * Get a category with its associated posts (with pagination)
 */
export async function getCategoryWithPosts(
  id: number,
  page = 1,
  perPage = 50,
) {
  try {
    // Get the category
    const [category] = await db
      .select()
      .from(categories)
      .where(eq(categories.id, id));

    if (!category) {
      return { success: false, error: "カテゴリが見つかりません" };
    }

    // Get total count of posts associated with this category
    const [{ count }] = await db
      .select({ count: sql<number>`count(*)` })
      .from(posts)
      .innerJoin(postCategories, eq(posts.id, postCategories.postId))
      .where(eq(postCategories.categoryId, id));

    const totalPages = Math.ceil(count / perPage);
    const offset = (page - 1) * perPage;

    // Get paginated posts associated with this category
    const categoryPosts = await db
      .select({
        id: posts.id,
        title: posts.title,
        slug: posts.slug,
        status: posts.status,
        publishedAt: posts.publishedAt,
        createdAt: posts.createdAt,
      })
      .from(posts)
      .innerJoin(postCategories, eq(posts.id, postCategories.postId))
      .where(eq(postCategories.categoryId, id))
      .orderBy(desc(posts.createdAt))
      .limit(perPage)
      .offset(offset);

    return {
      success: true,
      category,
      posts: categoryPosts,
      totalCount: count,
      totalPages,
    };
  } catch (error) {
    console.error("Failed to fetch category with posts:", error);
    return {
      success: false,
      error:
        error instanceof Error
          ? error.message
          : "カテゴリと記事の取得に失敗しました",
    };
  }
}
