import { eq } from "drizzle-orm";
import { notFound } from "next/navigation";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { db } from "@/db";
import { categories, postCategories, postTags, posts, tags } from "@/db/schema";
import { requireAuth } from "@/features/auth/lib/auth-helpers";
import { PostForm } from "@/features/posts/components/post-form";

interface EditPostPageProps {
  params: Promise<{ id: string }>;
}

export default async function EditPostPage({ params }: EditPostPageProps) {
  const { user } = await requireAuth();
  const { id } = await params;
  const postId = Number.parseInt(id, 10);

  if (Number.isNaN(postId)) {
    notFound();
  }

  const [post] = await db.select().from(posts).where(eq(posts.id, postId));

  if (!post) {
    notFound();
  }

  // Fetch all categories and tags
  const [allCategories, allTags] = await Promise.all([
    db.select().from(categories).orderBy(categories.name),
    db.select().from(tags).orderBy(tags.name),
  ]);

  // Fetch post's current categories and tags
  const [postCategoryIds, postTagIds] = await Promise.all([
    db
      .select({ categoryId: postCategories.categoryId })
      .from(postCategories)
      .where(eq(postCategories.postId, postId)),
    db
      .select({ tagId: postTags.tagId })
      .from(postTags)
      .where(eq(postTags.postId, postId)),
  ]);

  return (
    <div className="container mx-auto py-8 max-w-4xl">
      <Card>
        <CardHeader>
          <CardTitle>記事編集</CardTitle>
        </CardHeader>
        <CardContent>
          <PostForm
            authorId={user.id}
            post={post}
            allCategories={allCategories}
            allTags={allTags}
            selectedCategoryIds={postCategoryIds.map((pc) => pc.categoryId)}
            selectedTagIds={postTagIds.map((pt) => pt.tagId)}
          />
        </CardContent>
      </Card>
    </div>
  );
}
