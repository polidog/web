import { and, desc, eq, sql } from "drizzle-orm";
import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { db } from "@/db";
import { posts, postCategories, categories } from "@/db/schema";
import { Header } from "@/components/layout/header";
import { Footer } from "@/components/layout/footer";
import { Pagination } from "@/components/ui/pagination";

interface CategoryPageProps {
  params: Promise<{ slug: string }>;
  searchParams: Promise<{ page?: string }>;
}

// 動的メタデータ生成
export async function generateMetadata({
  params,
}: CategoryPageProps): Promise<Metadata> {
  const { slug } = await params;

  const [category] = await db
    .select()
    .from(categories)
    .where(eq(categories.slug, slug));

  if (!category) {
    return {
      title: "カテゴリが見つかりません",
    };
  }

  return {
    title: `${category.name}`,
    description: `${category.name}カテゴリの記事一覧`,
  };
}

export default async function CategoryPage({
  params,
  searchParams,
}: CategoryPageProps) {
  const { slug } = await params;
  const searchParamsResolved = await searchParams;
  const page = Number(searchParamsResolved.page) || 1;
  const perPage = 10;
  const offset = (page - 1) * perPage;

  // カテゴリを取得
  const [category] = await db
    .select()
    .from(categories)
    .where(eq(categories.slug, slug));

  if (!category) {
    notFound();
  }

  // カテゴリに紐づく公開済み記事を取得
  const categoryPostsRaw = await db
    .select({
      id: posts.id,
      title: posts.title,
      slug: posts.slug,
      excerpt: posts.excerpt,
      publishedAt: posts.publishedAt,
    })
    .from(posts)
    .innerJoin(postCategories, eq(posts.id, postCategories.postId))
    .where(
      and(
        eq(postCategories.categoryId, category.id),
        eq(posts.status, "published"),
      ),
    )
    .orderBy(desc(posts.publishedAt))
    .limit(perPage)
    .offset(offset);

  // 重複を除去
  const categoryPosts = Array.from(
    new Map(categoryPostsRaw.map((item) => [item.slug, item])).values(),
  );

  // 総記事数を取得
  const [{ count }] = await db
    .select({ count: sql<number>`count(*)` })
    .from(posts)
    .innerJoin(postCategories, eq(posts.id, postCategories.postId))
    .where(
      and(
        eq(postCategories.categoryId, category.id),
        eq(posts.status, "published"),
      ),
    );

  const totalPages = Math.ceil(count / perPage);

  return (
    <div className="flex min-h-screen flex-col bg-white dark:bg-black">
      <Header />

      <main className="flex-1">
        <div className="container mx-auto px-4 py-16 max-w-4xl">
          <header className="mb-12">
            <Link
              href="/blog"
              className="inline-flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white mb-8 transition-colors"
            >
              ← ブログ一覧に戻る
            </Link>

            <h1 className="text-4xl font-bold text-gray-900 dark:text-white mb-4">
              カテゴリ: {category.name}
            </h1>
            <p className="text-lg text-gray-600 dark:text-gray-400">
              {count}件の記事
            </p>
          </header>

          {count === 0 ? (
            <div className="text-center py-16">
              <p className="text-gray-500 dark:text-gray-400">
                このカテゴリに記事がありません
              </p>
            </div>
          ) : (
            <>
              <div className="space-y-8">
                {categoryPosts.map((post) => {
                  const publishedDate = post.publishedAt
                    ? new Date(post.publishedAt)
                    : new Date();
                  const year = publishedDate.getFullYear();
                  const month = String(publishedDate.getMonth() + 1).padStart(
                    2,
                    "0",
                  );

                  return (
                    <article
                      key={post.slug}
                      className="border-b border-gray-200 dark:border-gray-800 pb-8 last:border-b-0"
                    >
                      <Link
                        href={`/blog/${year}/${month}/${post.slug}`}
                        className="group block hover:opacity-80 transition-opacity"
                      >
                        <h2 className="text-2xl font-bold text-gray-900 dark:text-white mb-3 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                          {post.title}
                        </h2>

                        {post.excerpt && (
                          <p className="text-gray-600 dark:text-gray-400 mb-4 line-clamp-3">
                            {post.excerpt}
                          </p>
                        )}

                        <div className="flex items-center gap-4 text-sm text-gray-500 dark:text-gray-500">
                          <time dateTime={post.publishedAt?.toISOString()}>
                            {post.publishedAt
                              ? new Date(post.publishedAt).toLocaleDateString(
                                  "ja-JP",
                                  {
                                    year: "numeric",
                                    month: "long",
                                    day: "numeric",
                                  },
                                )
                              : "日付未設定"}
                          </time>
                        </div>
                      </Link>
                    </article>
                  );
                })}
              </div>

              {totalPages > 1 && (
                <div className="mt-12">
                  <Pagination currentPage={page} totalPages={totalPages} />
                </div>
              )}
            </>
          )}
        </div>
      </main>

      <Footer />
    </div>
  );
}
