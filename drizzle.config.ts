import { defineConfig } from "drizzle-kit";
import { env } from "./src/env/server";

export default defineConfig({
	// Schema location
	schema: "./src/db/schema.ts",

	// Output directory for migrations
	out: "./drizzle",

	// Database dialect
	dialect: "turso",

	// Database credentials
	dbCredentials: {
		url: env.TURSO_CONNECTION_URL,
		authToken: env.TURSO_AUTH_TOKEN,
	},

	// Verbose logging
	verbose: true,

	// Strict mode
	strict: true,
});
