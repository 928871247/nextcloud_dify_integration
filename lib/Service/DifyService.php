<?php

namespace OCA\NextcloudDifyIntegration\Service;

use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\Files\Node;
use Psr\Log\LoggerInterface;

class DifyService {
    
    private $client;
    private $configService;
    private $logger;
    
    public function __construct(
        IClientService $clientService,
        ConfigService $configService,
        LoggerInterface $logger
    ) {
        $this->client = $clientService->newClient([
            'allow_local_address' => true
        ]);
        $this->configService = $configService;
        $this->logger = $logger;
    }
    
    /**
     * 上传文档到 Dify 知识库（通过文件内容）
     */
    public function uploadDocument(string $kbId, string $fileName, string $fileContent): void {
        $this->uploadDocumentWithRetry($kbId, $fileName, $fileContent);
    }
    
    /**
     * 上传文档到 Dify 知识库（带重试机制）
     */
    public function uploadDocumentWithRetry(string $kbId, string $fileName, string $fileContent, int $maxRetries = 3): bool {
        $retryCount = 0;
        
        // 确保文件名符合Dify API要求
        $fileName = $this->sanitizeFileName($fileName);
        
        while ($retryCount < $maxRetries) {
            try {
                $difyUrl = $this->configService->getDifyUrl();
                $apiKey = $this->configService->getDifyApiKey();
                
                if (empty($difyUrl) || empty($apiKey)) {
                    throw new \Exception('Dify URL 或 API Key 未配置');
                }
                
                // 为通过内容上传的文件，我们保持原始文件名不变
                $fileIdentifier = $fileName;
                
                // 构造请求 URL (使用正确的 Dify API 端点)
                $baseUrl = rtrim($difyUrl, '/');
                // 确保 URL 以 /v1 结尾
                if (substr($baseUrl, -3) !== '/v1') {
                    $baseUrl .= '/v1';
                }
                // 构造上传文档的端点
                $url = $baseUrl . '/datasets/' . $kbId . '/document/create-by-file';
                
                // 创建临时文件
                $tempFile = tempnam(sys_get_temp_dir(), 'dify_upload_');
                file_put_contents($tempFile, $fileContent);
                
                // 构造请求头
                $headers = [
                    'Authorization' => 'Bearer ' . $apiKey
                ];
                
                // 构造 multipart 请求体
                $multipart = [
                    [
                        'name' => 'file',
                        'contents' => fopen($tempFile, 'r'),
                        'filename' => $fileIdentifier
                    ],
                    [
                        'name' => 'data',
                        'contents' => json_encode([
                            'indexing_technique' => 'high_quality',
                            'process_rule' => [
                                'mode' => 'automatic'
                            ]
                        ])
                    ]
                ];
                
                try {
                    // 发送请求
                    $this->client->post($url, [
                        'headers' => $headers,
                        'multipart' => $multipart,
                        'nextcloud' => [
                            'allow_local_address' => true
                        ]
                    ]);
                    
                    // 清理临时文件
                    if ($tempFile && file_exists($tempFile)) {
                        unlink($tempFile);
                    }
                    return true;
                } finally {
                    // 清理临时文件
                    if ($tempFile && file_exists($tempFile)) {
                        unlink($tempFile);
                    }
                }
            } catch (\Exception $e) {
                $retryCount++;
                $this->logger->warning('DifyService: 上传文档失败，重试 ' . $retryCount . '/' . $maxRetries . ': ' . $e->getMessage(), ['app' => 'nextcloud_dify_integration']);
                
                if ($retryCount >= $maxRetries) {
                    // 记录到失败队列
                    $this->logFailedOperation('upload', $kbId, $fileName, $e->getMessage());
                    return false;
                }
                
                // 指数退避等待
                sleep(pow(2, $retryCount));
            }
        }
        
        return false;
    }
    
    /**
     * 上传文档到 Dify 知识库（通过文件内容和路径）
     */
    public function uploadDocumentWithPath(string $kbId, string $filePath, string $fileName, string $fileContent, int $modificationTime = null): void {
        $difyUrl = $this->configService->getDifyUrl();
        $apiKey = $this->configService->getDifyApiKey();
        
        if (empty($difyUrl) || empty($apiKey)) {
            throw new \Exception('Dify URL 或 API Key 未配置');
        }
        
        // 生成文件在Dify中的标识符（使用改进的命名方式）
        $fileIdentifier = $this->configService->generateImprovedDifyFileIdentifier($filePath, $fileName, $modificationTime);
        
        // 确保文件标识符符合Dify API要求
        $fileIdentifier = $this->sanitizeFileName($fileIdentifier);
        
        // 记录调试信息
        $this->logger->debug('DifyService: 准备上传文档', [
            'kbId' => $kbId,
            'filePath' => $filePath,
            'fileName' => $fileName,
            'fileIdentifier' => $fileIdentifier,
            'modificationTime' => $modificationTime,
            'fileSize' => strlen($fileContent),
            'app' => 'nextcloud_dify_integration'
        ]);
        
        // 构造请求 URL (使用正确的 Dify API 端点)
        $baseUrl = rtrim($difyUrl, '/');
        // 确保 URL 以 /v1 结尾
        if (substr($baseUrl, -3) !== '/v1') {
            $baseUrl .= '/v1';
        }
        // 构造上传文档的端点
        $url = $baseUrl . '/datasets/' . $kbId . '/document/create-by-file';
        
        // 创建临时文件
        $tempFile = tempnam(sys_get_temp_dir(), 'dify_upload_');
        file_put_contents($tempFile, $fileContent);
        
        // 构造请求头
        $headers = [
            'Authorization' => 'Bearer ' . $apiKey
        ];
        
        // 构造 multipart 请求体
        $multipart = [
            [
                'name' => 'file',
                'contents' => fopen($tempFile, 'r'),
                'filename' => $fileIdentifier
            ],
            [
                'name' => 'data',
                'contents' => json_encode([
                    'indexing_technique' => 'high_quality',
                    'process_rule' => [
                        'mode' => 'automatic'
                    ]
                ])
            ]
        ];
        
        // 记录请求参数用于调试
        $this->logger->info('DifyService: 发送POST请求到Dify API', [
            'url' => $url,
            'headers' => $headers,
            'fileIdentifier' => $fileIdentifier,
            'fileSize' => strlen($fileContent),
            'tempFile' => $tempFile,
            'app' => 'nextcloud_dify_integration'
        ]);
        
        try {
            // 发送请求
            $response = $this->client->post($url, [
                'headers' => $headers,
                'multipart' => $multipart,
                'nextcloud' => [
                    'allow_local_address' => true
                ]
            ]);
            
            $this->logger->info('DifyService: 文档上传成功', [
                'statusCode' => $response->getStatusCode(),
                'fileIdentifier' => $fileIdentifier,
                'app' => 'nextcloud_dify_integration'
            ]);
        } catch (\Exception $e) {
            $this->logger->error('DifyService: 文档上传失败', [
                'errorMessage' => $e->getMessage(),
                'url' => $url,
                'fileIdentifier' => $fileIdentifier,
                'fileSize' => strlen($fileContent),
                'app' => 'nextcloud_dify_integration'
            ]);
            throw $e;
        } finally {
            // 清理临时文件
            if ($tempFile && file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
    
    /**
     * 上传文档到 Dify 知识库（通过 Nextcloud 文件对象）
     */
    public function uploadDocumentFromFile(Node $fileNode): bool {
        $maxRetries = 3;
        $retryCount = 0;
        
        while ($retryCount < $maxRetries) {
            $tempFile = null;
            $fileHandle = null;
            
            try {
                $difyUrl = $this->configService->getDifyUrl();
                $apiKey = $this->configService->getDifyApiKey();
                
                if (empty($difyUrl) || empty($apiKey)) {
                    throw new \Exception('Dify URL 或 API Key 未配置');
                }
                
                // 获取文件路径
                $filePath = $fileNode->getPath();
                
                // 根据目录映射关系找到对应的 Dify 知识库 ID
                $mapping = $this->configService->getMappingByPath($filePath);
                if (!$mapping) {
                    $this->logger->debug('DifyService: 未找到映射关系，跳过文件上传: ' . $filePath, ['app' => 'nextcloud_dify_integration']);
                    return false;
                }
                
                $kbId = $mapping['dify_kb_id'];
                $fileName = $fileNode->getName();
                $modificationTime = $fileNode->getMTime(); // 获取文件修改时间
                
                // 生成文件在Dify中的标识符（使用配置的命名模式）
                $fileIdentifier = $this->configService->generateDifyFileIdentifier($filePath, $fileName, $modificationTime);
                
                // 确保文件标识符符合Dify API要求
                $fileIdentifier = $this->sanitizeFileName($fileIdentifier);
                
                // 确保文件标识符符合Dify API要求
                $fileIdentifier = $this->sanitizeFileName($fileIdentifier);
                
                // 构造请求 URL (使用正确的 Dify API 端点)
                $baseUrl = rtrim($difyUrl, '/');
                // 确保 URL 以 /v1 结尾
                if (substr($baseUrl, -3) !== '/v1') {
                    $baseUrl .= '/v1';
                }
                // 构造上传文档的端点
                $url = $baseUrl . '/datasets/' . $kbId . '/document/create-by-file';
                
                // 构造请求头
                $headers = [
                    'Authorization' => 'Bearer ' . $apiKey
                ];
                
                // 获取文件的本地路径（如果可用）
                $localPath = null;
                try {
                    $localPath = $fileNode->getStorage()->getLocalFile($fileNode->getInternalPath());
                } catch (\Exception $e) {
                    $this->logger->warning('DifyService: 无法获取文件本地路径: ' . $e->getMessage(), ['app' => 'nextcloud_dify_integration']);
                }
                
                if ($localPath && file_exists($localPath)) {
                    // 如果可以直接访问本地文件，使用文件路径
                    $fileHandle = fopen($localPath, 'r');
                    if (!$fileHandle) {
                        throw new \Exception('无法打开本地文件: ' . $localPath);
                    }
                    
                    $multipart = [
                        [
                            'name' => 'file',
                            'contents' => $fileHandle,
                            'filename' => $fileIdentifier
                        ],
                        [
                            'name' => 'data',
                            'contents' => json_encode([
                                'indexing_technique' => 'high_quality',
                                'process_rule' => [
                                    'mode' => 'automatic'
                                ]
                            ])
                        ]
                    ];
                } else {
                    // 如果无法直接访问本地文件，使用文件内容
                    $fileContent = $fileNode->getContent();
                    $tempFile = tempnam(sys_get_temp_dir(), 'dify_upload_');
                    if (!$tempFile) {
                        throw new \Exception('无法创建临时文件');
                    }
                    
                    if (file_put_contents($tempFile, $fileContent) === false) {
                        throw new \Exception('无法写入临时文件');
                    }
                    
                    $fileHandle = fopen($tempFile, 'r');
                    if (!$fileHandle) {
                        throw new \Exception('无法打开临时文件: ' . $tempFile);
                    }
                    
                    $multipart = [
                        [
                            'name' => 'file',
                            'contents' => $fileHandle,
                            'filename' => $fileIdentifier
                        ],
                        [
                            'name' => 'data',
                            'contents' => json_encode([
                                'indexing_technique' => 'high_quality',
                                'process_rule' => [
                                    'mode' => 'automatic'
                                ]
                            ])
                        ]
                    ];
                }
                
                // 发送请求
                $this->logger->debug('DifyService: 准备发送请求到Dify API: ' . $url, ['app' => 'nextcloud_dify_integration']);
                $this->logger->debug('DifyService: 请求头: ' . json_encode($headers), ['app' => 'nextcloud_dify_integration']);
                $this->logger->debug('DifyService: 文件标识符: ' . $fileIdentifier, ['app' => 'nextcloud_dify_integration']);
                
                // 记录更详细的请求信息
                $this->logger->info('DifyService: 发送POST请求到Dify API (uploadDocumentFromFile)', [
                    'url' => $url,
                    'kbId' => $kbId,
                    'fileName' => $fileName,
                    'fileIdentifier' => $fileIdentifier,
                    'filePath' => $filePath,
                    'modificationTime' => $modificationTime,
                    'fileSize' => isset($fileContent) ? strlen($fileContent) : 'unknown',
                    'useLocalPath' => !empty($localPath) && file_exists($localPath),
                    'localPath' => $localPath ?? 'none',
                    'app' => 'nextcloud_dify_integration'
                ]);
                
                $response = $this->client->post($url, [
                    'headers' => $headers,
                    'multipart' => $multipart,
                    'nextcloud' => [
                        'allow_local_address' => true
                    ]
                ]);
                
                $this->logger->info('DifyService: 成功上传文件到Dify知识库: ' . $fileIdentifier . ' (状态码: ' . $response->getStatusCode() . ')', ['app' => 'nextcloud_dify_integration']);
                
                // 清理资源
                if ($fileHandle && is_resource($fileHandle)) {
                    fclose($fileHandle);
                }
                if ($tempFile && file_exists($tempFile)) {
                    unlink($tempFile);
                }
                
                return true;
            } catch (\Exception $e) {
                // 清理资源
                if ($fileHandle && is_resource($fileHandle)) {
                    try {
                        fclose($fileHandle);
                    } catch (\Exception $closeException) {
                        $this->logger->warning('DifyService: 关闭文件句柄时出错: ' . $closeException->getMessage(), ['app' => 'nextcloud_dify_integration']);
                    }
                }
                if ($tempFile && file_exists($tempFile)) {
                    try {
                        unlink($tempFile);
                    } catch (\Exception $unlinkException) {
                        $this->logger->warning('DifyService: 删除临时文件时出错: ' . $unlinkException->getMessage(), ['app' => 'nextcloud_dify_integration']);
                    }
                }
                
                $retryCount++;
                $this->logger->warning('DifyService: 上传文档失败，重试 ' . $retryCount . '/' . $maxRetries . ': ' . $e->getMessage(), ['app' => 'nextcloud_dify_integration']);
                $this->logger->warning('DifyService: 请求URL: ' . ($url ?? '未知'), ['app' => 'nextcloud_dify_integration']);
                $this->logger->warning('DifyService: 文件标识符: ' . ($fileIdentifier ?? '未知'), ['app' => 'nextcloud_dify_integration']);
                $this->logger->warning('DifyService: 知识库ID: ' . ($kbId ?? '未知'), ['app' => 'nextcloud_dify_integration']);
                
                if ($retryCount >= $maxRetries) {
                    // 记录到失败队列
                    $this->logFailedOperation('upload', $mapping['dify_kb_id'] ?? 'unknown', $fileNode->getName(), $e->getMessage());
                    $this->logger->error('DifyService: 上传文档最终失败: ' . $e->getMessage() . ' 文件路径: ' . $filePath, ['app' => 'nextcloud_dify_integration']);
                    $this->logger->error('DifyService: 错误详情: ' . $e->getTraceAsString(), ['app' => 'nextcloud_dify_integration']);
                    return false;
                }
                
                // 指数退避等待
                sleep(pow(2, $retryCount));
            }
        }
        
        return false;
    }
    
    /**
     * 从 Dify 知识库删除文档
     */
    public function deleteDocument(string $kbId, string $fileName): void {
        $difyUrl = $this->configService->getDifyUrl();
        $apiKey = $this->configService->getDifyApiKey();
        
        if (empty($difyUrl) || empty($apiKey)) {
            throw new \Exception('Dify URL 或 API Key 未配置');
        }
        
        // 首先获取文档 ID
        $documentId = $this->getDocumentIdByName($kbId, $fileName);
        
        if (!$documentId) {
            $this->logger->warning('DifyService: 未找到文档 ID，无法删除文件: ' . $fileName, ['app' => 'nextcloud_dify_integration']);
            throw new \Exception('未找到文档 ID，无法删除文件: ' . $fileName);
        }
        
        // 构造请求 URL (使用正确的 Dify API 端点)
        $baseUrl = rtrim($difyUrl, '/');
        // 确保 URL 以 /v1 结尾
        if (substr($baseUrl, -3) !== '/v1') {
            $baseUrl .= '/v1';
        }
        // 构造删除文档的端点
        $url = $baseUrl . '/datasets/' . $kbId . '/documents/' . $documentId;
        
        // 记录调试信息
        $this->logger->debug('DifyService: 准备删除文档，URL: ' . $url . ', 文档ID: ' . $documentId . ', 文件名: ' . $fileName, ['app' => 'nextcloud_dify_integration']);
        
        // 构造请求头
        $headers = [
            'Authorization' => 'Bearer ' . $apiKey
        ];
        
        // 发送删除请求
        $response = $this->client->delete($url, [
            'headers' => $headers,
            'nextcloud' => [
                'allow_local_address' => true
            ]
        ]);
        
        // 记录响应信息
        $this->logger->debug('DifyService: 删除文档响应状态码: ' . $response->getStatusCode(), ['app' => 'nextcloud_dify_integration']);
    }
    
    /**
     * 从 Dify 知识库删除文档（通过文档ID）
     */
    public function deleteDocumentById(string $kbId, string $documentId): void {
        $difyUrl = $this->configService->getDifyUrl();
        $apiKey = $this->configService->getDifyApiKey();
        
        if (empty($difyUrl) || empty($apiKey)) {
            throw new \Exception('Dify URL 或 API Key 未配置');
        }
        
        // 构造请求 URL (使用正确的 Dify API 端点)
        $baseUrl = rtrim($difyUrl, '/');
        // 确保 URL 以 /v1 结尾
        if (substr($baseUrl, -3) !== '/v1') {
            $baseUrl .= '/v1';
        }
        // 构造删除文档的端点
        $url = $baseUrl . '/datasets/' . $kbId . '/documents/' . $documentId;
        
        // 记录调试信息
        $this->logger->debug('DifyService: 准备删除文档，URL: ' . $url . ', 文档ID: ' . $documentId, ['app' => 'nextcloud_dify_integration']);
        
        // 构造请求头
        $headers = [
            'Authorization' => 'Bearer ' . $apiKey
        ];
        
        // 发送删除请求
        $response = $this->client->delete($url, [
            'headers' => $headers,
            'nextcloud' => [
                'allow_local_address' => true
            ]
        ]);
        
        // 记录响应信息
        $this->logger->debug('DifyService: 删除文档响应状态码: ' . $response->getStatusCode(), ['app' => 'nextcloud_dify_integration']);
    }
    
    /**
     * 从 Dify 知识库删除文档（根据文件标识符）
     */
    public function deleteDocumentByIdentifier(string $kbId, string $filePath, string $fileName, int $modificationTime = null): void {
        $difyUrl = $this->configService->getDifyUrl();
        $apiKey = $this->configService->getDifyApiKey();
        
        if (empty($difyUrl) || empty($apiKey)) {
            throw new \Exception('Dify URL 或 API Key 未配置');
        }
        
        // 生成文件在Dify中的标识符
        $fileIdentifier = $this->configService->generateDifyFileIdentifier($filePath, $fileName, $modificationTime);
        
        // 首先获取文档 ID
        $documentId = $this->getDocumentIdByIdentifier($kbId, $filePath, $fileName, $modificationTime);
        
        if (!$documentId) {
            $this->logger->warning('DifyService: 未找到文档 ID，无法删除文件: ' . $fileIdentifier, ['app' => 'nextcloud_dify_integration']);
            throw new \Exception('未找到文档 ID，无法删除文件: ' . $fileIdentifier);
        }
        
        // 构造请求 URL (使用正确的 Dify API 端点)
        $baseUrl = rtrim($difyUrl, '/');
        // 确保 URL 以 /v1 结尾
        if (substr($baseUrl, -3) !== '/v1') {
            $baseUrl .= '/v1';
        }
        // 构造删除文档的端点
        $url = $baseUrl . '/datasets/' . $kbId . '/documents/' . $documentId;
        
        // 记录调试信息
        $this->logger->debug('DifyService: 准备删除文档，URL: ' . $url . ', 文档ID: ' . $documentId . ', 文件标识符: ' . $fileIdentifier, ['app' => 'nextcloud_dify_integration']);
        
        // 构造请求头
        $headers = [
            'Authorization' => 'Bearer ' . $apiKey
        ];
        
        // 发送删除请求
        $response = $this->client->delete($url, [
            'headers' => $headers,
            'nextcloud' => [
                'allow_local_address' => true
            ]
        ]);
        
        // 记录响应信息
        $this->logger->debug('DifyService: 删除文档响应状态码: ' . $response->getStatusCode(), ['app' => 'nextcloud_dify_integration']);
    }
    
    /**
     * 更新 Dify 知识库中的文档
     */
    public function updateDocument(string $kbId, string $documentId, string $fileName, string $fileContent): void {
        $difyUrl = $this->configService->getDifyUrl();
        $apiKey = $this->configService->getDifyApiKey();
        
        if (empty($difyUrl) || empty($apiKey)) {
            throw new \Exception('Dify URL 或 API Key 未配置');
        }
        
        // 为更新操作，我们保持原始文件名不变
        $fileIdentifier = $fileName;
        
        // 确保文件标识符符合Dify API要求
        $fileIdentifier = $this->sanitizeFileName($fileIdentifier);
        
        // 构造请求 URL (使用正确的 Dify API 端点)
        $baseUrl = rtrim($difyUrl, '/');
        // 确保 URL 以 /v1 结尾
        if (substr($baseUrl, -3) !== '/v1') {
            $baseUrl .= '/v1';
        }
        // 构造更新文档的端点
        $url = $baseUrl . '/datasets/' . $kbId . '/document/create-by-file';
        
        // 创建临时文件
        $tempFile = tempnam(sys_get_temp_dir(), 'dify_update_');
        file_put_contents($tempFile, $fileContent);
        
        // 构造请求头
        $headers = [
            'Authorization' => 'Bearer ' . $apiKey
        ];
        
        // 构造 multipart 请求体
        $multipart = [
            [
                'name' => 'file',
                'contents' => fopen($tempFile, 'r'),
                'filename' => $fileIdentifier
            ],
            [
                'name' => 'data',
                'contents' => json_encode([
                    'original_document_id' => $documentId,
                    'indexing_technique' => 'high_quality',
                    'process_rule' => [
                        'mode' => 'automatic'
                    ]
                ])
            ]
        ];
        
        try {
            // 发送更新请求
            $this->client->post($url, [
                'headers' => $headers,
                'multipart' => $multipart,
                'nextcloud' => [
                    'allow_local_address' => true
                ]
            ]);
        } finally {
            // 清理临时文件
            if ($tempFile && file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
    
    /**
     * 更新 Dify 知识库中的文档（根据文件路径）
     */
    public function updateDocumentByPath(string $kbId, string $documentId, string $filePath, string $fileName, string $fileContent, int $modificationTime = null): void {
        // 对于文件更新，我们采用先删除后上传的方式，而不是使用update API
        // 因为Dify的update API可能不会正确处理文件名变更
        
        // 先删除旧文档
        $this->deleteDocumentByIdentifier($kbId, $filePath, $fileName, null);
        
        // 然后上传新文档
        // 生成文件在Dify中的标识符（使用改进的命名方式）
        $fileIdentifier = $this->configService->generateImprovedDifyFileIdentifier($filePath, $fileName, $modificationTime);
        
        // 确保文件标识符符合Dify API要求
        $fileIdentifier = $this->sanitizeFileName($fileIdentifier);
        
        // 构造请求 URL (使用正确的 Dify API 端点)
        $baseUrl = rtrim($this->configService->getDifyUrl(), '/');
        // 确保 URL 以 /v1 结尾
        if (substr($baseUrl, -3) !== '/v1') {
            $baseUrl .= '/v1';
        }
        // 构造上传文档的端点
        $url = $baseUrl . '/datasets/' . $kbId . '/document/create-by-file';
        
        // 创建临时文件
        $tempFile = tempnam(sys_get_temp_dir(), 'dify_update_');
        file_put_contents($tempFile, $fileContent);
        
        // 构造请求头
        $headers = [
            'Authorization' => 'Bearer ' . $this->configService->getDifyApiKey()
        ];
        
        // 构造 multipart 请求体
        $multipart = [
            [
                'name' => 'file',
                'contents' => fopen($tempFile, 'r'),
                'filename' => $fileIdentifier
            ],
            [
                'name' => 'data',
                'contents' => json_encode([
                    'indexing_technique' => 'high_quality',
                    'process_rule' => [
                        'mode' => 'automatic'
                    ]
                ])
            ]
        ];
        
        try {
            // 发送上传请求
            $this->client->post($url, [
                'headers' => $headers,
                'multipart' => $multipart,
                'nextcloud' => [
                    'allow_local_address' => true
                ]
            ]);
        } finally {
            // 清理临时文件
            if ($tempFile && file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
    
    /**
     * 根据知识库 ID 查询知识库信息
     */
    public function getDataset(string $kbId): array {
        $difyUrl = $this->configService->getDifyUrl();
        $apiKey = $this->configService->getDifyApiKey();
        
        if (empty($difyUrl) || empty($apiKey)) {
            throw new \Exception('Dify URL 或 API Key 未配置');
        }
        
        // 构造请求 URL (使用正确的 Dify API 端点)
        $baseUrl = rtrim($difyUrl, '/');
        // 确保 URL 以 /v1 结尾
        if (substr($baseUrl, -3) !== '/v1') {
            $baseUrl .= '/v1';
        }
        // 构造查询知识库的端点
        $url = $baseUrl . '/datasets/' . $kbId;
        
        // 构造请求头
        $headers = [
            'Authorization' => 'Bearer ' . $apiKey
        ];
        
        // 发送请求
        $response = $this->client->get($url, [
            'headers' => $headers,
            'nextcloud' => [
                'allow_local_address' => true
            ]
        ]);
        
        // 解析响应
        return json_decode($response->getBody(), true);
    }
    
    /**
     * 根据知识库 ID 查询文档列表
     */
    public function listDocuments(string $kbId, string $keyword = '', int $page = 1, int $limit = 20): array {
        $difyUrl = $this->configService->getDifyUrl();
        $apiKey = $this->configService->getDifyApiKey();
        
        if (empty($difyUrl) || empty($apiKey)) {
            throw new \Exception('Dify URL 或 API Key 未配置');
        }
        
        // 构造请求 URL (使用正确的 Dify API 端点)
        $baseUrl = rtrim($difyUrl, '/');
        // 确保 URL 以 /v1 结尾
        if (substr($baseUrl, -3) !== '/v1') {
            $baseUrl .= '/v1';
        }
        // 构造查询文档列表的端点
        $url = $baseUrl . '/datasets/' . $kbId . '/documents';
        
        // 添加查询参数
        $queryParams = [];
        if (!empty($keyword)) {
            $queryParams['keyword'] = $keyword;
        }
        if ($page > 1) {
            $queryParams['page'] = $page;
        }
        if ($limit !== 20) {
            $queryParams['limit'] = max(1, min(100, $limit)); // 限制范围在1-100之间
        }
        
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }
        
        // 构造请求头
        $headers = [
            'Authorization' => 'Bearer ' . $apiKey
        ];
        
        // 发送请求
        $response = $this->client->get($url, [
            'headers' => $headers,
            'nextcloud' => [
                'allow_local_address' => true
            ]
        ]);
        
        // 解析响应
        return json_decode($response->getBody(), true);
    }
    
    /**
     * 根据文件名获取文档信息
     */
    public function getDocumentByName(string $kbId, string $fileName): ?array {
        try {
            // 使用新的API端点获取文档列表
            $documentsResponse = $this->listDocuments($kbId);
            
            // 记录调试信息
            $this->logger->debug('DifyService: 文档列表数据: ' . json_encode($documentsResponse) . ', 查找文件名: ' . $fileName, ['app' => 'nextcloud_dify_integration']);
            
            // 查找匹配的文档
            if (isset($documentsResponse['data']) && is_array($documentsResponse['data'])) {
                foreach ($documentsResponse['data'] as $document) {
                    // 添加调试信息
                    $this->logger->debug('DifyService: 检查文档: ' . json_encode($document), ['app' => 'nextcloud_dify_integration']);
                    if (isset($document['name']) && $document['name'] === $fileName) {
                        return $document;
                    }
                }
            } elseif (isset($documentsResponse['documents']) && is_array($documentsResponse['documents'])) {
                // 尝试另一种数据结构
                foreach ($documentsResponse['documents'] as $document) {
                    $this->logger->debug('DifyService: 检查文档(documents): ' . json_encode($document), ['app' => 'nextcloud_dify_integration']);
                    if (isset($document['name']) && $document['name'] === $fileName) {
                        return $document;
                    }
                }
            }
            
            $this->logger->debug('DifyService: 未找到匹配的文档', ['app' => 'nextcloud_dify_integration']);
            return null;
        } catch (\Exception $e) {
            $this->logger->warning('DifyService: 获取文档信息失败: ' . $e->getMessage(), ['app' => 'nextcloud_dify_integration']);
            return null;
        }
    }
    
    /**
     * 根据文件标识符获取文档信息
     */
    public function getDocumentByIdentifier(string $kbId, string $filePath, string $fileName, int $modificationTime = null): ?array {
        try {
            // 生成文件在Dify中的标识符
            $fileIdentifier = $this->configService->generateDifyFileIdentifier($filePath, $fileName, $modificationTime);
            
            // 使用新的API端点获取文档列表
            $documentsResponse = $this->listDocuments($kbId);
            
            // 记录调试信息
            $this->logger->debug('DifyService: 文档列表数据: ' . json_encode($documentsResponse) . ', 查找文件标识符: ' . $fileIdentifier, ['app' => 'nextcloud_dify_integration']);
            
            // 查找匹配的文档
            if (isset($documentsResponse['data']) && is_array($documentsResponse['data'])) {
                foreach ($documentsResponse['data'] as $document) {
                    // 添加调试信息
                    $this->logger->debug('DifyService: 检查文档: ' . json_encode($document), ['app' => 'nextcloud_dify_integration']);
                    if (isset($document['name']) && $document['name'] === $fileIdentifier) {
                        return $document;
                    }
                }
            } elseif (isset($documentsResponse['documents']) && is_array($documentsResponse['documents'])) {
                // 尝试另一种数据结构
                foreach ($documentsResponse['documents'] as $document) {
                    $this->logger->debug('DifyService: 检查文档(documents): ' . json_encode($document), ['app' => 'nextcloud_dify_integration']);
                    if (isset($document['name']) && $document['name'] === $fileIdentifier) {
                        return $document;
                    }
                }
            }
            
            $this->logger->debug('DifyService: 未找到匹配的文档', ['app' => 'nextcloud_dify_integration']);
            return null;
        } catch (\Exception $e) {
            $this->logger->warning('DifyService: 获取文档信息失败: ' . $e->getMessage(), ['app' => 'nextcloud_dify_integration']);
            return null;
        }
    }
    
    /**
     * 根据文件名获取文档 ID
     */
    public function getDocumentIdByName(string $kbId, string $fileName): ?string {
        $document = $this->getDocumentByName($kbId, $fileName);
        return $document ? $document['id'] : null;
    }
    
    /**
     * 根据文件标识符获取文档 ID
     */
    public function getDocumentIdByIdentifier(string $kbId, string $filePath, string $fileName, int $modificationTime = null): ?string {
        $document = $this->getDocumentByIdentifier($kbId, $filePath, $fileName, $modificationTime);
        return $document ? $document['id'] : null;
    }
    
    /**
     * 检查知识库中是否已存在同名文件
     */
    public function isDocumentNameExists(string $kbId, string $fileName): bool {
        try {
            $document = $this->getDocumentByName($kbId, $fileName);
            return $document !== null;
        } catch (\Exception $e) {
            $this->logger->warning('DifyService: 检查文档是否存在时出错: ' . $e->getMessage(), ['app' => 'nextcloud_dify_integration']);
            return false;
        }
    }
    
    /**
     * 生成带有路径前缀的文件名以避免同名冲突
     */
    public function generatePathPrefixedFileName(string $filePath, string $originalFileName, int $modificationTime = null): string {
        // 提取目录路径部分
        $filesPos = strpos($filePath, '/files/');
        if ($filesPos !== false) {
            // 提取 /files/ 后面的部分
            $relativePath = substr($filePath, $filesPos + 7); // 7 是 '/files/' 的长度
            // 获取目录部分
            $dirPath = dirname($relativePath);
            // 如果是根目录，dirname会返回"."，我们需要处理这种情况
            if ($dirPath !== '.' && $dirPath !== '/') {
                // 将目录分隔符替换为下划线，并移除前导斜杠
                $dirPrefix = str_replace('/', '-', ltrim($dirPath, '/'));
                // 添加前缀到文件名，限制前缀长度以避免文件名过长
                $dirPrefix = substr($dirPrefix, 0, 50); // 限制前缀长度为50字符
                // 添加前缀到文件名
                $prefixedName = $dirPrefix . '-' . $originalFileName;
                
                // 如果提供了修改时间，将其作为后缀添加到文件名中
                if ($modificationTime !== null) {
                    $pathInfo = pathinfo($prefixedName);
                    $fileName = $pathInfo['filename'];
                    $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
                    // 使用修改时间作为后缀，格式为 yyyyMMdd-HHmmss
                    $dateTimeSuffix = date('Ymd-His', $modificationTime);
                    return $fileName . '-' . $dateTimeSuffix . $extension;
                }
                
                return $prefixedName;
            }
        }
        
        // 如果无法提取目录信息，返回原始文件名
        // 如果提供了修改时间，将其作为后缀添加到文件名中
        if ($modificationTime !== null) {
            $pathInfo = pathinfo($originalFileName);
            $fileName = $pathInfo['filename'];
            $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
            // 使用修改时间作为后缀，格式为 yyyyMMdd-HHmmss
            $dateSuffix = date('Ymd-His', $modificationTime);
            return $fileName . '_' . $dateSuffix . $extension;
        }
        
        return $originalFileName;
    }
    
    /**
     * 清理文件名，确保符合Dify API要求
     */
    private function sanitizeFileName(string $fileName): string {
        // 移除或替换Dify API不支持的特殊字符
        $sanitized = $fileName;
        
        // 移除可能导致问题的字符（根据Dify API错误信息）
        $sanitized = preg_replace('/[<>:"\/\\|?*\x00-\x1F]/', '-', $sanitized);
        
        // 移除Unicode控制字符
        $sanitized = preg_replace('/[\x00-\x1F\x7F]/', '', $sanitized);
        
        // 限制长度
        if (strlen($sanitized) > 150) {
            $sanitized = substr($sanitized, 0, 150);
        }
        
        // 确保不以点或空格结尾
        $sanitized = rtrim($sanitized, '. ');
        
        // 确保不以空格开头
        $sanitized = ltrim($sanitized, ' ');
        
        // 如果清理后文件名为空，使用默认名称
        if (empty($sanitized)) {
            $sanitized = 'unnamed-file';
        }
        
        return $sanitized;
    }
    private function logFailedOperation(string $operation, string $kbId, string $fileName, string $errorMessage): void {
        $this->logger->error('DifyService: 操作失败 - ' . $operation . ' kbId: ' . $kbId . ' fileName: ' . $fileName . ' 错误: ' . $errorMessage, ['app' => 'nextcloud_dify_integration']);
    }
    
    /**
     * 生成唯一的文件名以避免同名冲突
     */
    public function generateUniqueFileName(string $kbId, string $originalFileName, int $modificationTime = null): string {
        // 如果提供了修改时间，将其作为后缀添加到文件名中
        if ($modificationTime !== null) {
            $pathInfo = pathinfo($originalFileName);
            $fileName = $pathInfo['filename'];
            $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
            // 使用修改时间作为后缀，格式为 yyyyMMdd-HHmmss
            $timestampedName = $fileName . '_' . date('Ymd-His', $modificationTime) . $extension;
            
            // 检查带时间戳的文件名是否已存在
            if (!$this->isDocumentNameExists($kbId, $timestampedName)) {
                return $timestampedName;
            }
            
            // 如果带时间戳的文件名仍然存在冲突，添加序号
            $counter = 1;
            do {
                $newFileName = $fileName . '_' . date('Ymd-His', $modificationTime) . '_' . $counter . $extension;
                $counter++;
                // 限制尝试次数以避免无限循环
                if ($counter > 100) {
                    // 如果尝试了100次 still 有冲突，使用时间戳
                    $newFileName = $fileName . '_' . date('Ymd-His', time()) . $extension;
                    break;
                }
            } while ($this->isDocumentNameExists($kbId, $newFileName));
            
            return $newFileName;
        }
        
        // 如果没有提供修改时间，使用原来的逻辑
        // 如果文件不存在同名文件，直接返回原文件名
        if (!$this->isDocumentNameExists($kbId, $originalFileName)) {
            return $originalFileName;
        }
        
        // 分离文件名和扩展名
        $pathInfo = pathinfo($originalFileName);
        $fileName = $pathInfo['filename'];
        $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
        
        // 尝试添加时间戳或序号来生成唯一文件名
        $counter = 1;
        do {
            $newFileName = $fileName . '_' . $counter . $extension;
            $counter++;
            // 限制尝试次数以避免无限循环
            if ($counter > 100) {
                // 如果尝试了100次 still 有冲突，使用时间戳
                $newFileName = $fileName . '_' . date('Ymd-His', time()) . $extension;
                break;
            }
        } while ($this->isDocumentNameExists($kbId, $newFileName));
        
        return $newFileName;
    }
}
