<?php

namespace Doctrine\Tests\ChangeSet\Unit;

use Doctrine\ORM\ChangeSet\ChangeSet;
use Doctrine\ORM\ChangeSet\Field\BooleanField;
use Doctrine\ORM\ChangeSet\Field\DateField;
use Doctrine\ORM\ChangeSet\Field\IntegerField;
use Doctrine\ORM\ChangeSet\Field\StringField;
use Doctrine\ORM\ChangeSet\Visitor\AbstractCommonFieldVisitor;
use Doctrine\Tests\ChangeSet\Entity\EntityA;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class AbstractCommonFieldVisitorTest
 * @package Doctrine\Tests\ChangeSet\Unit
 */
class AbstractCommonFieldVisitorTest extends TestCase
{
    /**
     * @inheritDoc
     */
    protected function setUp()
    {
        parent::setUp();
    }

    /**
     * Test accept fields
     */
    public function testAcceptAllFields(): void
    {
        $visitor = $this->getVisitorMock([], []);
        $visitor
            ->expects($this->exactly(3))
            ->method('processField')
            ->with(
                $this->logicalOr(
                    $this->equalTo('string'),
                    $this->equalTo('integer'),
                    $this->equalTo('date')
                )
            );

        $changeSet = new ChangeSet(EntityA::class, []);
        $changeSet->addField(new DateField('date', DateField::TYPE_DATE, null, new \DateTime()));
        $changeSet->addField(new StringField('string', null, 'STRING'));
        $changeSet->addField(new IntegerField('integer', null, 1));

        $changeSet->applyVisitor($visitor);
    }

    /**
     * Test accept fields
     */
    public function testAcceptExclusiveFields(): void
    {
        $visitor = $this->getVisitorMock([], [], ['string', 'integer']);
        $visitor
            ->expects($this->exactly(2))
            ->method('processField')
            ->with(
                $this->logicalAnd(
                    $this->logicalNot(
                        $this->equalTo('date')
                    ),
                    $this->logicalOr(
                        $this->equalTo('string'),
                        $this->equalTo('integer')
                    )
                )
            );

        $changeSet = new ChangeSet(EntityA::class, []);
        $changeSet->addField(new DateField('date', DateField::TYPE_DATE, null, new \DateTime()));
        $changeSet->addField(new StringField('string', null, 'STRING'));
        $changeSet->addField(new IntegerField('integer', null, 1));

        $changeSet->applyVisitor($visitor);
    }

    /**
     * @dataProvider datesDataProvider
     * @param string $fieldName
     * @param string $fieldType
     */
    public function testDateConverts(string $fieldName, string $fieldType): void
    {
        $date = \DateTime::createFromFormat('Y-m-d H:i:s', '2020-01-30 12:45:10');
        $visitor = $this->getVisitorMock(
            [
                DateField::TYPE_TIME => 'H:i',
                DateField::TYPE_DATETIME => 'm/d/Y H:i',
                DateField::TYPE_DATE => 'm/d/Y'
            ],
            []
        );

        $visitor
            ->expects($this->exactly(1))
            ->method('processField')
            ->with(
                $this->equalTo($fieldName),
                $this->anything(),
                $this->callback(function ($newValue) use ($fieldType) {
                    switch ($fieldType) {
                        case 'date':
                            $this->assertEquals('01/30/2020', $newValue);
                            break;

                        case 'dateTime':
                            $this->assertEquals('01/30/2020 12:45', $newValue);
                            break;

                        case 'time':
                            $this->assertEquals('12:45', $newValue);
                            break;

                        default:
                            break;
                    }

                    return true;
                })
            );

        $changeSet = new ChangeSet(EntityA::class, []);
        $changeSet->addField(new DateField($fieldName, $fieldType, null, $date));

        $changeSet->applyVisitor($visitor);
    }

    /**
     * @dataProvider booleanDataProvider
     * @param bool $fieldValue
     */
    public function testBooleanFormats(bool $fieldValue): void
    {
        $visitor = $this->getVisitorMock(
            [],
            [
                AbstractCommonFieldVisitor::BOOLEAN_CHECKED => 'Checked Value',
                AbstractCommonFieldVisitor::BOOLEAN_UNCHECKED => 'Unchecked Value',
            ]
        );

        $visitor
            ->expects($this->exactly(1))
            ->method('processField')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function ($newValue) use ($fieldValue) {
                    switch ($fieldValue) {
                        case true:
                            $this->assertEquals('Checked Value', $newValue);
                            break;

                        case false:
                            $this->assertEquals('Unchecked Value', $newValue);
                            break;

                        default:
                            break;
                    }

                    return true;
                })
            );

        $changeSet = new ChangeSet(EntityA::class, []);
        $changeSet->addField(new BooleanField('boolean', null, $fieldValue));

        $changeSet->applyVisitor($visitor);
    }

    /**
     * @dataProvider booleanDataProvider
     * @param bool $fieldValue
     */
    public function testDefaultBooleanFormats(bool $fieldValue): void
    {
        $visitor = $this->getVisitorMock([], []);
        $visitor
            ->expects($this->exactly(1))
            ->method('processField')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function ($newValue) use ($fieldValue) {
                    switch ($fieldValue) {
                        case true:
                            $this->assertEquals('Checked', $newValue);
                            break;

                        case false:
                            $this->assertEquals('Unchecked', $newValue);
                            break;

                        default:
                            break;
                    }

                    return true;
                })
            );

        $changeSet = new ChangeSet(EntityA::class, []);
        $changeSet->addField(new BooleanField('boolean', null, $fieldValue));

        $changeSet->applyVisitor($visitor);
    }


    /**
     * @return array
     */
    public function datesDataProvider(): array
    {
        return [
            ['date', DateField::TYPE_DATE],
            ['dateTime', DateField::TYPE_DATETIME],
            ['time', DateField::TYPE_TIME],
        ];
    }

    /**
     * @return array
     */
    public function booleanDataProvider(): array
    {
        return [
            [true],
            [false]
        ];
    }

    /**
     * @param array $dateFormats
     * @param array $booleanFormats
     * @param array $acceptedFields
     * @return MockObject
     */
    private function getVisitorMock(array $dateFormats, array $booleanFormats, array $acceptedFields = []): MockObject
    {
        return $this->getMockBuilder(AbstractCommonFieldVisitor::class)
            ->enableOriginalConstructor()
            ->setConstructorArgs([
                $dateFormats,
                $booleanFormats,
                $acceptedFields
            ])
            ->getMockForAbstractClass();
    }
}
