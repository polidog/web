import { drizzle } from "drizzle-orm/libsql";
import { createClient } from "@libsql/client";
import * as schema from "./schema";
import { env } from "@/env/server";

// Create libSQL client based on environment
// Development: Use local SQLite file
// Production: Use Turso cloud database
const client = createClient(
  env.NODE_ENV === "production"
    ? {
        url: env.TURSO_CONNECTION_URL ?? "",
        authToken: env.TURSO_AUTH_TOKEN ?? "",
      }
    : {
        url: "file:local.db",
      },
);

// Create Drizzle ORM instance
export const db = drizzle(client, { schema });
