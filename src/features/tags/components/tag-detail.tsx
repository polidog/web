import { ArrowLeft, Calendar, Edit } from "lucide-react";
import Link from "next/link";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Pagination } from "@/components/ui/pagination";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import type { Tag } from "@/db/schema";

interface TagDetailProps {
  tag: Tag;
  posts: Array<{
    id: number;
    title: string;
    slug: string;
    status: "draft" | "published";
    publishedAt: Date | null;
    createdAt: Date;
  }>;
  currentPage: number;
  totalPages: number;
  totalCount: number;
}

export function TagDetail({
  tag,
  posts,
  currentPage,
  totalPages,
  totalCount,
}: TagDetailProps) {
  const formatDate = (date: Date | null) => {
    if (!date) return "-";
    return new Date(date).toLocaleDateString("ja-JP", {
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
    });
  };

  return (
    <div className="container mx-auto py-8 max-w-6xl space-y-6">
      {/* Back button */}
      <div>
        <Link href="/admin/tags">
          <Button variant="outline" size="sm">
            <ArrowLeft className="mr-2 h-4 w-4" />
            タグ一覧に戻る
          </Button>
        </Link>
      </div>

      {/* Tag info card */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center justify-between">
            <span>タグ詳細</span>
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <p className="text-sm font-medium text-gray-500 dark:text-gray-400">
                タグ名
              </p>
              <p className="text-lg font-semibold">{tag.name}</p>
            </div>
            <div>
              <p className="text-sm font-medium text-gray-500 dark:text-gray-400">
                スラッグ
              </p>
              <p className="text-lg font-mono">{tag.slug}</p>
            </div>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <p className="text-sm font-medium text-gray-500 dark:text-gray-400">
                作成日時
              </p>
              <p className="text-sm">{formatDate(tag.createdAt)}</p>
            </div>
            <div>
              <p className="text-sm font-medium text-gray-500 dark:text-gray-400">
                更新日時
              </p>
              <p className="text-sm">{formatDate(tag.updatedAt)}</p>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Posts card */}
      <Card>
        <CardHeader>
          <CardTitle>
            紐づけられている記事 ({posts.length}件
            {totalCount > posts.length && ` / 全${totalCount}件`})
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          {totalCount === 0 ? (
            <div className="text-center py-8 text-muted-foreground">
              このタグに紐づけられている記事はありません
            </div>
          ) : (
            <>
              <div className="rounded-md border">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>タイトル</TableHead>
                      <TableHead>ステータス</TableHead>
                      <TableHead>公開日</TableHead>
                      <TableHead>作成日</TableHead>
                      <TableHead className="w-[100px]">操作</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {posts.map((post) => (
                      <TableRow key={post.id}>
                        <TableCell className="font-medium">
                          {post.title}
                        </TableCell>
                        <TableCell>
                          <span
                            className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ${
                              post.status === "published"
                                ? "bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300"
                                : "bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300"
                            }`}
                          >
                            {post.status === "published" ? "公開" : "下書き"}
                          </span>
                        </TableCell>
                        <TableCell>
                          <div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                            <Calendar className="h-4 w-4" />
                            {formatDate(post.publishedAt)}
                          </div>
                        </TableCell>
                        <TableCell>
                          <div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                            <Calendar className="h-4 w-4" />
                            {formatDate(post.createdAt)}
                          </div>
                        </TableCell>
                        <TableCell>
                          <Link href={`/admin/posts/${post.id}/edit`}>
                            <Button variant="ghost" size="sm">
                              <Edit className="h-4 w-4" />
                            </Button>
                          </Link>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
              <Pagination currentPage={currentPage} totalPages={totalPages} />
            </>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
