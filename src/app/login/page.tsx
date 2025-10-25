import { redirect } from "next/navigation";
import { getSession } from "@/features/auth/lib/auth-helpers";
import { LoginButton } from "@/features/auth/components/login-button";

export default async function LoginPage() {
  const session = await getSession();

  // If already logged in, redirect to admin dashboard
  if (session) {
    redirect("/admin/dashboard");
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-gray-50 dark:bg-gray-900">
      <div className="w-full max-w-md space-y-8 rounded-lg bg-white p-8 shadow-lg dark:bg-gray-800">
        <div className="text-center">
          <h1 className="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
            polidog.jp
          </h1>
          <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
            管理画面へログイン
          </p>
        </div>

        <div className="mt-8">
          <LoginButton />
        </div>

        <p className="mt-4 text-center text-xs text-gray-500 dark:text-gray-400">
          許可されたメールアドレスのみログインできます
        </p>
      </div>
    </div>
  );
}
