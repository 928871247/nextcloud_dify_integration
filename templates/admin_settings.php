<div id="nextcloud-dify-settings" class="section">
    <h2>Dify 知识库集成</h2>
    
    <form id="dify-settings-form">
        <div class="form-group">
            <label for="dify-url">Dify 地址</label>
            <input type="text" id="dify-url" name="dify_url" value="<?php p($_['difyUrl']); ?>" placeholder="例如: https://dify.example.com">
        </div>
        
        <div class="form-group">
            <label for="dify-api-key">Dify API Key</label>
            <input type="password" id="dify-api-key" name="dify_api_key" value="<?php p($_['difyApiKey']); ?>" placeholder="请输入 Dify API Key">
        </div>
        
        <div class="form-group">
            <label for="naming-pattern">文档命名模式</label>
            <select id="naming-pattern" name="naming_pattern">
                <option value="pattern1" <?php if ($_['namingPattern'] === 'pattern1') echo 'selected'; ?>>📄file 📁directory 📅modifiedDate modifiedTime.md (推荐)</option>
                <option value="pattern2" <?php if ($_['namingPattern'] === 'pattern2') echo 'selected'; ?>>📁directory 📄file 📅modifiedDate modifiedTime.md</option>
                <option value="pattern3" <?php if ($_['namingPattern'] === 'pattern3') echo 'selected'; ?>>file (directory) modifiedDate modifiedTime.md</option>
                <option value="pattern4" <?php if ($_['namingPattern'] === 'pattern4') echo 'selected'; ?>> (directory) file modifiedDate modifiedTime.md</option>
            </select>
            <p>选择Dify中文档的命名方式</p>
        </div>
        
        <h3>目录映射关系</h3>
        <p>请输入相对于 Nextcloud 根目录的路径，例如：/test 表示根目录下的 test 文件夹</p>
        <div id="directory-mappings">
            <?php if (!empty($_['directoryMappings'])): ?>
                <?php foreach ($_['directoryMappings'] as $index => $mapping): ?>
                    <div class="mapping-row">
                        <input type="text" name="mappings[<?php p($index); ?>][nextcloud_path]" 
                               value="<?php p($mapping['nextcloud_path']); ?>" 
                               placeholder="Nextcloud 目录路径" class="nextcloud-path">
                        <input type="text" name="mappings[<?php p($index); ?>][dify_kb_id]" 
                               value="<?php p($mapping['dify_kb_id']); ?>" 
                               placeholder="Dify 知识库 ID" class="dify-kb-id">
                        <button type="button" class="remove-mapping">删除</button>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="mapping-row">
                    <input type="text" name="mappings[0][nextcloud_path]" placeholder="Nextcloud 目录路径" class="nextcloud-path">
                    <input type="text" name="mappings[0][dify_kb_id]" placeholder="Dify 知识库 ID" class="dify-kb-id">
                    <button type="button" class="remove-mapping">删除</button>
                </div>
            <?php endif; ?>
        </div>
        
        <button type="button" id="add-mapping">添加映射</button>
        <button type="submit">保存</button>
    </form>
</div>

<style>
    #nextcloud-dify-settings .form-group {
        margin-bottom: 15px;
    }
    
    #nextcloud-dify-settings label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
    }
    
    #nextcloud-dify-settings input {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    #nextcloud-dify-settings .mapping-row {
        display: flex;
        gap: 10px;
        margin-bottom: 10px;
        align-items: center;
    }
    
    #nextcloud-dify-settings .nextcloud-path,
    #nextcloud-dify-settings .dify-kb-id {
        flex: 1;
    }
    
    #nextcloud-dify-settings .remove-mapping {
        background-color: #e74c3c;
        color: white;
        border: none;
        padding: 8px 12px;
        border-radius: 4px;
        cursor: pointer;
    }
    
    #nextcloud-dify-settings #add-mapping,
    #nextcloud-dify-settings button[type="submit"] {
        background-color: #3498db;
        color: white;
        border: none;
        padding: 10px 15px;
        border-radius: 4px;
        cursor: pointer;
        margin-right: 10px;
    }
</style>

<?php script('nextcloud_dify_integration', 'admin_settings'); ?>
