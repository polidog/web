"use client";

import { useRouter } from "next/navigation";
import { useActionState, useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { Textarea } from "@/components/ui/textarea";
import type { Category, Post, Tag } from "@/db/schema";
import { createPost, updatePost } from "@/features/posts/actions/posts";

interface PostFormProps {
  authorId: string;
  post?: Post;
  allCategories?: Category[];
  allTags?: Tag[];
  selectedCategoryIds?: number[];
  selectedTagIds?: number[];
}

export function PostForm({
  authorId,
  post,
  allCategories = [],
  allTags = [],
  selectedCategoryIds = [],
  selectedTagIds = [],
}: PostFormProps) {
  const router = useRouter();
  const isEdit = !!post;
  const [categoryIds, setCategoryIds] = useState<number[]>(selectedCategoryIds);
  const [tagIds, setTagIds] = useState<number[]>(selectedTagIds);
  const [status, setStatus] = useState<"draft" | "published">(
    post?.status || "draft"
  );
  const [publishedAt, setPublishedAt] = useState<string>(
    post?.publishedAt
      ? new Date(post.publishedAt).toISOString().slice(0, 16)
      : ""
  );

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
        <Label>ステータス</Label>
        <RadioGroup
          name="status"
          value={status}
          onValueChange={(value) => setStatus(value as "draft" | "published")}
          disabled={isPending}
        >
          <div className="flex items-center space-x-2">
            <RadioGroupItem value="draft" id="status-draft" />
            <Label htmlFor="status-draft" className="font-normal cursor-pointer">
              下書き
            </Label>
          </div>
          <div className="flex items-center space-x-2">
            <RadioGroupItem value="published" id="status-published" />
            <Label
              htmlFor="status-published"
              className="font-normal cursor-pointer"
            >
              公開
            </Label>
          </div>
        </RadioGroup>
      </div>

      {status === "published" && (
        <div className="space-y-2">
          <Label htmlFor="publishedAt">公開日時 (オプション)</Label>
          <Input
            id="publishedAt"
            name="publishedAt"
            type="datetime-local"
            value={publishedAt}
            onChange={(e) => setPublishedAt(e.target.value)}
            disabled={isPending}
          />
          <p className="text-sm text-muted-foreground">
            空欄の場合は現在日時が設定されます
          </p>
        </div>
      )}

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

      <div className="space-y-2">
        <Label>カテゴリ</Label>
        <div className="grid grid-cols-2 gap-2 border rounded-md p-3 max-h-48 overflow-y-auto">
          {allCategories.length === 0 ? (
            <p className="text-sm text-muted-foreground col-span-2">
              カテゴリがありません
            </p>
          ) : (
            allCategories.map((category) => (
              <label
                key={category.id}
                className="flex items-center space-x-2 cursor-pointer"
              >
                <input
                  type="checkbox"
                  name="categoryIds"
                  value={category.id}
                  checked={categoryIds.includes(category.id)}
                  onChange={(e) => {
                    if (e.target.checked) {
                      setCategoryIds([...categoryIds, category.id]);
                    } else {
                      setCategoryIds(
                        categoryIds.filter((id) => id !== category.id),
                      );
                    }
                  }}
                  disabled={isPending}
                  className="rounded"
                />
                <span className="text-sm">{category.name}</span>
              </label>
            ))
          )}
        </div>
      </div>

      <div className="space-y-2">
        <Label>タグ</Label>
        <div className="grid grid-cols-2 gap-2 border rounded-md p-3 max-h-48 overflow-y-auto">
          {allTags.length === 0 ? (
            <p className="text-sm text-muted-foreground col-span-2">
              タグがありません
            </p>
          ) : (
            allTags.map((tag) => (
              <label
                key={tag.id}
                className="flex items-center space-x-2 cursor-pointer"
              >
                <input
                  type="checkbox"
                  name="tagIds"
                  value={tag.id}
                  checked={tagIds.includes(tag.id)}
                  onChange={(e) => {
                    if (e.target.checked) {
                      setTagIds([...tagIds, tag.id]);
                    } else {
                      setTagIds(tagIds.filter((id) => id !== tag.id));
                    }
                  }}
                  disabled={isPending}
                  className="rounded"
                />
                <span className="text-sm">{tag.name}</span>
              </label>
            ))
          )}
        </div>
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
