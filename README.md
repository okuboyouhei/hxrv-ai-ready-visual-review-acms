# HXRV for a-blog cms

AI-Ready Visual Review plugin for a-blog cms.

WordPress版 [HXRV](https://wordpress.org/plugins/hxrv-ai-ready-visual-review/) の a-blog cms 移植版。

## 機能

- 公開ページ上でCSSセレクタ付きのフィードバックピンを配置
- ピンにコメントを追加（レビュー・修正指示）
- 3段階再アンカー（セレクタ → テキスト抜粋 → orphan）
- AIエージェント向けMarkdownエクスポート
- open / resolved ステータス管理
- ピン番号クリックでページ内スクロール

## 動作環境

- a-blog cms 3.2.x
- PHP 8.x

## インストール

### ⚠️ 最初にフォルダ名を `HxrvAcms` にリネーム

GitHubから「Download ZIP」でダウンロードすると、フォルダ名が
`hxrv-ai-ready-visual-review-acms-main` になります。
a-blog cms はフォルダ名と名前空間の一致を要求するため、
**必ず `HxrvAcms` にリネームしてから** `extension/plugins/` に設置してください。

詳しい手順は `CLAUDE.md` を参照してください。

## ⚠️ 重要な注意事項

### パスの設定は hxrv-overlay.html の【設定】3行だけ

`template/hxrv-overlay.html` の冒頭に【設定①〜③】のブロックがあります。
CSS・Alpine.js・APIエンドポイントのパスはここで**直接指定**します。
テンプレート変数には依存しないので、環境を問わず確実に動きます。

| 設定 | 内容 | 直下設置の例 | サブディレクトリ(/hoge/)の例 |
|---|---|---|---|
| ① CSS | hxrv.css のパス | `/extension/plugins/HxrvAcms/assets/css/hxrv.css` | `/hoge/extension/plugins/HxrvAcms/assets/css/hxrv.css` |
| ② Alpine | CDN | 変更不要 | 変更不要 |
| ③ API | data-api-base | `/bid/1/admin/app_hxrv_api/` | `/hoge/bid/1/admin/app_hxrv_api/` |

`bid` の番号は、レビュー対象のブログIDに合わせてください（親ブログなら通常 `1`）。

### JavaScript の読み込み順（重要）

a-blog cms 3.2 の `vendor.js` には **Alpine.js は含まれていません**。
HXRV は以下の順序で読み込む必要があります。

1. `hxrv-acms.js` を `<head>` で**同期読み込み** → `hxrvApp()` をグローバル定義
   ```html
   <!-- themes/{テーマ名}/include/head/js.html などに追記 -->
   <!-- BEGIN_IF [%{SUID}/nem] -->
   <script src="/extension/plugins/HxrvAcms/assets/js/hxrv-acms.js"></script>
   <!-- END_IF -->
   ```
   ※ サブディレクトリ設置なら先頭にディレクトリ名を付ける（例 `/hoge/extension/...`）
2. `hxrv-overlay.html` 内の Alpine.js CDN（defer）→ DOM解析後に `x-data="hxrvApp()"` を処理

この順序が崩れると `hxrvApp is not defined` エラーになります。

### サブディレクトリ設置の場合

ドメイン直下ではなくサブディレクトリ（例: `/hoge/`）に a-blog cms を設置している場合、
`hxrv-acms.js` の読み込みパスと `data-api-base` にそのプレフィックスを含める必要があります。
本番投入時は Hook.php 側でルートパスを動的に組み立てることを推奨します。

## License

GPL-2.0-or-later
