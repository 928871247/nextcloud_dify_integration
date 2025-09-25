<?php

// 测试多语言支持
function testL10n() {
    // 模拟Nextcloud的多语言函数
    function t($app, $text) {
        // 简化的翻译函数，实际Nextcloud环境中会从l10n文件中获取翻译
        $translations = [
            'nextcloud_dify_integration' => [
                'Knowledge Base' => '知识库',
                'Dify Knowledge Base Integration' => 'Dify 知识库集成',
                'Dify URL' => 'Dify 地址',
                'Dify API Key' => 'Dify API 密钥',
                'Document Naming Pattern' => '文档命名模式',
                'Nextcloud Directory Path' => 'Nextcloud 目录路径',
                'Dify Knowledge Base ID' => 'Dify 知识库 ID',
                'Delete' => '删除',
                'Add Mapping' => '添加映射',
                'Save' => '保存',
                'Configuration saved' => '配置已保存',
                'Save failed' => '保存失败'
            ]
        ];
        
        if (isset($translations[$app][$text])) {
            return $translations[$app][$text];
        }
        
        return $text; // 如果没有翻译，返回原文
    }
    
    // 测试翻译
    echo "Testing L10n support:\n";
    echo "English: " . t('nextcloud_dify_integration', 'Knowledge Base') . "\n";
    echo "Chinese: " . t('nextcloud_dify_integration', '知识库') . "\n";
    echo "English to Chinese: " . t('nextcloud_dify_integration', 'Dify Knowledge Base Integration') . "\n";
    
    // 验证翻译文件是否存在
    $l10nDir = __DIR__ . '/l10n';
    if (is_dir($l10nDir)) {
        echo "L10n directory exists\n";
        $files = scandir($l10nDir);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'js') {
                echo "Found translation file: $file\n";
            }
        }
    } else {
        echo "L10n directory does not exist\n";
    }
}

// 运行测试
testL10n();
