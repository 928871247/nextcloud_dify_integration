<?php
/**
 * 创建路由配置文件
 */
return [
    'routes' => [
        // 配置页面路由
        ['name' => 'admin_settings#index', 'url' => '/settings/admin/nextcloud_dify_integration/settings', 'verb' => 'GET'],
        
        // API 路由用于处理文件变化
        ['name' => 'file_sync#handleFileChange', 'url' => '/api/sync', 'verb' => 'POST'],
    ],
    'ocs' => [
        // OCS API 路由用于保存配置
        ['name' => 'API#save', 'url' => '/api/v1/config', 'verb' => 'POST'],
        ['name' => 'API#get', 'url' => '/api/v1/config', 'verb' => 'GET'],
        // 扫描目录路由
        ['name' => 'API#scan', 'url' => '/api/v1/scan/{path}', 'verb' => 'GET'],
    ]
];
