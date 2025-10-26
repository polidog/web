import { and, eq } from "drizzle-orm";
import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { db } from "@/db";
import { posts } from "@/db/schema";
import { MarkdownContent } from "@/features/posts/components/markdown-content";

interface BlogPostPageProps {
  params: Promise<{ year: string; month: string; title: string }>;
}

// 動的メタデータ生成
export async function generateMetadata({
  params,
}: BlogPostPageProps): Promise<Metadata> {
  const { title } = await params;

  const [post] = await db
    .select()
    .from(posts)
    .where(and(eq(posts.slug, title), eq(posts.status, "published")));

  if (!post) {
    return {
      title: "記事が見つかりません",
    };
  }

  return {
    title: post.title,
    description: post.excerpt || post.title,
    openGraph: {
      title: post.title,
      description: post.excerpt || post.title,
      type: "article",
      publishedTime: post.publishedAt?.toISOString(),
    },
  };
}

export default async function BlogPostPage({ params }: BlogPostPageProps) {
  const { title } = await params;

  // 公開済みの記事のみ取得
  const [post] = await db
    .select()
    .from(posts)
    .where(and(eq(posts.slug, title), eq(posts.status, "published")));

  if (!post) {
    notFound();
  }

  return (
    <div className="min-h-screen bg-white dark:bg-black">
      <article className="container mx-auto px-4 py-16 max-w-4xl">
        {/* ヘッダー */}
        <header className="mb-12">
          <Link
            href="/blog"
            className="inline-flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white mb-8 transition-colors"
          >
            ← ブログ一覧に戻る
          </Link>

          <h1 className="text-4xl font-bold text-gray-900 dark:text-white mb-6">
            {post.title}
          </h1>

          <div className="flex items-center gap-4 text-sm text-gray-500 dark:text-gray-500">
            <time dateTime={post.publishedAt?.toISOString()}>
              {post.publishedAt
                ? new Date(post.publishedAt).toLocaleDateString("ja-JP", {
                    year: "numeric",
                    month: "long",
                    day: "numeric",
                  })
                : "日付未設定"}
            </time>
          </div>
        </header>

        {/* 本文（Markdownレンダリング） */}
        <div className="mb-12">
          <MarkdownContent content={post.content} />
        </div>

        {/* フッター */}
        <footer className="pt-8 border-t border-gray-200 dark:border-gray-800">
          <Link
            href="/blog"
            className="inline-flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors"
          >
            ← ブログ一覧に戻る
          </Link>
        </footer>
      </article>
    </div>
  );
}
