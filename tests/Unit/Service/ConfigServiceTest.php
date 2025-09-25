<?php

namespace OCA\NextcloudDifyIntegration\Tests\Unit\Service;

use OCP\IConfig;
use OCA\NextcloudDifyIntegration\Service\ConfigService;
use PHPUnit\Framework\TestCase;

class ConfigServiceTest extends TestCase {
    
    private $configService;
    private $config;
    
    protected function setUp(): void {
        $this->config = $this->createMock(IConfig::class);
        $this->configService = new ConfigService($this->config);
    }
    
    public function testGetDifyUrl(): void {
        $expectedUrl = 'https://dify.example.com';
        $this->config->expects($this->once())
            ->method('getAppValue')
            ->with('nextcloud_dify_integration', 'dify_url', '')
            ->willReturn($expectedUrl);
            
        $this->assertEquals($expectedUrl, $this->configService->getDifyUrl());
    }
    
    public function testSetDifyUrl(): void {
        $url = 'https://dify.example.com';
        $this->config->expects($this->once())
            ->method('setAppValue')
            ->with('nextcloud_dify_integration', 'dify_url', $url);
            
        $this->configService->setDifyUrl($url);
    }
    
    public function testGetDifyApiKey(): void {
        $expectedKey = 'api_key_123456';
        $this->config->expects($this->once())
            ->method('getAppValue')
            ->with('nextcloud_dify_integration', 'dify_api_key', '')
            ->willReturn($expectedKey);
            
        $this->assertEquals($expectedKey, $this->configService->getDifyApiKey());
    }
    
    public function testSetDifyApiKey(): void {
        $apiKey = 'api_key_123456';
        $this->config->expects($this->once())
            ->method('setAppValue')
            ->with('nextcloud_dify_integration', 'dify_api_key', $apiKey);
            
        $this->configService->setDifyApiKey($apiKey);
    }
    
    public function testGetDirectoryMappings(): void {
        $expectedMappings = [
            [
                'nextcloud_path' => '/knowledge/documents',
                'dify_kb_id' => 'kb_123456'
            ]
        ];
        $this->config->expects($this->once())
            ->method('getAppValue')
            ->with('nextcloud_dify_integration', 'directory_mappings', '[]')
            ->willReturn(json_encode($expectedMappings));
            
        $this->assertEquals($expectedMappings, $this->configService->getDirectoryMappings());
    }
    
    public function testSetDirectoryMappings(): void {
        $mappings = [
            [
                'nextcloud_path' => '/knowledge/documents',
                'dify_kb_id' => 'kb_123456'
            ]
        ];
        $this->config->expects($this->once())
            ->method('setAppValue')
            ->with('nextcloud_dify_integration', 'directory_mappings', json_encode($mappings));
            
        $this->configService->setDirectoryMappings($mappings);
    }
}
