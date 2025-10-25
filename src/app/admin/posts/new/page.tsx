import { PostForm } from "@/features/posts/components/post-form";
import { requireAuth } from "@/features/auth/lib/auth-helpers";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";

export default async function NewPostPage() {
  const { user } = await requireAuth();

  return (
    <div className="container mx-auto py-8 max-w-4xl">
      <Card>
        <CardHeader>
          <CardTitle>新規記事作成</CardTitle>
        </CardHeader>
        <CardContent>
          <PostForm authorId={user.id} />
        </CardContent>
      </Card>
    </div>
  );
}
