<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart\Token;

use League\OAuth2\Server\CryptKey;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Cart\Token\JWTConfigurationFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
#[Package('checkout')]
class JWTConfigurationFactoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testWithFile(): void
    {
        $signer = $this->getContainer()->get('shopware.jwt_signer');
        $privateKey = $this->getContainer()->get('shopware.private_key');
        $publicKey = $this->getContainer()->get('shopware.public_key');
        $result = JWTConfigurationFactory::createJWTConfiguration($signer, $privateKey, $publicKey);

        static::assertSame($signer, $result->signer());
    }

    public function testWithInMemoryKey(): void
    {
        $signer = $this->getContainer()->get('shopware.jwt_signer');
        $privateKey = $this->getContainer()->get('shopware.private_key');
        $publicKey = $this->getContainer()->get('shopware.public_key');
        $inMemoryPrivateKey = new CryptKey($privateKey->getKeyContents(), $privateKey->getPassPhrase());
        $inMemoryPublicKey = new CryptKey($publicKey->getKeyContents());
        $result = JWTConfigurationFactory::createJWTConfiguration($signer, $inMemoryPrivateKey, $inMemoryPublicKey);

        static::assertSame($signer, $result->signer());
    }
}
