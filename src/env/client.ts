import { createEnv } from "@t3-oss/env-nextjs";
import { z } from "zod";

/**
 * Client-side environment variables schema
 * These variables are accessible on both client and server
 * All client variables must be prefixed with NEXT_PUBLIC_
 */
export const env = createEnv({
  client: {
    NEXT_PUBLIC_APP_URL: z.string().url().optional(),
  },
  runtimeEnv: {
    NEXT_PUBLIC_APP_URL: process.env.NEXT_PUBLIC_APP_URL,
  },
});
