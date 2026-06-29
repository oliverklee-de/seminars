<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\ViewHelpers;

use OliverKlee\Seminars\ViewHelpers\RichTextViewHelper;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \OliverKlee\Seminars\ViewHelpers\RichTextViewHelper
 */
final class RichTextViewHelperTest extends FunctionalTestCase
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

    private RichTextViewHelper $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['TYPO3_REQUEST'] = new ServerRequest();

        $this->subject = new RichTextViewHelper();
    }

    /**
     * @test
     */
    public function wrapsPlainTextInParagraph(): void
    {
        $result = $this->subject->render('This is plain text.');

        self::assertSame('<p>This is plain text.</p>', $result);
    }

    /**
     * @test
     */
    public function rendersAllowedTagsUnchanged(): void
    {
        $result = $this->subject->render('<p><b>bold text</b></p>');

        self::assertSame('<p><b>bold text</b></p>', $result);
    }

    /**
     * @test
     */
    public function discardsStrayClosingTag(): void
    {
        $result = $this->subject->render('<p>bold text</b></p>');

        self::assertSame('<p>bold text</p>', $result);
    }

    /**
     * @test
     */
    public function encodesUnknownTag(): void
    {
        $result = $this->subject->render('<p><coffee>bold text</coffee></p>');

        $expected = '<p>' . \htmlspecialchars('<coffee>bold text</coffee>', ENT_QUOTES | ENT_HTML5) . '</p>';
        self::assertSame($expected, $result);
    }
}
