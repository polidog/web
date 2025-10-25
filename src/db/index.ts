import { drizzle } from "drizzle-orm/libsql";
import { createClient } from "@libsql/client";
import * as schema from "./schema";
import { env } from "@/env/server";

// Create libSQL client based on environment
// Development: Use local SQLite file
// Production: Use Turso cloud database
const getClientConfig = () => {
  if (env.NODE_ENV === "production") {
    // In production, require Turso credentials
    const url = env.TURSO_CONNECTION_URL;
    const authToken = env.TURSO_AUTH_TOKEN;

    if (!url || !authToken) {
      // If building for production without credentials, use local file as fallback
      console.warn("Production build without Turso credentials, using local database");
      return { url: "file:local.db" };
    }

    return { url, authToken };
  }

  // Development: always use local SQLite
  return { url: "file:local.db" };
};

const client = createClient(getClientConfig());

// Create Drizzle ORM instance
export const db = drizzle(client, { schema });
