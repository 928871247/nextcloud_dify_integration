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

## 许可证

AGPL-3.0-or-later
