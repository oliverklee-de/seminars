<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Unit\Middleware;

use OliverKlee\Seminars\Middleware\ResponseHeadersModifier;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \OliverKlee\Seminars\Middleware\ResponseHeadersModifier
 */
final class ResponseHeadersModifierTest extends UnitTestCase
{
    private ResponseHeadersModifier $subject;

    /**
     * @var ServerRequestInterface&MockObject
     */
    private ServerRequestInterface $serverRequestMock;

    /**
     * @var RequestHandlerInterface&MockObject
     */
    private RequestHandlerInterface $requestHandlerMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new ResponseHeadersModifier();

        $this->serverRequestMock = $this->createMock(ServerRequestInterface::class);
        $this->requestHandlerMock = $this->createMock(RequestHandlerInterface::class);
        $headers = [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Language' => 'de',
            'X-TYPO3-Parsetime' => '0ms',
        ];
        $this->requestHandlerMock->method('handle')->willReturn(new Response('php://temp', 200, $headers));
    }

    /**
     * @test
     */
    public function isMiddleware(): void
    {
        self::assertInstanceOf(MiddlewareInterface::class, $this->subject);
    }

    /**
     * @test
     */
    public function processByDefaultReturnsResponse(): void
    {
        $response = $this->subject->process($this->serverRequestMock, $this->requestHandlerMock);

        self::assertInstanceOf(Response::class, $response);
    }

    /**
     * @test
     */
    public function processByDefaultKeepsResponseStatusCodeUnchanged(): void
    {
        $response = $this->subject->process($this->serverRequestMock, $this->requestHandlerMock);

        self::assertSame(200, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function getOverrideStatusCodeByDefaultReturnsNull(): void
    {
        self::assertNull($this->subject->getOverrideStatusCode());
    }

    /**
     * @test
     */
    public function setOverrideStatusCodeOverwritesSavedStatusCode(): void
    {
        $statusCode = 404;

        $this->subject->setOverrideStatusCode($statusCode);

        self::assertSame($statusCode, $this->subject->getOverrideStatusCode());
    }

    /**
     * @test
     */
    public function setOverrideStatusCodeTwoTimesSetsSavedStatusCodeToLatestSetStatusCode(): void
    {
        $this->subject->setOverrideStatusCode(500);

        $statusCode = 404;
        $this->subject->setOverrideStatusCode($statusCode);

        self::assertSame($statusCode, $this->subject->getOverrideStatusCode());
    }

    /**
     * @test
     */
    public function setOverrideStatusCodeOverwritesResponseStatusCode(): void
    {
        $statusCode = 404;

        $this->subject->setOverrideStatusCode($statusCode);

        $response = $this->subject->process($this->serverRequestMock, $this->requestHandlerMock);
        self::assertSame($statusCode, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function setOverrideStatusCodeTwoTimesOverwritesResponseStatusWithLatestCode(): void
    {
        $this->subject->setOverrideStatusCode(500);

        $statusCode = 404;
        $this->subject->setOverrideStatusCode($statusCode);

        $response = $this->subject->process($this->serverRequestMock, $this->requestHandlerMock);
        self::assertSame($statusCode, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function processByDefaultKeepsResponseHeaderUnchanged(): void
    {
        $response = $this->subject->process($this->serverRequestMock, $this->requestHandlerMock);

        $expectedHeaders = [
            'Content-Type' => ['text/html; charset=utf-8'],
            'Content-Language' => ['de'],
            'X-TYPO3-Parsetime' => ['0ms'],
        ];

        self::assertSame($expectedHeaders, $response->getHeaders());
    }

    /**
     * @test
     */
    public function getOverrideHeadersByDefaultReturnsEmptyArray(): void
    {
        self::assertSame([], $this->subject->getOverrideHeaders());
    }

    /**
     * @test
     */
    public function addOverrideHeaderCanAddNewSavedHeader(): void
    {
        $headerName = 'X-Test-Header';
        $headerValue = 'test-value';

        $this->subject->addOverrideHeader($headerName, $headerValue);

        self::assertSame([$headerName => $headerValue], $this->subject->getOverrideHeaders());
    }

    /**
     * @test
     */
    public function addOverrideHeaderCanAddTwoDifferentHeadersToSavedHeaders(): void
    {
        $headerName1 = 'X-Test-Header-1';
        $headerValue1 = 'test-value-1';
        $this->subject->addOverrideHeader($headerName1, $headerValue1);

        $headerName2 = 'X-Test-Header-2';
        $headerValue2 = 'test-value-2';
        $this->subject->addOverrideHeader($headerName2, $headerValue2);

        self::assertSame(
            [
                $headerName1 => $headerValue1,
                $headerName2 => $headerValue2,
            ],
            $this->subject->getOverrideHeaders(),
        );
    }

    /**
     * @test
     */
    public function addOverrideHeaderCalledTwoTimesForTheSameHeaderKeepsSecondSavedHeader(): void
    {
        $headerName = 'X-Test-Header';
        $headerValue1 = 'test-value-1';
        $this->subject->addOverrideHeader($headerName, $headerValue1);

        $headerValue2 = 'test-value-2';
        $this->subject->addOverrideHeader($headerName, $headerValue2);

        self::assertSame([$headerName => $headerValue2], $this->subject->getOverrideHeaders());
    }

    /**
     * @test
     */
    public function addOverrideHeaderCanAddNewHeaderToResponse(): void
    {
        $headerName = 'X-Test-Header';
        $headerValue = 'test-value';

        $this->subject->addOverrideHeader($headerName, $headerValue);

        $response = $this->subject->process($this->serverRequestMock, $this->requestHandlerMock);
        self::assertSame($headerValue, $response->getHeaderLine($headerName));
    }

    /**
     * @test
     */
    public function addOverrideHeaderCanOverwriteExistingHeaderFromResponse(): void
    {
        $headerName = 'Content-Type';
        $headerValue = 'application/json';

        $this->subject->addOverrideHeader($headerName, $headerValue);

        $response = $this->subject->process($this->serverRequestMock, $this->requestHandlerMock);
        self::assertSame($headerValue, $response->getHeaderLine($headerName));
    }
}
