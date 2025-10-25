import { createEnv } from "@t3-oss/env-nextjs";
import { z } from "zod";

/**
 * Server-side environment variables schema
 * These variables are only accessible on the server-side
 */
export const env = createEnv({
  server: {
    // Node environment
    NODE_ENV: z
      .enum(["development", "production", "test"])
      .default("development"),

    // Database Configuration
    // For local development: uses local SQLite file (file:local.db)
    // For production: uses Turso cloud database
    TURSO_CONNECTION_URL: z.string().url().optional(),
    TURSO_AUTH_TOKEN: z.string().min(1).optional(),

    // BetterAuth Configuration
    BETTER_AUTH_SECRET: z.string().min(32).optional(),
    BETTER_AUTH_URL: z.string().url().optional(),

    // Google OAuth Configuration
    GOOGLE_CLIENT_ID: z.string().min(1).optional(),
    GOOGLE_CLIENT_SECRET: z.string().min(1).optional(),

    // Allowed emails for registration (comma-separated)
    ALLOWED_EMAILS: z.string().min(1).optional(),
  },
  // For Next.js >= 13.4.4, you only need to destructure client variables:
  experimental__runtimeEnv: process.env,
});
