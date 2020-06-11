<?php

namespace Doctrine\Tests\ChangeSet\Unit;

use Doctrine\ORM\ChangeSet\AbstractField;
use Doctrine\ORM\ChangeSet\ChangeSet;
use Doctrine\ORM\ChangeSet\Field\BooleanField;
use Doctrine\ORM\ChangeSet\Field\DateField;
use Doctrine\ORM\ChangeSet\Field\EntityField;
use Doctrine\ORM\ChangeSet\EntityIdentifier;
use Doctrine\ORM\ChangeSet\Field\IntegerField;
use Doctrine\ORM\ChangeSet\Field\StringField;
use Doctrine\ORM\ChangeSet\Visitor\CallbackFieldVisitor;
use Doctrine\Tests\ChangeSet\Entity\EntityA;
use Doctrine\Tests\ChangeSet\Entity\EntityD;
use PHPUnit\Framework\TestCase;

/**
 * Class CallbackFieldVisitorTest
 * @package Doctrine\Tests\ChangeSet\Unit
 */
class CallbackFieldVisitorTest extends TestCase
{
    /**
     * @dataProvider fieldsDataProvider
     * @param AbstractField $sourceField
     * @throws \Exception
     */
    public function testVisitField(AbstractField $sourceField): void
    {
        $changeSet = new ChangeSet(EntityA::class, null);
        $changeSet->addField($sourceField);

        $mock = $this->createPartialMock(\stdClass::class, ['__invoke']);
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with(
                $this->logicalAnd(
                    $this->isInstanceOf(get_class($sourceField)),
                    $this->callback(function ($field) use ($sourceField) {
                        $this->assertEquals($sourceField, $field);
                        return true;
                    })
                )
            );

        $changeSet->applyVisitor(
            new CallbackFieldVisitor(
                get_class($sourceField),
                $mock
            )
        );
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function fieldsDataProvider(): array
    {
        return [
            [new DateField('date', DateField::TYPE_DATE, null, new \DateTime())],
            [new StringField('string', null, 'STRING')],
            [new IntegerField('integer', null, 1)],
            [new BooleanField('boolean', null, true)],
            [new EntityField('entityD', EntityD::class, null, new EntityIdentifier(['id' => 1]))],
        ];
    }
}
