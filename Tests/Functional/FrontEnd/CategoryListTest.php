<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\FrontEnd;

use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\FrontEnd\CategoryList;
use OliverKlee\Seminars\Tests\Functional\Support\BackendLanguageTrait;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\FrontEnd\AbstractView
 * @covers \OliverKlee\Seminars\FrontEnd\CategoryList
 * @covers \OliverKlee\Seminars\Templating\TemplateHelper
 */
final class CategoryListTest extends FunctionalTestCase
{
    use BackendLanguageTrait;

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

    private CategoryList $subject;

    private TestingFramework $testingFramework;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testingFramework = $this->get(TestingFramework::class);
        $this->testingFramework->createFakeFrontEnd($this->testingFramework->createFrontEndPage());

        $this->subject = new CategoryList(
            [
                'templateFile' => 'EXT:seminars/Resources/Private/Templates/FrontEnd/FrontEnd.html',
            ],
            $this->getFrontEndController()->cObj,
        );
    }

    protected function tearDown(): void
    {
        $this->testingFramework->cleanUpWithoutDatabase();

        parent::tearDown();
    }

    private function getFrontEndController(): TypoScriptFrontendController
    {
        return $GLOBALS['TSFE'];
    }

    /**
     * @test
     */
    public function renderWithoutCategoriesDoesNotCreateCategoryTable(): void
    {
        $this->subject->setConfigurationValue('pages', 1);

        $result = $this->subject->render();

        self::assertStringNotContainsString('<table', $result);
    }

    /**
     * @test
     */
    public function renderWithoutCategoriesOutputsMessageAboutNoCategories(): void
    {
        $this->subject->setConfigurationValue('pages', 1);

        $result = $this->subject->render();

        self::assertStringContainsString($this->translate('label_no_categories'), $result);
    }

    /**
     * @test
     */
    public function renderIncludesTitleOfCategoryWithEvent(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/CategoryList/OneCategoryWithAsciiTitle.csv');

        $result = $this->subject->render();

        self::assertStringContainsString('category with ASCII title', $result);
    }

    /**
     * @test
     */
    public function renderEncodesCategoryTitles(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/CategoryList/OneCategoryWithSpecialCharactersInTitle.csv');

        $result = $this->subject->render();

        self::assertStringContainsString('category with ampersand &amp;', $result);
    }
}
