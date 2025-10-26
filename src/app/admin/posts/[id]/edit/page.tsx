import { eq } from "drizzle-orm";
import { notFound } from "next/navigation";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { db } from "@/db";
import { posts } from "@/db/schema";
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

  return (
    <div className="container mx-auto py-8 max-w-4xl">
      <Card>
        <CardHeader>
          <CardTitle>記事編集</CardTitle>
        </CardHeader>
        <CardContent>
          <PostForm authorId={user.id} post={post} />
        </CardContent>
      </Card>
    </div>
  );
}
