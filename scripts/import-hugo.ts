import fs from "node:fs";
import path from "node:path";
import { eq } from "drizzle-orm";
import { glob } from "glob";
import matter from "gray-matter";
import { db } from "../src/db/index";
import {
  categories,
  postCategories,
  posts,
  postTags,
  tags,
} from "../src/db/schema";

// Hugo website path
const HUGO_CONTENT_PATH = path.resolve(__dirname, "../../website/content/blog");

/**
 * Generate slug from file path
 * Example: blog/2024/01/haskell.md → 2024-01-haskell
 */
function generateSlug(filePath: string): string {
  const relativePath = path.relative(HUGO_CONTENT_PATH, filePath);
  const parts = relativePath.split(path.sep);

  // Remove .md extension
  const fileName = path.basename(parts[parts.length - 1], ".md");

  // Extract year and month if exists
  if (parts.length >= 3) {
    const year = parts[0];
    const month = parts[1];
    return `${year}-${month}-${fileName}`;
  }

  return fileName;
}

/**
 * Create slug from name
 */
function createSlug(name: string): string {
  return name
    .toLowerCase()
    .replace(/[^\w\s-]/g, "")
    .replace(/\s+/g, "-")
    .replace(/-+/g, "-")
    .trim();
}

/**
 * Get or create category
 */
async function getOrCreateCategory(name: string): Promise<number> {
  const slug = createSlug(name);

  // Check if category exists
  const existing = await db
    .select()
    .from(categories)
    .where(eq(categories.slug, slug))
    .limit(1);

  if (existing.length > 0) {
    return existing[0].id;
  }

  // Create new category
  const result = await db
    .insert(categories)
    .values({ name, slug })
    .returning({ id: categories.id });
  return result[0].id;
}

/**
 * Get or create tag
 */
async function getOrCreateTag(name: string): Promise<number> {
  const slug = createSlug(name);

  // Check if tag exists
  const existing = await db
    .select()
    .from(tags)
    .where(eq(tags.slug, slug))
    .limit(1);

  if (existing.length > 0) {
    return existing[0].id;
  }

  // Create new tag
  const result = await db
    .insert(tags)
    .values({ name, slug })
    .returning({ id: tags.id });
  return result[0].id;
}

/**
 * Import a single Hugo post
 */
async function importPost(filePath: string, authorId: string) {
  try {
    const fileContent = fs.readFileSync(filePath, "utf-8");
    const { data: frontMatter, content } = matter(fileContent);

    const slug = generateSlug(filePath);

    // Check if post already exists
    const existingPost = await db
      .select()
      .from(posts)
      .where(eq(posts.slug, slug))
      .limit(1);

    if (existingPost.length > 0) {
      console.log(`⏭️  Skipping (already exists): ${slug}`);
      return;
    }

    // Parse date
    const publishedAt = frontMatter.date
      ? new Date(frontMatter.date)
      : new Date();

    // Extract excerpt (first 200 chars)
    const excerpt = `${content.trim().substring(0, 200).replace(/\n/g, " ")}...`;

    // Insert post
    const [post] = await db
      .insert(posts)
      .values({
        title: frontMatter.title || "Untitled",
        slug,
        content,
        excerpt,
        status: "published",
        authorId,
        publishedAt,
        createdAt: publishedAt,
      })
      .returning({ id: posts.id });

    // Handle categories
    if (frontMatter.categories && Array.isArray(frontMatter.categories)) {
      for (const categoryName of frontMatter.categories) {
        const categoryId = await getOrCreateCategory(categoryName);
        await db.insert(postCategories).values({
          postId: post.id,
          categoryId,
        });
      }
    }

    // Handle tags
    if (frontMatter.tags && Array.isArray(frontMatter.tags)) {
      for (const tagName of frontMatter.tags) {
        const tagId = await getOrCreateTag(tagName);
        await db.insert(postTags).values({
          postId: post.id,
          tagId,
        });
      }
    }

    console.log(`✅ Imported: ${slug}`);
  } catch (error) {
    console.error(`❌ Error importing ${filePath}:`, error);
  }
}

/**
 * Main import function
 */
async function main() {
  console.log("🚀 Hugo to Next.js Blog Import Script\n");

  // Check if Hugo content path exists
  if (!fs.existsSync(HUGO_CONTENT_PATH)) {
    console.error(`❌ Hugo content path not found: ${HUGO_CONTENT_PATH}`);
    process.exit(1);
  }

  // Get author ID from command line argument
  const authorId = process.argv[2];

  if (!authorId) {
    console.error("❌ Usage: pnpm tsx scripts/import-hugo.ts <author-user-id>");
    console.error("\nCreate a user first:");
    console.error("  pnpm tsx scripts/create-user.ts");
    process.exit(1);
  }

  console.log(`Using author ID: ${authorId}`);

  // Find all markdown files
  console.log("\n📂 Finding Hugo posts...");
  const files = await glob(`${HUGO_CONTENT_PATH}/**/*.md`);
  console.log(`Found ${files.length} posts\n`);

  // Import posts
  let imported = 0;
  const skipped = 0;
  let errors = 0;

  for (let i = 0; i < files.length; i++) {
    const file = files[i];
    console.log(`[${i + 1}/${files.length}] Processing...`);

    try {
      await importPost(file, authorId);
      imported++;
    } catch (error) {
      errors++;
      console.error(`❌ Failed to import ${file}:`, error);
    }
  }

  console.log("\n✨ Import completed!");
  console.log(`✅ Imported: ${imported}`);
  console.log(`⏭️  Skipped: ${skipped}`);
  console.log(`❌ Errors: ${errors}`);
}

main().catch(console.error);
