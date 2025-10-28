"use client";

import { useRouter } from "next/navigation";
import { useActionState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import type { Tag } from "@/db/schema";
import { createTag, updateTag } from "@/features/tags/actions/tags";

interface TagFormProps {
  tag?: Tag;
  onSuccess?: () => void;
}

export function TagForm({ tag, onSuccess }: TagFormProps) {
  const router = useRouter();
  const isEdit = !!tag;

  type ActionState = { success: boolean; error?: string; tag?: Tag };

  const action = isEdit
    ? async (
        _prevState: ActionState,
        formData: FormData,
      ): Promise<ActionState> => {
        const result = await updateTag(tag.id, formData);
        if (result.success) {
          onSuccess?.();
          router.refresh();
        }
        return result;
      }
    : async (
        _prevState: ActionState,
        formData: FormData,
      ): Promise<ActionState> => {
        const result = await createTag(formData);
        if (result.success) {
          onSuccess?.();
          router.refresh();
        }
        return result;
      };

  const [state, formAction, isPending] = useActionState<ActionState, FormData>(
    action,
    { success: false },
  );

  return (
    <form action={formAction} className="space-y-4">
      <div className="space-y-2">
        <Label htmlFor="name">タグ名</Label>
        <Input
          id="name"
          name="name"
          defaultValue={tag?.name}
          required
          disabled={isPending}
          placeholder="タグ名を入力"
        />
      </div>

      <div className="space-y-2">
        <Label htmlFor="slug">スラッグ</Label>
        <Input
          id="slug"
          name="slug"
          defaultValue={tag?.slug}
          required
          disabled={isPending}
          placeholder="tag-slug"
          pattern="[a-z0-9-]+"
          title="小文字英数字とハイフンのみ使用できます"
        />
      </div>

      {state && !state.success && "error" in state && (
        <div className="rounded-md bg-destructive/15 p-3 text-sm text-destructive">
          {state.error}
        </div>
      )}

      <div className="flex gap-2">
        <Button type="submit" disabled={isPending}>
          {isPending ? "保存中..." : isEdit ? "更新" : "作成"}
        </Button>
      </div>
    </form>
  );
}
