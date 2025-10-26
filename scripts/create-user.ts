import { nanoid } from "nanoid";
import { db } from "../src/db/index";
import { user } from "../src/db/schema";

/**
 * Create a user for importing Hugo posts
 */
async function main() {
  console.log("👤 Create User for Hugo Import\n");

  // Get name and email from command line args or use defaults
  const name = process.argv[2] || "Polidog";
  const email = process.argv[3] || "polidog@example.com";

  if (!name || !email) {
    console.error("❌ Usage: pnpm tsx scripts/create-user.ts <name> <email>");
    process.exit(1);
  }

  console.log(`Creating user: ${name} (${email})`);

  // Create user
  const userId = nanoid();
  const [newUser] = await db
    .insert(user)
    .values({
      id: userId,
      name,
      email,
      emailVerified: true,
    })
    .returning();

  console.log("\n✅ User created successfully!");
  console.log(`User ID: ${newUser.id}`);
  console.log(`Name: ${newUser.name}`);
  console.log(`Email: ${newUser.email}`);
  console.log("\nUse this User ID when running the import script:");
  console.log(`\npnpm tsx scripts/import-hugo.ts ${newUser.id}`);
}

main().catch(console.error);
