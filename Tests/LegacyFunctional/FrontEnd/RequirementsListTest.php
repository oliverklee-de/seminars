<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyFunctional\FrontEnd;

use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\Configuration\DummyConfiguration;
use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\Domain\Model\Event\EventInterface;
use OliverKlee\Seminars\FrontEnd\RequirementsList;
use OliverKlee\Seminars\OldModel\LegacyEvent;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\FrontEnd\AbstractView
 * @covers \OliverKlee\Seminars\FrontEnd\RequirementsList
 * @covers \OliverKlee\Seminars\Templating\TemplateHelper
 */
final class RequirementsListTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'oliverklee/feuserextrafields',
        'oliverklee/oelib',
        'oliverklee/seminars',
    ];

    private RequirementsList $subject;

    /**
     * @var positive-int the UID of a seminar to which the plugin relates
     */
    private int $seminarUid;

    private TestingFramework $testingFramework;

    private DummyConfiguration $pluginConfiguration;

    private int $rootPageUid;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testingFramework = new TestingFramework();
        $this->rootPageUid = $this->testingFramework->createFrontEndPage();
        $this->testingFramework->changeRecord('pages', $this->rootPageUid, ['slug' => '/home']);
        $this->testingFramework->createFakeFrontEnd($this->rootPageUid);

        $systemFolderPid = $this->testingFramework->createSystemFolder();

        $this->seminarUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $systemFolderPid,
                'title' => 'Test event',
            ],
        );

        $this->pluginConfiguration = new DummyConfiguration();
        ConfigurationRegistry::getInstance()->set('plugin.tx_seminars_pi1', $this->pluginConfiguration);

        $this->subject = new RequirementsList(
            ['templateFile' => 'EXT:seminars/Resources/Private/Templates/FrontEnd/FrontEnd.html'],
            $this->getFrontEndController()->cObj,
        );
    }

    protected function tearDown(): void
    {
        $this->testingFramework->cleanUpWithoutDatabase();

        ConfigurationRegistry::purgeInstance();

        parent::tearDown();
    }

    private function getFrontEndController(): TypoScriptFrontendController
    {
        return $GLOBALS['TSFE'];
    }

    // Tests for the render function

    /**
     * @test
     */
    public function renderWithoutSetSeminarThrowsException(): void
    {
        $this->expectException(
            \BadMethodCallException::class,
        );
        $this->expectExceptionMessage(
            'No event was set, please set an event before calling render',
        );

        $this->subject->render();
    }

    /**
     * @test
     */
    public function renderShowsHtmlspecialcharedTitleOfOneRequirement(): void
    {
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC],
        );
        $requiredEvent = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'title' => 'required & foo',
            ],
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $this->seminarUid,
            $requiredEvent,
            'requirements',
        );
        $this->subject->setEvent(new LegacyEvent($this->seminarUid));

        self::assertStringContainsString(
            'required &amp; foo',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderLinksOneRequirementToTheSingleView(): void
    {
        $detailPageUid = $this->testingFramework->createFrontEndPage($this->rootPageUid);
        $this->testingFramework->changeRecord('pages', $detailPageUid, ['slug' => '/eventDetail']);
        $this->pluginConfiguration->setAsInteger('detailPID', $detailPageUid);
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC],
        );
        $requiredEvent = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'title' => 'required_foo',
            ],
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $this->seminarUid,
            $requiredEvent,
            'requirements',
        );
        $this->subject->setEvent(new LegacyEvent($this->seminarUid));

        self::assertMatchesRegularExpression(
            '/<a href=.*' . $requiredEvent . '.*>required_foo<\\/a>/',
            $this->subject->render(),
        );
    }

    /**
     * @test
     */
    public function renderShowsTitleOfTwoRequirements(): void
    {
        $this->testingFramework->changeRecord(
            'tx_seminars_seminars',
            $this->seminarUid,
            ['object_type' => EventInterface::TYPE_EVENT_TOPIC],
        );
        $requiredEvent1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'title' => 'required_foo',
            ],
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $this->seminarUid,
            $requiredEvent1,
            'requirements',
        );
        $requiredEvent2 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'object_type' => EventInterface::TYPE_EVENT_TOPIC,
                'title' => 'required_bar',
            ],
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $this->seminarUid,
            $requiredEvent2,
            'requirements',
        );
        $this->subject->setEvent(new LegacyEvent($this->seminarUid));

        self::assertMatchesRegularExpression(
            '/required_foo.*required_bar/s',
            $this->subject->render(),
        );
    }
}
