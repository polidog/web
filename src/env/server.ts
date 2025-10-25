import { createEnv } from "@t3-oss/env-nextjs";
import { z } from "zod";

/**
 * Server-side environment variables schema
 * These variables are only accessible on the server-side
 */
export const env = createEnv({
	server: {
		// Turso Database Configuration
		TURSO_CONNECTION_URL: z.string().url(),
		TURSO_AUTH_TOKEN: z.string().min(1),
	},
	// For Next.js >= 13.4.4, you only need to destructure client variables:
	experimental__runtimeEnv: process.env,
});
