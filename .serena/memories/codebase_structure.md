# コードベース構造

## ディレクトリ構成

```
/
├── src/
│   └── app/              # Next.js App Router
│       ├── layout.tsx    # ルートレイアウト（Geistフォント設定）
│       ├── page.tsx      # トップページ
│       ├── globals.css   # グローバルスタイル（Tailwind）
│       └── favicon.ico   # ファビコン
├── public/               # 静的アセット
├── node_modules/         # 依存パッケージ
├── .next/                # Next.jsビルド出力
├── .git/                 # Gitリポジトリ
├── .claude/              # Claude Code設定
├── .serena/              # Serenaメモリ
├── biome.json            # Biome設定
├── next.config.ts        # Next.js設定
├── tsconfig.json         # TypeScript設定
├── postcss.config.mjs    # PostCSS設定（Tailwind用）
├── package.json          # npm設定
├── pnpm-lock.yaml        # pnpmロックファイル
├── CLAUDE.md             # Claude Code向けガイド
├── README.md             # プロジェクトREADME
└── .gitignore            # Git除外設定
```

## App Routerの構成

- `src/app/` がルートディレクトリ
- `layout.tsx`: アプリケーション全体のレイアウト
- `page.tsx`: 各ルートのページコンポーネント
- `globals.css`: Tailwind CSS設定とグローバルスタイル

## パスエイリアス

- `@/*` → `./src/*` （tsconfig.jsonで設定）

## ビルド出力

- `.next/`: Next.jsのビルドキャッシュと出力
- `node_modules/`: npmパッケージ

## 設定ファイル

- **biome.json**: リント/フォーマット設定
- **next.config.ts**: Next.js設定（React Compiler有効化）
- **tsconfig.json**: TypeScript設定（strict mode、パスエイリアス）
- **postcss.config.mjs**: PostCSS設定
