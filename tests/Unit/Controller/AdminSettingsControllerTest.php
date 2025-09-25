<?php

namespace OCA\NextcloudDifyIntegration\Tests\Unit\Controller;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCA\NextcloudDifyIntegration\Controller\AdminSettingsController;
use OCA\NextcloudDifyIntegration\Service\ConfigService;
use PHPUnit\Framework\TestCase;

class AdminSettingsControllerTest extends TestCase {
    
    private $controller;
    private $request;
    private $configService;
    
    protected function setUp(): void {
        $this->request = $this->createMock(IRequest::class);
        $this->configService = $this->createMock(ConfigService::class);
        $this->controller = new AdminSettingsController(
            'nextcloud_dify_integration',
            $this->request,
            $this->configService
        );
    }
    
    public function testIndex(): void {
        // 设置模拟返回值
        $this->configService->expects($this->once())
            ->method('getDifyUrl')
            ->willReturn('https://dify.example.com');
            
        $this->configService->expects($this->once())
            ->method('getDifyApiKey')
            ->willReturn('api_key_123456');
            
        $this->configService->expects($this->once())
            ->method('getDirectoryMappings')
            ->willReturn([
                [
                    'nextcloud_path' => '/knowledge/documents',
                    'dify_kb_id' => 'kb_123456'
                ]
            ]);
            
        // 调用方法
        $result = $this->controller->index();
        
        // 验证结果
        $this->assertInstanceOf(TemplateResponse::class, $result);
        $this->assertEquals('nextcloud_dify_integration', $result->getAppName());
        $this->assertEquals('admin_settings', $result->getTemplateName());
    }
}
