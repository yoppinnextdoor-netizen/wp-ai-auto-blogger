<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_AI_Auto_Blogger_Post_Creator {

    public function create_draft_post( $theme, $content ) {
        // AIが生成したコンテンツからタイトル（最初の見出し）を抽出
        $title = $theme;
        
        // 正規表現で最初の <h1> または # タイトルを探す
        if ( preg_match( '/^(?:#\s|<h1[^>]*>)(.*?)(?:<\/h1>|$)/m', $content, $matches ) ) {
            $title = wp_strip_all_tags( $matches[1] );
            // コンテンツからタイトル部分を削除
            $content = preg_replace( '/^(?:#\s|<h1[^>]*>)(.*?)(?:<\/h1>|$)\r?\n?/m', '', $content, 1 );
        }
        
        // スラッグ（パーマリンク）を抽出
        $slug = '';
        if ( preg_match( '/\[Slug:\s*([a-zA-Z0-9\-]+)\]/i', $content, $slug_matches ) ) {
            $slug = sanitize_title( $slug_matches[1] );
            // コンテンツからスラッグ指定タグを削除
            $content = preg_replace( '/\[Slug:\s*[a-zA-Z0-9\-]+\]/i', '', $content );
        }
        
        // マークダウンを一部HTMLに置換（APIがマークダウンを返した場合の保険）
        $content = preg_replace('/^##\s+(.*)$/m', '<h2>$1</h2>', $content);
        $content = preg_replace('/^###\s+(.*)$/m', '<h3>$1</h3>', $content);
        $content = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $content);
        
        $post_data = array(
            'post_title'   => sanitize_text_field( $title ),
            'post_content' => wp_kses_post( wpautop( $content ) ),
            'post_status'  => 'draft',
            'post_type'    => 'post',
            'post_author'  => get_current_user_id(),
        );
        
        // 英語のスラッグが生成されていればセットする
        if ( ! empty( $slug ) ) {
            $post_data['post_name'] = $slug;
        }

        $post_id = wp_insert_post( $post_data, true );

        return $post_id;
    }
}
