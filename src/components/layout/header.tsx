import Link from "next/link";
import { ThemeToggle } from "../theme-toggle";

export function Header() {
  return (
    <header className="border-b border-gray-200 dark:border-gray-800 bg-white dark:bg-black">
      <div className="container mx-auto px-4 py-4 max-w-6xl">
        <nav className="flex items-center justify-between">
          <Link
            href="/"
            className="text-xl font-bold text-gray-900 dark:text-white hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
          >
            polidog lab
          </Link>

          <div className="flex items-center gap-6">
            <Link
              href="/blog"
              className="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors"
            >
              Blog
            </Link>
            <ThemeToggle />
          </div>
        </nav>
      </div>
    </header>
  );
}
