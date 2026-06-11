<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\Seo\Event;

use OliverKlee\Seminars\Seo\Event\AfterSlugGeneratedEvent;
use OliverKlee\Seminars\Seo\SlugContext;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\EventDispatcher\ListenerProvider;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Seo\Event\AfterSlugGeneratedEvent
 */
final class AfterSlugGeneratedEventTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'typo3/cms-extensionmanager',
        'typo3/cms-install',
    ];

    protected array $testExtensionsToLoad = [
        'sjbr/static-info-tables',
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    protected bool $initializeDatabase = false;

    private ListenerProvider $listenerProvider;

    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->listenerProvider = $this->get(ListenerProvider::class);
        $this->eventDispatcher = $this->get(EventDispatcherInterface::class);
    }

    /**
     * @test
     */
    public function canBeDispatched(): void
    {
        $listenedEvent = null;
        $container = $this->get('service_container');
        self::assertInstanceOf(Container::class, $container);

        $listener = static function (AfterSlugGeneratedEvent $event) use (&$listenedEvent): void {
            $listenedEvent = $event;
        };
        $alias = 'slug-changed-listener';
        $container->set($alias, $listener);
        $this->listenerProvider->addListener(AfterSlugGeneratedEvent::class, $alias);

        $eventToDispatch = new AfterSlugGeneratedEvent(new SlugContext(1, '', ''), '');
        $this->eventDispatcher->dispatch($eventToDispatch);

        self::assertSame($eventToDispatch, $listenedEvent);
    }
}
