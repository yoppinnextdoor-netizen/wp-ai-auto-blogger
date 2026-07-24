<?php
/**
 * Plugin Name: WP AI Auto Blogger
 * Plugin URI:  https://example.com
 * Description: AI API（Gemini, OpenAI, Claude）を活用してブログ記事を自動生成するプラグイン。業種やターゲットに合わせたコンテンツを自動で下書き保存します。
 * Version:     1.0.4
 * Author:      AI Organization COO
 * Text Domain: wp-ai-auto-blogger
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'WP_AI_AUTO_BLOGGER_VERSION', '1.0.4' );
define( 'WP_AI_AUTO_BLOGGER_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_AI_AUTO_BLOGGER_URL', plugin_dir_url( __FILE__ ) );

// Includes
require_once WP_AI_AUTO_BLOGGER_DIR . 'includes/class-admin-page.php';
require_once WP_AI_AUTO_BLOGGER_DIR . 'includes/class-api-handler.php';
require_once WP_AI_AUTO_BLOGGER_DIR . 'includes/class-post-creator.php';

/**
 * Initialize the plugin.
 */
function wp_ai_auto_blogger_init() {
    new WP_AI_Auto_Blogger_Admin_Page();
}
add_action( 'plugins_loaded', 'wp_ai_auto_blogger_init' );
