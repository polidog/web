import { db } from "@/db";
import { posts } from "@/db/schema";
import { eq, desc } from "drizzle-orm";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { FileText, FilePlus, Clock } from "lucide-react";
import Link from "next/link";

export default async function DashboardPage() {
  // Get post statistics
  const allPosts = await db.select().from(posts);
  const publishedPosts = allPosts.filter((post) => post.status === "published");
  const draftPosts = allPosts.filter((post) => post.status === "draft");

  // Get recent posts
  const recentPosts = await db
    .select()
    .from(posts)
    .orderBy(desc(posts.createdAt))
    .limit(5);

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold text-gray-900 dark:text-white">
          ダッシュボード
        </h1>
        <p className="mt-2 text-gray-600 dark:text-gray-400">
          ブログ管理の概要
        </p>
      </div>

      {/* Statistics Cards */}
      <div className="grid gap-4 md:grid-cols-3">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">総記事数</CardTitle>
            <FileText className="h-4 w-4 text-gray-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{allPosts.length}</div>
            <p className="text-xs text-gray-500 dark:text-gray-400">
              全ての記事
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">公開済み</CardTitle>
            <FilePlus className="h-4 w-4 text-green-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{publishedPosts.length}</div>
            <p className="text-xs text-gray-500 dark:text-gray-400">
              公開中の記事
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">下書き</CardTitle>
            <Clock className="h-4 w-4 text-yellow-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{draftPosts.length}</div>
            <p className="text-xs text-gray-500 dark:text-gray-400">
              下書き中の記事
            </p>
          </CardContent>
        </Card>
      </div>

      {/* Recent Posts */}
      <Card>
        <CardHeader>
          <CardTitle>最近の記事</CardTitle>
        </CardHeader>
        <CardContent>
          {recentPosts.length === 0 ? (
            <div className="py-8 text-center">
              <p className="text-gray-500 dark:text-gray-400">
                まだ記事がありません
              </p>
              <Link
                href="/admin/posts/new"
                className="mt-4 inline-block text-sm text-blue-600 hover:underline dark:text-blue-400"
              >
                最初の記事を作成
              </Link>
            </div>
          ) : (
            <div className="space-y-4">
              {recentPosts.map((post) => (
                <div
                  key={post.id}
                  className="flex items-center justify-between border-b border-gray-200 pb-3 last:border-0 dark:border-gray-700"
                >
                  <div>
                    <Link
                      href={`/admin/posts/${post.id}/edit`}
                      className="font-medium text-gray-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-400"
                    >
                      {post.title}
                    </Link>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                      {new Date(post.createdAt).toLocaleDateString("ja-JP")}
                    </p>
                  </div>
                  <span
                    className={`rounded-full px-3 py-1 text-xs font-medium ${
                      post.status === "published"
                        ? "bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200"
                        : "bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200"
                    }`}
                  >
                    {post.status === "published" ? "公開" : "下書き"}
                  </span>
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
