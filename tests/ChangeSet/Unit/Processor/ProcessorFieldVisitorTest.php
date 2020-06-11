<?php

namespace Doctrine\Tests\ChangeSet\Unit\Processor;

use Doctrine\ORM\ChangeSet\AbstractField;
use Doctrine\ORM\ChangeSet\ChangeSet;
use Doctrine\ORM\ChangeSet\EntityIdentifier;
use Doctrine\ORM\ChangeSet\Field\BooleanField;
use Doctrine\ORM\ChangeSet\Field\DateField;
use Doctrine\ORM\ChangeSet\Field\EntityField;
use Doctrine\ORM\ChangeSet\Field\FloatField;
use Doctrine\ORM\ChangeSet\Field\IntegerField;
use Doctrine\ORM\ChangeSet\Field\StringField;
use Doctrine\ORM\ChangeSet\Processor\ProcessorFieldVisitor;
use Doctrine\ORM\ChangeSet\Visitor\AbstractCommonFieldVisitor;
use Doctrine\Tests\ChangeSet\Entity\EntityA;
use PHPUnit\Framework\TestCase;

/**
 * Class ProcessorFieldVisitorTest
 * @package Doctrine\Tests\ChangeSet\Unit\Processor
 */
class ProcessorFieldVisitorTest extends TestCase
{
    /**
     * @var ProcessorFieldVisitor
     */
    private $visitor;

    /**
     * @inheritDoc
     */
    protected function setUp()
    {
        parent::setUp();
        $this->visitor = new ProcessorFieldVisitor(
            [
                DateField::TYPE_TIME => 'H:i',
                DateField::TYPE_DATETIME => 'm/d/Y H:i',
                DateField::TYPE_DATE => 'm/d/Y',
            ],
            [
                AbstractCommonFieldVisitor::BOOLEAN_UNCHECKED => 'unchecked',
                AbstractCommonFieldVisitor::BOOLEAN_CHECKED => 'checked',
            ]
        );
    }

    /**
     * @dataProvider dataProviderStringFields
     * @dataProvider dataProviderIntegerFields
     * @dataProvider dataProviderFloatFields
     * @dataProvider dataProviderBooleanFields
     * @dataProvider dataProviderDateFields
     * @dataProvider dataProviderEntityFields
     *
     * @param AbstractField $field
     * @param string|null $expectedOldValue
     * @param string|null $expectedNewValue
     */
    public function testVisitFields(AbstractField $field, ?string $expectedOldValue, ?string $expectedNewValue): void
    {
        $changeSet = new ChangeSet(EntityA::class, null);
        $changeSet->addField($field);

        $changeSet->applyVisitor($this->visitor);
        $newFields = $this->visitor->getFields();

        $this->assertCount(1, $newFields);
        $this->assertSame($field->getName(true), $newFields[0]->getName(true));
        $this->assertSame($field->getName(), $newFields[0]->getName());
        $this->assertSame($expectedOldValue, $newFields[0]->getOldValue());
        $this->assertSame($expectedNewValue, $newFields[0]->getNewValue());
    }

    /**
     * Test namespace inheritance
     */
    public function testVisitFieldsWithNamespace(): void
    {
        $field = new StringField('field1', 'a', 'v');
        $changeSet = new ChangeSet(EntityA::class, null, 'space.x');
        $changeSet->addField($field);

        $changeSet->applyVisitor($this->visitor);
        $newFields = $this->visitor->getFields();

        $this->assertCount(1, $newFields);
        $this->assertSame($field->getName(true), $newFields[0]->getName(true));
        $this->assertSame($field->getName(), $newFields[0]->getName());
    }

    /**
     * @return array
     */
    public function dataProviderStringFields(): array
    {
        return [
            [new StringField('field1', null, 'A'), null, 'A'],
            [new StringField('field2', 'B', 'A'), 'B', 'A'],
            [new StringField('field3', 'B', null), 'B', null]
        ];
    }

    /**
     * @return array
     */
    public function dataProviderIntegerFields(): array
    {
        return [
            [new IntegerField('field1', null, 1), null, '1'],
            [new IntegerField('field2', 10, null), '10', null],
            [new IntegerField('field3', 10, 11), '10', '11'],
            [new IntegerField('field1', null, 0), null, '0'],
        ];
    }

    /**
     * @return array
     */
    public function dataProviderFloatFields(): array
    {
        return [
            [new FloatField('field1', null, 1), null, '1.00'],
            [new FloatField('field2', 10.01, null), '10.01', null],
            [new FloatField('field3', 10.0, 11.1), '10.00', '11.10'],
            [new FloatField('field3', 12.1345, 13.12), '12.13', '13.12'],
        ];
    }

    /**
     * @return array
     */
    public function dataProviderBooleanFields(): array
    {
        return [
            [new BooleanField('field1', null, true), 'unchecked', 'checked'],
            [new BooleanField('field2', true, null), 'checked', 'unchecked'],
            [new BooleanField('field3', true, false), 'checked', 'unchecked'],
        ];
    }

    /**
     * @return array
     */
    public function dataProviderDateFields(): array
    {
        $date = \DateTime::createFromFormat('Y-m-d H:i:s', '2020-02-15 13:49:10');

        return [
            [new DateField('field1', DateField::TYPE_TIME, null, $date), null, '13:49'],
            [new DateField('field2', DateField::TYPE_DATE, $date, null), '02/15/2020', null],
            [
                new DateField('field3', DateField::TYPE_DATETIME, $date, (clone $date)->setTime(15, 10, 20)),
                '02/15/2020 13:49',
                '02/15/2020 15:10'
            ],
        ];
    }

    /**
     * @return array
     */
    public function dataProviderEntityFields(): array
    {
        return [
            [
                new EntityField('field1', EntityA::class, new EntityIdentifier(['id' => 1]), null),
                serialize([
                    'class' => EntityA::class,
                    'identifier' => new EntityIdentifier(['id' => 1])
                ]),
                null
            ],
            [
                new EntityField('field2', EntityA::class, null, new EntityIdentifier(['id' => 2])),
                null,
                serialize([
                    'class' => EntityA::class,
                    'identifier' => new EntityIdentifier(['id' => 2])
                ]),
            ],
            [
                new EntityField('field3', EntityA::class, new EntityIdentifier(['id' => 1]), new EntityIdentifier(['id' => 2])),
                serialize([
                    'class' => EntityA::class,
                    'identifier' => new EntityIdentifier(['id' => 1])
                ]),
                serialize([
                    'class' => EntityA::class,
                    'identifier' => new EntityIdentifier(['id' => 2])
                ]),
            ]
        ];
    }
}
