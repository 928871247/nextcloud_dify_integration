const fs = require('fs');
const path = require('path');

// 检查翻译文件
function checkTranslationFiles() {
    console.log('Checking Nextcloud Dify Integration translation files...');
    
    const basePath = path.join(__dirname, '..');
    const files = [
        'l10n/en.js',
        'l10n/en.json',
        'l10n/zh_CN.js',
        'l10n/zh_CN.json'
    ];
    
    files.forEach(file => {
        const fullPath = path.join(basePath, file);
        if (fs.existsSync(fullPath)) {
            const stats = fs.statSync(fullPath);
            console.log('✓ ' + file + ' exists');
            console.log('  Size: ' + stats.size + ' bytes');
            
            // 读取文件内容
            const content = fs.readFileSync(fullPath, 'utf8');
            console.log('  First 50 chars: ' + content.substring(0, 50) + '...');
        } else {
            console.log('✗ ' + file + ' not found');
        }
        console.log('');
    });
}

// 运行检查
checkTranslationFiles();
