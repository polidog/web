import { desc, eq } from "drizzle-orm";
import type { Metadata } from "next";
import Link from "next/link";
import { db } from "@/db";
import { posts } from "@/db/schema";

export const metadata: Metadata = {
  title: "Blog",
  description: "ブログ記事一覧",
};

export default async function BlogPage() {
  // 公開済みの記事のみ取得
  const publishedPosts = await db
    .select()
    .from(posts)
    .where(eq(posts.status, "published"))
    .orderBy(desc(posts.publishedAt));

  return (
    <div className="min-h-screen bg-white dark:bg-black">
      <div className="container mx-auto px-4 py-16 max-w-4xl">
        <header className="mb-12">
          <h1 className="text-4xl font-bold text-gray-900 dark:text-white mb-4">
            Blog
          </h1>
          <p className="text-lg text-gray-600 dark:text-gray-400">
            技術記事やメモを公開しています
          </p>
        </header>

        {publishedPosts.length === 0 ? (
          <div className="text-center py-16">
            <p className="text-gray-500 dark:text-gray-400">
              まだ記事がありません
            </p>
          </div>
        ) : (
          <div className="space-y-8">
            {publishedPosts.map((post) => {
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
        )}
      </div>
    </div>
  );
}
