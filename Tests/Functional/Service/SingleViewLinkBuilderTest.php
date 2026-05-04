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

    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private TestingFramework $testingFramework;

    private DummyConfiguration $configuration;

    private SingleViewLinkBuilder $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importDataSet(__DIR__ . '/Fixtures/SingleViewLinkBuilder.xml');

        $this->testingFramework = new TestingFramework();
        $this->testingFramework->createFakeFrontEnd(1);
        $this->configuration = new DummyConfiguration();

        $this->subject = new SingleViewLinkBuilder($this->configuration);
    }

    protected function tearDown(): void
    {
        MapperRegistry::purgeInstance();
        $this->testingFramework->cleanUpWithoutDatabase();

        parent::tearDown();
    }

    /**
     * @test
     */
    public function createRelativeUrlForEventWithoutAnyConfigurationReturnsEmptyString(): void
    {
        $event = MapperRegistry::get(EventMapper::class)->find(1);

        $result = $this->subject->createRelativeUrlForEvent($event);

        self::assertSame('', $result);
    }

    /**
     * @test
     */
    public function createRelativeUrlForEventWithConfiguredSingleViewPageReturnsLinkToItWithEventUid(): void
    {
        $this->configuration->setAsInteger('detailPID', self::DEFAULT_SINGLE_VIEW_PAGE_UID);
        $event = MapperRegistry::get(EventMapper::class)->find(1);

        $result = $this->subject->createRelativeUrlForEvent($event);

        self::assertStringContainsString('/defaultSingleView?tx_seminars_pi1%5BshowUid%5D=1', $result);
    }

    /**
     * @test
     */
    public function createRelativeUrlForEventWithExternalSingleViewPageReturnsLinkToExternalUrl(): void
    {
        $this->configuration->setAsInteger('detailPID', self::DEFAULT_SINGLE_VIEW_PAGE_UID);
        $event = MapperRegistry::get(EventMapper::class)->find(2);

        $result = $this->subject->createRelativeUrlForEvent($event);

        self::assertSame('http://www.example.com', $result);
    }

    /**
     * @test
     */
    public function createRelativeUrlWithEventTypeAndCategoryWithoutSingleViewPageUsesPageFromConfiguration(): void
    {
        $this->configuration->setAsInteger('detailPID', self::DEFAULT_SINGLE_VIEW_PAGE_UID);
        $event = MapperRegistry::get(EventMapper::class)->find(3);

        $result = $this->subject->createRelativeUrlForEvent($event);

        $expected = '/defaultSingleView?tx_seminars_pi1%5BshowUid%5D=3';
        self::assertStringContainsString($expected, $result);
    }

    /**
     * @test
     */
    public function createRelativeUrlWithEventTypeWithSingleViewPageUsesPageFromEventType(): void
    {
        $this->configuration->setAsInteger('detailPID', self::DEFAULT_SINGLE_VIEW_PAGE_UID);
        $event = MapperRegistry::get(EventMapper::class)->find(4);

        $result = $this->subject->createRelativeUrlForEvent($event);

        self::assertStringContainsString('/eventTypeSpecificSingleView?tx_seminars_pi1%5BshowUid%5D=4', $result);
    }

    /**
     * @test
     */
    public function createRelativeUrlWithCategoryWithSingleViewPageUsesPageFromCategory(): void
    {
        $this->configuration->setAsInteger('detailPID', self::DEFAULT_SINGLE_VIEW_PAGE_UID);
        $event = MapperRegistry::get(EventMapper::class)->find(5);

        $result = $this->subject->createRelativeUrlForEvent($event);

        self::assertStringContainsString('/categorySpecificSingleView?tx_seminars_pi1%5BshowUid%5D=5', $result);
    }

    /**
     * @test
     */
    public function createRelativeUrlWithCategoryAndEventTypeWithSingleViewPageUsesPageFromEventType(): void
    {
        $this->configuration->setAsInteger('detailPID', self::DEFAULT_SINGLE_VIEW_PAGE_UID);
        $event = MapperRegistry::get(EventMapper::class)->find(6);

        $result = $this->subject->createRelativeUrlForEvent($event);

        self::assertStringContainsString('/eventTypeSpecificSingleView?tx_seminars_pi1%5BshowUid%5D=6', $result);
    }

    /**
     * @test
     */
    public function createRelativeUrlWithIndividualUrlAndEventTypeWithSingleViewPageUsesIndividualUrl(): void
    {
        $this->configuration->setAsInteger('detailPID', self::DEFAULT_SINGLE_VIEW_PAGE_UID);
        $event = MapperRegistry::get(EventMapper::class)->find(7);

        $result = $this->subject->createRelativeUrlForEvent($event);

        self::assertSame('http://www.example.com', $result);
    }

    /**
     * @test
     */
    public function createAbsoluteUrlForEventWithoutAnyConfigurationReturnsBaseUrl(): void
    {
        $event = MapperRegistry::get(EventMapper::class)->find(1);

        $result = $this->subject->createAbsoluteUrlForEvent($event);

        self::assertSame('http://typo3-test.dev/', $result);
    }

    /**
     * @test
     */
    public function createAbsoluteUrlWithConfiguredSingleViewPageReturnsLinkToItWithEventUid(): void
    {
        $this->configuration->setAsInteger('detailPID', self::DEFAULT_SINGLE_VIEW_PAGE_UID);
        $event = MapperRegistry::get(EventMapper::class)->find(1);

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
        $event = MapperRegistry::get(EventMapper::class)->find(2);

        $result = $this->subject->createAbsoluteUrlForEvent($event);

        self::assertSame('http://www.example.com', $result);
    }

    /**
     * @test
     */
    public function createAbsoluteUrlWithEventTypeAndCategoryWithoutSingleViewPageUsesPageFromConfiguration(): void
    {
        $this->configuration->setAsInteger('detailPID', self::DEFAULT_SINGLE_VIEW_PAGE_UID);
        $event = MapperRegistry::get(EventMapper::class)->find(3);

        $result = $this->subject->createAbsoluteUrlForEvent($event);

        $expected = 'http://typo3-test.dev/defaultSingleView?tx_seminars_pi1%5BshowUid%5D=3';
        self::assertStringContainsString($expected, $result);
    }

    /**
     * @test
     */
    public function createAbsoluteUrlWithEventTypeWithSingleViewPageUsesPageFromEventType(): void
    {
        $this->configuration->setAsInteger('detailPID', self::DEFAULT_SINGLE_VIEW_PAGE_UID);
        $event = MapperRegistry::get(EventMapper::class)->find(4);

        $result = $this->subject->createAbsoluteUrlForEvent($event);

        $expected = 'http://typo3-test.dev/eventTypeSpecificSingleView?tx_seminars_pi1%5BshowUid%5D=4';
        self::assertStringContainsString($expected, $result);
    }

    /**
     * @test
     */
    public function createAbsoluteUrlWithCategoryWithSingleViewPageUsesPageFromCategory(): void
    {
        $this->configuration->setAsInteger('detailPID', self::DEFAULT_SINGLE_VIEW_PAGE_UID);
        $event = MapperRegistry::get(EventMapper::class)->find(5);

        $result = $this->subject->createAbsoluteUrlForEvent($event);

        $expected = 'http://typo3-test.dev/categorySpecificSingleView?tx_seminars_pi1%5BshowUid%5D=5';
        self::assertStringContainsString($expected, $result);
    }

    /**
     * @test
     */
    public function createAbsoluteUrlWithCategoryAndEventTypeWithSingleViewPageUsesPageFromEventType(): void
    {
        $this->configuration->setAsInteger('detailPID', self::DEFAULT_SINGLE_VIEW_PAGE_UID);
        $event = MapperRegistry::get(EventMapper::class)->find(6);

        $result = $this->subject->createAbsoluteUrlForEvent($event);

        $expected = 'http://typo3-test.dev/eventTypeSpecificSingleView?tx_seminars_pi1%5BshowUid%5D=6';
        self::assertStringContainsString($expected, $result);
    }

    /**
     * @test
     */
    public function createAbsoluteUrlWithIndividualUrlAndEventTypeWithSingleViewPageUsesIndividualUrl(): void
    {
        $this->configuration->setAsInteger('detailPID', self::DEFAULT_SINGLE_VIEW_PAGE_UID);
        $event = MapperRegistry::get(EventMapper::class)->find(7);

        $result = $this->subject->createAbsoluteUrlForEvent($event);

        self::assertSame('http://www.example.com', $result);
    }
}
