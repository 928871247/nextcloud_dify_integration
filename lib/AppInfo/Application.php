<?php

namespace OCA\NextcloudDifyIntegration\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\Settings\ISection;
use OCP\Settings\ISettings;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCA\NextcloudDifyIntegration\Listener\FileChangeListener;
use OCP\IConfig;
use OCA\NextcloudDifyIntegration\Service\ConfigService;
use OCA\NextcloudDifyIntegration\Controller\APIController;
use Psr\Log\LoggerInterface;

class Application extends App implements IBootstrap {
    
    public const APP_ID = 'nextcloud_dify_integration';
    
    public function __construct() {
        parent::__construct(self::APP_ID);
    }
    
    public function register(IRegistrationContext $context): void {
        // 注册 ConfigService
        $context->registerService(ConfigService::class, function ($c) {
            return new ConfigService(
                $c->query(IConfig::class),
                $c->query(\OCP\Files\IRootFolder::class),
                $c->query(\OCP\IUserSession::class),
                $c->query(LoggerInterface::class)
            );
        });
        
        // 注册 FileSyncService
        $context->registerService(\OCA\NextcloudDifyIntegration\Service\FileSyncService::class, function ($c) {
            return new \OCA\NextcloudDifyIntegration\Service\FileSyncService(
                $c->query(ConfigService::class),
                $c->query(\OCA\NextcloudDifyIntegration\Service\DifyService::class),
                $c->query(LoggerInterface::class)
            );
        });
        
        // 注册 DifyService
        $context->registerService(\OCA\NextcloudDifyIntegration\Service\DifyService::class, function ($c) {
            return new \OCA\NextcloudDifyIntegration\Service\DifyService(
                $c->query(\OCP\Http\Client\IClientService::class),
                $c->query(ConfigService::class),
                $c->query(LoggerInterface::class)
            );
        });
        
        // 注册设置部分
        $context->registerService(ISection::class, function($c) {
            return new \OCA\NextcloudDifyIntegration\Settings\AdminSection(
                $c->query(\OCP\IL10N::class),
                $c->query(\OCP\IURLGenerator::class)
            );
        });
        
        // 注册设置面板
        $context->registerService(ISettings::class, function($c) {
            return new \OCA\NextcloudDifyIntegration\Settings\Admin(
                $c->query(ConfigService::class)
            );
        });
        
        // 注册文件变化监听器
        $context->registerEventListener(NodeCreatedEvent::class, FileChangeListener::class);
        $context->registerEventListener(NodeDeletedEvent::class, FileChangeListener::class);
        $context->registerEventListener(NodeWrittenEvent::class, FileChangeListener::class);
        
        // 注册 APIController 服务
        $context->registerService(APIController::class, function($c) {
            return new APIController(
                self::APP_ID,
                $c->query(\OCP\IRequest::class),
                $c->query(ConfigService::class),
                $c->query(\OCP\IConfig::class),
                $c->query(LoggerInterface::class)
            );
        });
    }
    
    public function boot(IBootContext $context): void {
        // 启动时执行的代码
        // 检查所有配置目录中的文件
        try {
            $logger = $this->getContainer()->get(\Psr\Log\LoggerInterface::class);
            $logger->info('NextcloudDifyIntegration: 开始启动时目录检查', ['app' => 'nextcloud_dify_integration']);
            
            $fileSyncService = $this->getContainer()->get(\OCA\NextcloudDifyIntegration\Service\FileSyncService::class);
            $fileSyncService->checkAllConfiguredDirectories();
            
            $logger->info('NextcloudDifyIntegration: 启动时目录检查完成', ['app' => 'nextcloud_dify_integration']);
        } catch (\Exception $e) {
            // 记录错误但不中断启动过程
            $errorMessage = 'NextcloudDifyIntegration: 启动时检查目录失败 - ' . $e->getMessage();
            error_log($errorMessage);
            
            // 尝试获取日志服务记录错误
            try {
                $logger = $this->getContainer()->get(\Psr\Log\LoggerInterface::class);
                $logger->error($errorMessage, ['app' => 'nextcloud_dify_integration']);
                $logger->error('NextcloudDifyIntegration: 错误堆栈: ' . $e->getTraceAsString(), ['app' => 'nextcloud_dify_integration']);
            } catch (\Exception $loggerException) {
                // 如果无法获取日志服务，只记录到系统错误日志
                error_log('NextcloudDifyIntegration: 无法记录详细错误信息 - ' . $loggerException->getMessage());
            }
        }
    }
}
