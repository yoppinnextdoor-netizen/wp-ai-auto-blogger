<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_AI_Auto_Blogger_API_Handler {

    public function __construct() {
        add_action( 'wp_ajax_wp_ai_auto_blogger_generate', array( $this, 'handle_generation_request' ) );
        add_action( 'wp_ajax_wp_ai_auto_blogger_delete_tag', array( $this, 'handle_delete_tag_request' ) );
    }

    public function handle_delete_tag_request() {
        check_ajax_referer( 'wp_ai_auto_blogger_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => '権限がありません。' ) );
        }

        $tag_value = sanitize_text_field( $_POST['tag_value'] );
        $tag_type  = sanitize_text_field( $_POST['tag_type'] );

        if ( $tag_type === 'industry' ) {
            $saved = get_option( 'wp_ai_auto_blogger_saved_industries', array() );
            $saved = array_values( array_diff( $saved, array( $tag_value ) ) );
            update_option( 'wp_ai_auto_blogger_saved_industries', $saved );
        } elseif ( $tag_type === 'target' ) {
            $saved = get_option( 'wp_ai_auto_blogger_saved_targets', array() );
            $saved = array_values( array_diff( $saved, array( $tag_value ) ) );
            update_option( 'wp_ai_auto_blogger_saved_targets', $saved );
        }

        wp_send_json_success( array( 'message' => '削除しました。' ) );
    }

    public function handle_generation_request() {
        check_ajax_referer( 'wp_ai_auto_blogger_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => '権限がありません。' ) );
        }

        $model    = sanitize_text_field( $_POST['model'] );
        $theme    = sanitize_text_field( $_POST['theme'] );
        $industry = sanitize_text_field( $_POST['industry'] );
        $target   = sanitize_text_field( $_POST['target'] );
        $content  = sanitize_textarea_field( $_POST['content'] );
        $generate_thumbnail = isset( $_POST['generate_thumbnail'] ) ? (int) $_POST['generate_thumbnail'] : 0;

        // 業種とターゲット層の入力履歴を保存
        if ( ! empty( $industry ) ) {
            $saved_industries = get_option( 'wp_ai_auto_blogger_saved_industries', array() );
            if ( ! in_array( $industry, $saved_industries ) ) {
                array_unshift( $saved_industries, $industry );
                if ( count( $saved_industries ) > 20 ) array_pop( $saved_industries );
                update_option( 'wp_ai_auto_blogger_saved_industries', $saved_industries );
            }
        }
        if ( ! empty( $target ) ) {
            $saved_targets = get_option( 'wp_ai_auto_blogger_saved_targets', array() );
            if ( ! in_array( $target, $saved_targets ) ) {
                array_unshift( $saved_targets, $target );
                if ( count( $saved_targets ) > 20 ) array_pop( $saved_targets );
                update_option( 'wp_ai_auto_blogger_saved_targets', $saved_targets );
            }
        }

        $prompt = $this->build_prompt( $theme, $industry, $target, $content, $generate_thumbnail );

        $start_time = microtime(true);
        $result = array();

        if ( strpos( $model, 'gemini' ) !== false ) {
            $result = $this->call_gemini_api( $model, $prompt );
        } elseif ( strpos( $model, 'gpt' ) !== false ) {
            $result = $this->call_openai_api( $model, $prompt );
        } elseif ( strpos( $model, 'claude' ) !== false ) {
            $result = $this->call_claude_api( $model, $prompt );
        } else {
            wp_send_json_error( array( 'message' => '無効なモデルが選択されました。' ) );
        }

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        $generated_text = $result['text'];
        $usage = $result['usage'];
        $processing_time = round( microtime(true) - $start_time, 2 );
        
        $image_prompt = '';
        // Extract Image Prompt
        if ( preg_match( '/\[Image\s*Prompt:\s*(.*?)\]/is', $generated_text, $img_matches ) ) {
            $image_prompt = trim( $img_matches[1] );
            $generated_text = str_replace( $img_matches[0], '', $generated_text );
        }

        $char_count = mb_strlen( $generated_text );

        // Pass to post creator
        $post_creator = new WP_AI_Auto_Blogger_Post_Creator();
        $post_id = $post_creator->create_draft_post( $theme, $generated_text );

        if ( is_wp_error( $post_id ) ) {
            wp_send_json_error( array( 'message' => '記事の保存に失敗しました。: ' . $post_id->get_error_message() ) );
        }

        // Handle Image Generation
        $thumbnail_message = '';
        if ( $generate_thumbnail && ! empty( $image_prompt ) ) {
            $thumbnail_result = $this->generate_and_attach_thumbnail( $post_id, $theme, $image_prompt, $model );
            if ( is_wp_error( $thumbnail_result ) ) {
                $err_msg = $thumbnail_result->get_error_message();
                error_log( 'WP AI Auto Blogger - Thumbnail Error: ' . $err_msg );
                $thumbnail_message = ' (アイキャッチ失敗: ' . $err_msg . ')';
            } else {
                $thumbnail_message = ' (アイキャッチ設定完了)';
            }
        } elseif ( $generate_thumbnail && empty( $image_prompt ) ) {
            error_log( 'WP AI Auto Blogger - Thumbnail Error: Image prompt could not be extracted.' );
            $thumbnail_message = ' (アイキャッチ用プロンプト抽出失敗)';
        }

        // Log the generation
        $this->log_generation( array(
            'date'            => current_time('mysql'),
            'model'           => $model,
            'theme'           => $theme,
            'post_id'         => $post_id,
            'char_count'      => $char_count,
            'processing_time' => $processing_time,
            'tokens'          => $usage
        ) );

        $edit_link = get_edit_post_link( $post_id, 'raw' );

        wp_send_json_success( array(
            'message'   => '記事が正常に生成され、下書きとして保存されました。' . $thumbnail_message,
            'post_id'   => $post_id,
            'edit_link' => html_entity_decode( $edit_link )
        ) );
    }

    private function build_prompt( $theme, $industry, $target, $content, $generate_thumbnail ) {
        $prompt = "あなたはプロフェッショナルなコンテンツクリエイターです。以下の要件に従って、ブログ記事を作成してください。\n\n";
        $prompt .= "【テーマ】: " . $theme . "\n";
        
        if ( ! empty( $industry ) ) {
            $prompt .= "【業種】: " . $industry . "\n";
        }
        if ( ! empty( $target ) ) {
            $prompt .= "【ターゲット層】: " . $target . "\n";
        }
        if ( ! empty( $content ) ) {
            $prompt .= "【含めるべき内容・メモ】:\n" . $content . "\n";
        }

        $prompt .= "\n【指示】:\n";
        $prompt .= "- 魅力的なタイトルをつけてください（最初の行に <h1> または # で記述）。\n";
        $prompt .= "- ターゲット層に響くトーン＆マナーで記述してください。\n";
        $prompt .= "- SEOを意識し、見出し（h2, h3）を適切に使用して構造化してください。\n";
        $prompt .= "- HTML形式（h2, h3, p, ul, li 等）で出力してください（マークダウンでも可ですがHTMLタグが望ましいです）。\n";
        $prompt .= "- 最後に、この記事のURLに最適な英語のスラッグ（パーマリンク用、英小文字とハイフンのみ）を考え、記事の末尾に `[Slug: your-english-slug]` という形式で必ず出力してください。\n";

        if ( $generate_thumbnail ) {
            $prompt .= "- さらに、この記事のアイキャッチ画像を作成するための高品質な画像生成用プロンプト（詳細な情景、被写体、スタイルを英語で記述）を考え、スラッグの次の行に `[ImagePrompt: your english prompt here]` という形式で1行で必ず出力してください。\n";
        }

        return $prompt;
    }

    /**
     * 共通のAPIリクエスト実行メソッド（リトライ付き）
     */
    private function execute_api_request( $url, $headers, $body, $error_prefix ) {
        $max_retries = 3;
        $retry_count = 0;
        $response = null;

        while ( $retry_count < $max_retries ) {
            $response = wp_remote_post( $url, array(
                'headers' => $headers,
                'body'    => wp_json_encode( $body ),
                'timeout' => 120
            ) );

            if ( is_wp_error( $response ) ) {
                return $response;
            }

            $response_code = wp_remote_retrieve_response_code( $response );
            if ( $response_code === 503 || $response_code === 429 ) {
                $retry_count++;
                if ( $retry_count < $max_retries ) {
                    sleep( 5 );
                    continue;
                }
            }
            break;
        }

        $response_body = wp_remote_retrieve_body( $response );
        $data = json_decode( $response_body, true );

        if ( $response_code !== 200 ) {
             $error_message = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown error';
             return new WP_Error( 'api_error', "{$error_prefix}エラー: {$error_message}" );
        }

        return $data;
    }

    private function call_gemini_api( $model, $prompt ) {
        $api_key = get_option( 'wp_ai_auto_blogger_gemini_key' );
        if ( empty( $api_key ) ) {
            return new WP_Error( 'missing_key', 'Gemini APIキーが設定されていません。' );
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";
        $headers = array( 'Content-Type' => 'application/json' );
        $body = array(
            'contents' => array(
                array( 'parts' => array( array( 'text' => $prompt ) ) )
            )
        );

        $data = $this->execute_api_request( $url, $headers, $body, 'Gemini API' );
        if ( is_wp_error( $data ) ) return $data;

        if ( isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
            $usage = array(
                'prompt_tokens'     => isset($data['usageMetadata']['promptTokenCount']) ? $data['usageMetadata']['promptTokenCount'] : 0,
                'completion_tokens' => isset($data['usageMetadata']['candidatesTokenCount']) ? $data['usageMetadata']['candidatesTokenCount'] : 0,
                'total_tokens'      => isset($data['usageMetadata']['totalTokenCount']) ? $data['usageMetadata']['totalTokenCount'] : 0,
            );
            return array( 'text' => $data['candidates'][0]['content']['parts'][0]['text'], 'usage' => $usage );
        }

        return new WP_Error( 'api_error', '予期しないレスポンス形式です。' );
    }

    private function call_openai_api( $model, $prompt ) {
        $api_key = get_option( 'wp_ai_auto_blogger_openai_key' );
        if ( empty( $api_key ) ) {
            return new WP_Error( 'missing_key', 'OpenAI APIキーが設定されていません。' );
        }

        $url = 'https://api.openai.com/v1/chat/completions';
        $headers = array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        );
        $body = array(
            'model'    => $model,
            'messages' => array( array( 'role' => 'user', 'content' => $prompt ) )
        );

        $data = $this->execute_api_request( $url, $headers, $body, 'OpenAI API' );
        if ( is_wp_error( $data ) ) return $data;

        if ( isset( $data['choices'][0]['message']['content'] ) ) {
            $usage = array(
                'prompt_tokens'     => isset($data['usage']['prompt_tokens']) ? $data['usage']['prompt_tokens'] : 0,
                'completion_tokens' => isset($data['usage']['completion_tokens']) ? $data['usage']['completion_tokens'] : 0,
                'total_tokens'      => isset($data['usage']['total_tokens']) ? $data['usage']['total_tokens'] : 0,
            );
            return array( 'text' => $data['choices'][0]['message']['content'], 'usage' => $usage );
        }

        return new WP_Error( 'api_error', '予期しないレスポンス形式です。' );
    }

    private function call_claude_api( $model, $prompt ) {
        $api_key = get_option( 'wp_ai_auto_blogger_claude_key' );
        if ( empty( $api_key ) ) {
            return new WP_Error( 'missing_key', 'Claude APIキーが設定されていません。' );
        }

        $url = 'https://api.anthropic.com/v1/messages';
        $headers = array(
            'Content-Type'      => 'application/json',
            'x-api-key'         => $api_key,
            'anthropic-version' => '2023-06-01'
        );
        $body = array(
            'model'      => $model,
            'max_tokens' => 4096,
            'messages'   => array( array( 'role' => 'user', 'content' => $prompt ) )
        );

        $data = $this->execute_api_request( $url, $headers, $body, 'Claude API' );
        if ( is_wp_error( $data ) ) return $data;

        if ( isset( $data['content'][0]['text'] ) ) {
            $input_tokens = isset($data['usage']['input_tokens']) ? $data['usage']['input_tokens'] : 0;
            $output_tokens = isset($data['usage']['output_tokens']) ? $data['usage']['output_tokens'] : 0;
            $usage = array(
                'prompt_tokens'     => $input_tokens,
                'completion_tokens' => $output_tokens,
                'total_tokens'      => $input_tokens + $output_tokens,
            );
            return array( 'text' => $data['content'][0]['text'], 'usage' => $usage );
        }

        return new WP_Error( 'api_error', '予期しないレスポンス形式です。' );
    }

    private function log_generation( $log_data ) {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/wp-ai-auto-blogger-logs';
        
        if ( ! file_exists( $log_dir ) ) {
            wp_mkdir_p( $log_dir );
            // Protect directory from web access
            file_put_contents( $log_dir . '/.htaccess', 'Deny from all' );
            file_put_contents( $log_dir . '/index.php', '<?php // Silence is golden' );
        }
        
        $log_file = $log_dir . '/generation_log.csv';
        $file_exists = file_exists( $log_file );
        
        $fp = fopen( $log_file, 'a' );
        if ( $fp ) {
            // Write CSV header if new file
            if ( ! $file_exists ) {
                fputcsv( $fp, array('日時', 'モデル', 'テーマ', 'Post ID', '生成文字数', '処理時間(秒)', '入力トークン数', '出力トークン数', '合計トークン数') );
            }
            
            fputcsv( $fp, array(
                $log_data['date'],
                $log_data['model'],
                $log_data['theme'],
                $log_data['post_id'],
                $log_data['char_count'],
                $log_data['processing_time'],
                $log_data['tokens']['prompt_tokens'],
                $log_data['tokens']['completion_tokens'],
                $log_data['tokens']['total_tokens']
            ) );
            
            fclose( $fp );
        }
    }

    private function generate_and_attach_thumbnail( $post_id, $theme, $image_prompt, $model ) {
        $use_api = '';
        if ( strpos( $model, 'gemini' ) !== false ) {
            $use_api = 'gemini';
        } elseif ( strpos( $model, 'gpt' ) !== false ) {
            $use_api = 'openai';
        } else {
            // Claude: fallback
            $api_key_openai = get_option( 'wp_ai_auto_blogger_openai_key' );
            if ( ! empty( $api_key_openai ) ) {
                $use_api = 'openai';
            } else {
                $use_api = 'gemini';
            }
        }

        $image_url = '';
        $local_file_path = '';

        if ( $use_api === 'openai' ) {
            $api_key = get_option( 'wp_ai_auto_blogger_openai_key' );
            if ( empty( $api_key ) ) {
                return new WP_Error( 'missing_key', 'OpenAI APIキーが設定されていません。' );
            }

            $url = 'https://api.openai.com/v1/images/generations';
            $headers = array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            );
            $body = array(
                'model'  => 'gpt-image-2',
                'prompt' => $image_prompt,
                'n'      => 1,
                'size'   => '1024x1024'
            );

            $data = $this->execute_api_request( $url, $headers, $body, 'gpt-image-2' );
            if ( is_wp_error( $data ) ) return $data;

            if ( isset( $data['data'][0]['url'] ) ) {
                $image_url = $data['data'][0]['url'];
            }
        } elseif ( $use_api === 'gemini' ) {
            $api_key = get_option( 'wp_ai_auto_blogger_gemini_key' );
            if ( empty( $api_key ) ) {
                return new WP_Error( 'missing_key', 'Gemini APIキーが設定されていません。' );
            }

            // 初回モデル: gemini-3.1-flash-image
            $models_to_try = array( 'gemini-3.1-flash-image', 'imagen-3.0-generate-002' );
            $gemini_success = false;

            foreach ( $models_to_try as $g_model ) {
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$g_model}:generateContent?key={$api_key}";
                $headers = array( 'Content-Type' => 'application/json' );
                $body = array(
                    'contents' => array(
                        array( 'parts' => array( array( 'text' => $image_prompt ) ) )
                    )
                );

                $data = $this->execute_api_request( $url, $headers, $body, "Gemini Image ({$g_model})" );
                if ( ! is_wp_error( $data ) && isset( $data['candidates'][0]['content']['parts'] ) ) {
                    foreach ( $data['candidates'][0]['content']['parts'] as $part ) {
                        if ( isset( $part['inlineData']['data'] ) ) {
                            $image_data = base64_decode( $part['inlineData']['data'] );
                            $upload = wp_upload_bits( 'gemini_flash_' . time() . '.png', null, $image_data );
                            if ( ! $upload['error'] ) {
                                $local_file_path = $upload['file'];
                                $image_url = $upload['url'];
                                $gemini_success = true;
                            }
                            break;
                        }
                    }
                }
                if ( $gemini_success ) break;
                sleep( 2 ); // 次のフォールバックまで短いスリープ
            }

            // Geminiが高負荷で全滅し、かつOpenAIキーが登録されている場合は gpt-image-2 に自動フォールバック
            if ( ! $gemini_success ) {
                $openai_key = get_option( 'wp_ai_auto_blogger_openai_key' );
                if ( ! empty( $openai_key ) ) {
                    $url = 'https://api.openai.com/v1/images/generations';
                    $headers = array(
                        'Content-Type'  => 'application/json',
                        'Authorization' => 'Bearer ' . $openai_key
                    );
                    $body = array(
                        'model'  => 'gpt-image-2',
                        'prompt' => $image_prompt,
                        'n'      => 1,
                        'size'   => '1024x1024'
                    );

                    $data = $this->execute_api_request( $url, $headers, $body, 'gpt-image-2 (Fallback)' );
                    if ( ! is_wp_error( $data ) && isset( $data['data'][0]['url'] ) ) {
                        $image_url = $data['data'][0]['url'];
                    }
                }
            }
        }

        if ( ! empty( $local_file_path ) ) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            
            $file_array = array(
                'name'     => basename( $local_file_path ),
                'tmp_name' => $local_file_path
            );
            $desc = "アイキャッチ画像: " . sanitize_text_field( $theme );
            $attachment_id = media_handle_sideload( $file_array, $post_id, $desc );
            
            if ( ! is_wp_error( $attachment_id ) ) {
                set_post_thumbnail( $post_id, $attachment_id );
                return true;
            } else {
                return $attachment_id;
            }
        } elseif ( ! empty( $image_url ) ) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            
            $desc = "アイキャッチ画像: " . sanitize_text_field( $theme );
            $attachment_id = media_sideload_image( $image_url, $post_id, $desc, 'id' );
            
            if ( ! is_wp_error( $attachment_id ) ) {
                set_post_thumbnail( $post_id, $attachment_id );
                return true;
            } else {
                return $attachment_id;
            }
        }

        return new WP_Error( 'api_error', '画像データを取得できませんでした。' );
    }
}

// Initialize handler so it hooks to AJAX
new WP_AI_Auto_Blogger_API_Handler();
