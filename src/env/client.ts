import { createEnv } from "@t3-oss/env-nextjs";
import { z } from "zod";

/**
 * Client-side environment variables schema
 * These variables are accessible on both client and server
 * All client variables must be prefixed with NEXT_PUBLIC_
 */
export const env = createEnv({
  client: {
    // Example: NEXT_PUBLIC_API_URL: z.string().url(),
  },
  runtimeEnv: {
    // Example: NEXT_PUBLIC_API_URL: process.env.NEXT_PUBLIC_API_URL,
  },
});
