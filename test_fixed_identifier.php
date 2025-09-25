<?php
/**
 * 测试修复后的文件标识符生成逻辑
 */

// 模拟 ConfigService 类的部分功能
class TestConfigService {
    private $namingPattern;
    
    public function __construct($namingPattern = 'pattern1') {
        $this->namingPattern = $namingPattern;
    }
    
    /**
     * 生成文件在Dify中的标识符（用于避免同名文件冲突）
     * 使用文件路径+文件名作为Dify中的文档名，并将文件路径转换成下划线格式
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
     * 生成改进的文件在Dify中的标识符（更美观的命名方式）
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
     * 确保文件标识符是URL安全的并且符合Dify API要求
     */
    private function makeIdentifierUrlSafe(string $identifier): string {
        // 移除或替换可能导致URL问题的字符
        $safeIdentifier = $identifier;
        
        // 确保不会出现连续的空格
        $safeIdentifier = preg_replace('/\s+/', ' ', $safeIdentifier);
        
        // 移除Dify API不支持的特殊字符
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
}

// 测试用例
function runTests() {
    echo "测试修复后的文件标识符生成逻辑\n";
    echo "================================\n\n";
    
    // 测试所有命名模式
    $patterns = ['pattern1', 'pattern2', 'pattern3', 'pattern4', 'improved'];
    
    foreach ($patterns as $pattern) {
        echo "测试命名模式: $pattern\n";
        $configService = new TestConfigService($pattern);
        
        // 测试用例1: 带扩展名的文件
        $filePath1 = '/admin/files/test/test.md';
        $fileName1 = 'test.md';
        $modificationTime1 = strtotime('2025-09-25 09:05:02');
        
        $identifier1 = $configService->generateDifyFileIdentifier($filePath1, $fileName1, $modificationTime1);
        echo "  文件路径: $filePath1\n";
        echo "  文件名: $fileName1\n";
        echo "  生成的标识符: $identifier1\n";
        
        // 检查文件扩展名是否在正确位置（对于支持扩展名的模式）
        if (in_array($pattern, ['pattern1', 'pattern2', 'pattern3', 'pattern4'])) {
            $pathInfo = pathinfo($fileName1);
            $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
            if (!empty($extension)) {
                // 检查扩展名是否在标识符末尾
                if (substr($identifier1, -strlen($extension)) === $extension) {
                    echo "  ✓ 文件扩展名位置正确\n";
                } else {
                    echo "  ✗ 文件扩展名位置不正确\n";
                }
            }
        }
        
        echo "\n";
    }
    
    echo "测试完成。\n";
}

// 运行测试
runTests();
?>
