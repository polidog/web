/**
 * Drizzle ORM Usage Examples
 * このファイルは実際のコードではなく、使い方のリファレンスです
 */

import { db } from "@/db";
import { posts, type NewPost } from "@/db/schema";
import { eq, and, or, like, gt, lt, sql, desc } from "drizzle-orm";

/**
 * 基本的なCRUD操作
 */

// ==================== CREATE ====================

// 単一レコード挿入
async function createPost() {
  const [newPost] = await db
    .insert(posts)
    .values({
      title: "Sample Post",
      slug: "sample-post",
      content: "This is a sample post content",
      status: "draft",
      authorId: "user-id-here",
    })
    .returning();

  return newPost;
}

// 複数レコード挿入
async function createMultiplePosts() {
  const newPosts = await db
    .insert(posts)
    .values([
      {
        title: "First Post",
        slug: "first-post",
        content: "Content 1",
        status: "published",
        authorId: "user-id-here",
      },
      {
        title: "Second Post",
        slug: "second-post",
        content: "Content 2",
        status: "draft",
        authorId: "user-id-here",
      },
    ])
    .returning();

  return newPosts;
}

// ==================== READ ====================

// 全件取得
async function getAllPosts() {
  const allPosts = await db.select().from(posts);
  return allPosts;
}

// 条件付き取得
async function getPostById(id: number) {
  const [foundPost] = await db.select().from(posts).where(eq(posts.id, id));
  return foundPost;
}

// スラッグで検索
async function getPostBySlug(slug: string) {
  const [foundPost] = await db
    .select()
    .from(posts)
    .where(eq(posts.slug, slug));
  return foundPost;
}

// 複数条件 (AND)
async function getPostByTitleAndStatus(title: string, status: "draft" | "published") {
  const [post] = await db
    .select()
    .from(posts)
    .where(and(eq(posts.title, title), eq(posts.status, status)));
  return post;
}

// 複数条件 (OR)
async function getPostsByAuthorOrStatus(authorId: string, status: "draft" | "published") {
  const results = await db
    .select()
    .from(posts)
    .where(or(eq(posts.authorId, authorId), eq(posts.status, status)));
  return results;
}

// LIKE検索
async function searchPostsByTitle(searchTerm: string) {
  const results = await db
    .select()
    .from(posts)
    .where(like(posts.title, `%${searchTerm}%`));
  return results;
}

// ページネーション
async function getPostsPaginated(page = 1, pageSize = 10) {
  const offset = (page - 1) * pageSize;
  const results = await db.select().from(posts).limit(pageSize).offset(offset);
  return results;
}

// 並び替え
async function getPostsSorted() {
  const results = await db.select().from(posts).orderBy(desc(posts.createdAt));
  return results;
}

// 特定カラムのみ取得
async function getPostTitlesOnly() {
  const results = await db
    .select({
      id: posts.id,
      title: posts.title,
      slug: posts.slug,
    })
    .from(posts);
  return results;
}

// ==================== UPDATE ====================

// 単一レコード更新
async function updatePost(id: number, title: string) {
  const [updated] = await db
    .update(posts)
    .set({ title })
    .where(eq(posts.id, id))
    .returning();
  return updated;
}

// 複数カラム更新
async function updatePostFull(id: number, data: Partial<NewPost>) {
  const [updated] = await db
    .update(posts)
    .set(data)
    .where(eq(posts.id, id))
    .returning();
  return updated;
}

// ==================== DELETE ====================

// 単一レコード削除
async function deletePost(id: number) {
  await db.delete(posts).where(eq(posts.id, id));
}

// 条件付き削除
async function deletePostsByAuthor(authorId: string) {
  await db.delete(posts).where(eq(posts.authorId, authorId));
}

// ==================== ADVANCED ====================

// カウント
async function countPosts() {
  const [{ count }] = await db.select({ count: sql<number>`count(*)` }).from(posts);
  return count;
}

// トランザクション
async function createPostWithTransaction() {
  await db.transaction(async (tx) => {
    // トランザクション内で複数の操作
    const [post] = await tx
      .insert(posts)
      .values({
        title: "Test Post",
        slug: "test-post",
        content: "Test content",
        status: "draft",
        authorId: "user-id-here",
      })
      .returning();

    // 何か条件に応じてロールバック
    if (!post) {
      tx.rollback();
    }
  });
}

// 生SQLクエリ (必要な場合のみ使用)
async function rawSqlQuery() {
  // 型安全性が失われるため、通常は避けるべき
  const result = await db.run(
    sql`SELECT * FROM posts WHERE slug = ${"test-post"}`,
  );
  return result;
}

/**
 * Next.js App Router での使い方
 */

// Server Component での使用例
export async function PostsServerComponent() {
  const allPosts = await db.select().from(posts);

  return (
    <div>
      <h1>Posts</h1>
      <ul>
        {allPosts.map((post) => (
          <li key={post.id}>
            {post.title} - {post.status}
          </li>
        ))}
      </ul>
    </div>
  );
}

// Server Action での使用例
export async function createPostAction(formData: FormData) {
  "use server";

  const title = formData.get("title") as string;
  const slug = formData.get("slug") as string;
  const content = formData.get("content") as string;
  const authorId = formData.get("authorId") as string;

  const [newPost] = await db
    .insert(posts)
    .values({ title, slug, content, status: "draft", authorId })
    .returning();

  return newPost;
}

// Route Handler での使用例
// Note: このプロジェクトではServer Actionsを推奨しますが、
// 外部APIとして公開する必要がある場合はRoute Handlerを使用できます
