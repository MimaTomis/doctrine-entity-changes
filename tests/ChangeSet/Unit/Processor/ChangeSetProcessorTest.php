<?php

namespace Doctrine\Tests\ChangeSet\Unit\Processor;

use Doctrine\ORM\ChangeSet\ChangeSet;
use Doctrine\ORM\ChangeSet\ChangeSetCollector;
use Doctrine\ORM\ChangeSet\Exception\InvalidChangeSetException;
use Doctrine\ORM\ChangeSet\Exception\InvalidEntityException;
use Doctrine\ORM\ChangeSet\Field\DateField;
use Doctrine\ORM\ChangeSet\Field\StringField;
use Doctrine\ORM\ChangeSet\Processor\ChangeSetProcessor;
use Doctrine\ORM\ChangeSet\Processor\ProcessorFieldVisitorFactory;
use Doctrine\ORM\ChangeSet\Visitor\AbstractCommonFieldVisitor;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\Tests\ChangeSet\Entity\EntityA;
use Doctrine\Tests\ChangeSet\Entity\EntityB;
use Doctrine\Tests\ChangeSet\Entity\EntityC;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class ChangeSetProcessorTest
 * @package Doctrine\Tests\ChangeSet\Unit\Processor
 */
class ChangeSetProcessorTest extends TestCase
{
    /**
     * @var MockObject
     */
    private $changeSetCollectorMock;

    /**
     * @var ChangeSetProcessor
     */
    private $processor;

    /**
     * @inheritDoc
     */
    public function setUp()
    {
        parent::setUp();
        $this->changeSetCollectorMock = $this
            ->getMockBuilder(ChangeSetCollector::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->processor = new ChangeSetProcessor(
            $this->changeSetCollectorMock,
            new ProcessorFieldVisitorFactory(
                [
                    DateField::TYPE_TIME => 'H:i',
                    DateField::TYPE_DATETIME => 'm/d/Y H:i',
                    DateField::TYPE_DATE => 'm/d/Y',
                ],
                [
                    EntityA::class => [
                        AbstractCommonFieldVisitor::BOOLEAN_UNCHECKED => 'unchecked',
                        AbstractCommonFieldVisitor::BOOLEAN_CHECKED => 'checked',
                    ]
                ]
            )
        );
    }

    /**
     * @throws InvalidChangeSetException
     * @throws InvalidEntityException
     * @throws MappingException
     */
    public function testTopLevelChanges(): void
    {
        $changeSet = new ChangeSet(EntityA::class, null);
        $changeSet->addField(new StringField('field1', 'A', 'B'));

        $this->changeSetCollectorMock
            ->expects($this->any())
            ->method('collectChanges')
            ->willReturn([$changeSet]);

        $object = new EntityA();
        $fields = $this->processor->getChanges($object);

        $this->assertCount(1, $fields);
        $this->assertSame('field1', $fields[0]->getName());
    }

    /**
     * @throws InvalidChangeSetException
     * @throws InvalidEntityException
     * @throws MappingException
     */
    public function testRelatedChanges(): void
    {
        $changeSet = new ChangeSet(EntityA::class, null);
        $changeSet->addField(new StringField('field1', 'A', 'B'));

        $subChangeSet = new ChangeSet(EntityB::class, null, 'sub');
        $subChangeSet->addField(new StringField('field2', 'C', 'D'));
        $changeSet->addRelatedChangeSet($subChangeSet);

        $this->changeSetCollectorMock
            ->expects($this->any())
            ->method('collectChanges')
            ->willReturn([$changeSet]);

        $object = new EntityA();
        $fields = $this->processor->getChanges($object);

        $this->assertCount(2, $fields);
        $this->assertSame('field1', $fields[0]->getName());
        $this->assertSame('field2', $fields[1]->getName());
        $this->assertSame('sub.field2', $fields[1]->getName(true));
    }

    /**
     * @throws InvalidChangeSetException
     * @throws InvalidEntityException
     * @throws MappingException
     */
    public function testThirdLevelChanges(): void
    {
        $changeSet = new ChangeSet(EntityA::class, null);
        $changeSet->addField(new StringField('field1', 'A', 'B'));

        $subChangeSet = new ChangeSet(EntityB::class, null, 'sub');
        $subChangeSet->addField(new StringField('field2', 'C', 'D'));
        $changeSet->addRelatedChangeSet($subChangeSet);

        $thirdLevelChanges = new ChangeSet(EntityC::class, null, 'sub.third');
        $thirdLevelChanges->addField(new StringField('field3', 'E', 'F'));
        $subChangeSet->addRelatedChangeSet($thirdLevelChanges);

        $this->changeSetCollectorMock
            ->expects($this->any())
            ->method('collectChanges')
            ->willReturn([$changeSet]);

        $object = new EntityA();
        $fields = $this->processor->getChanges($object);

        $this->assertCount(3, $fields);
        $this->assertSame('field1', $fields[0]->getName());
        $this->assertSame('field2', $fields[1]->getName());
        $this->assertSame('sub.field2', $fields[1]->getName(true));
        $this->assertSame('field3', $fields[2]->getName());
        $this->assertSame('sub.third.field3', $fields[2]->getName(true));
    }
}
