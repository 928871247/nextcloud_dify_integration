# 更新日志

## 2025-09-25

### 问题修复
- 修复了ConfigService.php中generateDifyFileIdentifier方法的向后兼容部分导致的Dify API报错问题
  - 移除了可能导致invalid_param错误的旧命名模式实现
  - 简化了命名模式选择逻辑，确保所有命名模式都能正确工作
  - 更新了DifyService.php中的uploadDocumentFromFile方法，使用配置的命名模式而不是固定的改进模式
- 修复了模板文件和README.md中的命名模式描述不一致问题
  - 统一了四种命名模式的描述格式
  - 使用更清晰的占位符名称（file, directory, modifiedDate, modifiedTime）
- 修复了DifyService.php中fclose调用时可能传入无效资源的问题
  - 添加了is_resource检查，确保只在有效资源上调用fclose
  - 修复了插件启动时检查配置目录过程中出现的"fclose(): supplied resource is not a valid stream resource"错误
- 修复了DifyService.php中unlink调用时可能传入无效文件路径的问题
  - 添加了file_exists检查，确保只在存在的文件上调用unlink
  - 修复了插件启动时检查配置目录过程中可能出现的文件删除错误
- 修复了文件更新时Dify中会存在两份文档的问题
  - 改进了文件更新处理逻辑，先通过目录+文件名查找并删除旧文档，再创建新文档
  - 添加了通过文档ID直接删除文档的方法，提高删除操作的准确性
  - 优化了文件匹配逻辑，确保能正确识别同一文件的不同版本
- 修复了FileSyncService.php中的语法错误
  - 修复了handleFileDelete方法中缺少闭合大括号的问题
  - 修复了未定义变量$modificationTime的问题
  - 确保所有方法都有正确的闭合和语法结构
- 修复了多语言支持中的依赖注入问题
  - 修复了Admin.php中IL10N依赖注入不正确的问题
  - 更新了Application.php中服务注册代码，确保正确注入IL10N依赖
- 修复了多语言界面切换问题
  - 确保翻译文件格式正确
  - 验证了info.xml中的语言配置
  - 检查了模板文件中的多语言函数调用
- 修复了JavaScript中的硬编码文本问题
  - 将JavaScript文件中的硬编码中文文本替换为多语言函数调用
  - 确保所有用户界面文本都支持多语言切换

### 技术改进
- 移除了重复的文件名清理调用，提高性能
- 确保所有文件标识符生成方法都遵循相同的命名模式选择逻辑
- 增强了资源清理的健壮性，防止fclose调用错误
- 改进了文件更新处理流程，确保文档版本的一致性
- 修复了插件启动时的语法错误，确保插件能正确加载和运行
- 添加了多语言支持（英文和中文）
  - 创建了英文和中文翻译文件
  - 更新了模板文件以支持多语言
  - 更新了JavaScript文件以支持多语言通知
  - 更新了info.xml文件以声明多语言支持
  - 遵循Nextcloud官方文档的多语言实现规范
