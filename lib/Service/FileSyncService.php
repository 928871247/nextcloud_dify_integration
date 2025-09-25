<?php

namespace OCA\NextcloudDifyIntegration\Service;

use OCP\Files\Node;
use OCA\NextcloudDifyIntegration\Service\ConfigService;
use OCA\NextcloudDifyIntegration\Service\DifyService;
use Psr\Log\LoggerInterface;

class FileSyncService {
    
    private $configService;
    private $difyService;
    private $logger;
    
    public function __construct(
        ConfigService $configService,
        DifyService $difyService,
        LoggerInterface $logger
    ) {
        $this->configService = $configService;
        $this->difyService = $difyService;
        $this->logger = $logger;
    }
    
    /**
     * 处理文件创建事件
     */
    public function handleFileCreate(Node $node): void {
        try {
            $this->logger->debug('FileSyncService: 开始处理文件创建事件', ['app' => 'nextcloud_dify_integration']);
            
            // 检查是否为文件（而非目录）
            if ($node->getType() !== \OCP\Files\FileInfo::TYPE_FILE) {
                $this->logger->debug('FileSyncService: 节点不是文件，跳过处理', ['app' => 'nextcloud_dify_integration']);
                return;
            }
            
            // 获取文件路径
            $filePath = $node->getPath();
            $fileName = $node->getName();
            $fileSize = $node->getSize();
            $modificationTime = $node->getMTime();
            
            $this->logger->debug('FileSyncService: 文件信息', [
                'filePath' => $filePath,
                'fileName' => $fileName,
                'fileSize' => $fileSize,
                'modificationTime' => $modificationTime,
                'app' => 'nextcloud_dify_integration'
            ]);
            
            // 根据目录映射关系找到对应的 Dify 知识库 ID
            $mapping = $this->configService->getMappingByPath($filePath);
            if ($mapping) {
                $this->logger->debug('FileSyncService: 找到映射关系: ' . json_encode($mapping), ['app' => 'nextcloud_dify_integration']);
                
                // 记录准备上传的文件信息
                $this->logger->info('FileSyncService: 准备异步上传文件到Dify', [
                    'fileName' => $fileName,
                    'filePath' => $filePath,
                    'kbId' => $mapping['dify_kb_id'],
                    'fileSize' => $fileSize,
                    'app' => 'nextcloud_dify_integration'
                ]);
                
                // 异步处理文件上传
                $this->processFileOperationAsync('create', $node);
            } else {
                $this->logger->debug('FileSyncService: 未找到映射关系，跳过创建处理', ['app' => 'nextcloud_dify_integration']);
                $this->logger->debug('FileSyncService: 当前配置的映射关系: ' . json_encode($this->configService->getDirectoryMappings()), ['app' => 'nextcloud_dify_integration']);
            }
        } catch (\Exception $e) {
            $this->logger->error('FileSyncService: 处理文件创建事件时出错: ' . $e->getMessage(), ['app' => 'nextcloud_dify_integration']);
            $this->logger->error('FileSyncService: 错误堆栈: ' . $e->getTraceAsString(), ['app' => 'nextcloud_dify_integration']);
        }
    }
    
    /**
     * 处理文件更新事件
     */
    public function handleFileUpdate(Node $node): void {
        try {
            $this->logger->debug('FileSyncService: 开始处理文件更新事件', ['app' => 'nextcloud_dify_integration']);
            
            // 检查是否为文件（而非目录）
            if ($node->getType() !== \OCP\Files\FileInfo::TYPE_FILE) {
                $this->logger->debug('FileSyncService: 节点不是文件，跳过处理', ['app' => 'nextcloud_dify_integration']);
                return;
            }
            
            // 获取文件路径
            $filePath = $node->getPath();
            $this->logger->debug('FileSyncService: 文件路径: ' . $filePath, ['app' => 'nextcloud_dify_integration']);
            
            // 根据目录映射关系找到对应的 Dify 知识库 ID
            $mapping = $this->configService->getMappingByPath($filePath);
            if ($mapping) {
                $this->logger->debug('FileSyncService: 找到映射关系: ' . json_encode($mapping), ['app' => 'nextcloud_dify_integration']);
                
                // 获取文件名
                $fileName = $node->getName();
                $this->logger->debug('FileSyncService: 文件名: ' . $fileName, ['app' => 'nextcloud_dify_integration']);
                
                // 获取当前文件的修改时间
                $currentModificationTime = $node->getMTime();
                
                // 查找旧文档（通过列出所有文档并匹配文件名）
                $oldDocument = null;
                try {
                    $documentsResponse = $this->difyService->listDocuments($mapping['dify_kb_id']);
                    $this->logger->debug('FileSyncService: 查找文档结果: ' . json_encode($documentsResponse), ['app' => 'nextcloud_dify_integration']);
                    
                    // 查找匹配的文档（通过文件名匹配）
                    if (isset($documentsResponse['data']) && is_array($documentsResponse['data'])) {
                        foreach ($documentsResponse['data'] as $document) {
                            if (isset($document['name']) && $this->isSameFile($document['name'], $filePath, $fileName)) {
                                $oldDocument = $document;
                                break;
                            }
                        }
                    } elseif (isset($documentsResponse['documents']) && is_array($documentsResponse['documents'])) {
                        foreach ($documentsResponse['documents'] as $document) {
                            if (isset($document['name']) && $this->isSameFile($document['name'], $filePath, $fileName)) {
                                $oldDocument = $document;
                                break;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $this->logger->warning('FileSyncService: 查找旧文档时出错: ' . $e->getMessage(), ['app' => 'nextcloud_dify_integration']);
                }
                
                if ($oldDocument) {
                    $this->logger->debug('FileSyncService: 找到旧文档，ID: ' . $oldDocument['id'], ['app' => 'nextcloud_dify_integration']);
                    // 如果找到了旧文档，先删除它
                    try {
                        // 直接通过文档ID删除旧文档
                        $this->difyService->deleteDocumentById($mapping['dify_kb_id'], $oldDocument['id']);
                        $this->logger->debug('FileSyncService: 成功删除旧文档', ['app' => 'nextcloud_dify_integration']);
                    } catch (\Exception $e) {
                        $this->logger->warning('FileSyncService: 删除旧文档时出错: ' . $e->getMessage(), ['app' => 'nextcloud_dify_integration']);
                    }
                } else {
                    $this->logger->debug('FileSyncService: 未找到旧文档', ['app' => 'nextcloud_dify_integration']);
                }
                
                // 然后异步上传新文档（使用新的修改时间）
                $this->processFileOperationAsync('update', $node);
            } else {
                $this->logger->debug('FileSyncService: 未找到映射关系，跳过更新处理', ['app' => 'nextcloud_dify_integration']);
                $this->logger->debug('FileSyncService: 当前配置的映射关系: ' . json_encode($this->configService->getDirectoryMappings()), ['app' => 'nextcloud_dify_integration']);
            }
        } catch (\Exception $e) {
            $this->logger->error('FileSyncService: 处理文件更新事件时出错: ' . $e->getMessage(), ['app' => 'nextcloud_dify_integration']);
            $this->logger->error('FileSyncService: 错误堆栈: ' . $e->getTraceAsString(), ['app' => 'nextcloud_dify_integration']);
        }
    }
    
    /**
     * 检查两个文件名是否为同一文件（不考虑时间戳后缀）
     */
    private function isSameFileWithoutTimestamp(string $documentName, string $fileIdentifierWithoutTimestamp): bool {
        // 对于新的命名格式（文件名+目录+日期时间），我们需要提取文件名部分进行比较
        // 格式示例：📄test.md📁test 📅2025-09-25 09:05:02
        
        // 检查是否是新的命名格式（包含📄表情符号）
        if (strpos($documentName, '📄') === 0) {
            // 提取文件名部分（在第一个📁或📅之前的部分）
            if (preg_match('/^📄(.*?)(?:📁|📅)/', $documentName, $matches)) {
                $fileNameInDocument = $matches[1];
                
                // 从不带时间戳的标识符中提取文件名
                if (preg_match('/^📄(.*?)(?:📁|📅)/', $fileIdentifierWithoutTimestamp, $matches)) {
                    $fileNameInIdentifier = $matches[1];
                    return $fileNameInDocument === $fileNameInIdentifier;
                }
            }
        }
        
        // 对于旧的命名格式，保持原有逻辑
        // 提取文件名的基本部分（去除时间戳后缀）
        $pathInfo = pathinfo($documentName);
        $fileNameWithoutExt = $pathInfo['filename'];
        $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
        
        // 检查是否以时间戳格式结尾 (yyyyMMdd-HHmmss)
        if (preg_match('/^(.*)_([0-9]{8}-[0-9]{6})$/', $fileNameWithoutExt, $matches)) {
            $baseName = $matches[1];
            // 检查基本名称是否匹配
            $pathInfoWithoutTimestamp = pathinfo($fileIdentifierWithoutTimestamp);
            $expectedBaseName = $pathInfoWithoutTimestamp['filename'];
            return $baseName === $expectedBaseName;
        }
        
        // 如果没有时间戳后缀，直接比较文件名
        return $documentName === $fileIdentifierWithoutTimestamp;
    }
    
    /**
     * 检查文档名是否与指定的文件路径和文件名匹配
     */
    private function isSameFile(string $documentName, string $filePath, string $fileName): bool {
        // 生成当前文件的标识符（不带时间戳）
        $currentFileIdentifier = $this->configService->generateDifyFileIdentifier($filePath, $fileName);
        
        // 使用现有的匹配逻辑
        return $this->isSameFileWithoutTimestamp($documentName, $currentFileIdentifier);
    }
    
    /**
     * 处理文件删除事件
     */
    public function handleFileDelete(Node $node): void {
        try {
            $this->logger->debug('FileSyncService: 开始处理文件删除事件', ['app' => 'nextcloud_dify_integration']);
            
            // 检查是否为文件（而非目录）
            if ($node->getType() !== \OCP\Files\FileInfo::TYPE_FILE) {
                $this->logger->debug('FileSyncService: 节点不是文件，跳过处理', ['app' => 'nextcloud_dify_integration']);
                return;
            }
            
            // 获取文件路径
            $filePath = $node->getPath();
            $this->logger->debug('FileSyncService: 文件路径: ' . $filePath, ['app' => 'nextcloud_dify_integration']);
            
            // 根据目录映射关系找到对应的 Dify 知识库 ID
            $mapping = $this->configService->getMappingByPath($filePath);
            if ($mapping) {
                $this->logger->debug('FileSyncService: 找到映射关系: ' . json_encode($mapping), ['app' => 'nextcloud_dify_integration']);
                
                // 获取文件名和修改时间
                $fileName = $node->getName();
                $modificationTime = $node->getMTime();
                $this->logger->debug('FileSyncService: 文件名: ' . $fileName, ['app' => 'nextcloud_dify_integration']);
                
                // 异步删除 Dify 知识库中的文件
                $this->processFileOperationAsync('delete', $node, $mapping['dify_kb_id'], $modificationTime);
            } else {
                $this->logger->debug('FileSyncService: 未找到映射关系，跳过删除处理', ['app' => 'nextcloud_dify_integration']);
                $this->logger->debug('FileSyncService: 当前配置的映射关系: ' . json_encode($this->configService->getDirectoryMappings()), ['app' => 'nextcloud_dify_integration']);
            }
        } catch (\Exception $e) {
            $this->logger->error('FileSyncService: 处理文件删除事件时出错: ' . $e->getMessage(), ['app' => 'nextcloud_dify_integration']);
            $this->logger->error('FileSyncService: 错误堆栈: ' . $e->getTraceAsString(), ['app' => 'nextcloud_dify_integration']);
        }
    }
    
    /**
     * 异步处理文件操作
     */
    private function processFileOperationAsync(string $operation, Node $node, string $kbId = null, int $modificationTime = null): void {
        try {
            // 获取文件信息
            $filePath = $node->getPath();
            $fileName = $node->getName();
            
            // 检查是否启用异步处理
            $asyncProcessing = $this->configService->getAsyncProcessing();
            
            if ($asyncProcessing) {
                // 记录异步处理任务
                $this->logger->info('FileSyncService: 添加异步任务到队列', [
                    'operation' => $operation,
                    'fileName' => $fileName,
                    'filePath' => $filePath,
                    'kbId' => $kbId,
                    'app' => 'nextcloud_dify_integration'
                ]);
                
                // 使用简单的延迟处理来模拟异步处理
                $processingDelay = $this->configService->getProcessingDelay();
                if ($processingDelay > 0) {
                    sleep($processingDelay);
                }
                
                // 执行文件操作
                $this->executeFileOperation($operation, $node, $kbId, $modificationTime);
            } else {
                // 同步处理
                $this->logger->info('FileSyncService: 同步处理文件操作', [
                    'operation' => $operation,
                    'fileName' => $fileName,
                    'filePath' => $filePath,
                    'app' => 'nextcloud_dify_integration'
                ]);
                
                // 直接执行文件操作
                $this->executeFileOperation($operation, $node, $kbId, $modificationTime);
            }
        } catch (\Exception $e) {
            $this->logger->error('FileSyncService: 添加异步任务时出错: ' . $e->getMessage(), ['app' => 'nextcloud_dify_integration']);
        }
    }
    
    /**
     * 执行文件操作
     */
    private function executeFileOperation(string $operation, Node $node, string $kbId = null, int $modificationTime = null): void {
        try {
            switch ($operation) {
                case 'create':
                    $this->difyService->uploadDocumentFromFile($node);
                    break;
                case 'update':
                    // 重新实现更新逻辑
                    $this->handleFileUpdateInternal($node);
                    break;
                case 'delete':
                    if ($kbId) {
                        // 查找并删除文档
                        $filePath = $node->getPath();
                        $fileName = $node->getName();
                        $this->difyService->deleteDocumentByIdentifier($kbId, $filePath, $fileName, $modificationTime);
                    }
                    break;
            }
        } catch (\Exception $e) {
            $this->logger->error('FileSyncService: 执行文件操作时出错: ' . $e->getMessage(), ['app' => 'nextcloud_dify_integration']);
        }
    }
    
    /**
     * 内部文件更新处理逻辑
     */
    private function handleFileUpdateInternal(Node $node): void {
        try {
            // 获取文件路径
            $filePath = $node->getPath();
            
            // 根据目录映射关系找到对应的 Dify 知识库 ID
            $mapping = $this->configService->getMappingByPath($filePath);
            if ($mapping) {
                // 获取文件名
                $fileName = $node->getName();
                
                // 获取当前文件的修改时间
                $currentModificationTime = $node->getMTime();
                
                // 查找旧文档（通过列出所有文档并匹配文件名）
                $oldDocument = null;
                try {
                    $documentsResponse = $this->difyService->listDocuments($mapping['dify_kb_id']);
                    $this->logger->debug('FileSyncService: 查找文档结果: ' . json_encode($documentsResponse), ['app' => 'nextcloud_dify_integration']);
                    
                    // 查找匹配的文档（通过文件名匹配）
                    if (isset($documentsResponse['data']) && is_array($documentsResponse['data'])) {
                        foreach ($documentsResponse['data'] as $document) {
                            if (isset($document['name']) && $this->isSameFile($document['name'], $filePath, $fileName)) {
                                $oldDocument = $document;
                                break;
                            }
                        }
                    } elseif (isset($documentsResponse['documents']) && is_array($documentsResponse['documents'])) {
                        foreach ($documentsResponse['documents'] as $document) {
                            if (isset($document['name']) && $this->isSameFile($document['name'], $filePath, $fileName)) {
                                $oldDocument = $document;
                                break;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $this->logger->warning('FileSyncService: 查找旧文档时出错: ' . $e->getMessage(), ['app' => 'nextcloud_dify_integration']);
                }
                
                if ($oldDocument) {
                    $this->logger->debug('FileSyncService: 找到旧文档，ID: ' . $oldDocument['id'], ['app' => 'nextcloud_dify_integration']);
                    // 如果找到了旧文档，先删除它
                    try {
                        // 直接通过文档ID删除旧文档
                        $this->difyService->deleteDocumentById($mapping['dify_kb_id'], $oldDocument['id']);
                        $this->logger->debug('FileSyncService: 成功删除旧文档', ['app' => 'nextcloud_dify_integration']);
                    } catch (\Exception $e) {
                        $this->logger->warning('FileSyncService: 删除旧文档时出错: ' . $e->getMessage(), ['app' => 'nextcloud_dify_integration']);
                    }
                } else {
                    $this->logger->debug('FileSyncService: 未找到旧文档', ['app' => 'nextcloud_dify_integration']);
                }
                
                // 上传新文档（使用新的修改时间）
                $this->difyService->uploadDocumentFromFile($node);
            }
        } catch (\Exception $e) {
            $this->logger->error('FileSyncService: 内部文件更新处理时出错: ' . $e->getMessage(), ['app' => 'nextcloud_dify_integration']);
        }
    }
    
    /**
     * 从 Dify 获取文档 ID
     */
    private function getDocumentIdFromDify(string $kbId, string $filePath, string $fileName, int $modificationTime = null): ?string {
        try {
            $document = $this->difyService->getDocumentByIdentifier($kbId, $filePath, $fileName, $modificationTime);
            return $document ? $document['id'] : null;
        } catch (\Exception $e) {
            $this->logger->warning('FileSyncService: 从Dify获取文档ID时出错: ' . $e->getMessage(), ['app' => 'nextcloud_dify_integration']);
            return null;
        }
    }
    
    /**
     * 同步目录中的现有文件
     */
    public function syncExistingFiles(string $nextcloudPath, string $kbId): void {
        // 这个方法需要在 Nextcloud 环境中实现
        // 由于当前环境限制，我们只提供概念性实现
        $this->logger->info('FileSyncService: 计划同步目录中的现有文件: ' . $nextcloudPath . ' 到知识库: ' . $kbId, ['app' => 'nextcloud_dify_integration']);
        
        // 实际实现需要:
        // 1. 获取目录中的所有文件
        // 2. 过滤出需要同步的文件
        // 3. 逐个上传到 Dify
        // 4. 记录已处理的文件以避免重复处理
    }
    
    /**
     * 启动时检查所有配置目录中的文件
     */
    public function checkAllConfiguredDirectories(): void {
        $this->logger->info('FileSyncService: 开始检查所有配置目录', ['app' => 'nextcloud_dify_integration']);
        
        try {
            // 获取所有目录映射关系
            $mappings = $this->configService->getDirectoryMappings();
            
            $this->logger->debug('FileSyncService: 配置的目录映射关系数量: ' . count($mappings), ['app' => 'nextcloud_dify_integration']);
            
            foreach ($mappings as $index => $mapping) {
                try {
                    $nextcloudPath = $mapping['nextcloud_path'] ?? '';
                    $kbId = $mapping['dify_kb_id'] ?? '';
                    
                    if (empty($nextcloudPath) || empty($kbId)) {
                        $this->logger->warning('FileSyncService: 跳过无效的目录映射关系 #' . $index, ['app' => 'nextcloud_dify_integration']);
                        continue;
                    }
                    
                    $this->logger->info('FileSyncService: 检查目录映射 #' . $index . ' - Nextcloud路径: ' . $nextcloudPath . ', Dify知识库ID: ' . $kbId, ['app' => 'nextcloud_dify_integration']);
                    
                    // 检查单个目录中的文件
                    $this->checkDirectoryFiles($nextcloudPath, $kbId);
                } catch (\Exception $e) {
                    $this->logger->error('FileSyncService: 处理目录映射 #' . $index . ' 时出错: ' . $e->getMessage(), ['app' => 'nextcloud_dify_integration']);
                    $this->logger->error('FileSyncService: 错误堆栈: ' . $e->getTraceAsString(), ['app' => 'nextcloud_dify_integration']);
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('FileSyncService: 检查所有配置目录时发生错误: ' . $e->getMessage(), ['app' => 'nextcloud_dify_integration']);
            $this->logger->error('FileSyncService: 错误堆栈: ' . $e->getTraceAsString(), ['app' => 'nextcloud_dify_integration']);
        }
        
        $this->logger->info('FileSyncService: 目录检查完成', ['app' => 'nextcloud_dify_integration']);
    }
    
    /**
     * 检查单个目录中的文件
     */
    public function checkDirectoryFiles(string $nextcloudPath, string $kbId): void {
        $this->logger->info('FileSyncService: 检查目录文件 - 路径: ' . $nextcloudPath . ', 知识库ID: ' . $kbId, ['app' => 'nextcloud_dify_integration']);
        
        try {
            // 获取根文件夹
            $rootFolder = \OC::$server->get(\OCP\Files\IRootFolder::class);
            
            // 获取当前用户
            $userSession = \OC::$server->get(\OCP\IUserSession::class);
            $user = $userSession->getUser();
            
            if (!$user) {
                $this->logger->warning('FileSyncService: 无法获取当前用户', ['app' => 'nextcloud_dify_integration']);
                return;
            }
            
            // 获取用户文件夹
            $userFolder = $rootFolder->getUserFolder($user->getUID());
            
            // 获取指定路径的节点
            // 确保路径格式正确
            $normalizedPath = '/' . trim($nextcloudPath, '/');
            
            $this->logger->debug('FileSyncService: 规范化路径: ' . $normalizedPath . ', 用户ID: ' . $user->getUID(), ['app' => 'nextcloud_dify_integration']);
            
            // 检查节点是否存在
            if (!$userFolder->nodeExists($normalizedPath)) {
                $this->logger->warning('FileSyncService: 目录不存在 - 路径: ' . $normalizedPath, ['app' => 'nextcloud_dify_integration']);
                return;
            }
            
            $node = $userFolder->get($normalizedPath);
            
            // 检查是否为目录
            if ($node instanceof \OCP\Files\Folder) {
                // 递归获取目录中的所有文件
                $nodes = $this->getAllFilesFromFolder($node);
                
                $this->logger->info('FileSyncService: 在目录中发现 ' . count($nodes) . ' 个文件', ['app' => 'nextcloud_dify_integration']);
                
                foreach ($nodes as $index => $childNode) {
                    try {
                        if ($childNode instanceof \OCP\Files\File) {
                            // 获取文件信息
                            $fileName = $childNode->getName();
                            $filePath = $childNode->getPath();
                            $modificationTime = $childNode->getMTime();
                            
                            $this->logger->debug('FileSyncService: 发现文件 #' . $index . ' - ' . $fileName . ' (' . $filePath . ')', ['app' => 'nextcloud_dify_integration']);
                            
                            // 检查文件是否需要同步
                            // 这里我们简化逻辑，直接同步所有文件
                            $this->logger->info('FileSyncService: 同步文件 #' . $index . ' - ' . $fileName . ' 到知识库 ' . $kbId, ['app' => 'nextcloud_dify_integration']);
                            $this->handleFileCreate($childNode);
                        }
                    } catch (\Exception $e) {
                        $this->logger->error('FileSyncService: 处理文件 #' . $index . ' 时出错: ' . $e->getMessage(), ['app' => 'nextcloud_dify_integration']);
                        $this->logger->error('FileSyncService: 错误堆栈: ' . $e->getTraceAsString(), ['app' => 'nextcloud_dify_integration']);
                    }
                }
            } else {
                $this->logger->warning('FileSyncService: 路径不是目录 - 路径: ' . $normalizedPath, ['app' => 'nextcloud_dify_integration']);
            }
        } catch (\Exception $e) {
            $this->logger->error('FileSyncService: 检查目录文件时发生错误 - ' . $e->getMessage(), ['app' => 'nextcloud_dify_integration']);
            $this->logger->error('FileSyncService: 错误堆栈: ' . $e->getTraceAsString(), ['app' => 'nextcloud_dify_integration']);
        }
    }
    
    /**
     * 递归获取文件夹中的所有文件
     */
    private function getAllFilesFromFolder(\OCP\Files\Folder $folder): array {
        $files = [];
        
        try {
            $nodes = $folder->getDirectoryListing();
            
            foreach ($nodes as $node) {
                if ($node instanceof \OCP\Files\File) {
                    $files[] = $node;
                } elseif ($node instanceof \OCP\Files\Folder) {
                    // 递归获取子文件夹中的文件
                    $files = array_merge($files, $this->getAllFilesFromFolder($node));
                }
            }
        } catch (\Exception $e) {
            $this->logger->warning('FileSyncService: 获取文件夹内容时出错 - ' . $e->getMessage(), ['app' => 'nextcloud_dify_integration']);
        }
        
        return $files;
    }
}
