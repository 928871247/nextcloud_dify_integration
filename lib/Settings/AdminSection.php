<?php

namespace OCA\NextcloudDifyIntegration\Settings;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class AdminSection implements IIconSection {
    
    private $l;
    private $urlGenerator;
    
    public function __construct(IL10N $l, IURLGenerator $urlGenerator) {
        $this->l = $l;
        $this->urlGenerator = $urlGenerator;
    }
    
    /**
     * 返回设置部分的唯一标识符
     */
    public function getID(): string {
        return 'nextcloud_dify_integration';
    }
    
    /**
     * 返回设置部分的名称
     */
    public function getName(): string {
        return $this->l->t('Knowledge Base');
    }
    
    /**
     * 返回设置部分的优先级
     */
    public function getPriority(): int {
        return 75;
    }
    
    /**
     * 返回设置部分的图标 URL
     */
    public function getIcon(): string {
        return $this->urlGenerator->imagePath('core', 'actions/settings-dark.svg');
    }
}
