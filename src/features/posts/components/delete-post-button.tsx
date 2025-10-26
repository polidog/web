"use client";

import { Trash2 } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { deletePost } from "@/features/posts/actions/posts";

interface DeletePostButtonProps {
  postId: number;
}

export function DeletePostButton({ postId }: DeletePostButtonProps) {
  const [isDeleting, setIsDeleting] = useState(false);

  const handleDelete = async () => {
    if (!confirm("この記事を削除してもよろしいですか?")) {
      return;
    }

    try {
      setIsDeleting(true);
      await deletePost(postId);
    } catch (_error) {
      alert("削除に失敗しました");
      setIsDeleting(false);
    }
  };

  return (
    <Button
      variant="outline"
      size="sm"
      onClick={handleDelete}
      disabled={isDeleting}
      className="text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-900/20"
    >
      <Trash2 className="h-4 w-4" />
    </Button>
  );
}
