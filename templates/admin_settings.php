<div id="nextcloud-dify-settings" class="section">
    <h2><?php p($l->t('Dify Knowledge Base Integration')); ?></h2>
    
    <form id="dify-settings-form">
        <div class="form-group">
            <label for="dify-url"><?php p($l->t('Dify URL')); ?></label>
            <input type="text" id="dify-url" name="dify_url" value="<?php p($_['difyUrl']); ?>" placeholder="<?php p($l->t('e.g.: https://dify.example.com/v1')); ?>">
        </div>
        
        <div class="form-group">
            <label for="dify-api-key"><?php p($l->t('Dify API Key')); ?></label>
            <input type="password" id="dify-api-key" name="dify_api_key" value="<?php p($_['difyApiKey']); ?>" placeholder="<?php p($l->t('Please enter Dify API Key')); ?>">
        </div>
        
        <div class="form-group">
            <label for="naming-pattern"><?php p($l->t('Document Naming Pattern')); ?></label>
            <select id="naming-pattern" name="naming_pattern">
                <option value="pattern1" <?php if ($_['namingPattern'] === 'pattern1') echo 'selected'; ?>><?php p($l->t('📄file 📁directory 📅modifiedDate modifiedTime.md (Recommended)')); ?></option>
                <option value="pattern2" <?php if ($_['namingPattern'] === 'pattern2') echo 'selected'; ?>><?php p($l->t('📁directory 📄file 📅modifiedDate modifiedTime.md')); ?></option>
                <option value="pattern3" <?php if ($_['namingPattern'] === 'pattern3') echo 'selected'; ?>><?php p($l->t('file (directory) modifiedDate modifiedTime.md')); ?></option>
                <option value="pattern4" <?php if ($_['namingPattern'] === 'pattern4') echo 'selected'; ?>><?php p($l->t(' (directory) file modifiedDate modifiedTime.md')); ?></option>
            </select>
            <p><?php p($l->t('Select the naming pattern for documents in Dify')); ?></p>
        </div>
        
        <h3><?php p($l->t('Directory Mapping')); ?></h3>
        <p><?php p($l->t('Please enter the path relative to the Nextcloud root directory, e.g.: /test represents the test folder under the root directory')); ?></p>
        <div id="directory-mappings">
            <?php if (!empty($_['directoryMappings'])): ?>
                <?php foreach ($_['directoryMappings'] as $index => $mapping): ?>
                    <div class="mapping-row">
                        <input type="text" name="mappings[<?php p($index); ?>][nextcloud_path]" 
                               value="<?php p($mapping['nextcloud_path']); ?>" 
                               placeholder="<?php p($l->t('Nextcloud Directory Path')); ?>" class="nextcloud-path">
                        <input type="text" name="mappings[<?php p($index); ?>][dify_kb_id]" 
                               value="<?php p($mapping['dify_kb_id']); ?>" 
                               placeholder="<?php p($l->t('Dify Knowledge Base ID')); ?>" class="dify-kb-id">
                        <button type="button" class="remove-mapping"><?php p($l->t('Delete')); ?></button>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="mapping-row">
                    <input type="text" name="mappings[0][nextcloud_path]" placeholder="<?php p($l->t('Nextcloud Directory Path')); ?>" class="nextcloud-path">
                    <input type="text" name="mappings[0][dify_kb_id]" placeholder="<?php p($l->t('Dify Knowledge Base ID')); ?>" class="dify-kb-id">
                    <button type="button" class="remove-mapping"><?php p($l->t('Delete')); ?></button>
                </div>
            <?php endif; ?>
        </div>
        
        <button type="button" id="add-mapping"><?php p($l->t('Add Mapping')); ?></button>
        <button type="submit"><?php p($l->t('Save')); ?></button>
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
