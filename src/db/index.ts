import { drizzle } from "drizzle-orm/libsql";
import { createClient } from "@libsql/client";
import * as schema from "./schema";
import { env } from "@/env/server";

// Create libSQL client
const client = createClient({
	url: env.TURSO_CONNECTION_URL,
	authToken: env.TURSO_AUTH_TOKEN,
});

// Create Drizzle ORM instance
export const db = drizzle(client, { schema });
