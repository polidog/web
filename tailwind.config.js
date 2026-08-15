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
  // 本文の見た目は下の typography 設定と assets/tailwind.css で当てている。
  theme: {
    extend: {
      // 色は assets/tailwind.css の CSS 変数を参照する。`.dark` が変数ごと
      // 差し替えるので、ページ側に `dark:` を書く必要が無い。20 年ぶんの
      // ページに 2 系統のクラスを撒くより、切り替え点を 1 か所に寄せる。
      colors: {
        surface: 'rgb(var(--color-surface) / <alpha-value>)',
        raised: 'rgb(var(--color-raised) / <alpha-value>)',
        ink: 'rgb(var(--color-ink) / <alpha-value>)',
        muted: 'rgb(var(--color-muted) / <alpha-value>)',
        // 年号マーカー。情報を運ぶので、薄くても大サイズで 3:1 は満たす。
        faint: 'rgb(var(--color-faint) / <alpha-value>)',
        hairline: 'rgb(var(--color-hairline) / <alpha-value>)',
        accent: 'rgb(var(--color-accent) / <alpha-value>)',
        'accent-strong': 'rgb(var(--color-accent-strong) / <alpha-value>)',
        // 管理画面の通知だけが使う意味色。公開側には出てこない。
        danger: 'rgb(var(--color-danger) / <alpha-value>)',
        success: 'rgb(var(--color-success) / <alpha-value>)',
      },
      fontFamily: {
        // 日本語の Web フォントは数 MB になり、CDN キャッシュ前提の
        // コスト構造と噛み合わない。書体は環境任せにして、級差・字間・
        // 行間で差をつける。
        sans: [
          'system-ui',
          '-apple-system',
          '"Segoe UI"',
          '"Hiragino Sans"',
          '"Hiragino Kaku Gothic ProN"',
          '"Noto Sans JP"',
          '"Yu Gothic UI"',
          'Meiryo',
          'sans-serif',
        ],
        // 日付と数字はここ。20 年ぶんの日付が縦に揃うのは等幅だから。
        mono: [
          'ui-monospace',
          'SFMono-Regular',
          '"SF Mono"',
          'Menlo',
          'Consolas',
          '"Liberation Mono"',
          'monospace',
        ],
      },
      maxWidth: {
        // 本文 17px で 1 行 40 字強。日本語の読み物としてはこのあたり。
        measure: '44rem',
      },
      typography: {
        DEFAULT: {
          css: {
            // prose の色も同じ変数に寄せる。これで dark:prose-invert が要らない。
            '--tw-prose-body': 'rgb(var(--color-ink))',
            '--tw-prose-headings': 'rgb(var(--color-ink))',
            '--tw-prose-lead': 'rgb(var(--color-muted))',
            '--tw-prose-links': 'rgb(var(--color-accent))',
            '--tw-prose-bold': 'rgb(var(--color-ink))',
            '--tw-prose-counters': 'rgb(var(--color-muted))',
            '--tw-prose-bullets': 'rgb(var(--color-faint))',
            '--tw-prose-hr': 'rgb(var(--color-hairline))',
            '--tw-prose-quotes': 'rgb(var(--color-muted))',
            '--tw-prose-quote-borders': 'rgb(var(--color-hairline))',
            '--tw-prose-captions': 'rgb(var(--color-muted))',
            '--tw-prose-code': 'rgb(var(--color-ink))',
            '--tw-prose-th-borders': 'rgb(var(--color-hairline))',
            '--tw-prose-td-borders': 'rgb(var(--color-hairline))',
            maxWidth: 'none',
            fontSize: '1.0625rem',
            lineHeight: '1.9',
            a: {
              fontWeight: '400',
              textDecoration: 'underline',
              textDecorationThickness: '1px',
              textUnderlineOffset: '3px',
              '&:hover': {
                color: 'rgb(var(--color-accent-strong))',
              },
            },
            'h2, h3, h4': {
              letterSpacing: '-0.02em',
              fontWeight: '600',
            },
            h2: {
              fontSize: '1.375rem',
              marginTop: '3.5rem',
              marginBottom: '1rem',
            },
            h3: {
              fontSize: '1.125rem',
              marginTop: '2.5rem',
              marginBottom: '0.75rem',
            },
            blockquote: {
              fontStyle: 'normal',
              fontWeight: '400',
            },
          },
        },
      },
    },
  },
  plugins: [require('@tailwindcss/typography')],
}
