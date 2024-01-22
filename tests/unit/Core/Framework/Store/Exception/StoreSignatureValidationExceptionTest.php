<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Exception\StoreSignatureValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(StoreSignatureValidationException::class)]
class StoreSignatureValidationExceptionTest extends TestCase
{
    public function testGetErrorCode(): void
    {
        static::assertSame(
            'FRAMEWORK__STORE_SIGNATURE_INVALID',
            (new StoreSignatureValidationException('reason'))->getErrorCode()
        );
    }

    public function testGetStatusCode(): void
    {
        static::assertSame(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            (new StoreSignatureValidationException('reason'))->getStatusCode()
        );
    }

    public function testGetMessage(): void
    {
        static::assertSame(
            'Store signature validation failed. Error: reason',
            (new StoreSignatureValidationException('reason'))->getMessage()
        );
    }
}
