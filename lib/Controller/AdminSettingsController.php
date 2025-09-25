<?php

namespace OCA\NextcloudDifyIntegration\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCA\NextcloudDifyIntegration\Service\ConfigService;

class AdminSettingsController extends Controller {
    
    private $configService;
    private $urlGenerator;
    
    public function __construct(
        string $appName,
        IRequest $request,
        ConfigService $configService,
        IURLGenerator $urlGenerator
    ) {
        parent::__construct($appName, $request);
        $this->configService = $configService;
        $this->urlGenerator = $urlGenerator;
    }
    
    /**
     * 显示设置表单（GET）
     */
    public function index(): TemplateResponse {
        $params = [
            'difyUrl' => $this->configService->getDifyUrl(),
            'difyApiKey' => $this->configService->getDifyApiKey(),
            'directoryMappings' => $this->configService->getDirectoryMappings()
        ];
        
        return new TemplateResponse('nextcloud_dify_integration', 'admin_settings', $params);
    }
    
    /**
     * 保存设置（POST）
     */
    public function save(): RedirectResponse {
        $difyUrl = $this->request->getParam('dify_url', '');
        $difyApiKey = $this->request->getParam('dify_api_key', '');
        $directoryMappingsJson = $this->request->getParam('directory_mappings', '[]');
        $directoryMappings = json_decode($directoryMappingsJson, true) ?? [];
        
        $this->configService->setDifyUrl($difyUrl);
        $this->configService->setDifyApiKey($difyApiKey);
        $this->configService->setDirectoryMappings($directoryMappings);
        
        // 重定向回设置页面，并添加成功消息（可选）
        return new RedirectResponse(
            $this->urlGenerator->linkToRoute('nextcloud_dify_integration.admin_settings.index')
        );
    }
}