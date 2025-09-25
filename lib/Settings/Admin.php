<?php

namespace OCA\NextcloudDifyIntegration\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;
use OCA\NextcloudDifyIntegration\Service\ConfigService;

class Admin implements ISettings {
    
    private $configService;
    
    // 构造函数保持不变（DI 会注入 ConfigService）
    public function __construct(ConfigService $configService) {
        $this->configService = $configService;
    }
    
    // getForm() 和其他方法不变
    public function getForm(): TemplateResponse {
        $difyUrl = $this->configService->getDifyUrl();
        $difyApiKey = $this->configService->getDifyApiKey();
        $directoryMappings = $this->configService->getDirectoryMappings();
        $namingPattern = $this->configService->getNamingPattern();
        
        $params = [
            'difyUrl' => $difyUrl,
            'difyApiKey' => $difyApiKey,
            'directoryMappings' => $directoryMappings,
            'namingPattern' => $namingPattern
        ];
        
        return new TemplateResponse(
            'nextcloud_dify_integration',
            'admin_settings',
            $params
        );
    }
    
    public function getSection(): string {
        return 'nextcloud_dify_integration';
    }
    
    public function getPriority(): int {
        return 50;
    }
}
