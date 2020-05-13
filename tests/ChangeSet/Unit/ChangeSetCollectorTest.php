<?php

namespace Doctrine\Tests\ChangeSet\Unit;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\UnitOfWork;
use Doctrine\ORM\ChangeSet\AbstractField;
use Doctrine\ORM\ChangeSet\ChangeSetCollector;
use Doctrine\ORM\ChangeSet\Exception\InvalidChangeSetException;
use Doctrine\ORM\ChangeSet\Exception\InvalidEntityException;
use Doctrine\ORM\ChangeSet\Field\BooleanField;
use Doctrine\ORM\ChangeSet\Field\DateField;
use Doctrine\ORM\ChangeSet\Field\EntityField;
use Doctrine\ORM\ChangeSet\Field\FloatField;
use Doctrine\ORM\ChangeSet\Field\IntegerField;
use Doctrine\ORM\ChangeSet\Field\StringField;
use Doctrine\ORM\ChangeSet\FieldVisitorInterface;
use Doctrine\Tests\ChangeSet\Entity\EntityA;
use Doctrine\Tests\ChangeSet\Entity\EntityB;
use Doctrine\Tests\ChangeSet\Entity\EntityC;
use Doctrine\Tests\ChangeSet\Entity\EntityD;
use PHPUnit\Framework\TestCase;

/**
 * Class ChangeSetCollectorTest
 * @package Doctrine\Tests\ChangeSet\Unit
 */
class ChangeSetCollectorTest extends TestCase
{
    private static $VISITOR_METHOD_BY_TYPES;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|EntityManager
     */
    private $manager;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|UnitOfWork
     */
    private $uow;

    /**
     * @inheritDoc
     */
    public function setUp()
    {
        parent::setUp();

        $this->initManagerAndUow();
    }

    /**
     * @throws \ReflectionException
     */
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        self::initVisitorMethodByType();
    }

    /**
     * @dataProvider dataProviderSimpleField
     *
     * @param object $entity
     * @param array $changes
     * @param array $types
     * @throws InvalidChangeSetException
     * @throws InvalidEntityException
     * @throws MappingException
     */
    public function testSimpleFieldChanged($entity, array $changes, array $types): void
    {
        $this->resetUow(true, true, true);
        $this->uow
            ->expects($this->atLeastOnce())
            ->method('getEntityChangeSet')
            ->willReturn($changes);

        $collector = new ChangeSetCollector($this->manager);
        $changeSet = $collector->collectChanges($entity);

        $this->assertCount(1, $changeSet);

        /** @var FieldVisitorInterface|\PHPUnit_Framework_MockObject_MockObject $visitor */
        $visitor = $this->createMock(FieldVisitorInterface::class);

        foreach ($types as $type => $fields) {
            $method = $this->getMethodByType($type);

            if ($method === null) {
                continue;
            }

            $visitor
                ->expects($this->exactly(count($fields)))
                ->method($method)
                ->with(
                    $this->logicalAnd(
                        $this->isInstanceOf($type),
                        $this->callback(function (AbstractField $field) use ($fields) {
                            return in_array($field->getName(), $fields);
                        })
                    )
                );
        }

        $changeSet[0]->applyVisitor($visitor);
    }

    /**
     * @throws InvalidChangeSetException
     * @throws InvalidEntityException
     * @throws MappingException
     */
    public function testNoChanges(): void
    {
        $this->resetUow(true, true, true);
        $this->uow
            ->expects($this->atLeastOnce())
            ->method('getEntityChangeSet')
            ->willReturn([]);

        $collector = new ChangeSetCollector($this->manager);
        $changeSet = $collector->collectChanges(new EntityA());

        $this->assertCount(0, $changeSet);
    }

    /**
     * This i try to test no changes when data / time objects contain different time for date field and different date for time fields
     *
     * @dataProvider dataProviderDateTimeNoChanges
     *
     * @param object $entity
     * @param array $changes
     * @throws InvalidChangeSetException
     * @throws InvalidEntityException
     * @throws MappingException
     */
    public function testDateTimeCompareWhenNoRealChanges($entity, array $changes): void
    {
        $this->resetUow(true, true, true);
        $this->uow
            ->expects($this->atLeastOnce())
            ->method('getEntityChangeSet')
            ->willReturn($changes);

        $collector = new ChangeSetCollector($this->manager);
        $changeSet = $collector->collectChanges($entity);

        $this->assertCount(0, $changeSet);
    }

    /**
     * @dataProvider dataProviderDateTimeWithChanges
     *
     * @param $entity
     * @param array $changes
     * @throws InvalidChangeSetException
     * @throws InvalidEntityException
     * @throws MappingException
     */
    public function testDateTimeCompareWithChanges($entity, array $changes): void
    {
        $this->resetUow(true, true, true);
        $this->uow
            ->expects($this->atLeastOnce())
            ->method('getEntityChangeSet')
            ->willReturn($changes);

        $collector = new ChangeSetCollector($this->manager);
        $changeSet = $collector->collectChanges($entity);

        $this->assertCount(1, $changeSet);

        /** @var FieldVisitorInterface|\PHPUnit_Framework_MockObject_MockObject $visitor */
        $visitor = $this->createMock(FieldVisitorInterface::class);

        $visitor
            ->expects($this->exactly(count($changes)))
            ->method('visitDateField')
            ->with(
                $this->logicalAnd(
                    $this->isInstanceOf(DateField::class),
                    $this->callback(function (DateField $field) use ($changes) {
                        return isset($changes[$field->getName()])
                            && $field->getOldValue() === $changes[$field->getName()][0]
                            && $field->getNewValue() === $changes[$field->getName()][1];
                    })
                )
            );

        $changeSet[0]->applyVisitor($visitor);
    }

    /**
     * @throws InvalidChangeSetException
     * @throws InvalidEntityException
     * @throws MappingException
     */
    public function testUpdateDeepInside(): void
    {
        $updatedEntity = new EntityC();
        $throughEntity = new EntityB();
        $throughEntity->addEntityC($updatedEntity);
        $sourceEntity = new EntityA();
        $sourceEntity->addEntityB($throughEntity);

        $this->resetUow(true, false, true);

        $this->uow
            ->expects($this->any())
            ->method('getScheduledEntityUpdates')
            ->willReturn([$updatedEntity]);

        $this->uow
            ->expects($this->atLeastOnce())
            ->method('getEntityChangeSet')
            ->willReturnCallback(function ($inputEntity) use ($updatedEntity) {
                if ($inputEntity === $updatedEntity) {
                    return [
                        'integer' => [1, 2],
                    ];
                }

                return [];
            });

        $collector = new ChangeSetCollector($this->manager);
        $changeSet = $collector->collectChanges($sourceEntity);

        $this->assertCount(1, $changeSet);

        $visitor = $this->createMock(FieldVisitorInterface::class);
        $visitor
            ->expects($this->exactly(1))
            ->method('visitIntegerField')
            ->with(
                $this->logicalAnd(
                    $this->isInstanceOf(IntegerField::class),
                    $this->callback(function (IntegerField $field) {
                        return $field->getName() === 'integer'
                            && $field->getName(true) === 'bCollection.cCollection.integer'
                            && $field->getOldValue() === 1
                            && $field->getNewValue() === 2;
                    })
                )
            );

        $changeSet[0]->applyVisitor($visitor, 'bCollection.cCollection.integer');
    }

    /**
     * @throws InvalidChangeSetException
     * @throws InvalidEntityException
     * @throws MappingException
     */
    public function testCollectWhenChangesIsNotRealAssociatedEntity(): void
    {
        $updatedEntity = new EntityC();
        $sourceEntity = new EntityA();

        $this->resetUow(true, false, true);

        $this->uow
            ->expects($this->any())
            ->method('getScheduledEntityUpdates')
            ->willReturn([$updatedEntity]);

        $this->uow
            ->expects($this->atLeastOnce())
            ->method('getEntityChangeSet')
            ->willReturnCallback(function ($inputEntity) use ($updatedEntity) {
                if ($inputEntity === $updatedEntity) {
                    return [
                        'integer' => [1, 2],
                    ];
                }

                return [];
            });

        $collector = new ChangeSetCollector($this->manager);
        $changeSet = $collector->collectChanges($sourceEntity);

        $this->assertCount(0, $changeSet);
    }

    /**
     * @throws InvalidChangeSetException
     * @throws InvalidEntityException
     * @throws MappingException
     */
    public function testAssociationNotNewAndNotChanged(): void
    {
        $this->resetUow(true, true, true);

        $sourceEntity = new EntityA();
        $associatedEntity = new EntityD();
        $sourceEntity->setEntityD($associatedEntity);

        $this->manager
            ->getClassMetadata(EntityD::class)
            ->setIdentifierValues($associatedEntity, ['id' => 111]);

        $this->manager
            ->expects($this->any())
            ->method('contains')
            ->willReturn(true);

        $this->uow
            ->expects($this->any())
            ->method('getEntityChangeSet')
            ->willReturnCallback(function ($inputEntity) use ($sourceEntity, $associatedEntity) {
                if ($inputEntity === $sourceEntity) {
                    return ['dEntity' => [null, $associatedEntity]];
                }

                return [];
            });

        $collector = new ChangeSetCollector($this->manager);
        $changeSet = $collector->collectChanges($sourceEntity);

        $this->assertCount(1, $changeSet);

        $visitor = $this->createMock(FieldVisitorInterface::class);
        $visitor
            ->expects($this->exactly(1))
            ->method('visitEntityField')
            ->with(
                $this->logicalAnd(
                    $this->isInstanceOf(EntityField::class),
                    $this->callback(function (EntityField $field) {
                        return $field->getOldIdentifier('id') === null
                            && (int) $field->getNewIdentifier('id') === 111
                            && $field->getName() === 'dEntity';
                    })
                )
            );

        $changeSet[0]->applyVisitor($visitor);
    }

    /**
     * @throws InvalidChangeSetException
     * @throws InvalidEntityException
     * @throws MappingException
     */
    public function testAssociationInsertNewRelationInCollection(): void
    {
        $this->resetUow(false, true, true);

        $sourceEntity = new EntityA();
        $insertedEntity = new EntityB();

        $sourceEntity->addEntityB($insertedEntity);

        $this->uow
            ->expects($this->any())
            ->method('getEntityChangeSet')
            ->willReturnCallback(function ($inputEntity) use ($insertedEntity) {
                if ($inputEntity === $insertedEntity) {
                    return [
                        'string' => [null, 'ABC']
                    ];
                }

                return [];
            });

        $this->uow
            ->expects($this->any())
            ->method('getScheduledEntityInsertions')
            ->willReturn([$insertedEntity]);

        $collector = new ChangeSetCollector($this->manager);
        $changeSet = $collector->collectChanges($sourceEntity);

        $this->assertCount(1, $changeSet);

        $visitor = $this->createMock(FieldVisitorInterface::class);
        $visitor
            ->expects($this->never())
            ->method($this->anything());

        $changeSet[0]->applyVisitor($visitor);

        $this->assertCount(1, $changeSet[0]->getRelatedChangeSets());

        $visitor = $this->createMock(FieldVisitorInterface::class);
        $visitor
            ->expects($this->exactly(1))
            ->method('visitStringField')
            ->with(
                $this->callback(function (StringField $field) {
                    return $field->getName() === 'string'
                        && $field->getNewValue() === 'ABC'
                        && $field->getOldValue() === null;
                })
            );

        $changeSet[0]->getRelatedChangeSets()[0]->applyVisitor($visitor);
    }

    /**
     * @throws InvalidChangeSetException
     * @throws InvalidEntityException
     * @throws MappingException
     */
    public function testCollectWhenDeleteEntityFromCollection(): void
    {
        $this->resetUow(true, true, false);

        $sourceEntity = new EntityA();
        $insertedEntity = new EntityB();
        $insertedEntity->setInteger(1);
        $insertedEntity->setString('abc');

        $this->uow
            ->expects($this->any())
            ->method('getEntityChangeSet')
            ->willReturn([]);

        $this->uow
            ->expects($this->any())
            ->method('getScheduledCollectionUpdates')
            ->willReturnCallback(function () use ($sourceEntity, $insertedEntity) {
                // Emulate persistent collection change
                $collection = new PersistentCollection(
                    $this->manager,
                    $this->manager->getClassMetadata(EntityB::class),
                    new ArrayCollection([$insertedEntity])
                );
                $collection->takeSnapshot();
                $collection->removeElement($insertedEntity);
                $collection->setInitialized(true);
                $collection->setOwner(
                    $sourceEntity,
                    $this->manager
                        ->getClassMetadata(EntityA::class)
                        ->getAssociationMapping('bCollection')
                );

                return [$collection];
            });

        $collector = new ChangeSetCollector($this->manager);
        $changeSet = $collector->collectChanges($sourceEntity);

        $this->assertCount(1, $changeSet);
        $this->assertCount(1, $changeSet[0]->getRelatedChangeSets());

        $visitor = $this->createMock(FieldVisitorInterface::class);
        $visitor
            ->expects($this->never())
            ->method($this->anything());

        $changeSet[0]->applyVisitor($visitor);

        $visitor = $this->createMock(FieldVisitorInterface::class);
        $visitor
            ->expects($this->atLeastOnce())
            ->method($this->anything())
            ->with(
                $this->logicalAnd(
                    $this->isInstanceOf(AbstractField::class),
                    $this->callback(function (AbstractField $field) {
                        return strpos($field->getName(true), 'bCollection.') !== false
                            && (
                                $field instanceof DateField && $field->getNewValue() === null
                                || $field instanceof StringField && $field->getNewValue() === null
                                || $field instanceof FloatField && $field->getNewValue() === null
                                || $field instanceof IntegerField && $field->getNewValue() === null
                                || $field instanceof BooleanField && $field->getNewValue() === null
                            );
                    })
                )
            );

        $changeSet[0]->applyVisitor($visitor, 'bCollection');
    }

    /**
     * @throws InvalidChangeSetException
     * @throws InvalidEntityException
     * @throws MappingException
     */
    public function testInsertCollectionUpdateDeepInside(): void
    {
        $this->resetUow(true, true, false);

        $sourceEntity = new EntityA();
        $subSourceEntity = new EntityB();
        $insertedEntity = new EntityC();
        $insertedEntity->setInteger(23);

        $sourceEntity->addEntityB($subSourceEntity);
        $subSourceEntity->addEntityC($insertedEntity);

        $this->uow
            ->expects($this->any())
            ->method('getEntityChangeSet')
            ->willReturn([]);

        $this->uow
            ->expects($this->any())
            ->method('getScheduledCollectionUpdates')
            ->willReturnCallback(function () use ($sourceEntity, $insertedEntity) {
                // Emulate persistent collection change
                $collection = new PersistentCollection(
                    $this->manager,
                    $this->manager->getClassMetadata(EntityC::class),
                    new ArrayCollection()
                );
                $collection->takeSnapshot();
                $collection->add($insertedEntity);
                $collection->setInitialized(true);
                $collection->setOwner(
                    $sourceEntity,
                    $this->manager
                        ->getClassMetadata(EntityB::class)
                        ->getAssociationMapping('cCollection')
                );

                return [$collection];
            });

        $collector = new ChangeSetCollector($this->manager);
        $changeSet = $collector->collectChanges($sourceEntity);

        $this->assertCount(1, $changeSet);
        $this->assertCount(1, $changeSet[0]->getRelatedChangeSets());
        $this->assertCount(1, $changeSet[0]->getRelatedChangeSets()[0]->getRelatedChangeSets());

        $visitor = $this->createMock(FieldVisitorInterface::class);
        $visitor
            ->expects($this->never())
            ->method($this->anything());

        $changeSet[0]->applyVisitor($visitor);

        $visitor = $this->createMock(FieldVisitorInterface::class);
        $visitor
            ->expects($this->atLeastOnce())
            ->method($this->anything())
            ->with(
                $this->logicalAnd(
                    $this->isInstanceOf(AbstractField::class),
                    $this->callback(function (AbstractField $field) {
                        return strpos($field->getName(true), 'bCollection.cCollection.') !== false
                            && (
                                $field instanceof DateField && $field->getOldValue() === null
                                || $field instanceof StringField && $field->getOldValue() === null
                                || $field instanceof FloatField && $field->getOldValue() === null
                                || $field instanceof IntegerField && $field->getOldValue() === null
                                || $field instanceof BooleanField && $field->getOldValue() === null
                            );
                    })
                )
            );

        $changeSet[0]->applyVisitor($visitor, 'bCollection.cCollection');
    }

    /**
     * @throws InvalidChangeSetException
     * @throws InvalidEntityException
     * @throws MappingException
     */
    public function testIdentifierChangesIgnore(): void
    {
        $this->resetUow(true, true, true);

        $sourceEntity = new EntityA();
        $this->uow
            ->expects($this->any())
            ->method('getEntityChangeSet')
            ->willReturn([
                'id' => [null, 1]
            ]);

        $collector = new ChangeSetCollector($this->manager);
        $changeSet = $collector->collectChanges($sourceEntity);

        $this->assertCount(0, $changeSet);
    }

    /**
     * Doctrine UoW can return array of changes without real difference (false => '0', as example)
     *
     * @dataProvider dataProviderChangesWithoutRealChanges
     *
     * @param object $entity
     * @param array $changes
     * @throws InvalidChangeSetException
     * @throws InvalidEntityException
     * @throws MappingException
     */
    public function testNoChangesWithDifferentTypes($entity, array $changes): void
    {
        $this->resetUow(true, true, true);
        $this->uow
            ->expects($this->atLeastOnce())
            ->method('getEntityChangeSet')
            ->willReturn($changes);

        $collector = new ChangeSetCollector($this->manager);
        $changeSet = $collector->collectChanges($entity);

        $this->assertCount(0, $changeSet);
    }


    /**
     * @return array
     * @throws \Exception
     */
    public function dataProviderSimpleField(): array
    {
        return [
            [
                new EntityA(),
                [
                    'integer' => [1, 2],
                    'string' => ['TEST', 'NOT TEST'],
                    'float' => [1.11, 1.11112],
                    'decimal' => [1.11, 1.11112],
                    'boolean' => [false, true],
                    'date' => [null, new \DateTime()],
                    'time' => [null, new \DateTime()],
                    'dateTime' => [null, new \DateTime()],
                    'smallInt' => [0, 1],
                    'bigInt' => [111111111, 111111112],
                ],
                [
                    IntegerField::class => ['integer', 'smallInt', 'bigInt'],
                    StringField::class => ['string'],
                    BooleanField::class => ['boolean'],
                    DateField::class => ['date', 'time', 'dateTime'],
                    FloatField::class => ['float', 'decimal'],
                ],
            ],
        ];
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function dataProviderDateTimeNoChanges(): array
    {
        return [
            [
                new EntityA(),
                [
                    'date' => [
                        \DateTime::createFromFormat('Y-m-d H:i:s', '2019-01-01 12:02:03'),
                        \DateTime::createFromFormat('Y-m-d H:i:s', '2019-01-01 14:03:03')
                    ],
                ]
            ],
            [
                new EntityA(),
                [
                    'time' => [
                        \DateTime::createFromFormat('Y-m-d H:i:s', '2020-03-33 12:02:03'),
                        \DateTime::createFromFormat('Y-m-d H:i:s', '2019-01-01 12:02:03')
                    ],
                ]
            ],
            [
                new EntityA(),
                [
                    'dateTime' => [
                        \DateTime::createFromFormat('Y-m-d H:i:s', '2020-03-33 12:02:03'),
                        \DateTime::createFromFormat('Y-m-d H:i:s', '2020-03-33 12:02:03')
                    ],
                ]
            ]
        ];
    }

    /**
     * @return array
     */
    public function dataProviderDateTimeWithChanges(): array
    {
        return [
            [
                new EntityA(),
                [
                    'date' => [
                        \DateTime::createFromFormat('Y-m-d', '2019-01-02'),
                        \DateTime::createFromFormat('Y-m-d', '2019-01-01')
                    ],
                ]
            ],
            [
                new EntityA(),
                [
                    'time' => [
                        \DateTime::createFromFormat('Y-m-d H:i:s', '2019-01-01 12:02:04'),
                        \DateTime::createFromFormat('Y-m-d H:i:s', '2019-01-01 12:02:03')
                    ],
                ]
            ],
            [
                new EntityA(),
                [
                    'dateTime' => [
                        \DateTime::createFromFormat('Y-m-d H:i:s', '2019-01-01 15:03:03'),
                        \DateTime::createFromFormat('Y-m-d H:i:s', '2019-01-01 14:03:03')
                    ]
                ]
            ]
        ];
    }

    /**
     * @return array
     */
    public function dataProviderChangesWithoutRealChanges(): array
    {
        return [
            [
                new EntityA(),
                [
                    'boolean' => [false, '0'],
                ],
            ],
            [
                new EntityA(),
                [
                    'boolean' => [true, '1'],
                ],
            ],
            [
                new EntityA(),
                [
                    'boolean' => [0, '0'],
                ],
            ],
            [
                new EntityA(),
                [
                    'boolean' => [1, '1'],
                ],
            ],
            [
                new EntityA(),
                [
                    'float' => [3.000000001, 3.0],
                ],
            ],
            [
                new EntityA(),
                [
                    'decimal' => [2, 2.000000001],
                ],
            ],
            [
                new EntityA(),
                [
                    'float' => [2.000000, 2.000004],
                ],
            ],
            [
                new EntityA(),
                [
                    'integer' => [2.000000, 2.7],
                ],
            ],
        ];
    }

    /**
     * @param string $type
     * @return string|null
     */
    private function getMethodByType(string $type): ?string
    {
        return self::$VISITOR_METHOD_BY_TYPES[$type] ?? null;
    }

    /**
     * Initialize list of methos per type from FieldVisitorInterface
     *
     * @throws \ReflectionException
     */
    private static function initVisitorMethodByType(): void
    {
        if (self::$VISITOR_METHOD_BY_TYPES === null) {
            self::$VISITOR_METHOD_BY_TYPES = [];

            $class = new \ReflectionClass(FieldVisitorInterface::class);
            $methods = $class->getMethods();

            foreach ($methods as $method) {
                if (
                    strpos($method->getName(), 'visit') === 0
                    && $method->getNumberOfParameters() > 0
                ) {
                    $parameter = $method->getParameters()[0];
                    self::$VISITOR_METHOD_BY_TYPES[$parameter->getType()->getName()] = $method->getName();
                }
            }
        }
    }

    /**
     * Initialize EM and UoW mocks
     */
    private function initManagerAndUow(): void
    {
        $testManager = $this->getTestEntityManager();

        $uow = $this->getMockBuilder(UnitOfWork::class)
            ->disableOriginalConstructor()
            ->getMock();

        $manager = $this->getMockBuilder(EntityManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        // must return mock uow
        $manager
            ->expects($this->any())
            ->method('getUnitOfWork')
            ->willReturn($uow);

        // must return real factory
        $manager
            ->expects($this->any())
            ->method('getMetadataFactory')
            ->willReturn($testManager->getMetadataFactory());

        // must return real metadata
        $manager
            ->expects($this->any())
            ->method('getClassMetadata')
            ->willReturnCallback([$testManager, 'getClassMetadata']);

        $this->manager = $manager;
        $this->uow = $uow;
    }

    /**
     * @param bool $resetScheduledInsertions
     * @param bool $resetScheduledUpdates
     * @param bool $resetScheduledCollectionUpdates
     */
    private function resetUow(bool $resetScheduledInsertions, bool $resetScheduledUpdates, bool $resetScheduledCollectionUpdates): void
    {
        if ($resetScheduledInsertions) {
            $this->uow
                ->expects($this->any())
                ->method('getScheduledEntityInsertions')
                ->willReturn([]);
        }

        if ($resetScheduledUpdates) {
            $this->uow
                ->expects($this->any())
                ->method('getScheduledEntityUpdates')
                ->willReturn([]);
        }

        if ($resetScheduledCollectionUpdates) {
            $this->uow
                ->expects($this->any())
                ->method('getScheduledCollectionUpdates')
                ->willReturn([]);
        }
    }

    /**
     * @return mixed
     * @throws \ReflectionException
     * @throws ORMException
     */
    private function getTestEntityManager()
    {
        $config = new Configuration();

        $config->setMetadataCacheImpl(new ArrayCache());
        $config->setQueryCacheImpl(new ArrayCache());
        $config->setProxyDir(__DIR__ . '/Proxies');
        $config->setProxyNamespace('Doctrine\Tests\ChangeSet\Proxies');
        $config->setMetadataDriverImpl(
            $config->newDefaultAnnotationDriver(
                [
                    realpath(__DIR__)
                ],
                false
            )
        );

        $conn = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();

        $conn->expects($this->any())
            ->method('getEventManager')
            ->willReturn(new EventManager());

        $conn->expects($this->any())
            ->method('getDatabasePlatform')
            ->willReturn(new MySqlPlatform());

        return EntityManager::create($conn, $config);
    }
}
