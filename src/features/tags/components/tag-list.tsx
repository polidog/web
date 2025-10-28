"use client";

import { Eye, Pencil, Trash2 } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import type { Tag } from "@/db/schema";
import { deleteTag } from "@/features/tags/actions/tags";
import { TagForm } from "./tag-form";

interface TagListProps {
  tags: Tag[];
}

export function TagList({ tags }: TagListProps) {
  const router = useRouter();
  const [editingTag, setEditingTag] = useState<Tag | null>(null);
  const [deletingTag, setDeletingTag] = useState<Tag | null>(null);
  const [isDeleting, setIsDeleting] = useState(false);

  const handleDelete = async () => {
    if (!deletingTag) return;

    setIsDeleting(true);
    const result = await deleteTag(deletingTag.id);

    if (result.success) {
      setDeletingTag(null);
      router.refresh();
    }
    setIsDeleting(false);
  };

  return (
    <>
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>タグ名</TableHead>
            <TableHead>スラッグ</TableHead>
            <TableHead>作成日</TableHead>
            <TableHead className="text-right">操作</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {tags.length === 0 ? (
            <TableRow>
              <TableCell
                colSpan={4}
                className="text-center text-muted-foreground"
              >
                タグがありません
              </TableCell>
            </TableRow>
          ) : (
            tags.map((tag) => (
              <TableRow key={tag.id}>
                <TableCell className="font-medium">
                  <Link
                    href={`/admin/tags/${tag.id}`}
                    className="hover:underline text-blue-600 dark:text-blue-400"
                  >
                    {tag.name}
                  </Link>
                </TableCell>
                <TableCell>{tag.slug}</TableCell>
                <TableCell>
                  {new Date(tag.createdAt).toLocaleDateString("ja-JP")}
                </TableCell>
                <TableCell className="text-right">
                  <div className="flex justify-end gap-2">
                    <Link href={`/admin/tags/${tag.id}`}>
                      <Button variant="ghost" size="icon" title="詳細を見る">
                        <Eye className="h-4 w-4" />
                      </Button>
                    </Link>
                    <Button
                      variant="ghost"
                      size="icon"
                      onClick={() => setEditingTag(tag)}
                      title="編集"
                    >
                      <Pencil className="h-4 w-4" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      onClick={() => setDeletingTag(tag)}
                      title="削除"
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      <Dialog
        open={!!editingTag}
        onOpenChange={(open) => !open && setEditingTag(null)}
      >
        <DialogContent>
          <DialogHeader>
            <DialogTitle>タグ編集</DialogTitle>
          </DialogHeader>
          {editingTag && (
            <TagForm tag={editingTag} onSuccess={() => setEditingTag(null)} />
          )}
        </DialogContent>
      </Dialog>

      <AlertDialog
        open={!!deletingTag}
        onOpenChange={(open) => !open && setDeletingTag(null)}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>タグを削除しますか?</AlertDialogTitle>
            <AlertDialogDescription>
              この操作は取り消せません。タグ「{deletingTag?.name}
              」を削除してもよろしいですか?
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={isDeleting}>
              キャンセル
            </AlertDialogCancel>
            <AlertDialogAction onClick={handleDelete} disabled={isDeleting}>
              {isDeleting ? "削除中..." : "削除"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}
