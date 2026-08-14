/** @type {import('tailwindcss').Config} */
module.exports = {
  // SiteDocument の head インラインスクリプトが <html> に .dark を付ける。
  darkMode: 'class',
  content: [
    // クラス名はページ（.psx）・レイアウト・共通部品（PHP）に散っている。
    './src/Pages/**/*.psx',
    './src/Pages/**/*.php',
    './src/View/**/*.php',
    './src/Http/**/*.php',
  ],
  // 記事本文の HTML は DB の中にあってスキャン対象に入らないが、そこに
  // 現れるのは `language-php` のような Tailwind 外のクラスなので safelist
  // は要らない（safelist は Tailwind が生成するクラスの保持リスト）。
  // 本文の見た目は assets/tailwind.css の @layer components で当てている。
  theme: {
    extend: {
      colors: {
        primary: {
          50: '#f0f9ff',
          100: '#e0f2fe',
          200: '#bae6fd',
          300: '#7dd3fc',
          400: '#38bdf8',
          500: '#0ea5e9',
          600: '#0284c7',
          700: '#0369a1',
          800: '#075985',
          900: '#0c4a6e',
          950: '#082f49',
        },
      },
      typography: {
        DEFAULT: {
          css: {
            maxWidth: '65ch',
            a: {
              textDecoration: 'underline',
              fontWeight: '500',
            },
          },
        },
      },
    },
  },
  plugins: [require('@tailwindcss/typography')],
}
