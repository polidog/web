import { and, eq } from "drizzle-orm";
import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { db } from "@/db";
import {
  posts,
  postCategories,
  categories,
  postTags,
  tags,
} from "@/db/schema";
import { Header } from "@/components/layout/header";
import { Footer } from "@/components/layout/footer";
import { Badge } from "@/components/ui/badge";
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

  // カテゴリを取得
  const postCategoryDataRaw = await db
    .select({
      id: categories.id,
      name: categories.name,
      slug: categories.slug,
    })
    .from(postCategories)
    .innerJoin(categories, eq(postCategories.categoryId, categories.id))
    .where(eq(postCategories.postId, post.id));

  // 重複を除去
  const postCategoryData = Array.from(
    new Map(postCategoryDataRaw.map((item) => [item.slug, item])).values(),
  );

  // タグを取得
  const postTagDataRaw = await db
    .select({
      id: tags.id,
      name: tags.name,
      slug: tags.slug,
    })
    .from(postTags)
    .innerJoin(tags, eq(postTags.tagId, tags.id))
    .where(eq(postTags.postId, post.id));

  // 重複を除去
  const postTagData = Array.from(
    new Map(postTagDataRaw.map((item) => [item.slug, item])).values(),
  );

  return (
    <div className="flex min-h-screen flex-col bg-white dark:bg-black">
      <Header />

      <main className="flex-1">
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

            <div className="flex flex-wrap items-center gap-4 text-sm mb-4">
              <time
                dateTime={post.publishedAt?.toISOString()}
                className="text-gray-500 dark:text-gray-500"
              >
                {post.publishedAt
                  ? new Date(post.publishedAt).toLocaleDateString("ja-JP", {
                      year: "numeric",
                      month: "long",
                      day: "numeric",
                    })
                  : "日付未設定"}
              </time>
            </div>

            {/* カテゴリとタグ */}
            {(postCategoryData.length > 0 || postTagData.length > 0) && (
              <div className="flex flex-wrap gap-4 mb-6">
                {/* カテゴリ */}
                {postCategoryData.length > 0 && (
                  <div className="flex items-center gap-2">
                    <span className="text-sm text-gray-600 dark:text-gray-400">
                      カテゴリ:
                    </span>
                    <div className="flex flex-wrap gap-2">
                      {postCategoryData.map((category) => (
                        <Link
                          key={category.slug}
                          href={`/blog/category/${category.slug}`}
                        >
                          <Badge variant="default">{category.name}</Badge>
                        </Link>
                      ))}
                    </div>
                  </div>
                )}

                {/* タグ */}
                {postTagData.length > 0 && (
                  <div className="flex items-center gap-2">
                    <span className="text-sm text-gray-600 dark:text-gray-400">
                      タグ:
                    </span>
                    <div className="flex flex-wrap gap-2">
                      {postTagData.map((tag) => (
                        <Link key={tag.slug} href={`/blog/tag/${tag.slug}`}>
                          <Badge variant="secondary">{tag.name}</Badge>
                        </Link>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            )}
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
      </main>

      <Footer />
    </div>
  );
}
