import { defineConfig } from "drizzle-kit";
import { env } from "./src/env/server";

export default defineConfig({
  // Schema location
  schema: "./src/db/schema.ts",

  // Output directory for migrations
  out: "./drizzle",

  // Database dialect (sqlite is compatible with both local SQLite and Turso)
  dialect: "sqlite",

  // Database credentials (switches based on environment)
  dbCredentials: {
    url:
      env.NODE_ENV === "production"
        ? (env.TURSO_CONNECTION_URL ?? "")
        : "file:local.db",
  },

  // Verbose logging
  verbose: true,

  // Strict mode
  strict: true,
});
