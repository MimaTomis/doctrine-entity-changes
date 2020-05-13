<?php

namespace Doctrine\Tests\ChangeSet\Integration;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Statement;
use Doctrine\ORM\ChangeSet\ChangeSetCollector;
use Doctrine\ORM\ChangeSet\Exception\InvalidChangeSetException;
use Doctrine\ORM\ChangeSet\Exception\InvalidEntityException;
use Doctrine\ORM\ChangeSet\Field\StringField;
use Doctrine\ORM\ChangeSet\FieldVisitorInterface;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\TransactionRequiredException;
use Doctrine\Tests\ChangeSet\Entity\EntityA;
use PHPUnit\Framework\TestCase;

/**
 * Class ChangeSetCollectorTest
 * @package Doctrine\Tests\ChangeSet\Integration
 */
class ChangeSetCollectorTest extends TestCase
{
    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $conn;

    /**
     * @throws ORMException
     */
    protected function setUp()
    {
        parent::setUp();

        $this->initTestEntityManager();
    }

    /**
     * @throws InvalidChangeSetException
     * @throws InvalidEntityException
     * @throws MappingException
     */
    public function testNewEntityChangeSetWithoutPersistCall(): void
    {
        $entityA = new EntityA();
        $entityA->setString('String');
        $entityA->setInteger(1);

        $changeSetCollector = new ChangeSetCollector($this->em);
        $changeSets = $changeSetCollector->collectChanges($entityA);

        $this->assertCount(1, $changeSets);
    }

    /**
     * @throws InvalidChangeSetException
     * @throws InvalidEntityException
     * @throws MappingException
     * @throws ORMException
     */
    public function testNewEntityChangeSetWithPersistCall(): void
    {
        $entityA = new EntityA();
        $entityA->setString('String');
        $entityA->setInteger(1);

        $this->em->persist($entityA);

        $changeSetCollector = new ChangeSetCollector($this->em);
        $changeSets = $changeSetCollector->collectChanges($entityA);

        $this->assertCount(1, $changeSets);
    }

    /**
     * @dataProvider entityDataProvider
     *
     * @param array $entityData
     * @throws InvalidChangeSetException
     * @throws InvalidEntityException
     * @throws MappingException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    public function testChangeSetOfExistentEntity(array $entityData): void
    {
        $stmt = $this->getMockBuilder(Statement::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->conn
            ->expects($this->any())
            ->method('executeQuery')
            ->willReturn($stmt);

        $stmt
            ->expects($this->any())
            ->method('fetch')
            ->willReturnOnConsecutiveCalls(
                $entityData,
                // prevent fetching rows
                false
            );

        /** @var EntityA $entityA */
        $entityA = $this->em->find(EntityA::class, 1);
        $entityA->setString('NEW STRING');

        $changeSetCollector = new ChangeSetCollector($this->em);
        $changeSets = $changeSetCollector->collectChanges($entityA);

        $this->assertCount(1, $changeSets);

        $visitor = $this->createMock(FieldVisitorInterface::class);
        $visitor
            ->expects($this->atLeastOnce())
            ->method($this->anything())
            ->with(
                $this->logicalAnd(
                    $this->isInstanceOf(StringField::class),
                    $this->callback(function (StringField $field) {
                        return $field->getName() === 'string'
                            && $field->getOldValue() === 'OLD STRING'
                            && $field->getNewValue() === 'NEW STRING';
                    })
                )
            );

        $changeSets[0]->applyVisitor($visitor);
    }

    /**
     * @dataProvider entityDataProvider
     * @param array $entityData
     * @throws InvalidChangeSetException
     * @throws InvalidEntityException
     * @throws MappingException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    public function testChangeSetNoChanges(array $entityData): void
    {
        $stmt = $this->getMockBuilder(Statement::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->conn
            ->expects($this->any())
            ->method('executeQuery')
            ->willReturn($stmt);

        $stmt
            ->expects($this->any())
            ->method('fetch')
            ->willReturnOnConsecutiveCalls(
                $entityData,
                // prevent fetching rows
                false
            );

        /** @var EntityA $entityA */
        $entityA = $this->em->find(EntityA::class, 1);
        $entityA->setString('OLD STRING');

        $changeSetCollector = new ChangeSetCollector($this->em);
        $changeSets = $changeSetCollector->collectChanges($entityA);

        $this->assertCount(0, $changeSets);
    }

    /**
     * @return array
     */
    public function entityDataProvider(): array
    {
        return [
            [
                [
                    'id_1' => 1,
                    'integer_2' => 2,
                    'string_3' => 'OLD STRING',
                    'boolean_4' => false,
                    'date_5' => '2020-01-02',
                    'time_6' => '05:10:16',
                    'date_time_7' => '2020-02-03 04:12:32',
                    'small_int_8' => 11,
                    'big_int_9' => 12,
                    'float_10' => 1.1,
                    'decimal_11' => 3.3,
                    'd_entity_id_12' => null,
                ]
            ]
        ];
    }

    /**
     * @return mixed
     * @throws ORMException
     */
    private function initTestEntityManager(): void
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

        $this->em = EntityManager::create($conn, $config);
        $this->conn = $conn;
    }
}