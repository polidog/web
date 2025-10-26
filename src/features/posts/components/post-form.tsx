"use client";

import { useRouter } from "next/navigation";
import { useActionState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import type { Post } from "@/db/schema";
import { createPost, updatePost } from "@/features/posts/actions/posts";

interface PostFormProps {
  authorId: string;
  post?: Post;
}

export function PostForm({ authorId, post }: PostFormProps) {
  const router = useRouter();
  const isEdit = !!post;

  type ActionState = { success: boolean; error?: string; post?: Post };

  // Define action based on mode
  const action = isEdit
    ? async (
        _prevState: ActionState,
        formData: FormData,
      ): Promise<ActionState> => {
        const result = await updatePost(post.id, formData);
        if (result.success) {
          router.push("/admin/posts");
          router.refresh();
        }
        return result;
      }
    : async (
        _prevState: ActionState,
        formData: FormData,
      ): Promise<ActionState> => {
        const result = await createPost(formData);
        if (result.success) {
          router.push("/admin/posts");
          router.refresh();
        }
        return result;
      };

  const [state, formAction, isPending] = useActionState<ActionState, FormData>(
    action,
    { success: false },
  );

  return (
    <form action={formAction} className="space-y-6">
      <input type="hidden" name="authorId" value={authorId} />

      <div className="space-y-2">
        <Label htmlFor="title">タイトル</Label>
        <Input
          id="title"
          name="title"
          defaultValue={post?.title}
          required
          disabled={isPending}
          placeholder="記事のタイトルを入力"
        />
      </div>

      <div className="space-y-2">
        <Label htmlFor="slug">スラッグ</Label>
        <Input
          id="slug"
          name="slug"
          defaultValue={post?.slug}
          required
          disabled={isPending}
          placeholder="url-friendly-slug"
          pattern="[a-z0-9-]+"
          title="小文字英数字とハイフンのみ使用できます"
        />
        <p className="text-sm text-muted-foreground">
          URLに使用されます (例: blog-post-title)
        </p>
      </div>

      <div className="space-y-2">
        <Label htmlFor="content">本文</Label>
        <Textarea
          id="content"
          name="content"
          defaultValue={post?.content}
          required
          disabled={isPending}
          placeholder="記事の本文を入力..."
          className="min-h-[400px] font-mono"
        />
        <p className="text-sm text-muted-foreground">Markdownで記述できます</p>
      </div>

      {state && !state.success && "error" in state && (
        <div className="rounded-md bg-destructive/15 p-3 text-sm text-destructive">
          {state.error}
        </div>
      )}

      <div className="flex gap-4">
        <Button type="submit" disabled={isPending}>
          {isPending ? "保存中..." : isEdit ? "更新" : "作成"}
        </Button>
        <Button
          type="button"
          variant="outline"
          onClick={() => router.push("/admin/posts")}
          disabled={isPending}
        >
          キャンセル
        </Button>
      </div>
    </form>
  );
}
