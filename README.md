# WP AI Auto Blogger (v1.0.4)

![WordPress Version](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)
![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)
![License](https://img.shields.io/badge/License-GPLv2%20or%20later-green.svg)

**WP AI Auto Blogger** は、最新のAI API（Google Gemini, OpenAI GPT, Anthropic Claude）を活用し、テーマやターゲット層を入力するだけで、検索エンジンや読者に最適化されたブログ記事（下書き）を自動作成する高機能WordPressプラグインです。

---

## 🌟 主な特徴

- 🤖 **マルチAIモデル対応 (2026年7月最新ラインナップ)**:
  - **Google Gemini**: Gemini 3.6 Flash (最新GA・主力), Gemini 3.5 Flash-Lite, Gemini 3.1 Pro
  - **OpenAI GPT**: GPT-5.6 (フラッグシップ), GPT-5.6 Terra, GPT-5.6 Luna, GPT-5.6 Sol (Preview), GPT-5.5 Pro, GPT-5.5 Instant
  - **Anthropic Claude**: Claude Sonnet 5 (最新エージェント/文章構成モデル), Claude Fable 5, Claude 4.8 Opus, Claude 4.6 Sonnet, Claude 4.5 Haiku
- 🎨 **アイキャッチ画像の自動生成**:
  - 本文からAIが英語プロンプトを考案し、`gpt-image-2` や `gemini-3.1-flash-image` で画像を自動生成。メディアライブラリへ直接登録・アイキャッチ設定。
- 🔗 **英語パーマリンク（スラッグ）自動設定**:
  - 本文に合わせた適切な英語スラッグをAIが考案し、WordPressの投稿スラッグ（`post_name`）に自動適用。
- 🏷️ **入力履歴（クイックタグ）機能**:
  - 過去に入力した「業種」や「ターゲット層」を最大20件まで自動記録。ワンクリックで再利用・ワンクリックで削除が可能。
- 📊 **トークン＆詳細ログ記録**:
  - 生成日時、使用モデル、生成文字数、処理時間、消費トークン数（Input/Output/Total）をセキュアなCSVに自動記録。専用ビューワーで閲覧可能。
- 🛡️ **堅牢なセキュリティ設計**:
  - Nonce検証、`manage_options` 権限チェック、XSS防止のエスケープ、直接アクセス防止、セキュアなディレクトリ保護（.htaccess）を標準装備。

---

## 📁 ディレクトリ構造

```text
wp-ai-auto-blogger/
├── wp-ai-auto-blogger.php      # メインエントリファイル
├── README.md                   # 本ドキュメント
├── plugin_specifications.md    # 詳細仕様書
├── assets/
│   ├── admin.css               # 管理画面スタイル
│   └── admin.js                # UIインタラクション・AJAX制御
└── includes/
    ├── class-admin-page.php    # 管理画面UIレンダリング
    ├── class-api-handler.php   # 各種AI API通信・画像生成・通信リトライ
    └── class-post-creator.php  # 投稿作成・スラッグ適用・フォーマット処理
```

---

## 🚀 インストール & 使い方

### 1. インストール
1. リポジトリをダウンロードし、フォルダ名を `wp-ai-auto-blogger` とします。
2. WordPressの `/wp-content/plugins/` ディレクトリに配置します。
3. WordPress管理画面の「プラグイン」メニューから **WP AI Auto Blogger** を有効化します。

### 2. 初期設定
1. 管理画面の左メニュー「AI Auto Blogger」→「設定」を開きます。
2. 使用するAIサービス（Google Gemini / OpenAI / Anthropic）のAPIキーを入力して保存します。

### 3. 記事生成
1. 「記事の作成」画面を開きます。
2. 使用するモデルを選択し、「記事のテーマ」「業種」「ターゲット層」「含めたい内容」を入力します。
3. 「アイキャッチ画像を自動生成する」にチェックを入れて「記事を生成する」をクリックします。
4. 生成が完了すると、自動的に下書き記事として保存されます。

---

## 🔒 セキュリティ & 仕様

- **API通信保護**: APIエラー（429 / 503）発生時は最大3回まで自動リトライを実施（5秒インターバル）。
- **ログ保護**: ログファイル保存先 `/wp-content/uploads/wp-ai-auto-blogger-logs/` は `.htaccess` (Deny from all) で直接アクセスから保護されています。

---

## 📄 ライセンス

GPL v2 or later
