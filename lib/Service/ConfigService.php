<?php

namespace OCA\NextcloudDifyIntegration\Service;

use OCP\IConfig;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;
use Generator;

class ConfigService {
    
    private $config;
    private $appName;
    private $rootFolder;
    private $userSession;
    private $logger;
    private $namingPattern;
    
    public function __construct(
        IConfig $config,
        IRootFolder $rootFolder,
        IUserSession $userSession,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->appName = 'nextcloud_dify_integration';
        $this->rootFolder = $rootFolder;
        $this->userSession = $userSession;
        $this->logger = $logger;
        // 获取命名模式配置，默认使用改进的表情符号模式
        $this->namingPattern = $this->config->getAppValue($this->appName, 'naming_pattern', 'improved');
    }
    
    /**
     * 获取 Dify URL
     */
    public function getDifyUrl(): string {
        return $this->config->getAppValue($this->appName, 'dify_url', '');
    }
    
    /**
     * 设置 Dify URL
     */
    public function setDifyUrl(string $url): void {
        $this->config->setAppValue($this->appName, 'dify_url', $url);
    }
    
    /**
     * 获取 Dify API Key
     */
    public function getDifyApiKey(): string {
        return $this->config->getAppValue($this->appName, 'dify_api_key', '');
    }
    
    /**
     * 设置 Dify API Key
     */
    public function setDifyApiKey(string $apiKey): void {
        $this->config->setAppValue($this->appName, 'dify_api_key', $apiKey);
    }
    
    /**
     * 获取文档命名模式
     */
    public function getNamingPattern(): string {
        return $this->namingPattern;
    }
    
    /**
     * 设置文档命名模式
     */
    public function setNamingPattern(string $pattern): void {
        $this->namingPattern = $pattern;
        $this->config->setAppValue($this->appName, 'naming_pattern', $pattern);
    }
    
    /**
     * 获取目录映射关系
     */
    public function getDirectoryMappings(): array {
        $mappings = $this->config->getAppValue($this->appName, 'directory_mappings', '[]');
        $decoded = json_decode($mappings, true);
        return is_array($decoded) ? $decoded : [];  // 防止解码失败返回 null
    }
    
    /**
     * 设置目录映射关系
     */
    public function setDirectoryMappings(array $mappings): void {
        $this->config->setAppValue($this->appName, 'directory_mappings', json_encode($mappings));
    }
    
    /**
     * 根据文件路径获取映射关系
     */
    public function getMappingByPath(string $filePath): ?array {
        $mappings = $this->getDirectoryMappings();
        
        // 按路径长度降序排列，确保更具体的路径优先匹配
        usort($mappings, function($a, $b) {
            return strlen($b['nextcloud_path']) <=> strlen($a['nextcloud_path']);
        });
        
        foreach ($mappings as $mapping) {
            // 检查文件路径是否匹配映射关系
            // Nextcloud文件路径格式: /用户名/files/目录路径/文件名
            // 我们需要匹配 /files/后面的目录路径部分
            $filesPos = strpos($filePath, '/files/');
            if ($filesPos !== false) {
                // 提取 /files/ 后面的部分
                $relativePath = substr($filePath, $filesPos + 7); // 7 是 '/files/' 的长度
                
                // 确保映射路径以 '/' 开头
                $mappedPath = '/' . ltrim($mapping['nextcloud_path'], '/');
                
                // 检查相对路径是否以配置的映射路径开头
                // 并且确保路径匹配是精确的（要么完全匹配，要么后面跟着 '/'）
                if (strpos($relativePath, ltrim($mappedPath, '/')) === 0) {
                    // 检查是否是精确匹配或者后面跟着 '/'
                    $pathAfterMatch = substr($relativePath, strlen(ltrim($mappedPath, '/')));
                    if (empty($pathAfterMatch) || $pathAfterMatch[0] === '/') {
                        return $mapping;
                    }
                }
            }
        }
        
        return null;
    }
    
    /**
     * 生成文件在Dify中的标识符（用于避免同名文件冲突）
     * 使用文件路径+文件名作为Dify中的文档名，并将文件路径转换成下划线格式
     * 例如：nextcloud中文件/test/test.md，dify中对应的是test_test_20230101-123045.md
     */
    public function generateDifyFileIdentifier(string $filePath, string $fileName, int $modificationTime = null): string {
        // 根据配置的命名模式选择不同的实现
        switch ($this->namingPattern) {
            case 'pattern1':
                return $this->generatePattern1DifyFileIdentifier($filePath, $fileName, $modificationTime);
            case 'pattern2':
                return $this->generatePattern2DifyFileIdentifier($filePath, $fileName, $modificationTime);
            case 'pattern3':
                return $this->generatePattern3DifyFileIdentifier($filePath, $fileName, $modificationTime);
            case 'pattern4':
                return $this->generatePattern4DifyFileIdentifier($filePath, $fileName, $modificationTime);
            case 'improved':
                return $this->generateImprovedDifyFileIdentifier($filePath, $fileName, $modificationTime);
            case 'emoji':
                return $this->generateEmojiDifyFileIdentifier($filePath, $fileName, $modificationTime);
            case 'path_separator':
                return $this->generatePathSeparatorDifyFileIdentifier($filePath, $fileName, $modificationTime);
            case 'original':
            default:
                return $this->generatePattern1DifyFileIdentifier($filePath, $fileName, $modificationTime);
        }
    }
    
    /**
     * 生成原始的文件在Dify中的标识符
     * 使用文件路径+文件名作为Dify中的文档名，并将文件路径转换成下划线格式
     * 例如：nextcloud中文件/test/test.md，dify中对应的是test_test_20230101-123045.md
     */
    private function generateOriginalDifyFileIdentifier(string $filePath, string $fileName, int $modificationTime = null): string {
        // 提取目录路径部分
        $filesPos = strpos($filePath, '/files/');
        if ($filesPos !== false) {
            // 提取 /files/ 后面的部分
            $relativePath = substr($filePath, $filesPos + 7); // 7 是 '/files/' 的长度
            
            // 获取文件名（不含路径）
            $pathInfo = pathinfo($relativePath);
            $dirname = $pathInfo['dirname'];
            $basename = $pathInfo['basename'];
            
            // 如果文件在根目录下，dirname 会是 '.'，我们需要处理这种情况
            if ($dirname === '.' || $dirname === '/') {
                // 添加修改时间作为后缀（如果提供）
                if ($modificationTime !== null) {
                    $pathInfo = pathinfo($basename);
                    $fileNameWithoutExt = $pathInfo['filename'];
                    $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
                    $dateSuffix = date('YmdHis', $modificationTime);
                    return $fileNameWithoutExt . '-' . $dateSuffix . $extension;
                }
                return $basename;
            }
            
            // 将目录路径中的分隔符替换为下划线
            $dirPath = str_replace('/', '-', trim($dirname, '/'));
            
            // 组合目录路径和文件名
            $identifier = $dirPath . '-' . $basename;
            
            // 添加修改时间作为后缀（如果提供）
            if ($modificationTime !== null) {
                $pathInfo = pathinfo($identifier);
                $fileNameWithoutExt = $pathInfo['filename'];
                $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
                $dateSuffix = date('YmdHis', $modificationTime);
                $identifier = $fileNameWithoutExt . '-' . $dateSuffix . $extension;
            }
            
            // 移除可能的特殊字符，但保留下划线
            // 支持中文字符、英文字母、数字、点、下划线和连字符
            // $identifier = preg_replace('/[^\p{Han}a-zA-Z0-9_.\-]/u', '-', $identifier);
            
            // 确保不会以多个下划线开头
            $identifier = ltrim($identifier, '-');
            
            return $identifier;
        }
        
        // 如果无法提取目录信息，使用文件名作为标识符
        // 添加修改时间作为后缀（如果提供）
        if ($modificationTime !== null && $fileName !== null) {
            $pathInfo = pathinfo($fileName);
            $fileNameWithoutExt = $pathInfo['filename'];
            $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
            $dateSuffix = date('YmdHis', $modificationTime);
            return $fileNameWithoutExt . '-' . $dateSuffix . $extension;
        }
        return $fileName;
    }
    
    /**
     * 生成改进的文件在Dify中的标识符（更美观的命名方式）
     * 使用表情符号突出时间、目录和文件名
     * 例如：📅2025-09-25 📁test 📄test.md
     */
    public function generateImprovedDifyFileIdentifier(string $filePath, string $fileName, int $modificationTime = null): string {
        // 提取目录路径部分
        $filesPos = strpos($filePath, '/files/');
        if ($filesPos !== false) {
            // 提取 /files/ 后面的部分
            $relativePath = substr($filePath, $filesPos + 7);
            
            // 获取目录路径
            $dirPath = dirname($relativePath);
            
            // 处理目录部分
            $dirPart = '';
            if ($dirPath !== '.' && $dirPath !== '/') {
                $dirPath = ltrim($dirPath, '/');
                $dirPart = '📁' . $dirPath . ' ';
            }
            
            // 处理时间部分
            $datePart = '';
            if ($modificationTime !== null) {
                $datePart = '📅' . date('Y-m-d His', $modificationTime);
            } else {
                // 如果没有提供修改时间，使用当前时间
                $datePart = '📅' . date('Y-m-d His');
            }
            
            // 组合所有部分 (文件名+目录+日期时间)
            $identifier = '📄' . $fileName . $dirPart . $datePart;
            
            // 确保文件名是URL安全的
            return $this->makeIdentifierUrlSafe($identifier);
        }
        
        // 如果无法提取目录信息，使用简化格式
        if ($modificationTime !== null) {
            $datePart = '📅' . date('Y-m-d His', $modificationTime);
            $identifier = '📄' . $fileName . ' ' . $datePart;
            return $this->makeIdentifierUrlSafe($identifier);
        } else {
            $identifier = '📄' . $fileName . ' 📅' . date('Y-m-d His');
            return $this->makeIdentifierUrlSafe($identifier);
        }
    }
    
    /**
     * 生成路径分隔符格式的文件在Dify中的标识符
     * 使用路径分隔符和更清晰的时间格式
     * 例如：test/test_2025-09-25_08-58-03.md
     */
    public function generatePathSeparatorDifyFileIdentifier(string $filePath, string $fileName, int $modificationTime = null): string {
        // 提取目录路径部分
        $filesPos = strpos($filePath, '/files/');
        if ($filesPos !== false) {
            // 提取 /files/ 后面的部分
            $relativePath = substr($filePath, $filesPos + 7);
            
            // 获取目录路径
            $dirPath = dirname($relativePath);
            
            // 处理目录部分
            if ($dirPath === '.' || $dirPath === '/') {
                $identifier = $fileName;
            } else {
                $dirPath = ltrim($dirPath, '/');
                $identifier = $dirPath . '/' . $fileName;
            }
            
            // 添加修改时间作为后缀（如果提供）
            if ($modificationTime !== null) {
                $pathInfo = pathinfo($identifier);
                $fileNameWithoutExt = $pathInfo['filename'];
                $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
                $dateSuffix = date('Y-m-d_His', $modificationTime);
                $identifier = $fileNameWithoutExt . '_' . $dateSuffix . $extension;
            }
            
            return $identifier;
        }
        
        // 如果无法提取目录信息，使用文件名作为标识符
        if ($modificationTime !== null && $fileName !== null) {
            $pathInfo = pathinfo($fileName);
            $fileNameWithoutExt = $pathInfo['filename'];
            $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
            $dateSuffix = date('Y-m-d_His', $modificationTime);
            return $fileNameWithoutExt . '_' . $dateSuffix . $extension;
        }
        return $fileName;
    }
    
    /**
     * 生成表情符号格式的文件在Dify中的标识符
     * 使用表情符号突出时间、目录和文件名
     * 例如：📅2025-09-25 📁test 📄test.md
     */
    public function generateEmojiDifyFileIdentifier(string $filePath, string $fileName, int $modificationTime = null): string {
        // 提取目录路径部分
        $filesPos = strpos($filePath, '/files/');
        if ($filesPos !== false) {
            // 提取 /files/ 后面的部分
            $relativePath = substr($filePath, $filesPos + 7);
            
            // 获取目录路径
            $dirPath = dirname($relativePath);
            
            // 处理目录部分
            $dirPart = '';
            if ($dirPath !== '.' && $dirPath !== '/') {
                $dirPath = ltrim($dirPath, '/');
                $dirPart = ' 📁' . $dirPath;
            }
            
            // 处理时间部分
            $datePart = '';
            if ($modificationTime !== null) {
                $datePart = '📅' . date('Y-m-d', $modificationTime);
            } else {
                // 如果没有提供修改时间，使用当前时间
                $datePart = '📅' . date('Y-m-d');
            }
            
            // 组合所有部分
            return $datePart . $dirPart . ' 📄' . $fileName;
        }
        
        // 如果无法提取目录信息，使用简化格式
        if ($modificationTime !== null) {
            $datePart = '📅' . date('Y-m-d', $modificationTime);
            return $datePart . ' 📄' . $fileName;
        } else {
            return '📅' . date('Y-m-d') . ' 📄' . $fileName;
        }
    }
    
    /**
     * 命名模式1: 📄[文件名] 📁[目录路径] 📅[YYYY-MM-DD HHMMSS] .[扩展名]
     */
    public function generatePattern1DifyFileIdentifier(string $filePath, string $fileName, int $modificationTime = null): string {
        // 提取目录路径部分
        $filesPos = strpos($filePath, '/files/');
        if ($filesPos !== false) {
            // 提取 /files/ 后面的部分
            $relativePath = substr($filePath, $filesPos + 7);
            
            // 获取目录路径
            $dirPath = dirname($relativePath);
            
            // 处理目录部分
            $dirPart = '';
            if ($dirPath !== '.' && $dirPath !== '/') {
                $dirPath = ltrim($dirPath, '/');
                $dirPart = '📁' . $dirPath . ' ';
            }
            
            // 处理时间部分
            $datePart = '';
            if ($modificationTime !== null) {
                $datePart = '📅' . date('Y-m-d His', $modificationTime);
            } else {
                // 如果没有提供修改时间，使用当前时间
                $datePart = '📅' . date('Y-m-d His');
            }
            
            // 获取文件扩展名
            $pathInfo = pathinfo($fileName);
            $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
            
            // 组合所有部分: 📄[文件名] 📁[目录路径] 📅[YYYY-MM-DD HHMMSS] .[扩展名]
            $identifier = '📄' . $fileName . ' ' . $dirPart . $datePart . ' ' . $extension;
            return $this->makeIdentifierUrlSafe($identifier);
        }
        
        // 如果无法提取目录信息，使用简化格式
        if ($modificationTime !== null) {
            $datePart = '📅' . date('Y-m-d His', $modificationTime);
            $pathInfo = pathinfo($fileName);
            $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
            $identifier = '📄' . $fileName . ' ' . $datePart . ' ' . $extension;
            return $this->makeIdentifierUrlSafe($identifier);
        } else {
            $pathInfo = pathinfo($fileName);
            $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
            $identifier = '📄' . $fileName . ' 📅' . date('Y-m-d His') . ' ' . $extension;
            return $this->makeIdentifierUrlSafe($identifier);
        }
    }
    
    /**
     * 命名模式2: 📁[目录路径] 📄[文件名] 📅[YYYY-MM-DD HHMMSS] .[扩展名]
     */
    public function generatePattern2DifyFileIdentifier(string $filePath, string $fileName, int $modificationTime = null): string {
        // 提取目录路径部分
        $filesPos = strpos($filePath, '/files/');
        if ($filesPos !== false) {
            // 提取 /files/ 后面的部分
            $relativePath = substr($filePath, $filesPos + 7);
            
            // 获取目录路径
            $dirPath = dirname($relativePath);
            
            // 处理目录部分
            $dirPart = '';
            if ($dirPath !== '.' && $dirPath !== '/') {
                $dirPath = ltrim($dirPath, '/');
                $dirPart = '📁' . $dirPath . ' ';
            }
            
            // 处理时间部分
            $datePart = '';
            if ($modificationTime !== null) {
                $datePart = '📅' . date('Y-m-d His', $modificationTime);
            } else {
                // 如果没有提供修改时间，使用当前时间
                $datePart = '📅' . date('Y-m-d His');
            }
            
            // 获取文件扩展名
            $pathInfo = pathinfo($fileName);
            $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
            
            // 组合所有部分: 📁[目录路径] 📄[文件名] 📅[YYYY-MM-DD HHMMSS] .[扩展名]
            $identifier = $dirPart . '📄' . $fileName . ' ' . $datePart . ' ' . $extension;
            return $this->makeIdentifierUrlSafe($identifier);
        }
        
        // 如果无法提取目录信息，使用简化格式
        if ($modificationTime !== null) {
            $datePart = '📅' . date('Y-m-d His', $modificationTime);
            $pathInfo = pathinfo($fileName);
            $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
            $identifier = '📄' . $fileName . ' ' . $datePart . ' ' . $extension;
            return $this->makeIdentifierUrlSafe($identifier);
        } else {
            $pathInfo = pathinfo($fileName);
            $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
            $identifier = '📁 ' . '📄' . $fileName . ' 📅' . date('Y-m-d His') . ' ' . $extension;
            return $this->makeIdentifierUrlSafe($identifier);
        }
    }
    
    /**
     * 命名模式3: [文件名] ([目录路径]) [YYYY-MM-DD HHMMSS].[扩展名]
     */
    public function generatePattern3DifyFileIdentifier(string $filePath, string $fileName, int $modificationTime = null): string {
        // 提取目录路径部分
        $filesPos = strpos($filePath, '/files/');
        if ($filesPos !== false) {
            // 提取 /files/ 后面的部分
            $relativePath = substr($filePath, $filesPos + 7);
            
            // 获取目录路径
            $dirPath = dirname($relativePath);
            
            // 处理目录部分
            $dirPart = '';
            if ($dirPath !== '.' && $dirPath !== '/') {
                $dirPath = ltrim($dirPath, '/');
                $dirPart = ' (' . $dirPath . ') ';
            }
            
            // 处理时间部分
            $datePart = '';
            if ($modificationTime !== null) {
                $datePart = ' ' . date('Y-m-d His', $modificationTime);
            } else {
                // 如果没有提供修改时间，使用当前时间
                $datePart = ' ' . date('Y-m-d His');
            }
            
            // 获取文件扩展名
            $pathInfo = pathinfo($fileName);
            $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
            
            // 组合所有部分: [文件名] ([目录路径]) [YYYY-MM-DD HHMMSS].[扩展名]
            $identifier = $fileName . $dirPart . $datePart . $extension;
            return $this->makeIdentifierUrlSafe($identifier);
        }
        
        // 如果无法提取目录信息，使用简化格式
        if ($modificationTime !== null) {
            $datePart = ' ' . date('Y-m-d His', $modificationTime);
            $pathInfo = pathinfo($fileName);
            $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
            $identifier = $fileName . $datePart . $extension;
            return $this->makeIdentifierUrlSafe($identifier);
        } else {
            $pathInfo = pathinfo($fileName);
            $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
            $identifier = $fileName . ' ' . date('Y-m-d His') . $extension;
            return $this->makeIdentifierUrlSafe($identifier);
        }
    }
    
    /**
     * 命名模式4: ([目录路径]) [文件名] [YYYY-MM-DD HHMMSS].[扩展名]
     */
    public function generatePattern4DifyFileIdentifier(string $filePath, string $fileName, int $modificationTime = null): string {
        // 提取目录路径部分
        $filesPos = strpos($filePath, '/files/');
        if ($filesPos !== false) {
            // 提取 /files/ 后面的部分
            $relativePath = substr($filePath, $filesPos + 7);
            
            // 获取目录路径
            $dirPath = dirname($relativePath);
            
            // 处理目录部分
            $dirPart = '';
            if ($dirPath !== '.' && $dirPath !== '/') {
                $dirPath = ltrim($dirPath, '/');
                $dirPart = '(' . $dirPath . ') ';
            }
            
            // 处理时间部分
            $datePart = '';
            if ($modificationTime !== null) {
                $datePart = ' ' . date('Y-m-d His', $modificationTime);
            } else {
                // 如果没有提供修改时间，使用当前时间
                $datePart = ' ' . date('Y-m-d His');
            }
            
            // 获取文件扩展名
            $pathInfo = pathinfo($fileName);
            $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
            
            // 组合所有部分: ([目录路径]) [文件名] [YYYY-MM-DD HHMMSS].[扩展名]
            $identifier = $dirPart . $fileName . $datePart . $extension;
            return $this->makeIdentifierUrlSafe($identifier);
        }
        
        // 如果无法提取目录信息，使用简化格式
        if ($modificationTime !== null) {
            $datePart = ' ' . date('Y-m-d His', $modificationTime);
            $pathInfo = pathinfo($fileName);
            $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
            $identifier = $fileName . $datePart . $extension;
            return $this->makeIdentifierUrlSafe($identifier);
        } else {
            $pathInfo = pathinfo($fileName);
            $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
            $identifier = '(' . ') ' . $fileName . ' ' . date('Y-m-d His') . $extension;
            return $this->makeIdentifierUrlSafe($identifier);
        }
    }
    
    /**
     * 记录文件为已处理
     */
    public function markFileAsProcessed(string $filePath): void {
        // 在实际实现中，这会将文件路径存储在数据库或配置中
        // 以避免重复处理
        $processedFiles = $this->config->getAppValue($this->appName, 'processed_files', '[]');
        $processedFilesArray = json_decode($processedFiles, true) ?: [];
        
        if (!in_array($filePath, $processedFilesArray)) {
            $processedFilesArray[] = $filePath;
            $this->config->setAppValue($this->appName, 'processed_files', json_encode($processedFilesArray));
        }
    }
    
    /**
     * 检查文件是否已处理
     */
    public function isFileProcessed(string $filePath): bool {
        $processedFiles = $this->config->getAppValue($this->appName, 'processed_files', '[]');
        $processedFilesArray = json_decode($processedFiles, true) ?: [];
        return in_array($filePath, $processedFilesArray);
    }
    
    /**
     * 清除已处理文件记录
     */
    public function clearProcessedFiles(): void {
        $this->config->setAppValue($this->appName, 'processed_files', '[]');
    }
    
    /**
     * 比较 Nextcloud 文件和 Dify 文档是否匹配
     */
    public function isFileMatchDocument(string $nextcloudFileName, int $nextcloudModificationTime, string $difyDocumentName): bool {
        // 生成 Nextcloud 文件的标识符
        $pathInfo = pathinfo($nextcloudFileName);
        $fileName = $pathInfo['filename'];
        $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
        $nextcloudIdentifier = $fileName . '-' . $nextcloudModificationTime . $extension;
        
        // 比较标识符是否匹配
        return $nextcloudIdentifier === $difyDocumentName;
    }
    
    /**
     * 确保文件标识符是URL安全的并且符合Dify API要求
     */
    private function makeIdentifierUrlSafe(string $identifier): string {
        // 移除或替换可能导致URL问题的字符
        // 但保留表情符号，因为它们通常是UTF-8编码的
        $safeIdentifier = $identifier;
        
        // 确保不会出现连续的空格
        $safeIdentifier = preg_replace('/\s+/', ' ', $safeIdentifier);
        
        // 移除Dify API不支持的特殊字符
        // 根据错误信息，需要移除可能导致问题的字符
        $safeIdentifier = preg_replace('/[<>:"\/\\|?*\x00-\x1F]/', '-', $safeIdentifier);
        
        // 移除可能导致问题的Unicode控制字符
        $safeIdentifier = preg_replace('/[\x00-\x1F\x7F]/', '', $safeIdentifier);
        
        // 限制总长度以避免URL过长
        if (strlen($safeIdentifier) > 150) {
            $safeIdentifier = substr($safeIdentifier, 0, 150);
        }
        
        // 确保不以点或空格结尾
        $safeIdentifier = rtrim($safeIdentifier, '. ');
        
        // 确保不以空格开头
        $safeIdentifier = ltrim($safeIdentifier, ' ');
        
        return $safeIdentifier;
    }
    
    /**
     * 检查文件是否需要同步
     */
    public function isFileSyncNeeded(string $kbId, string $nextcloudFileName, int $nextcloudModificationTime): bool {
        // 生成 Nextcloud 文件的标识符
        $pathInfo = pathinfo($nextcloudFileName);
        $fileName = $pathInfo['filename'];
        $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
        $nextcloudIdentifier = $fileName . '-' . $nextcloudModificationTime . $extension;
        
        // 检查 Dify 中是否已存在同名文档
        $documentExists = false;
        try {
            // 这里需要调用 DifyService 来检查文档是否存在
            // 但由于依赖关系，我们暂时返回 true 表示需要检查
            $documentExists = false; // 需要在实际使用中实现
        } catch (\Exception $e) {
            // 如果检查失败，假设需要同步
            return true;
        }
        
        // 如果文档不存在，需要同步
        if (!$documentExists) {
            return true;
        }
        
        // 如果已存在，需要进一步检查是否完全匹配
        return false; // 需要在实际使用中实现
    }
    
    /**
     * 获取目录中的文件列表（概念性实现）
     */
    public function getFilesFromDirectory(string $nextcloudPath, string $username = 'admin'): array {
        // 在实际的 Nextcloud 环境中，这个方法会访问文件系统
        // 由于当前环境限制，我们返回一个示例数据结构
        
        $this->logger->debug('ConfigService: 计划获取目录文件列表 - 路径: ' . $nextcloudPath, ['app' => 'nextcloud_dify_integration']);
        
        // 概念性实现示例：
        /*
        try {
            // 获取根文件夹
            $rootFolder = \OC::$server->get(\OCP\Files\IRootFolder::class);
            
            // 获取用户文件夹
            $userFolder = $rootFolder->getUserFolder($username);
            
            // 获取指定路径的节点
            $node = $userFolder->get($nextcloudPath);
            
            // 检查是否为目录
            if ($node instanceof \OCP\Files\Folder) {
                // 获取目录中的所有文件和子目录
                $nodes = $node->getDirectoryListing();
                
                $files = [];
                foreach ($nodes as $childNode) {
                    if ($childNode instanceof \OCP\Files\File) {
                        $files[] = [
                            'name' => $childNode->getName(),
                            'path' => $childNode->getPath(),
                            'size' => $childNode->getSize(),
                            'mtime' => $childNode->getMTime(),
                            'mimetype' => $childNode->getMimeType()
                        ];
                    }
                }
                
                return $files;
            }
        } catch (\Exception $e) {
            $this->logger->error('ConfigService: 获取目录文件列表失败 - ' . $e->getMessage(), ['app' => 'nextcloud_dify_integration']);
        }
        */
        
        // 返回空数组表示无法获取文件列表
        return [];
    }
    
    /**
     * 扫描指定目录的所有文件和子目录（递归），返回元数据列表
     * @param string $path 相对路径 (e.g., '/test' 或 '/')
     * @return Generator|array 文件/目录元数据数组（用Generator避免内存溢出）
     */
    public function scanDirectoryFiles(string $path = '/'): Generator {
        $user = $this->userSession->getUser();
        if (!$user) {
            throw new \Exception('No user session');
        }
        $userFolder = $this->rootFolder->getUserFolder($user->getUID());
        $targetFolder = $userFolder->getRelativePath(trim($path, '/')) ? 
             $userFolder->get($path) : $userFolder;
        if (!$targetFolder instanceof \OCP\Files\Folder) {
            throw new \Exception("Invalid path: $path");
        }
        $this->logger->info("Scanning directory: $path for user: {$user->getUID()}", ['app' => 'nextcloud_dify_integration']);
        // 递归遍历
        $iterator = $this->traverseNodes($targetFolder);
        foreach ($iterator as $node) {
            if ($node->getPermissions() & \OCP\Constants::PERMISSION_READ) {  // 检查读权限
                yield $this->extractNodeMetadata($node);
            }
        }
    }
    
    /**
     * 递归遍历节点（文件 + 子目录）
     */
    private function traverseNodes(\OCP\Files\Node $node): Generator {
        yield $node;  // 当前节点
        if ($node instanceof \OCP\Files\Folder) {
            foreach ($node->getDirectoryContent() as $child) {
                yield from $this->traverseNodes($child);  // 递归子节点
            }
        }
    }
    
    /**
     * 提取节点元数据
     */
    private function extractNodeMetadata(Node $node): array {
        return [
            'path' => $node->getPath(),  // 完整路径 (e.g., /user/files/test/file.txt)
            'name' => $node->getName(),
            'type' => $node->getType(),  // 'file' 或 'dir'
            'size' => $node->getSize(),  // 文件大小 (bytes)，目录为0
            'mtime' => $node->getMTime(),  // 修改时间 (Unix timestamp)
            'ctime' => $node->getCreationTime() ?? null,  // 创建时间 (Nextcloud 20+)
            'owner' => $node->getOwner()?->getDisplayName() ?? null,
            'mimetype' => method_exists($node, 'getMimetype') ? $node->getMimetype() : null,  // 文件MIME类型
        ];
    }
}
