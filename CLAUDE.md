# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## プロジェクト概要

Next.js 16 (App Router) + React 19 + TypeScript + Tailwind CSS v4 を使用したWebアプリケーション。
React Compilerが有効化されており、パフォーマンス最適化が自動的に適用されます。

## 開発コマンド

```bash
# 開発サーバー起動 (http://localhost:3000)
npm run dev

# プロダクションビルド
npm run build

# プロダクションサーバー起動
npm start

# Biomeでコードチェック
npm run lint

# Biomeでコードフォーマット
npm run format
```

## 技術スタック

- **Next.js**: 16.0.0 (App Router)
- **React**: 19.2.0 (React Compiler有効)
- **TypeScript**: 5.x (strict mode)
- **スタイリング**: Tailwind CSS v4
- **リンター/フォーマッター**: Biome 2.2.0
- **フォント**: Geist Sans & Geist Mono

## アーキテクチャ

### ディレクトリ構成

```
src/
  app/          # Next.js App Router
    layout.tsx  # ルートレイアウト (Geist フォント設定)
    page.tsx    # トップページ
    globals.css # グローバルスタイル (Tailwind)
```

### パス設定

- `@/*` は `./src/*` にマッピングされています (tsconfig.json)

### 重要な設定

- **React Compiler**: `next.config.ts`で有効化済み。手動のメモ化 (useMemo, useCallback) は不要
- **TypeScript**: strict mode有効、ES2017ターゲット
- **Biome**:
  - Next.js/React向けのルールセット (`domains: next, react`)
  - インポート自動整理有効
  - Tailwindの未知のCSSルール許可
  - VCS統合有効 (Git)

## スタイリング

Tailwind CSS v4を使用。ダークモード対応 (`dark:` プレフィックス)。
カスタムカラー変数:
- `foreground` / `background` (globals.cssで定義)
