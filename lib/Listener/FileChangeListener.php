<?php

namespace OCA\NextcloudDifyIntegration\Listener;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCA\NextcloudDifyIntegration\Service\FileSyncService;

class FileChangeListener implements IEventListener {
    
    private $fileSyncService;
    
    public function __construct(FileSyncService $fileSyncService) {
        $this->fileSyncService = $fileSyncService;
    }
    
    public function handle(Event $event): void {
        if ($event instanceof NodeCreatedEvent) {
            // 处理文件创建事件
            $this->fileSyncService->handleFileCreate($event->getNode());
        } elseif ($event instanceof NodeDeletedEvent) {
            // 处理文件删除事件
            $this->fileSyncService->handleFileDelete($event->getNode());
        } elseif ($event instanceof NodeWrittenEvent) {
            // 处理文件更新事件
            $this->fileSyncService->handleFileUpdate($event->getNode());
        }
    }
}
