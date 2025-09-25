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

### 技术改进
- 移除了重复的文件名清理调用，提高性能
- 确保所有文件标识符生成方法都遵循相同的命名模式选择逻辑
- 增强了资源清理的健壮性，防止fclose调用错误
- 改进了文件更新处理流程，确保文档版本的一致性
