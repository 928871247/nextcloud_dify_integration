(function() {
    document.addEventListener('DOMContentLoaded', function() {
        const mappingsContainer = document.getElementById('directory-mappings');
        const addMappingButton = document.getElementById('add-mapping');
        const form = document.getElementById('dify-settings-form');
        const difyUrlInput = document.getElementById('dify-url');
        const difyApiKeyInput = document.getElementById('dify-api-key');
        
        // 获取下一个可用的索引
        function getNextIndex() {
            const rows = document.querySelectorAll('.mapping-row');
            return rows.length;
        }
        
        // 添加新的映射行
        addMappingButton.addEventListener('click', function() {
            const nextIndex = getNextIndex();
            const newRow = document.createElement('div');
            newRow.className = 'mapping-row';
            newRow.innerHTML = `
                <input type="text" name="mappings[${nextIndex}][nextcloud_path]" placeholder="Nextcloud 目录路径" class="nextcloud-path">
                <input type="text" name="mappings[${nextIndex}][dify_kb_id]" placeholder="Dify 知识库 ID" class="dify-kb-id">
                <button type="button" class="remove-mapping">删除</button>
            `;
            mappingsContainer.appendChild(newRow);
            
            // 为新添加的删除按钮绑定事件
            const removeButton = newRow.querySelector('.remove-mapping');
            removeButton.addEventListener('click', function() {
                mappingsContainer.removeChild(newRow);
                renumberRows();
            });
        });
        
        // 为现有的删除按钮绑定事件
        document.querySelectorAll('.remove-mapping').forEach(button => {
            button.addEventListener('click', function() {
                const row = this.closest('.mapping-row');
                mappingsContainer.removeChild(row);
                renumberRows();
            });
        });
        
        // 重新编号所有行
        function renumberRows() {
            const rows = document.querySelectorAll('.mapping-row');
            rows.forEach((row, index) => {
                const nextcloudPathInput = row.querySelector('.nextcloud-path');
                const difyKbIdInput = row.querySelector('.dify-kb-id');
                
                // 更新 name 属性
                nextcloudPathInput.name = `mappings[${index}][nextcloud_path]`;
                difyKbIdInput.name = `mappings[${index}][dify_kb_id]`;
            });
        }
        
        // 页面加载时获取配置
        function loadConfig() {
            fetch(OC.generateUrl('/ocs/v2.php/apps/nextcloud_dify_integration/api/v1/config'), {
                method: 'GET',
                headers: {
                    'OCS-APIRequest': 'true',
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.ocs && data.ocs.data && data.ocs.data.status === 'success') {
                    const configData = data.ocs.data.data;
                    
                    // 填充基本配置
                    if (difyUrlInput && configData.dify_url) {
                        difyUrlInput.value = configData.dify_url;
                    }
                    
                    if (difyApiKeyInput && configData.dify_api_key) {
                        difyApiKeyInput.value = configData.dify_api_key;
                    }
                    
                    // 填充命名模式
                    const namingPatternSelect = document.getElementById('naming-pattern');
                    if (namingPatternSelect && configData.naming_pattern) {
                        namingPatternSelect.value = configData.naming_pattern;
                    }
                    
                    // 清空现有的映射行
                    mappingsContainer.innerHTML = '';
                    
                    // 填充目录映射关系
                    if (configData.directory_mappings && Array.isArray(configData.directory_mappings) && configData.directory_mappings.length > 0) {
                        configData.directory_mappings.forEach((mapping, index) => {
                            const newRow = document.createElement('div');
                            newRow.className = 'mapping-row';
                            newRow.innerHTML = `
                                <input type="text" name="mappings[${index}][nextcloud_path]" 
                                       value="${mapping.nextcloud_path || ''}" 
                                       placeholder="Nextcloud 目录路径" class="nextcloud-path">
                                <input type="text" name="mappings[${index}][dify_kb_id]" 
                                       value="${mapping.dify_kb_id || ''}" 
                                       placeholder="Dify 知识库 ID" class="dify-kb-id">
                                <button type="button" class="remove-mapping">删除</button>
                            `;
                            mappingsContainer.appendChild(newRow);
                            
                            // 为新添加的删除按钮绑定事件
                            const removeButton = newRow.querySelector('.remove-mapping');
                            removeButton.addEventListener('click', function() {
                                mappingsContainer.removeChild(newRow);
                                renumberRows();
                            });
                        });
                    } else {
                        // 如果没有映射关系，添加一个空行
                        const newRow = document.createElement('div');
                        newRow.className = 'mapping-row';
                        newRow.innerHTML = `
                            <input type="text" name="mappings[0][nextcloud_path]" placeholder="Nextcloud 目录路径" class="nextcloud-path">
                            <input type="text" name="mappings[0][dify_kb_id]" placeholder="Dify 知识库 ID" class="dify-kb-id">
                            <button type="button" class="remove-mapping">删除</button>
                        `;
                        mappingsContainer.appendChild(newRow);
                        
                        // 为新添加的删除按钮绑定事件
                        const removeButton = newRow.querySelector('.remove-mapping');
                        removeButton.addEventListener('click', function() {
                            mappingsContainer.removeChild(newRow);
                            renumberRows();
                        });
                    }
                }
            })
            .catch(error => {
                console.error('加载配置失败:', error);
            });
        }
        
        // 页面加载时自动获取配置
        loadConfig();
        
        // 表单提交处理
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // 收集表单数据
            const formData = new FormData(this);
            
            // 提取基本配置
            const difyUrl = formData.get('dify_url') || '';
            const difyApiKey = formData.get('dify_api_key') || '';
            const namingPattern = formData.get('naming_pattern') || 'improved';
            
            // 收集映射关系数据
            const mappings = [];
            const mappingRows = document.querySelectorAll('.mapping-row');
            
            mappingRows.forEach(row => {
                const nextcloudPath = row.querySelector('.nextcloud-path').value;
                const difyKbId = row.querySelector('.dify-kb-id').value;
                
                // 只有当两个字段都不为空时才添加映射关系
                if (nextcloudPath && difyKbId) {
                    mappings.push({
                        nextcloud_path: nextcloudPath,
                        dify_kb_id: difyKbId
                    });
                }
            });
            
            // 构建请求体
            const requestBody = new URLSearchParams();
            requestBody.append('dify_url', difyUrl);
            requestBody.append('dify_api_key', difyApiKey);
            requestBody.append('naming_pattern', namingPattern);
            requestBody.append('mappings', JSON.stringify(mappings));
            
            // 发送 AJAX 请求保存配置
            // 使用正确的 OCS API 端点
            fetch(OC.generateUrl('/ocs/v2.php/apps/nextcloud_dify_integration/api/v1/config'), {
                method: 'POST',
                body: requestBody,
                headers: {
                    'OCS-APIRequest': 'true',
                    'Content-Type': 'application/x-www-form-urlencoded'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.ocs && data.ocs.data && data.ocs.data.status === 'success') {
                    OC.Notification.showTemporary('配置已保存');
                } else {
                    let message = '保存失败';
                    if (data.ocs && data.ocs.data && data.ocs.data.message) {
                        message = data.ocs.data.message;
                    }
                    OC.Notification.showTemporary(message);
                }
            })
            .catch(error => {
                OC.Notification.showTemporary('保存失败: ' + error.message);
            });
        });
    });
})();
