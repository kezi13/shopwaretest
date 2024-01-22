<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Product;

use Doctrine\DBAL\Connection;
use OpenSearchDSL\BuilderInterface;
use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\FullText\MultiMatchQuery;
use OpenSearchDSL\Query\Joining\NestedQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\AbstractTokenFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\TokenFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Tokenizer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Elasticsearch\Product\ProductSearchQueryBuilder;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ProductSearchQueryBuilder::class)]
class ProductSearchQueryBuilderTest extends TestCase
{
    public function testDecoration(): void
    {
        $builder = new ProductSearchQueryBuilder(
            $this->createMock(Connection::class),
            new EntityDefinitionQueryHelper(),
            $this->getDefinition(),
            $this->createMock(TokenFilter::class),
            new Tokenizer(2),
        );

        static::expectException(DecorationPatternException::class);
        $builder->getDecorated();
    }

    public function testBuildQueryAndSearch(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
                ['and_logic' => '1', 'field' => 'name', 'tokenize' => 1, 'ranking' => 500],
                ['and_logic' => '1', 'field' => 'description', 'tokenize' => 0, 'ranking' => 500],
            ]);

        $tokenFilter = $this->createMock(AbstractTokenFilter::class);
        $tokenFilter
            ->method('filter')
            ->willReturnArgument(0);

        $helper = new EntityDefinitionQueryHelper();

        $builder = new ProductSearchQueryBuilder(
            $connection,
            $helper,
            $this->getDefinition(),
            $tokenFilter,
            new Tokenizer(2),
        );

        $criteria = new Criteria();
        $criteria->setTerm('foo bla');
        $queries = $builder->build($criteria, Context::createDefaultContext());

        static::assertEmpty($queries->getQueries(BoolQuery::SHOULD));

        /** @var BoolQuery[] $tokenQueries */
        $tokenQueries = array_values($queries->getQueries(BoolQuery::MUST));

        static::assertCount(2, $tokenQueries, 'Expected 2 token queries due to token searches');

        $nameQueries = array_map(fn (BuilderInterface $query) => $query->toArray(), array_values($tokenQueries[0]->getQueries(BoolQuery::SHOULD)));

        static::assertCount(6, $nameQueries);

        $expectedQueries = [
            [
                'multi_match' => [
                    'query' => 'foo',
                    'fields' => [
                        'name.2fbb5fe2e29a4d70aa5854ce7ce3e20b.search',
                    ],
                    'type' => 'best_fields',
                    'fuzziness' => 0,
                    'boost' => 2500,
                ],
            ],
            [
                'multi_match' => [
                    'query' => 'foo',
                    'fields' => [
                        'name.2fbb5fe2e29a4d70aa5854ce7ce3e20b.search',
                    ],
                    'type' => 'phrase_prefix',
                    'slop' => 5,
                    'boost' => 500,
                ],
            ],
            [
                'multi_match' => [
                    'query' => 'foo',
                    'fields' => [
                        'name.2fbb5fe2e29a4d70aa5854ce7ce3e20b.search',
                    ],
                    'type' => 'best_fields',
                    'fuzziness' => 'auto',
                    'boost' => 1500,
                ],
            ],
            [
                'multi_match' => [
                    'query' => 'foo',
                    'fields' => [
                        'name.2fbb5fe2e29a4d70aa5854ce7ce3e20b.ngram',
                    ],
                    'type' => 'phrase',
                    'boost' => 500,
                ],
            ],
            [
                'multi_match' => [
                    'query' => 'foo',
                    'fields' => [
                        'description.2fbb5fe2e29a4d70aa5854ce7ce3e20b.search',
                    ],
                    'type' => 'best_fields',
                    'fuzziness' => 0,
                    'boost' => 2500,
                ],
            ],
            [
                'multi_match' => [
                    'query' => 'foo',
                    'fields' => [
                        'description.2fbb5fe2e29a4d70aa5854ce7ce3e20b.search',
                    ],
                    'type' => 'phrase_prefix',
                    'slop' => 5,
                    'boost' => 500,
                ],
            ],
        ];

        static::assertSame($expectedQueries, $nameQueries);
    }

    public function testNestedQueries(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
                ['and_logic' => '1', 'field' => 'categories.name', 'tokenize' => 1, 'ranking' => 500],
            ]);

        $tokenFilter = $this->createMock(AbstractTokenFilter::class);
        $tokenFilter
            ->method('filter')
            ->willReturnArgument(0);

        $builder = new ProductSearchQueryBuilder(
            $connection,
            new EntityDefinitionQueryHelper(),
            $this->getDefinition(),
            $tokenFilter,
            new Tokenizer(2)
        );

        $criteria = new Criteria();
        $criteria->setTerm('foo bla');
        $queries = $builder->build($criteria, Context::createDefaultContext());

        /** @var BoolQuery $boolQuery */
        $boolQuery = array_values($queries->getQueries(BoolQuery::MUST))[0];

        $esQueries = array_values($boolQuery->getQueries(BoolQuery::SHOULD));

        static::assertNotEmpty($esQueries);

        $first = $esQueries[0];

        static::assertInstanceOf(NestedQuery::class, $first);

        static::assertSame('categories', $first->getPath());

        $query = $first->getQuery();

        static::assertInstanceOf(MultiMatchQuery::class, $query);

        static::assertSame(
            [
                'multi_match' => [
                    'query' => 'foo',
                    'fields' => [
                        'categories.name.2fbb5fe2e29a4d70aa5854ce7ce3e20b.search',
                    ],
                    'type' => 'best_fields',
                    'fuzziness' => 0,
                    'boost' => 2500,
                ],
            ],
            $query->toArray()
        );
    }

    public function testOrSearch(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
                ['and_logic' => '0', 'field' => 'name', 'tokenize' => 1, 'ranking' => 500],
                ['and_logic' => '0', 'field' => 'description', 'tokenize' => 0, 'ranking' => 500],
            ]);

        $tokenFilter = $this->createMock(AbstractTokenFilter::class);
        $tokenFilter
            ->method('filter')
            ->willReturnArgument(0);

        $builder = new ProductSearchQueryBuilder(
            $connection,
            new EntityDefinitionQueryHelper(),
            $this->getDefinition(),
            $tokenFilter,
            new Tokenizer(2)
        );

        $criteria = new Criteria();
        $criteria->setTerm('foo bla');
        $queries = $builder->build($criteria, Context::createDefaultContext());

        static::assertNotEmpty($queries->getQueries(BoolQuery::SHOULD));
        static::assertEmpty($queries->getQueries(BoolQuery::MUST));
    }

    public function getDefinition(): EntityDefinition
    {
        $instanceRegistry = new StaticDefinitionInstanceRegistry(
            [
                ProductDefinition::class,
                ProductTranslationDefinition::class,
                ProductManufacturerDefinition::class,
                ProductManufacturerTranslationDefinition::class,
                ProductCategoryDefinition::class,
                CategoryDefinition::class,
                CategoryTranslationDefinition::class,
            ],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        return $instanceRegistry->getByEntityName('product');
    }
}
