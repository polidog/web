# コードスタイルと規約

## TypeScript設定

- **strict mode**: 有効
- **target**: ES2017
- **jsx**: react-jsx (React 17+の新しいJSX変換)
- **moduleResolution**: bundler
- **パスエイリアス**: `@/*` は `./src/*` にマッピング

## Biome設定

### フォーマット

- **インデントスタイル**: スペース
- **インデント幅**: 2スペース

### リンター

- **推奨ルール**: 有効
- **ドメイン固有ルール**:
  - Next.js: 推奨設定
  - React: 推奨設定
- **特殊設定**:
  - `suspicious.noUnknownAtRules`: オフ（Tailwindの未知のCSSルール許可）

### コード整理

- **インポート自動整理**: 有効（source.organizeImports: on）

## ファイル除外

- `node_modules`
- `.next`
- `dist`
- `build`

## React規約

- **React Compiler有効**: 手動のメモ化（useMemo、useCallback、React.memo）は不要
- パフォーマンス最適化は自動的に適用される

## スタイリング規約

- Tailwind CSSのユーティリティクラスを使用
- ダークモード対応（`dark:` プレフィックス）
- カスタムカラー変数（`foreground`、`background`）はglobals.cssで定義
