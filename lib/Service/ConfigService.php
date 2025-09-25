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
        // 获取命名模式配置，默认使用pattern1模式
        $this->namingPattern = $this->config->getAppValue($this->appName, 'naming_pattern', 'pattern1');
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
