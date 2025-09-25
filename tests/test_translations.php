#!/usr/bin/env php
<?php

/**
 * 测试Nextcloud Dify Integration翻译功能
 * 
 * 该脚本模拟Nextcloud环境来测试翻译文件是否正确加载
 */

// 模拟Nextcloud的翻译函数
class MockL10N {
    private $translations = [];
    private $appId;
    
    public function __construct($appId) {
        $this->appId = $appId;
        $this->loadTranslations();
    }
    
    private function loadTranslations() {
        // 加载英文翻译
        $enFile = __DIR__ . '/../l10n/en.js';
        if (file_exists($enFile)) {
            $content = file_get_contents($enFile);
            // 简单解析JS格式的翻译文件
            if (preg_match('/OC\.L10N\.register\s*\(\s*"[^"]*"\s*,\s*(\{[^}]+\})/s', $content, $matches)) {
                $json = $matches[1];
                // 简单修复JSON格式
                $json = preg_replace('/\s*:\s*/', ':', $json);
                $json = preg_replace('/,\s*}/', '}', $json);
                $translations = json_decode($json, true);
                if ($translations) {
                    $this->translations['en'] = $translations;
                }
            }
        }
        
        // 加载中文翻译
        $zhFile = __DIR__ . '/../l10n/zh_CN.js';
        if (file_exists($zhFile)) {
            $content = file_get_contents($zhFile);
            // 简单解析JS格式的翻译文件
            if (preg_match('/OC\.L10N\.register\s*\(\s*"[^"]*"\s*,\s*(\{[^}]+\})/s', $content, $matches)) {
                $json = $matches[1];
                // 简单修复JSON格式
                $json = preg_replace('/\s*:\s*/', ':', $json);
                $json = preg_replace('/,\s*}/', '}', $json);
                $translations = json_decode($json, true);
                if ($translations) {
                    $this->translations['zh_CN'] = $translations;
                }
            }
        }
    }
    
    public function t($text) {
        $lang = 'zh_CN'; // 默认使用中文
        if (isset($this->translations[$lang][$text])) {
            return $this->translations[$lang][$text];
        }
        return $text; // 如果没有翻译，返回原文
    }
    
    public function getLanguageCode() {
        return 'zh_CN';
    }
}

// 测试函数
function testTranslations() {
    echo "Testing Nextcloud Dify Integration translations...\n\n";
    
    // 创建模拟的L10N对象
    $l = new MockL10N('nextcloud_dify_integration');
    
    // 测试一些关键翻译
    $testStrings = [
        'Knowledge Base',
        'Dify Knowledge Base Integration',
        'Dify URL',
        'Dify API Key',
        'Document Naming Pattern',
        'Directory Mapping',
        'Nextcloud Directory Path',
        'Dify Knowledge Base ID',
        'Delete',
        'Add Mapping',
        'Save',
        'Configuration saved',
        'Save failed'
    ];
    
    echo "Testing translations:\n";
    echo "====================\n";
    
    foreach ($testStrings as $string) {
        $translated = $l->t($string);
        echo "Original: $string\n";
        echo "Translated: $translated\n";
        echo "Language: " . $l->getLanguageCode() . "\n";
        echo "------------------------\n";
    }
    
    // 检查文件是否存在
    echo "\nChecking translation files:\n";
    echo "==========================\n";
    
    $files = [
        '../l10n/en.js',
        '../l10n/zh_CN.js'
    ];
    
    foreach ($files as $file) {
        $fullPath = __DIR__ . '/' . $file;
        if (file_exists($fullPath)) {
            echo "✓ $file exists\n";
            $size = filesize($fullPath);
            echo "  Size: $size bytes\n";
        } else {
            echo "✗ $file not found\n";
        }
    }
    
    echo "\nTest completed.\n";
}

// 运行测试
testTranslations();
