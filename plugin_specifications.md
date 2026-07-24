# WP AI Auto Blogger プラグイン仕様書

本ドキュメントは、WordPressプラグイン「**WP AI Auto Blogger** (v1.0.4)」の機能および設計仕様をまとめたものです。

---

## 1. 概要
AI API（Google Gemini, OpenAI GPT, Anthropic Claude）を使い、入力されたテーマ・業種・ターゲット層に基づいた高品質なブログ記事（下書き）を自動で作成・保存するWordPressプラグインです。記事に合わせたアイキャッチ画像の自動生成、履歴の管理、セキュリティ対策などが盛り込まれています。

---

## 2. システム構成・ディレクトリ構造

```text
wp-ai-auto-blogger/
├── wp-ai-auto-blogger.php      # プラグインのメインエントリ
├── assets/
│   ├── admin.css               # 管理画面のスタイリング
│   └── admin.js                # AJAXおよび履歴タグ操作などのフロントエンドロジック
├── includes/
│   ├── class-admin-page.php    # 管理画面UI（設定・ログ・生成）のレンダリング
│   ├── class-api-handler.php   # 各種AI APIとの通信・データ処理（リトライ・画像生成含む）
│   └── class-post-creator.php  # WordPressの投稿作成・スラッグ適用・フォーマット処理
└── wp-ai-auto-blogger.zip      # 配布用圧縮ファイル
```

---

## 3. 主要機能仕様

### 3.1. 記事生成UIと対応AIモデル
WordPress管理画面の専用メニューから、以下の設定を行って記事を自動生成できます。

*   **入力項目**:
    *   **AIモデル選択** (セレクトボックス)
    *   **記事のテーマ** (必須)
    *   **業種** (履歴補完対応)
    *   **ターゲット層** (履歴補完対応)
    *   **記事に含めたい内容・メモ** (フリーテキスト)
    *   **アイキャッチ画像生成オプション** (チェックボックス)
*   **対応モデル (2026年7月最新仕様)**:
    *   **Google Gemini**: Gemini 3.6 Flash (最新GA), Gemini 3.5 Flash-Lite (最新GA), Gemini 3.1 Pro
    *   **OpenAI GPT**: GPT-5.6, GPT-5.6 Terra, GPT-5.6 Luna, GPT-5.6 Sol (Preview), GPT-5.5 Pro, GPT-5.5 Instant
    *   **Anthropic Claude**: Claude Sonnet 5, Claude Fable 5, Claude 4.8 Opus, Claude 4.6 Sonnet, Claude 4.5 Haiku

### 3.2. 履歴記憶（クイックタグ）機能
「業種」と「ターゲット層」に入力した内容は、過去20件分までデータベース（`wp_options`）に自動保存されます。
*   入力欄の下にクイックタグとして表示され、クリックすると入力欄へ即座に反映されます。
*   各クイックタグには削除ボタン（`×`）が設置されており、不要な履歴は管理画面上から即座にデータベースから削除できます。

### 3.3. 自動アイキャッチ画像生成・設定
記事の内容に合わせてアイキャッチ画像を自動生成し、記事と紐付けます。
*   **プロンプト自動抽出**: 生成された本文の末尾から、AIが考案した英語画像用プロンプト `[ImagePrompt: ...]` を正規表現で抽出・除去します。
*   **使用画像モデル**:
    *   OpenAIキー使用時: `gpt-image-2`
    *   Geminiキー使用時: `gemini-3.1-flash-image` (※最新の `generateContent` エンドポイント経由でBase64画像データを取得)
*   **安全なメディア保存**: ローカル環境や一部サーバーでのループバック通信エラーを回避するため、取得したBase64データを直接WordPressのサーバーローカルディレクトリに保存し、`media_handle_sideload` を用いてメディアライブラリへ直接登録・紐付けを行います。

### 3.4. 英語スラッグ（パーマリンク）の自動設定
記事作成時に、AIが英語の適切なスラッグを考案し、本文末尾に `[Slug: your-english-slug]` という形式で出力します。プラグインがこれを自動抽出し、WordPressの投稿スラッグ（`post_name`）として設定します。

### 3.5. APIリクエスト共通化とリトライ制御
APIとの通信エラー（503 / 429 など）への耐性を高めるため、共通の通信メソッド `execute_api_request` を採用しています。
*   **自動リトライ**: 最大3回までリトライを実施。
*   **待機処理**: リトライ前に5秒間スリープし、API側のレート制限緩和を待ちます。

### 3.6. ログ記録・閲覧機能
記事が生成されるたびに、以下の項目がセキュアなCSV形式で記録されます。
*   **保存先**: `/wp-content/uploads/wp-ai-auto-blogger-logs/generation_log.csv`
    *   外部から直接ブラウザ経由でアクセスできないよう、`.htaccess` (Deny from all) と `index.php` が自動配置されます。
*   **記録項目**: 生成日時、使用モデル、テーマ、Post ID、生成文字数、処理時間（秒）、入力トークン数、出力トークン数、合計トークン数。
*   **ログビューワー**: 管理画面の「生成ログ」サブメニューからテーブル形式で閲覧可能。Post IDをクリックすると該当記事の編集画面へ直接遷移できます。

---

## 4. セキュリティ・堅牢性設計
*   **Ajaxリクエスト保護**: 全てのAjax処理に `check_ajax_referer` を用いたNonce検証を実装。
*   **アクセス権限の制限**: `manage_options` 権限を持つ管理者のみが記事生成・設定変更・ログ閲覧を行えるよう徹底。
*   **入力サニタイズ**: 受信データに対して `sanitize_text_field` / `sanitize_textarea_field` を適用。
*   **出力エスケープ**: HTML上の出力箇所すべてに `esc_html()`, `esc_attr()`, `esc_url()` を徹底し、XSS（クロスサイトスクリプティング）を防止。
*   **直接アクセスの防止**: すべてのPHPファイルの先頭に `defined( 'ABSPATH' ) or exit;` を配置。
