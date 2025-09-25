<?php

namespace OCA\NextcloudDifyIntegration\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCA\NextcloudDifyIntegration\Service\DifyService;
use OCA\NextcloudDifyIntegration\Service\ConfigService;

class FileSyncController extends Controller {
    
    private $difyService;
    private $configService;
    
    public function __construct(
        $AppName,
        IRequest $request,
        DifyService $difyService,
        ConfigService $configService
    ) {
        parent::__construct($AppName, $request);
        $this->difyService = $difyService;
        $this->configService = $configService;
    }
    
    /**
     * 处理文件变化事件
     */
    public function handleFileChange(): JSONResponse {
        // 获取文件信息
        $filePath = $this->request->getParam('file_path');
        $eventType = $this->request->getParam('event_type'); // create, update, delete
        
        // 根据事件类型执行相应操作
        switch ($eventType) {
            case 'create':
                $this->handleFileCreate($filePath);
                break;
                
            case 'update':
                $this->handleFileUpdate($filePath);
                break;
                
            case 'delete':
                $this->handleFileDelete($filePath);
                break;
                
            default:
                return new JSONResponse(['status' => 'error', 'message' => '未知事件类型']);
        }
        
        return new JSONResponse(['status' => 'success']);
    }
    
    /**
     * 处理文件创建事件
     */
    private function handleFileCreate(string $filePath) {
        // 获取文件内容
        $fileContent = file_get_contents($filePath);
        
        // 获取文件名
        $fileName = basename($filePath);
        
        // 根据目录映射关系找到对应的 Dify 知识库 ID
        $mapping = $this->configService->getMappingByPath($filePath);
        if ($mapping) {
            // 上传文件到 Dify 知识库
            $this->difyService->uploadDocument(
                $mapping['dify_kb_id'],
                $fileName,
                $fileContent
            );
        }
    }
    
    /**
     * 处理文件更新事件
     */
    private function handleFileUpdate(string $filePath) {
        // 获取文件名
        $fileName = basename($filePath);
        
        // 根据目录映射关系找到对应的 Dify 知识库 ID
        $mapping = $this->configService->getMappingByPath($filePath);
        if ($mapping) {
            // 先删除 Dify 知识库中的文件
            $this->difyService->deleteDocument(
                $mapping['dify_kb_id'],
                $fileName
            );
            
            // 重新上传新版本文件
            $fileContent = file_get_contents($filePath);
            $this->difyService->uploadDocument(
                $mapping['dify_kb_id'],
                $fileName,
                $fileContent
            );
        }
    }
    
    /**
     * 处理文件删除事件
     */
    private function handleFileDelete(string $filePath) {
        // 获取文件名
        $fileName = basename($filePath);
        
        // 根据目录映射关系找到对应的 Dify 知识库 ID
        $mapping = $this->configService->getMappingByPath($filePath);
        if ($mapping) {
            // 删除 Dify 知识库中的文件
            $this->difyService->deleteDocument(
                $mapping['dify_kb_id'],
                $fileName
            );
        }
    }
}
