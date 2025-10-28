export function Footer() {
  const currentYear = new Date().getFullYear();

  return (
    <footer className="border-t border-gray-200 dark:border-gray-800 bg-white dark:bg-black mt-auto">
      <div className="container mx-auto px-4 py-8 max-w-6xl">
        <div className="flex flex-col items-center justify-center gap-4 text-center">
          <p className="text-sm text-gray-600 dark:text-gray-400">
            © {currentYear} polidog lab. All rights reserved.
          </p>
        </div>
      </div>
    </footer>
  );
}
