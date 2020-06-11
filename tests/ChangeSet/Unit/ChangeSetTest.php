<?php

namespace Doctrine\Tests\ChangeSet\Unit;

use Doctrine\ORM\ChangeSet\AbstractField;
use Doctrine\ORM\ChangeSet\ChangeSet;
use Doctrine\ORM\ChangeSet\Exception\InvalidChangeSetException;
use Doctrine\ORM\ChangeSet\Field\BooleanField;
use Doctrine\ORM\ChangeSet\Field\DateField;
use Doctrine\ORM\ChangeSet\Field\EntityField;
use Doctrine\ORM\ChangeSet\EntityIdentifier;
use Doctrine\ORM\ChangeSet\Field\FloatField;
use Doctrine\ORM\ChangeSet\Field\IntegerField;
use Doctrine\ORM\ChangeSet\Field\StringField;
use Doctrine\ORM\ChangeSet\FieldVisitorInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class ChangeSetTest
 * @package Doctrine\Tests\ChangeSet
 */
class ChangeSetTest extends TestCase
{
    protected static $enabledLog = false;

    /**
     * @dataProvider dataProviderFields
     *
     * @param AbstractField ...$fields
     */
    public function testIterateCurrentChangeSetFields(AbstractField ...$fields): void
    {
        $changeSet = new ChangeSet('EntityA', new EntityIdentifier(['id' => 1]));

        foreach ($fields as $field) {
            $changeSet->addField($field);
        }

        $visitor = $this->createMock(FieldVisitorInterface::class);
        $visitor
            ->expects($this->exactly(count($fields)))
            ->method($this->anything())
            ->withConsecutive(
                ...array_map(function ($field) {
                    return [$this->equalTo($field)];
                }, $fields)
            );

        $changeSet->applyVisitor($visitor);
    }

    /**
     * @dataProvider dataProviderFields
     *
     * @param AbstractField ...$fields
     */
    public function testFindConcreteFieldInCurrentChangeSet(AbstractField ...$fields): void
    {
        $changeSet = new ChangeSet('EntityA', new EntityIdentifier(['id' => 1]));

        foreach ($fields as $field) {
            $changeSet->addField($field);
        }

        foreach ($fields as $field) {
            $visitor = $this->createMock(FieldVisitorInterface::class);
            $visitor
                ->expects($this->exactly(1))
                ->method($this->anything())
                ->with($field);

            $changeSet->applyVisitor($visitor, $field->getName());
        }
    }

    /**
     * @dataProvider dataProviderFields
     *
     * @param AbstractField ...$fields
     * @throws InvalidChangeSetException
     */
    public function testFindFieldsInRelatedChangeSetFirstLevel(AbstractField ...$fields): void
    {
        $changeSet = new ChangeSet('EntityA', new EntityIdentifier(['id' => 1]));
        $relatedChangeSet = new ChangeSet('EntityB', new EntityIdentifier(['id' => null]), 'bCollection');

        $changeSet->addRelatedChangeSet($relatedChangeSet);

        foreach ($fields as $field) {
            $relatedChangeSet->addField($field);
        }

        $visitor = $this->createMock(FieldVisitorInterface::class);
        $visitor
            ->expects($this->exactly(count($fields)))
            ->method($this->anything())
            ->withConsecutive(
                ...array_map(function ($field) {
                    return [$this->equalTo($field)];
                }, $fields)
            );

        $changeSet->applyVisitor($visitor, 'bCollection');
    }

    /**
     * @dataProvider dataProviderFields
     *
     * @param AbstractField ...$fields
     * @throws InvalidChangeSetException
     */
    public function testFindConcreteFieldInRelatedChangeSetFirstLevel(AbstractField ...$fields): void
    {
        $changeSet = new ChangeSet('EntityA', new EntityIdentifier(['id' => 1]));
        $relatedChangeSet = new ChangeSet('EntityB', new EntityIdentifier(['id' => null]), 'bCollection');

        $changeSet->addRelatedChangeSet($relatedChangeSet);

        foreach ($fields as $field) {
            $relatedChangeSet->addField($field);
        }

        foreach ($fields as $field) {
            $visitor = $this->createMock(FieldVisitorInterface::class);
            $visitor
                ->expects($this->exactly(1))
                ->method($this->anything())
                ->with($field);

            $changeSet->applyVisitor($visitor, 'bCollection.' . $field->getName());
        }
    }

    /**
     * @dataProvider dataProviderFields
     *
     * @param AbstractField ...$fields
     * @throws InvalidChangeSetException
     */
    public function testFindFieldsInRelatedChangeSetSecondLevel(AbstractField ...$fields): void
    {
        $changeSet = new ChangeSet('EntityA', new EntityIdentifier(['id' => 1]));
        $relatedChangeSet = new ChangeSet('EntityB', new EntityIdentifier(['id' => null]), 'bCollection');
        $subRelatedChangeSet = new ChangeSet('EntityC', new EntityIdentifier(['id' => null]), 'bCollection.cCollection');

        $changeSet->addRelatedChangeSet($relatedChangeSet);
        $relatedChangeSet->addRelatedChangeSet($subRelatedChangeSet);

        foreach ($fields as $field) {
            $subRelatedChangeSet->addField($field);
        }

        $visitor = $this->createMock(FieldVisitorInterface::class);
        $visitor
            ->expects($this->exactly(count($fields)))
            ->method($this->anything())
            ->withConsecutive(
                ...array_map(function ($field) {
                    return [$this->equalTo($field)];
                }, $fields)
            );

        $changeSet->applyVisitor($visitor, 'bCollection.cCollection');
    }

    /**
     * @dataProvider dataProviderFields
     *
     * @param AbstractField ...$fields
     * @throws InvalidChangeSetException
     */
    public function testFindConcreteFieldInRelatedChangeSetSecondLevel(AbstractField ...$fields): void
    {
        $changeSet = new ChangeSet('EntityA', new EntityIdentifier(['id' => 1]));
        $relatedChangeSet = new ChangeSet('EntityB', new EntityIdentifier(['id' => null]), 'bCollection');
        $subRelatedChangeSet = new ChangeSet('EntityC', new EntityIdentifier(['id' => null]), 'bCollection.cCollection');

        $changeSet->addRelatedChangeSet($relatedChangeSet);
        $relatedChangeSet->addRelatedChangeSet($subRelatedChangeSet);

        foreach ($fields as $field) {
            $subRelatedChangeSet->addField($field);
        }

        foreach ($fields as $field) {
            $visitor = $this->createMock(FieldVisitorInterface::class);
            $visitor
                ->expects($this->exactly(1))
                ->method($this->anything())
                ->with($field);

            $changeSet->applyVisitor($visitor, 'bCollection.cCollection.' . $field->getName());
        }
    }

    /**
     * Related change set must always have namespace
     *
     * @throws InvalidChangeSetException
     * @expectedException \Doctrine\ORM\ChangeSet\Exception\InvalidChangeSetException
     */
    public function testExceptionWithInvalidRelatedChangeSet(): void
    {
        $changeSet = new ChangeSet('EntityA', new EntityIdentifier(['id' => 1]));
        $changeSet->addRelatedChangeSet(new ChangeSet('EntityB', new EntityIdentifier(['id' => null])));
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function dataProviderFields(): array
    {
        return [
            [
                new DateField('field1', 'date', null, new \DateTime()),
                new StringField('field2', 'test', 'not test'),
            ],
            [
                new FloatField('field3', 1.0, 1.1),
                new IntegerField('field4', 1, 2),
                new StringField('field5', 3, 5),
            ],
            [
                new EntityField('field6', 'Client', new EntityIdentifier(['id' => 1]), new EntityIdentifier(['id' => 2])),
                new BooleanField('field7', false, true),
            ]
        ];
    }
}
