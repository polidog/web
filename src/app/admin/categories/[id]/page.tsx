import { notFound } from "next/navigation";
import { getCategoryWithPosts } from "@/features/categories/actions/categories";
import { CategoryDetail } from "@/features/categories/components/category-detail";

export default async function CategoryDetailPage({
  params,
  searchParams,
}: {
  params: Promise<{ id: string }>;
  searchParams: Promise<{ page?: string }>;
}) {
  const { id } = await params;
  const { page } = await searchParams;
  const categoryId = Number.parseInt(id, 10);
  const currentPage = Number(page) || 1;

  if (Number.isNaN(categoryId)) {
    notFound();
  }

  const result = await getCategoryWithPosts(categoryId, currentPage);

  if (!result.success || !result.category) {
    notFound();
  }

  return (
    <CategoryDetail
      category={result.category}
      posts={result.posts || []}
      currentPage={currentPage}
      totalPages={result.totalPages || 1}
      totalCount={result.totalCount || 0}
    />
  );
}
