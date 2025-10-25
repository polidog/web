import { defineConfig } from "drizzle-kit";
import { config } from "dotenv";

// Load environment variables from .env.local
config({ path: ".env.local" });

export default defineConfig({
	// Schema location
	schema: "./src/db/schema.ts",

	// Output directory for migrations
	out: "./drizzle",

	// Database dialect
	dialect: "turso",

	// Database credentials
	dbCredentials: {
		url: process.env.TURSO_CONNECTION_URL!,
		authToken: process.env.TURSO_AUTH_TOKEN!,
	},

	// Verbose logging
	verbose: true,

	// Strict mode
	strict: true,
});
