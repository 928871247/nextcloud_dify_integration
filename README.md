# Nextcloud Dify 知识库集成插件

这是一个将 Nextcloud 与 Dify 知识库集成的插件，可以自动将 Nextcloud 中指定目录的文件同步到 Dify 知识库中。

## 功能特性

- 在管理员设置中添加"知识库"菜单项
- 配置 Dify API 信息和目录映射关系
- 自动同步 Nextcloud 文件到 Dify 知识库
- 支持文件的新增、修改和删除操作同步
- 直接使用 Nextcloud 文件对象进行上传，提高效率和可靠性

## 系统要求

- Nextcloud 27+ (已针对 Nextcloud 31 进行优化)
- PHP 7.4+

## 安装

### 方法一：从应用商店安装（推荐）

1. 在 Nextcloud 管理员界面的应用商店中搜索"Dify 知识库集成"
2. 点击安装按钮

### 方法二：手动安装

1. 下载插件文件夹到 Nextcloud 的 `apps` 目录中
2. 在 Nextcloud 管理员界面中启用插件

## 配置

1. 登录 Nextcloud 管理员账户
2. 进入管理员设置页面
3. 点击左侧菜单中的"知识库"选项
4. 在配置界面中填写：
   - Dify 地址：Dify 服务的基础 URL 地址（建议包含 `/v1` 路径）
     - 正确示例：`http://172.16.207.5/v1` 或 `https://api.dify.ai/v1`
     - 也可以配置为基础地址：`http://172.16.207.5`（插件会自动添加 `/v1`）
   - Dify API Key：用于访问 Dify API 的密钥
   - 文档命名模式：选择Dify中文档的命名方式（提供四种新的命名模式）
     - 📄file 📁directory 📅modifiedDate modifiedTime.md (推荐)：文件名+目录+日期时间格式
     - 📁directory 📄file 📅modifiedDate modifiedTime.md：目录+文件名+日期时间格式
     - file (directory) modifiedDate modifiedTime.md：文件名+目录+日期时间格式
     - (directory) file modifiedDate modifiedTime.md：目录+文件名+日期时间格式
   - 目录映射关系：可以配置多个映射，每个映射包括 Nextcloud 目录路径和对应的 Dify 知识库 ID
     - Nextcloud 目录路径：请输入相对于 Nextcloud 根目录的路径，例如：/test 表示根目录下的 test 文件夹
     - Dify 知识库 ID：在 Dify 平台上创建知识库后获取的 dataset_id（注意：不是 kb_id）
       - 在 Dify 管理后台的知识库列表中，点击"设置"可以查看 dataset_id
5. 点击"保存"按钮保存配置

## 使用说明

插件会自动监控配置的目录，当文件发生以下变化时，会执行相应操作：

- **新增文件**：将文件上传到对应的 Dify 知识库
- **修改文件**：先从 Dify 知识库中删除该文件，再重新上传新版本
- **删除文件**：从 Dify 知识库中删除对应文件

## 故障排除

### 启用插件时出现"Could not download app nextcloud_dify_integration"错误

这个错误通常是因为 Nextcloud 无法正确识别或加载插件导致的。请按以下步骤排查：

1. 确保插件文件夹位于 Nextcloud 的 `apps` 目录中
2. 检查插件文件夹名称是否为 `nextcloud_dify_integration`（必须完全匹配，包括下划线）
3. 确保 `appinfo/info.xml` 文件存在且格式正确
4. 检查 Web 服务器对插件文件夹及其子文件夹的读取权限
5. 确保所有必需的文件都已正确创建，包括：
   - `appinfo/info.xml`
   - `appinfo/routes.php`
   - `lib/AppInfo/Application.php`
   - `lib/Settings/Admin.php`
   - `lib/Settings/AdminSection.php`
6. 清除 Nextcloud 缓存：
   ```bash
   sudo -u www-data php occ maintenance:mode --on
   sudo -u www-data php occ maintenance:mode --off
   ```
7. 如果是从应用商店安装的，请尝试手动下载并安装插件
8. 检查 Nextcloud 日志文件以获取更多错误信息：
   ```bash
   sudo -u www-data php occ log:watch
   ```

### 插件启用后不显示设置菜单

这个错误通常是因为设置部分未正确注册或实现导致的。请按以下步骤排查：

1. 确保 `lib/Settings/AdminSection.php` 正确实现了 `IIconSection` 接口
2. 确保 `lib/Settings/Admin.php` 正确实现了 `ISettings` 接口
3. 检查 `lib/AppInfo/Application.php` 中是否正确注册了设置部分和设置面板：
   ```php
   $context->registerSection('admin', \OCA\NextcloudDifyIntegration\Settings\AdminSection::class);
   $context->registerSettings('admin', \OCA\NextcloudDifyIntegration\Settings\Admin::class);
   ```
4. 确保 `AdminSection.php` 中的 `getID()` 方法和 `Admin.php` 中的 `getSection()` 方法返回相同的标识符
5. 确保 `appinfo/info.xml` 中没有与代码注册冲突的设置配置
6. 清除 Nextcloud 缓存并重新启用插件：
   ```bash
   sudo -u www-data php occ maintenance:mode --on
   sudo -u www-data php occ maintenance:mode --off
   ```

### 出现 "Call to undefined method" 错误

如果在 Nextcloud 日志中看到类似以下错误：
```
Error during app service registration: Call to undefined method OCP\AppFramework\Bootstrap\IRegistrationContext
```

这通常是因为使用了不正确的注册方法或 Nextcloud 版本不兼容。请确保：

1. `lib/AppInfo/Application.php` 中使用了与 Nextcloud 版本兼容的注册方法：
   - 对于 Nextcloud 27-30：
     ```php
     $context->registerSection('admin', \OCA\NextcloudDifyIntegration\Settings\AdminSection::class);
     $context->registerSettings('admin', \OCA\NextcloudDifyIntegration\Settings\Admin::class);
     ```
   - 对于 Nextcloud 31+：
     ```php
     $context->registerService(ISection::class, function() {
         return new \OCA\NextcloudDifyIntegration\Settings\AdminSection(
             \OC::$server->get(\OCP\IL10N::class),
             \OC::$server->get(\OCP\IURLGenerator::class)
         );
     });
     $context->registerService(ISettings::class, function() {
         return new \OCA\NextcloudDifyIntegration\Settings\Admin(
             \OC::$server->get(\OCA\NextcloudDifyIntegration\Service\ConfigService::class)
         );
     });
     ```
2. 检查 Nextcloud 版本是否与插件兼容
3. 清除缓存并重新启用插件

### 出现 Bootstrap 相关错误

如果在 Nextcloud 日志中看到类似以下错误：
```
/appinfo/app.php is not loaded when \OCP\AppFramework\Bootstrap\IBootstrap on the application class is used.
```

这通常是因为同时使用了 `app.php` 和 `IBootstrap` 接口。请确保：

1. 删除 `/appinfo/app.php` 文件
2. 确保 `Application` 类正确实现 `IBootstrap` 接口
3. 将 `app.php` 中的代码迁移到 `Application` 类的 `register()` 和 `boot()` 方法中
4. 清除缓存并重新启用插件

### 文件同步功能不工作

1. 检查 Dify API 配置是否正确
2. 确认目录映射关系配置正确
3. 检查 Nextcloud 日志中是否有相关错误信息

### 配置页面映射功能问题

如果在配置页面中点击"添加映射"按钮无法新增一行，请检查：

1. 确保 JavaScript 代码正确实现
2. 检查浏览器控制台是否有 JavaScript 错误
3. 确保所有按钮都有正确的事件处理程序
4. 清除浏览器缓存并刷新页面

### 配置页面保存功能问题

如果点击保存按钮时出现 404 错误，请检查：

1. 确保使用了正确的 OCS API 端点：`/ocs/v2.php/apps/nextcloud_dify_integration/api/v1/config`
2. 确保在 AJAX 请求中添加了 `'OCS-APIRequest': 'true'` 头部
3. 确保路由配置文件 `appinfo/routes.php` 中正确配置了 OCS 路由
4. 确保 `APIController` 类正确实现并注册
5. 清除 Nextcloud 缓存并重新启用插件

### 本地访问限制问题

如果配置 Dify URL 为本地 IP 地址时出现 "Host violates local access rules" 错误，请参考 `LOCAL_ACCESS_SOLUTION.md` 文件中的解决方案。

### 删除功能问题

如果在 Nextcloud 中删除文件后，Dify 知识库中对应的文档没有被删除，请参考 `DELETE_SOLUTION.md` 文件中的解决方案。可以使用以下方法进行调试：

1. 使用增强版测试脚本进行详细诊断：
   ```bash
   php test_delete_enhanced.php
   ```

2. 检查 Nextcloud 日志中的相关错误信息：
   ```bash
   sudo -u www-data php occ log:watch
   ```

### 文件监听器问题

如果怀疑文件删除事件没有被正确监控到，请参考 `FILE_LISTENER_SOLUTION.md` 文件中的诊断指南。可以使用以下方法进行验证：

1. 使用增强版文件监听器测试脚本：
   ```bash
   php test_file_listener_enhanced.php
   ```

2. 检查事件注册和处理逻辑是否正确

## 更新日志

查看 [CHANGELOG.md](CHANGELOG.md) 文件了解详细的更新日志。

### 最近更新 (2025-09-25)

- 修复了文件更新场景的问题
  - 解决了当文件被覆盖时，无法正确删除旧文档并上传新文档的问题
  - 改进了文件更新处理逻辑，先查找并删除旧文档，再上传新文档
  - 添加了不带时间戳的文件标识符匹配逻辑，确保能正确识别同一文件的不同版本
- 修复了文件删除功能无法正常工作的问题
- 修复了PHP 8.2+中的动态属性弃用警告
- 修复了创建和更新文档处的文档名称未变更的问题
- 修复了插件启动时服务未正确注册的问题
- 修复了文件标识符生成逻辑，使用文件路径+文件名作为Dify中的文档名
- 修复了插件重启后不能自动扫描目录文件的问题
  - 实现了 checkAllConfiguredDirectories 方法的实际功能
  - 实现了 checkDirectoryFiles 方法的实际功能
  - 添加了 getAllFilesFromFolder 方法用于递归获取文件夹中的所有文件
- 修复了 Dify 文档名称没有添加 Nextcloud 文件变更日期作为后缀的问题
  - 更新了 generateDifyFileIdentifier 方法，支持添加文件修改时间作为后缀（格式：yyyyMMdd-HHmmss）
  - 更新了 generatePathPrefixedFileName 方法，支持添加文件修改时间作为后缀（格式：yyyyMMdd-HHmmss）
  - 更新了 generateUniqueFileName 方法，支持添加文件修改时间作为后缀（格式：yyyyMMdd-HHmmss）
  - 更新了 DifyService 中的相关方法，支持传递文件修改时间参数
  - 更新了 FileSyncService 中的相关方法，传递文件修改时间参数
- 修复了路径匹配逻辑问题，确保目录映射关系正确匹配
  - 更新了 getMappingByPath 方法，改进路径匹配逻辑
  - 添加了路径长度排序，确保更具体的路径优先匹配
  - 修复了 /test 和 /test2 路径冲突的问题
- 修复了中文文件名处理问题，确保简体和繁体中文字符不会被替换为下划线
  - 更新了 generateDifyFileIdentifier 方法，支持中文字符
  - 修改了正则表达式，使其支持中文字符
- 添加了`listDocuments`方法来正确获取文档列表
- 增强了所有方法的错误处理和日志记录
- 添加了避免同名文件加入同一个知识库的功能
- 添加了使用文件路径作为唯一标识符的功能，解决多个目录映射到同一知识库时的同名文件问题
- 添加了服务故障处理机制
- 添加了现有文件处理功能
- 添加了文件同步优化功能
- 添加了文件访问功能（概念性实现）
- 添加了目录扫描功能

### 运行测试

```bash
composer install
composer test
```

### API 测试

可以使用以下脚本来测试 Dify API 连接：

```bash
# 测试文件上传功能
php test_dify_api.php

# 测试文件删除功能（基础版）
php test_delete_document.php

# 测试文件删除功能（增强版，详细调试信息）
php test_delete_enhanced.php

# 测试文件监听器功能（基础版）
php test_file_listener.php

# 测试文件监听器功能（增强版，详细检查）
php test_file_listener_enhanced.php

# 手动测试文件删除处理
php test_manual_delete.php <文件路径> [知识库ID]

# 测试文档列表API
php test_list_documents.php

# 测试同名文件处理功能
php test_duplicate_handling.php

# 测试文件标识符功能
php test_file_identifier.php

# 测试文件上传和更新功能
php test_upload_update.php

# 测试路径转换功能
php test_path_conversion.php

# 测试服务故障处理功能
php test_service_failure_handling.php

# 测试文件同步优化功能
php test_file_sync_optimization.php

# 测试文件访问功能概念
php test_file_access_concept.php

# 测试目录扫描功能
php test_directory_scan.php
```

### 代码检查

```bash
composer cs:check
composer cs:fix
```

## 许可证

AGPL-3.0-or-later

## 更新日志

查看 [CHANGELOG.md](CHANGELOG.md) 了解最新更新和修复。

### 文件变更
- 修改了以下文件：
  - `lib/Service/DifyService.php`
  - `lib/Service/FileSyncService.php`
  - `lib/Service/ConfigService.php`
  - `lib/AppInfo/Application.php`
  - `lib/Controller/APIController.php`
  - `appinfo/routes.php`
- 新增了文档文件：
  - `HANDLING_SERVICE_FAILURES_AND_EXISTING_FILES.md`
  - `PATH_CONVERSION_VALIDATION.md`
  - `FILE_SYNC_OPTIMIZATION.md`
  - `NEXTCLOUD_FILE_ACCESS_LIMITATIONS.md`
  - `CONFIGURATION_OPTIONS.md`
  - `IMPLEMENTING_FILE_ACCESS_IN_NEXTCLOUD.md`
  - `USING_DIRECTORY_SCAN_FEATURE.md`
  - `DIRECTORY_SCANNING_FEATURE.md`
  - `FILE_IDENTIFIER_WITH_DATE.md`
  - `GENERATE_PATH_PREFIXED_FILENAME.md`
  - `PATH_MATCHING_LOGIC.md`
  - `DIFY_INDEXING_STATUS_EXPLANATION.md`
  - `CHINESE_FILENAME_HANDLING.md`
- 新增了测试文件：
  - `test_list_documents.php`
  - `test_duplicate_handling.php`
  - `test_file_identifier.php`
  - `test_upload_update.php`
  - `test_path_conversion.php`
  - `test_service_failure_handling.php`
  - `test_file_sync_optimization.php`
  - `test_file_access_concept.php`
  - `test_directory_scan.php`
  - `test_service_registration.php`
  - `test_path_matching.php`
  - `test_chinese_filename_handling.php`
