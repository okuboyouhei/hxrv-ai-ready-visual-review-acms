# HXRV for a-blog cms — CLAUDE.md

## 概要

WordPress版 HXRV を a-blog cms 拡張アプリとして移植。
ページ上のフィードバックをCSSセレクタ付きのピンとして記録し、AIエージェント向けMarkdownとしてエクスポートする。

## ファイル構成

```
extension/plugins/HxrvAcms/
├── ServiceProvider.php          # DB作成 + Hook登録
├── Hook.php                     # テンプレート変数注入（HXRV_ACTIVE等）
├── GET/
│   └── HxrvPinApi.php          # JSON API (list / export)
├── POST/
│   ├── HxrvPinSave.php         # ピン保存
│   ├── HxrvPinDelete.php       # ピン削除
│   └── HxrvPinStatus.php       # open/resolved トグル
├── assets/
│   ├── js/hxrv-acms.js         # Alpine.js オーバーレイ
│   └── css/hxrv.css            # スタイル
├── sql/schema.yaml             # 参考用スキーマ（install()は直接SQL使用）
└── template/
    ├── hxrv-overlay.html            # → テーマの include/ に配置して @include
    └── admin/app/hxrv/
        └── api.html            # → themes/system/admin/app/hxrv/ にコピー
```

## インストール手順

### ⚠️ 最初に: フォルダ名を `HxrvAcms` にする

GitHubから「Download ZIP」でダウンロードすると、展開後のフォルダ名は
`hxrv-ai-ready-visual-review-acms-main` になります。

a-blog cms はプラグインのフォルダ名とクラスの名前空間が一致している必要があるため、
**必ず `HxrvAcms` にリネームしてから設置してください。**

```
hxrv-ai-ready-visual-review-acms-main/  ← このままではNG
        ↓ リネーム
HxrvAcms/                                ← これが正しい
```

### 手順

1. リネームした `HxrvAcms/` フォルダを、a-blog cms の
   `extension/plugins/` の下に置く
   ```
   {a-blog cmsのルート}/extension/plugins/HxrvAcms/
   ```

2. 管理画面 > 拡張アプリ で「HxrvAcms」を探して **インストール**
   → `acms_hxrv_pins` テーブルが作成される

3. APIテンプレートを所定の場所にコピー
   ```
   HxrvAcms/template/admin/app/hxrv/api.html
        ↓ コピー
   {a-blog cmsのルート}/themes/system/admin/app/hxrv/api.html
   ```
   ブラウザで `/bid/1/admin/app_hxrv_api/?action=list&page_url=/` を開き
   `{"success":true,"pins":[]}` が返れば、ここまで成功。

4. オーバーレイのJSを `<head>` で読み込む
   テーマの `include/head/js.html` の末尾に追記:
   ```html
   <!-- BEGIN_IF [%{SUID}/nem] -->
   <script src="/extension/plugins/HxrvAcms/assets/js/hxrv-acms.js"></script>
   <!-- END_IF -->
   ```
   ※ a-blog cms をサブディレクトリ（例 `/sub/`）に設置している場合は
      `/sub/extension/plugins/HxrvAcms/assets/js/hxrv-acms.js` のように
      プレフィックスを付ける。

5. オーバーレイ本体をテーマの include/ にコピー
   ```
   HxrvAcms/template/hxrv-overlay.html
        ↓ コピー（ファイル名はそのまま）
   {a-blog cmsのルート}/themes/{テーマ名}/include/hxrv-overlay.html
   ```
   テーマの `</body>` 直前に追記:
   ```
   <!-- BEGIN_IF [%{SUID}/nem] -->
   @include("/include/hxrv-overlay.html")
   <!-- END_IF -->
   ```

6. ログインした状態で公開ページを開くと、画面右端の縦中央に
   緑のタブが出る。クリックでパネルが開けば設置完了。

### JS読み込み順に関する注意（重要）

手順4で `hxrv-acms.js` を **`<head>` で同期読み込み**するのは意図的です。
a-blog cms 3.2 は Alpine.js を同梱していないため、プラグインが Alpine.js
v3.15.12 を同梱し（assets/js/alpine.min.js）、hxrv-overlay.html 側で
`defer` 読み込みします（v1.1.0でCDN依存を廃止。オフライン・イントラ環境対応）。

- `hxrv-acms.js`（head・同期）→ `hxrvApp()` をグローバル定義
- Alpine.js 同梱版（hxrv-overlay.html内・defer）→ DOM解析後に評価

この順序が崩れると `hxrvApp is not defined` になります。


## APIエンドポイント

すべて `/bid/{BID}/admin/app_hxrv_api/` へのリクエスト。

```
GET  ?action=list&page_url=/path/     → JSON: { success, pins[] }
GET  ?action=export&page_url=/path/   → JSON: { success, markdown }
POST fd: ACMS_POST_HxrvPinSave        → JSON: { success, pin_id }
POST fd: ACMS_POST_HxrvPinDelete      → JSON: { success }
POST fd: ACMS_POST_HxrvPinStatus      → JSON: { success, status }
```

## ソースコード確認済みのAPI（a-blog cms 3.2.26）

| 項目 | 正しい書き方 |
|---|---|
| ログイン中ユーザーID | `SUID`（null=未ログイン） |
| ユーザー名取得 | `ACMS_RAM::userName(SUID)` |
| DBクエリ実行 | `DB::query(['sql'=>..., 'params'=>[]], 'mode')` |
| INSERT後のID | `DB::query($sql->get(dsn()), 'seq')` |
| SELECT全件 | `'all'` モード |
| SELECT1行 | `'row'` モード |
| INSERT | `SQL::newInsert()` + `addInsert()` |
| UPDATE | `SQL::newUpdate()` + `addUpdate()` |
| DELETE | `SQL::newDelete()` + `addWhereOpr()` |
| テーブル作成 | `DB::query(['sql'=>'CREATE TABLE...', 'params'=>[]], 'exec')` |
| PLUGIN_DIR | `/extension/plugins/`（URLパス。ファイル読み込みには使えない）|
| dbDropTables / dbMakeTables | **存在しない** → 直接SQL |
| LOGIN_UID | **存在しない** → `SUID` を使う |

## Alpine.js についての重要な知見

a-blog cms 3.2 の `vendor.js` には **Alpine.js は含まれない**。

HXRV はプラグイン同梱の Alpine.js（assets/js/alpine.min.js、v3.15.12 =
WordPress版と同一）を `hxrv-overlay.html` 内で読み込む。
読み込み順:

1. `<head>` の `hxrv-acms.js`（同期）→ `hxrvApp()` をグローバル定義
2. `hxrv-overlay.html` の Alpine 同梱版（defer）→ DOM 解析後に `x-data="hxrvApp()"` を処理

チラつき防止として x-show 要素には `x-cloak` を付与し、CSS 側の
`[x-cloak] { display: none !important; }` で初期化前を非表示にしている。
また #hxrv-root 配下にはテーマCSSの継承を遮断する防御的リセット
（box-sizing / 見出し / ボタン / リスト）を入れている（v1.1.0）。

この順序が崩れると `hxrvApp is not defined` エラーになる。

## テンプレート変数（Hook.phpが提供）

| 変数 | 内容 |
|---|---|
| `{HXRV_ACTIVE}` | ログイン中なら `1`、未ログインなら `0`。テーマの出し分けに使える |

※ CSS/JS/APIのパスは Hook.php の変数ではなく、hxrv-overlay.html 冒頭の
  【設定①〜③】ブロックで直接指定する（環境非依存で確実なため）。
  author はサーバー側で `ACMS_RAM::userName(SUID)` から付与するので、
  テンプレート側でユーザー名変数を使う必要はない。

## 未実装 (v1.1以降)

- 返信スレッド
- orphan ピンのナビゲーション
- install() でのテンプレート自動コピー
- i18n (.pot)
- 管理一覧画面
