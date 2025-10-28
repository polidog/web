import { desc, eq } from "drizzle-orm";
import type { Metadata } from "next";
import Link from "next/link";
import { db } from "@/db";
import { posts } from "@/db/schema";
import { Header } from "@/components/layout/header";
import { Footer } from "@/components/layout/footer";

export const metadata: Metadata = {
  title: "Home",
  description: "最新のブログ記事をチェック",
};

export default async function Home() {
  // 公開済みの記事から最新10件を取得
  const latestPosts = await db
    .select()
    .from(posts)
    .where(eq(posts.status, "published"))
    .orderBy(desc(posts.publishedAt))
    .limit(10);

  return (
    <div className="flex min-h-screen flex-col bg-white dark:bg-black">
      <Header />

      <main className="flex-1">
        <div className="container mx-auto px-4 py-8 max-w-4xl">
          <section>

            {latestPosts.length === 0 ? (
              <div className="text-center py-16">
                <p className="text-gray-500 dark:text-gray-400">
                  まだ記事がありません
                </p>
              </div>
            ) : (
              <div className="space-y-8">
                {latestPosts.map((post) => {
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
                      key={post.id}
                      className="border-b border-gray-200 dark:border-gray-800 pb-8 last:border-b-0"
                    >
                      <Link
                        href={`/blog/${year}/${month}/${post.slug}`}
                        className="group block hover:opacity-80 transition-opacity"
                      >
                        <h3 className="text-2xl font-bold text-gray-900 dark:text-white mb-3 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                          {post.title}
                        </h3>

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
            )}
          </section>
        </div>
      </main>

      <Footer />
    </div>
  );
}
