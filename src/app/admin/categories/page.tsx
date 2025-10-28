"use client";

import { Plus } from "lucide-react";
import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import type { Category } from "@/db/schema";
import { getAllCategories } from "@/features/categories/actions/categories";
import { CategoryForm } from "@/features/categories/components/category-form";
import { CategoryList } from "@/features/categories/components/category-list";

export default function CategoriesPage() {
  const [categories, setCategories] = useState<Category[]>([]);
  const [isCreateDialogOpen, setIsCreateDialogOpen] = useState(false);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    loadCategories();
  }, []);

  const loadCategories = async () => {
    setIsLoading(true);
    const result = await getAllCategories();
    if (result.success) {
      setCategories(result.categories);
    }
    setIsLoading(false);
  };

  const handleCreateSuccess = () => {
    setIsCreateDialogOpen(false);
    loadCategories();
  };

  return (
    <div className="container mx-auto py-8 max-w-6xl">
      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle>カテゴリ管理</CardTitle>
          <Button onClick={() => setIsCreateDialogOpen(true)}>
            <Plus className="mr-2 h-4 w-4" />
            新規作成
          </Button>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="text-center py-8 text-muted-foreground">
              読み込み中...
            </div>
          ) : (
            <CategoryList categories={categories} />
          )}
        </CardContent>
      </Card>

      <Dialog open={isCreateDialogOpen} onOpenChange={setIsCreateDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>新規カテゴリ作成</DialogTitle>
          </DialogHeader>
          <CategoryForm onSuccess={handleCreateSuccess} />
        </DialogContent>
      </Dialog>
    </div>
  );
}
