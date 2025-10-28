import { notFound } from "next/navigation";
import { getTagWithPosts } from "@/features/tags/actions/tags";
import { TagDetail } from "@/features/tags/components/tag-detail";

export default async function TagDetailPage({
  params,
  searchParams,
}: {
  params: Promise<{ id: string }>;
  searchParams: Promise<{ page?: string }>;
}) {
  const { id } = await params;
  const { page } = await searchParams;
  const tagId = Number.parseInt(id, 10);
  const currentPage = Number(page) || 1;

  if (Number.isNaN(tagId)) {
    notFound();
  }

  const result = await getTagWithPosts(tagId, currentPage);

  if (!result.success || !result.tag) {
    notFound();
  }

  return (
    <TagDetail
      tag={result.tag}
      posts={result.posts || []}
      currentPage={currentPage}
      totalPages={result.totalPages || 1}
      totalCount={result.totalCount || 0}
    />
  );
}
