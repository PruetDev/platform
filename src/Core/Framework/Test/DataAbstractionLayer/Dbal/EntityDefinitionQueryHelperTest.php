<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Exception\FieldAccessorBuilderNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\FieldAccessorBuilderRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\JsonFieldAccessorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\FieldResolverRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ObjectField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class EntityDefinitionQueryHelperTest extends TestCase
{
    use KernelTestBehaviour;

    public function testJsonObjectAccessWithoutAccessorBuilder(): void
    {
        $this->expectException(FieldAccessorBuilderNotFoundException::class);

        $helper = new EntityDefinitionQueryHelper(
            new FieldResolverRegistry([]),
            new FieldAccessorBuilderRegistry([])
        );
        $helper->getFieldAccessor(
            'json_object_test.amount.gross',
            JsonObjectTestDefinition::class,
            JsonObjectTestDefinition::getEntityName(),
            Context::createDefaultContext()
        );
    }

    public function testJsonObjectAccess(): void
    {
        $helper = new EntityDefinitionQueryHelper(
            new FieldResolverRegistry([]),
            new FieldAccessorBuilderRegistry([
                new JsonFieldAccessorBuilder($this->getContainer()->get(Connection::class)),
            ])
        );
        $accessor = $helper->getFieldAccessor(
            'json_object_test.amount.gross',
            JsonObjectTestDefinition::class,
            JsonObjectTestDefinition::getEntityName(),
            Context::createDefaultContext()
        );
        $parameters = $accessor->getParameters();

        static::assertCount(1, $parameters);
        static::assertEquals('$.gross', current($parameters));

        self::assertEquals(
            sprintf('JSON_UNQUOTE(JSON_EXTRACT(`json_object_test`.`amount`, :%s))', key($parameters)),
            $accessor->getSQL()
        );
    }

    public function testNestedJsonObjectAccessor(): void
    {
        $helper = new EntityDefinitionQueryHelper(
            new FieldResolverRegistry([]),
            new FieldAccessorBuilderRegistry([
                new JsonFieldAccessorBuilder($this->getContainer()->get(Connection::class)),
            ])
        );
        $accessor = $helper->getFieldAccessor(
            'json_object_test.amount.gross.value',
            JsonObjectTestDefinition::class,
            JsonObjectTestDefinition::getEntityName(),
            Context::createDefaultContext()
        );

        $parameters = $accessor->getParameters();
        static::assertCount(1, $parameters);
        static::assertEquals('$.gross.value', current($parameters));

        self::assertEquals(
            sprintf('JSON_UNQUOTE(JSON_EXTRACT(`json_object_test`.`amount`, :%s))', key($parameters)),
            $accessor->getSQL()
        );
    }

    public function testGetFieldWithJsonAccessor(): void
    {
        $helper = new EntityDefinitionQueryHelper(
            new FieldResolverRegistry([]),
            new FieldAccessorBuilderRegistry([
                new JsonFieldAccessorBuilder($this->getContainer()->get(Connection::class)),
            ])
        );
        $field = $helper->getField(
            'json_object_test.amount.gross.value',
            JsonObjectTestDefinition::class,
            JsonObjectTestDefinition::getEntityName()
        );

        static::assertInstanceOf(ObjectField::class, $field);
    }
}

class JsonObjectTestDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'json_object_test';
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new ObjectField('amount', 'amount'),
        ]);
    }
}
