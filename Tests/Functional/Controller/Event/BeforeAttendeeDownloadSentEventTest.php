<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\Controller\Event;

use OliverKlee\Seminars\Controller\Event\BeforeAttendeeDownloadSentEvent;
use OliverKlee\Seminars\Domain\Model\Registration\Registration;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\EventDispatcher\ListenerProvider;
use TYPO3\CMS\Core\Resource\ResourceInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Controller\Event\BeforeAttendeeDownloadSentEvent
 */
final class BeforeAttendeeDownloadSentEventTest extends FunctionalTestCase
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

        $listener = static function (BeforeAttendeeDownloadSentEvent $event) use (&$listenedEvent): void {
            $listenedEvent = $event;
        };
        $alias = 'before-attendee-download-listener';
        $container->set($alias, $listener);
        $this->listenerProvider->addListener(BeforeAttendeeDownloadSentEvent::class, $alias);

        $eventToDispatch = new BeforeAttendeeDownloadSentEvent(
            new Registration(),
            $this->createStub(ResourceInterface::class),
            $this->createStub(StreamInterface::class),
        );
        $this->eventDispatcher->dispatch($eventToDispatch);

        self::assertSame($eventToDispatch, $listenedEvent);
    }
}
