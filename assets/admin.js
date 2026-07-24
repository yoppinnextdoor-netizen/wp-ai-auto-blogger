jQuery(document).ready(function($) {
    $('#wp-ai-generate-form').on('submit', function(e) {
        e.preventDefault();
        
        var $btn = $('#wp-ai-generate-btn');
        var $spinner = $('#wp-ai-spinner');
        var $resultContainer = $('#wp-ai-result-container');
        var $responseMessage = $('#wp-ai-response-message');
        var $generatedLink = $('#wp-ai-generated-link');
        
        $btn.prop('disabled', true);
        $spinner.show();
        $resultContainer.show();
        $responseMessage.html('<p>生成中... しばらくお待ちください（約1〜2分かかります）。</p>');
        $generatedLink.html('');
        
        var data = {
            action: 'wp_ai_auto_blogger_generate',
            nonce: wpAiAutoBlogger.nonce,
            model: $('#ai_model').val(),
            theme: $('#article_theme').val(),
            industry: $('#article_industry').val(),
            target: $('#article_target').val(),
            content: $('#article_content').val(),
            generate_thumbnail: $('#generate_thumbnail').is(':checked') ? 1 : 0
        };
        
        $.post(wpAiAutoBlogger.ajaxUrl, data, function(response) {
            $btn.prop('disabled', false);
            $spinner.hide();
            
            if (response.success) {
                $responseMessage.html('<p style="color: green; font-weight: bold;">' + response.data.message + '</p>');
                if (response.data.edit_link) {
                    $generatedLink.html('<a href="' + response.data.edit_link + '" class="button button-primary" target="_blank">記事を編集・プレビューする</a>');
                }
            } else {
                $responseMessage.html('<p style="color: red; font-weight: bold;">エラーが発生しました: ' + response.data.message + '</p>');
            }
        }).fail(function() {
            $btn.prop('disabled', false);
            $spinner.hide();
            $responseMessage.html('<p style="color: red; font-weight: bold;">通信エラーが発生しました。</p>');
        });
    });
    
    // History tag click handler
    $('.wp-ai-history-tag').on('click', function(e) {
        e.preventDefault();
        var targetId = $(this).data('target');
        var textValue = $(this).text();
        $('#' + targetId).val(textValue);
    });

    // Delete tag click handler
    $('.wp-ai-delete-tag').on('click', function(e) {
        e.preventDefault();
        var $wrapper = $(this).closest('.wp-ai-history-tag-wrapper');
        var value = $(this).data('value');
        var type = $(this).data('type');
        
        var confirmDelete = confirm('この入力履歴を削除しますか？');
        if (!confirmDelete) return;
        
        var data = {
            action: 'wp_ai_auto_blogger_delete_tag',
            nonce: wpAiAutoBlogger.nonce,
            tag_value: value,
            tag_type: type
        };
        
        $.post(wpAiAutoBlogger.ajaxUrl, data, function(response) {
            if (response.success) {
                $wrapper.fadeOut(300, function() { $(this).remove(); });
            } else {
                alert('削除に失敗しました: ' + response.data.message);
            }
        });
    });
});
