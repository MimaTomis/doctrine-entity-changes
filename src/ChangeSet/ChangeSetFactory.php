<?php

namespace Doctrine\ORM\ChangeSet;

use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\UnitOfWork;

/**
 * Class ChangeSetFactory
 * @package Doctrine\ORM\ChangeSet
 */
class ChangeSetFactory implements ChangeSetFactoryInterface
{
    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var UnitOfWork
     */
    private $unitOfWork;

    /**
     * ChangeSetFactory constructor.
     * @param EntityManager $entityManager
     */
    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->unitOfWork = $this->entityManager->getUnitOfWork();
    }

    /**
     * @inheritDoc
     */
    public function createChangeSet($entity, string $namespace): ChangeSet
    {
        $className = ClassUtils::getClass($entity);
        $identifier = $this->unitOfWork->isInIdentityMap($entity) ?
                      new EntityIdentifier($this->unitOfWork->getEntityIdentifier($entity)) : null;

        return new ChangeSet(
            $className,
            $identifier,
            $namespace
        );
    }
}
