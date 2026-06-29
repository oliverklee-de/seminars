<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Unit\Configuration;

use OliverKlee\Seminars\Configuration\LegacyConfiguration;
use OliverKlee\Seminars\Templating\TemplateHelper;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \OliverKlee\Seminars\Configuration\LegacyConfiguration
 * @covers \OliverKlee\Seminars\Templating\TemplateHelper
 */
final class LegacyConfigurationTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    /**
     * @var ContentObjectRenderer&MockObject
     */
    private ContentObjectRenderer $contentObjectMock;

    private LegacyConfiguration $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $requestMock = $this->createMock(ServerRequestInterface::class);
        $requestMock->method('getAttribute')->with('language')->willReturn($this->createStub(SiteLanguage::class));
        $GLOBALS['TYPO3_REQUEST'] = $requestMock;

        $frontEndControllerMock = $this->createMock(TypoScriptFrontendController::class);
        $this->contentObjectMock = $this->createMock(ContentObjectRenderer::class);
        $frontEndControllerMock->cObj = $this->contentObjectMock;
        $GLOBALS['TSFE'] = $frontEndControllerMock;

        $this->subject = new LegacyConfiguration();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TSFE'], $GLOBALS['TYPO3_REQUEST']);
        parent::tearDown();
    }

    /**
     * @test
     */
    public function extendsTemplateHelper(): void
    {
        self::assertInstanceOf(TemplateHelper::class, $this->subject);
    }

    /**
     * @test
     */
    public function constructionWithoutFrontEndThrowsException(): void
    {
        unset($GLOBALS['TSFE']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No front end found.');
        $this->expectExceptionCode(1668938450);

        $this->subject = new LegacyConfiguration();
    }

    /**
     * @test
     */
    public function hasContentObjectFromFrontEnd(): void
    {
        self::assertSame($this->contentObjectMock, $this->subject->getContentObjectRenderer());
    }
}
