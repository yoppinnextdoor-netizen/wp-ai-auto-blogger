<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_AI_Auto_Blogger_Admin_Page {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    public function add_admin_menu() {
        add_menu_page(
            'AI Auto Blogger',
            'AI Auto Blogger',
            'manage_options',
            'wp-ai-auto-blogger',
            array( $this, 'render_generator_page' ),
            'dashicons-edit',
            20
        );

        add_submenu_page(
            'wp-ai-auto-blogger',
            '記事生成',
            '記事生成',
            'manage_options',
            'wp-ai-auto-blogger',
            array( $this, 'render_generator_page' )
        );

        add_submenu_page(
            'wp-ai-auto-blogger',
            '設定 (APIキー)',
            '設定 (APIキー)',
            'manage_options',
            'wp-ai-auto-blogger-settings',
            array( $this, 'render_settings_page' )
        );

        add_submenu_page(
            'wp-ai-auto-blogger',
            '生成ログ',
            '生成ログ',
            'manage_options',
            'wp-ai-auto-blogger-logs',
            array( $this, 'render_logs_page' )
        );
    }

    public function register_settings() {
        register_setting( 'wp_ai_auto_blogger_settings_group', 'wp_ai_auto_blogger_gemini_key' );
        register_setting( 'wp_ai_auto_blogger_settings_group', 'wp_ai_auto_blogger_openai_key' );
        register_setting( 'wp_ai_auto_blogger_settings_group', 'wp_ai_auto_blogger_claude_key' );
    }

    public function enqueue_scripts( $hook ) {
        if ( strpos( $hook, 'wp-ai-auto-blogger' ) === false ) {
            return;
        }
        wp_enqueue_style( 'wp-ai-auto-blogger-css', WP_AI_AUTO_BLOGGER_URL . 'assets/admin.css', array(), WP_AI_AUTO_BLOGGER_VERSION );
        wp_enqueue_script( 'wp-ai-auto-blogger-js', WP_AI_AUTO_BLOGGER_URL . 'assets/admin.js', array( 'jquery' ), WP_AI_AUTO_BLOGGER_VERSION, true );
        wp_localize_script( 'wp-ai-auto-blogger-js', 'wpAiAutoBlogger', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'wp_ai_auto_blogger_nonce' )
        ) );
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>AI Auto Blogger - API設定</h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'wp_ai_auto_blogger_settings_group' ); ?>
                <?php do_settings_sections( 'wp_ai_auto_blogger_settings_group' ); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Gemini API Key</th>
                        <td><input type="password" name="wp_ai_auto_blogger_gemini_key" value="<?php echo esc_attr( get_option('wp_ai_auto_blogger_gemini_key') ); ?>" class="regular-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">OpenAI API Key</th>
                        <td><input type="password" name="wp_ai_auto_blogger_openai_key" value="<?php echo esc_attr( get_option('wp_ai_auto_blogger_openai_key') ); ?>" class="regular-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Claude API Key</th>
                        <td><input type="password" name="wp_ai_auto_blogger_claude_key" value="<?php echo esc_attr( get_option('wp_ai_auto_blogger_claude_key') ); ?>" class="regular-text" /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function render_generator_page() {
        ?>
        <div class="wrap wp-ai-auto-blogger-wrap">
            <h1>AI Auto Blogger - 記事生成</h1>
            
            <div class="wp-ai-form-container">
                <form id="wp-ai-generate-form">
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><label for="ai_model">AIモデル選択</label></th>
                            <td>
                                <select id="ai_model" name="ai_model">
                                    <optgroup label="Google Gemini (最新3.6/3.5世代)">
                                        <option value="gemini-3.6-flash">Gemini 3.6 Flash (最新GA・主力ワークホース・トークン効率最高)</option>
                                        <option value="gemini-3.5-flash-lite">Gemini 3.5 Flash-Lite (最新GA・超低レイテンシ・高頻度向け)</option>
                                        <option value="gemini-3.1-pro">Gemini 3.1 Pro (高度な推論・長文構成)</option>
                                    </optgroup>
                                    <optgroup label="OpenAI GPT (最新5.6/5.5世代)">
                                        <option value="gpt-5.6">GPT-5.6 (最新フラッグシップ・最高峰の表現力)</option>
                                        <option value="gpt-5.6-terra">GPT-5.6 Terra (バランス・高コストパフォーマンス)</option>
                                        <option value="gpt-5.6-luna">GPT-5.6 Luna (超高速・低コスト)</option>
                                        <option value="gpt-5.6-sol">GPT-5.6 Sol (Preview版・高度マルチモーダル/計画立案)</option>
                                        <option value="gpt-5.5-pro">GPT-5.5 Pro (一般利用可能な安定モデル)</option>
                                        <option value="gpt-5.5-instant">GPT-5.5 Instant (高速・日常タスク用)</option>
                                    </optgroup>
                                    <optgroup label="Anthropic Claude (最新5.x/4.x世代)">
                                        <option value="claude-sonnet-5">Claude Sonnet 5 (最新フラッグシップ・エージェント/文章構成特化)</option>
                                        <option value="claude-fable-5">Claude Fable 5 (最上位汎用モデル・高度分析)</option>
                                        <option value="claude-4.8-opus">Claude 4.8 Opus (高度な推論・熟考型)</option>
                                        <option value="claude-4.6-sonnet">Claude 4.6 Sonnet (安定実運用向け)</option>
                                        <option value="claude-4.5-haiku">Claude 4.5 Haiku (高速・低コスト)</option>
                                    </optgroup>
                                </select>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="article_theme">記事のテーマ (必須)</label></th>
                            <td><input type="text" id="article_theme" name="article_theme" class="large-text" required placeholder="例：最新のAIツールの活用法について" /></td>
                        </tr>
                        <?php
                        $saved_industries = get_option( 'wp_ai_auto_blogger_saved_industries', array() );
                        $saved_targets = get_option( 'wp_ai_auto_blogger_saved_targets', array() );
                        ?>
                        <datalist id="industry_list">
                            <?php foreach ( $saved_industries as $ind ) : ?>
                                <option value="<?php echo esc_attr( $ind ); ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <datalist id="target_list">
                            <?php foreach ( $saved_targets as $tgt ) : ?>
                                <option value="<?php echo esc_attr( $tgt ); ?>"></option>
                            <?php endforeach; ?>
                        </datalist>

                        <tr valign="top">
                            <th scope="row"><label for="article_industry">業種</label></th>
                            <td>
                                <input type="text" id="article_industry" name="article_industry" list="industry_list" class="regular-text" placeholder="例：IT・通信業" autocomplete="off" />
                                <?php if ( ! empty( $saved_industries ) ) : ?>
                                    <p class="description" style="margin-top:5px;">最近の入力: 
                                        <?php foreach ( array_slice( $saved_industries, 0, 5 ) as $ind ) : ?>
                                            <span class="wp-ai-history-tag-wrapper" style="display:inline-block; background:#f0f0f1; padding:2px 6px; border-radius:3px; margin-right:4px; font-size:12px;">
                                                <a href="#" class="wp-ai-history-tag" data-target="article_industry" style="text-decoration:none; color:#2271b1;"><?php echo esc_html( $ind ); ?></a>
                                                <span class="wp-ai-delete-tag" data-value="<?php echo esc_attr( $ind ); ?>" data-type="industry" style="margin-left:4px; color:#a00; font-weight:bold; cursor:pointer;" title="削除">&times;</span>
                                            </span>
                                        <?php endforeach; ?>
                                    </p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="article_target">ターゲット層</label></th>
                            <td>
                                <input type="text" id="article_target" name="article_target" list="target_list" class="regular-text" placeholder="例：中小企業の経営者" autocomplete="off" />
                                <?php if ( ! empty( $saved_targets ) ) : ?>
                                    <p class="description" style="margin-top:5px;">最近の入力: 
                                        <?php foreach ( array_slice( $saved_targets, 0, 5 ) as $tgt ) : ?>
                                            <span class="wp-ai-history-tag-wrapper" style="display:inline-block; background:#f0f0f1; padding:2px 6px; border-radius:3px; margin-right:4px; font-size:12px;">
                                                <a href="#" class="wp-ai-history-tag" data-target="article_target" style="text-decoration:none; color:#2271b1;"><?php echo esc_html( $tgt ); ?></a>
                                                <span class="wp-ai-delete-tag" data-value="<?php echo esc_attr( $tgt ); ?>" data-type="target" style="margin-left:4px; color:#a00; font-weight:bold; cursor:pointer;" title="削除">&times;</span>
                                            </span>
                                        <?php endforeach; ?>
                                    </p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="article_content">記事に含めたい内容・メモ</label></th>
                            <td><textarea id="article_content" name="article_content" class="large-text" rows="5" placeholder="箇条書きやメモを入力してください"></textarea></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="generate_thumbnail">アイキャッチ画像</label></th>
                            <td>
                                <label><input type="checkbox" id="generate_thumbnail" name="generate_thumbnail" value="1" /> 記事内容に基づいて自動生成する (gpt-image-2 または Gemini 3.1 Flash Imageを使用)</label>
                                <p class="description" style="color:#a00; margin-top:5px; font-size:12px;">※記事生成に選んだAIと同じモデル（APIキー）を使用して画像を生成します。</p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" id="wp-ai-generate-btn" class="button button-primary button-large">記事を生成（下書き保存）</button>
                    </p>
                </form>
            </div>
            
            <div id="wp-ai-result-container" style="display:none; margin-top:20px; padding:20px; background:#fff; border:1px solid #ccd0d4; box-shadow:0 1px 1px rgba(0,0,0,.04);">
                <h2>生成結果</h2>
                <div id="wp-ai-spinner" class="spinner is-active" style="float:none; margin-bottom:10px;"></div>
                <div id="wp-ai-response-message"></div>
                <div id="wp-ai-generated-link"></div>
            </div>
        </div>
        <?php
    }

    public function render_logs_page() {
        ?>
        <div class="wrap">
            <h1>AI Auto Blogger - 生成ログ</h1>
            <?php
            $upload_dir = wp_upload_dir();
            $log_file = $upload_dir['basedir'] . '/wp-ai-auto-blogger-logs/generation_log.csv';

            if ( ! file_exists( $log_file ) ) {
                echo '<p>まだログは記録されていません。</p>';
            } else {
                echo '<table class="wp-list-table widefat fixed striped" style="margin-top:20px;">';
                $fp = fopen( $log_file, 'r' );
                $is_header = true;
                if ( $fp ) {
                    while ( ( $row = fgetcsv( $fp ) ) !== false ) {
                        if ( $is_header ) {
                            echo '<thead><tr>';
                            foreach ( $row as $cell ) {
                                echo '<th><strong>' . esc_html( $cell ) . '</strong></th>';
                            }
                            echo '</tr></thead><tbody>';
                            $is_header = false;
                        } else {
                            echo '<tr>';
                            foreach ( $row as $index => $cell ) {
                                if ( $index === 3 && is_numeric($cell) ) {
                                    // Post ID column -> create an edit link
                                    $edit_link = get_edit_post_link( $cell );
                                    if ( $edit_link ) {
                                        echo '<td><a href="' . esc_url( $edit_link ) . '" target="_blank">' . esc_html( $cell ) . '</a></td>';
                                    } else {
                                        echo '<td>' . esc_html( $cell ) . '</td>';
                                    }
                                } else {
                                    echo '<td>' . esc_html( $cell ) . '</td>';
                                }
                            }
                            echo '</tr>';
                        }
                    }
                    fclose( $fp );
                    echo '</tbody></table>';
                }
            }
            ?>
        </div>
        <?php
    }
}
