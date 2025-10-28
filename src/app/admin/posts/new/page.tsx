import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { db } from "@/db";
import { categories, tags } from "@/db/schema";
import { requireAuth } from "@/features/auth/lib/auth-helpers";
import { PostForm } from "@/features/posts/components/post-form";

export default async function NewPostPage() {
  const { user } = await requireAuth();

  // Fetch all categories and tags
  const [allCategories, allTags] = await Promise.all([
    db.select().from(categories).orderBy(categories.name),
    db.select().from(tags).orderBy(tags.name),
  ]);

  return (
    <div className="container mx-auto py-8 max-w-4xl">
      <Card>
        <CardHeader>
          <CardTitle>新規記事作成</CardTitle>
        </CardHeader>
        <CardContent>
          <PostForm
            authorId={user.id}
            allCategories={allCategories}
            allTags={allTags}
          />
        </CardContent>
      </Card>
    </div>
  );
}
