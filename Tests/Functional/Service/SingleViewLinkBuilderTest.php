<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\Service;

use OliverKlee\Oelib\Configuration\DummyConfiguration;
use OliverKlee\Oelib\Mapper\MapperRegistry;
use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\Mapper\EventMapper;
use OliverKlee\Seminars\Service\SingleViewLinkBuilder;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\Service\SingleViewLinkBuilder
 */
final class SingleViewLinkBuilderTest extends FunctionalTestCase
{
    private const DEFAULT_SINGLE_VIEW_PAGE_UID = 3;

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

    private TestingFramework $testingFramework;

    private DummyConfiguration $configuration;

    private EventMapper $eventMapper;

    private SingleViewLinkBuilder $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/SingleViewLinkBuilder.csv');

        $this->testingFramework = $this->get(TestingFramework::class);
        $this->testingFramework->createFakeFrontEnd(1);
        $this->configuration = new DummyConfiguration();

        $this->eventMapper = $this->get(MapperRegistry::class)->getByClassName(EventMapper::class);

        $this->subject = new SingleViewLinkBuilder($this->configuration);
    }

    protected function tearDown(): void
    {
        $this->testingFramework->cleanUpWithoutDatabase();

        parent::tearDown();
    }

    /**
     * @test
     */
    public function createRelativeUrlForEventWithoutAnyConfigurationReturnsEmptyString(): void
    {
        $event = $this->eventMapper->find(1);

        $result = $this->subject->createRelativeUrlForEvent($event);

        self::assertSame('', $result);
    }

    /**
     * @test
     */
    public function createRelativeUrlForEventWithConfiguredSingleViewPageReturnsLinkToItWithEventUid(): void
    {
        $this->configuration->setAsInteger('detailPID', self::DEFAULT_SINGLE_VIEW_PAGE_UID);
        $event = $this->eventMapper->find(1);

        $result = $this->subject->createRelativeUrlForEvent($event);

        self::assertStringContainsString('/defaultSingleView?tx_seminars_pi1%5BshowUid%5D=1', $result);
    }

    /**
     * @test
     */
    public function createRelativeUrlForEventWithExternalSingleViewPageReturnsLinkToExternalUrl(): void
    {
        $this->configuration->setAsInteger('detailPID', self::DEFAULT_SINGLE_VIEW_PAGE_UID);
        $event = $this->eventMapper->find(2);

        $result = $this->subject->createRelativeUrlForEvent($event);

        self::assertSame('http://www.example.com', $result);
    }

    /**
     * @test
     */
    public function createRelativeUrlWithIndividualUrlUsesIndividualUrl(): void
    {
        $this->configuration->setAsInteger('detailPID', self::DEFAULT_SINGLE_VIEW_PAGE_UID);
        $event = $this->eventMapper->find(2);

        $result = $this->subject->createRelativeUrlForEvent($event);

        self::assertSame('http://www.example.com', $result);
    }

    /**
     * @test
     */
    public function createAbsoluteUrlForEventWithoutAnyConfigurationReturnsBaseUrl(): void
    {
        $event = $this->eventMapper->find(1);

        $result = $this->subject->createAbsoluteUrlForEvent($event);

        self::assertSame('http://typo3-test.dev/', $result);
    }

    /**
     * @test
     */
    public function createAbsoluteUrlWithConfiguredSingleViewPageReturnsLinkToItWithEventUid(): void
    {
        $this->configuration->setAsInteger('detailPID', self::DEFAULT_SINGLE_VIEW_PAGE_UID);
        $event = $this->eventMapper->find(1);

        $result = $this->subject->createAbsoluteUrlForEvent($event);

        $expected = 'http://typo3-test.dev/defaultSingleView?tx_seminars_pi1%5BshowUid%5D=1';
        self::assertStringContainsString($expected, $result);
    }

    /**
     * @test
     */
    public function createAbsoluteUrlWithExternalSingleViewPageReturnsLinkToExternalUrl(): void
    {
        $this->configuration->setAsInteger('detailPID', self::DEFAULT_SINGLE_VIEW_PAGE_UID);
        $event = $this->eventMapper->find(2);

        $result = $this->subject->createAbsoluteUrlForEvent($event);

        self::assertSame('http://www.example.com', $result);
    }
}
