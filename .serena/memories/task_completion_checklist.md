# タスク完了時のチェックリスト

コード変更を完了したら、以下の手順を必ず実行してください。

## 1. コードフォーマット

```bash
npm run format
```

- Biomeで自動フォーマットを適用
- インポート文も自動整理される

## 2. リントチェック

```bash
npm run lint
```

- Biomeでコードの問題をチェック
- Next.js/React固有のルールもチェックされる
- エラーがあれば修正する

## 3. TypeScriptの型チェック

TypeScriptのstrict modeが有効なため、ビルド時に型チェックが行われます:

```bash
npm run build
```

または開発サーバー起動時に型エラーが表示されます:

```bash
npm run dev
```

## 4. 動作確認

開発サーバーで動作を確認:

```bash
npm run dev
```

http://localhost:3000 でアプリケーションが正常に動作することを確認

## 5. Git コミット

問題がなければコミット:

```bash
git add .
git commit -m "適切なコミットメッセージ"
```

## 注意事項

- **React Compiler有効**: useMemo、useCallback、React.memoの手動追加は不要（自動最適化される）
- **strict mode**: 型エラーには厳格に対応
- **Tailwind CSS v4**: 未知のCSSルールは許可されているが、Tailwindの規約に従う
