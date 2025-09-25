<?php

namespace OCA\NextcloudDifyIntegration\Tests\Integration;

use OCP\AppFramework\App;
use OCP\IConfig;
use OCA\NextcloudDifyIntegration\Service\ConfigService;
use PHPUnit\Framework\TestCase;

class ConfigIntegrationTest extends TestCase {
    
    private $configService;
    private $config;
    
    protected function setUp(): void {
        $app = new App('nextcloud_dify_integration');
        $container = $app->getContainer();
        $this->configService = $container->get(ConfigService::class);
        $this->config = $container->get(IConfig::class);
    }
    
    public function testSetAndGetDifyUrl(): void {
        $url = 'https://dify.example.com';
        $this->configService->setDifyUrl($url);
        $this->assertEquals($url, $this->configService->getDifyUrl());
    }
    
    public function testSetAndGetDifyApiKey(): void {
        $apiKey = 'api_key_123456';
        $this->configService->setDifyApiKey($apiKey);
        $this->assertEquals($apiKey, $this->configService->getDifyApiKey());
    }
    
    public function testSetAndGetDirectoryMappings(): void {
        $mappings = [
            [
                'nextcloud_path' => '/knowledge/documents',
                'dify_kb_id' => 'kb_123456'
            ]
        ];
        $this->configService->setDirectoryMappings($mappings);
        $this->assertEquals($mappings, $this->configService->getDirectoryMappings());
    }
}
