import { betterAuth } from "better-auth";
import { drizzleAdapter } from "better-auth/adapters/drizzle";
import { db } from "@/db";
import { env } from "@/env/server";
import * as schema from "@/db/schema";

export const auth = betterAuth({
  database: drizzleAdapter(db, {
    provider: "sqlite",
    schema,
  }),
  emailAndPassword: {
    enabled: false, // Email/password login disabled, only OAuth
  },
  socialProviders: {
    google: {
      clientId: env.GOOGLE_CLIENT_ID ?? "",
      clientSecret: env.GOOGLE_CLIENT_SECRET ?? "",
    },
  },
  databaseHooks: {
    user: {
      create: {
        before: async (user) => {
          // Email restriction check
          const allowedEmails = env.ALLOWED_EMAILS?.split(",").map((e) =>
            e.trim()
          ) ?? [];

          if (allowedEmails.length > 0 && !allowedEmails.includes(user.email)) {
            throw new Error(
              "このメールアドレスは登録が許可されていません。管理者に連絡してください。"
            );
          }

          return { data: user };
        },
      },
    },
  },
});
