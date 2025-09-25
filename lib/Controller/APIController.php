<?php

namespace OCA\NextcloudDifyIntegration\Controller;

use OCP\AppFramework\OCSController;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IConfig;
use Psr\Log\LoggerInterface;
use OCA\NextcloudDifyIntegration\Service\ConfigService;

class APIController extends OCSController {
    
    private $configService;
    private $config;
    private $logger;
    
    public function __construct(
        $AppName,
        IRequest $request,
        ConfigService $configService,
        IConfig $config,
        LoggerInterface $logger
    ) {
        parent::__construct($AppName, $request);
        $this->configService = $configService;
        $this->config = $config;
        $this->logger = $logger;
    }
    
    /**
     * 保存配置
     */
    public function save(): JSONResponse {
        try {
            // 获取请求数据
            $difyUrl = $this->request->getParam('dify_url', '');
            $difyApiKey = $this->request->getParam('dify_api_key', '');
            
            // 获取所有参数
            $allParams = $this->request->getParams();
            $this->logger->debug('All request params values: ' . json_encode($allParams), ['app' => 'nextcloud_dify_integration']);
            
            // 解析映射：优先 JSON 格式（直接 mappings 键），fallback 扁平键
            $mappings = [];
            
            // 方式1: JSON 直接传（前端常见）
            if (isset($allParams['mappings']) && is_string($allParams['mappings'])) {
                $this->logger->debug('Received mappings as string: ' . $allParams['mappings'], ['app' => 'nextcloud_dify_integration']);
                $decodedMappings = json_decode($allParams['mappings'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedMappings)) {
                    // 过滤空值映射
                    $mappings = array_filter($decodedMappings, function($mapping) {
                        return !empty($mapping['nextcloud_path'] ?? '') && !empty($mapping['dify_kb_id'] ?? '');
                    });
                    $this->logger->debug('Parsed JSON mappings: ' . json_encode($mappings), ['app' => 'nextcloud_dify_integration']);
                } else {
                    $this->logger->warning('Invalid JSON in mappings: ' . json_last_error_msg(), ['app' => 'nextcloud_dify_integration']);
                }
            }
            
            // 方式2: Fallback 扁平键（兼容旧前端）
            if (empty($mappings)) {
                $mappingParams = array_filter($allParams, function($key) {
                    return strpos($key, 'mappings[') === 0;
                }, ARRAY_FILTER_USE_KEY);
                
                $this->logger->debug('Fallback to flat mapping params: ' . json_encode($mappingParams), ['app' => 'nextcloud_dify_integration']);
                
                $tempMappings = [];
                foreach ($mappingParams as $key => $value) {
                    if (preg_match('/^mappings\[(\d+)\]\[(nextcloud_path|dify_kb_id)\](?:\[])?$/', $key, $matches)) {
                        $index = (int)$matches[1];
                        $field = $matches[2];
                        
                        if (!isset($tempMappings[$index])) {
                            $tempMappings[$index] = [];
                        }
                        
                        $tempMappings[$index][$field] = trim($value ?? '');
                    }
                }
                
                ksort($tempMappings);
                foreach ($tempMappings as $mapping) {
                    if (isset($mapping['nextcloud_path']) && isset($mapping['dify_kb_id']) && !empty($mapping['nextcloud_path']) && !empty($mapping['dify_kb_id'])) {
                        $mappings[] = $mapping;
                    }
                }
                
                $this->logger->debug('Parsed flat mappings: ' . json_encode($mappings), ['app' => 'nextcloud_dify_integration']);
            }
            
            // 获取命名模式
            $namingPattern = $this->request->getParam('naming_pattern', 'pattern1');
            
            // 保存
            $this->configService->setDifyUrl($difyUrl);
            $this->configService->setDifyApiKey($difyApiKey);
            $this->configService->setDirectoryMappings($mappings);
            $this->configService->setNamingPattern($namingPattern);
            
            // 返回
            $response = [
                'ocs' => [
                    'meta' => [
                        'status' => 'ok',
                        'statuscode' => 200,
                        'message' => 'OK'
                    ],
                    'data' => [
                        'status' => 'success',
                        'message' => '配置已保存',
                        'mappings_count' => count($mappings)
                    ]
                ]
            ];
            
            return new JSONResponse($response);
        } catch (\Exception $e) {
            $this->logger->error('Save config error: ' . $e->getMessage(), ['app' => 'nextcloud_dify_integration']);
            $response = [
                'ocs' => [
                    'meta' => [
                        'status' => 'failure',
                        'statuscode' => 500,
                        'message' => 'Internal Server Error'
                    ],
                    'data' => [
                        'status' => 'error',
                        'message' => $e->getMessage()
                    ]
                ]
            ];
            
            return new JSONResponse($response, 500);
        }
    }
    
    /**
     * 获取配置
     */
    public function get(): JSONResponse {
        try {
            $difyUrl = $this->configService->getDifyUrl();
            $difyApiKey = $this->configService->getDifyApiKey();
            $directoryMappings = $this->configService->getDirectoryMappings();
            $namingPattern = $this->configService->getNamingPattern();
            
            $this->logger->debug('Retrieved mappings: ' . json_encode($directoryMappings), ['app' => 'nextcloud_dify_integration']);
            
            $response = [
                'ocs' => [
                    'meta' => [
                        'status' => 'ok',
                        'statuscode' => 200,
                        'message' => 'OK'
                    ],
                    'data' => [
                        'status' => 'success',
                        'data' => [
                            'dify_url' => $difyUrl,
                            'dify_api_key' => $difyApiKey,
                            'directory_mappings' => $directoryMappings,
                            'naming_pattern' => $namingPattern
                        ]
                    ]
                ]
            ];
            
            return new JSONResponse($response);
        } catch (\Exception $e) {
            $this->logger->error('Get config error: ' . $e->getMessage(), ['app' => 'nextcloud_dify_integration']);
            $response = [
                'ocs' => [
                    'meta' => [
                        'status' => 'failure',
                        'statuscode' => 500,
                        'message' => 'Internal Server Error'
                    ],
                    'data' => [
                        'status' => 'error',
                        'message' => $e->getMessage()
                    ]
                ]
            ];
            
            return new JSONResponse($response, 500);
        }
    }
    
    /**
     * 扫描目录文件
     */
    public function scan(string $path = '/'): JSONResponse {
        try {
            $files = [];
            foreach ($this->configService->scanDirectoryFiles($path) as $file) {
                $files[] = $file;
            }
            $this->logger->debug('Scanned files count: ' . count($files), ['app' => 'nextcloud_dify_integration']);
            $response = [
                'ocs' => [
                    'meta' => ['status' => 'ok', 'statuscode' => 200, 'message' => 'OK'],
                    'data' => ['status' => 'success', 'files' => $files]
                ]
            ];
            return new JSONResponse($response);
        } catch (\Exception $e) {
            $this->logger->error('Scan error: ' . $e->getMessage(), ['app' => 'nextcloud_dify_integration']);
            $response = [
                'ocs' => [
                    'meta' => [
                        'status' => 'failure',
                        'statuscode' => 500,
                        'message' => 'Internal Server Error'
                    ],
                    'data' => [
                        'status' => 'error',
                        'message' => $e->getMessage()
                    ]
                ]
            ];
            
            return new JSONResponse($response, 500);
        }
    }
}
