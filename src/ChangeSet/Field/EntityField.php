<?php

namespace Doctrine\ORM\ChangeSet\Field;

use Doctrine\ORM\ChangeSet\AbstractField;
use Doctrine\ORM\ChangeSet\EntityIdentifier;
use Doctrine\ORM\ChangeSet\FieldVisitorInterface;

/**
 * Class EntityField
 * @package Doctrine\ORM\ChangeSet\Field
 */
class EntityField extends AbstractField
{
    /**
     * @var string
     */
    private $entityClass;

    /**
     * @var EntityIdentifier|null
     */
    private $oldIdentifier;

    /**
     * @var EntityIdentifier|null
     */
    private $newIdentifier;

    /**
     * EntityField constructor.
     * @param string $name
     * @param string $entityClass
     * @param EntityIdentifier|null $oldIdentifier
     * @param EntityIdentifier|null $newIdentifier
     */
    public function __construct(string $name, string $entityClass, ?EntityIdentifier $oldIdentifier, ?EntityIdentifier $newIdentifier)
    {
        parent::__construct($name);
        $this->entityClass = $entityClass;
        $this->oldIdentifier = $oldIdentifier;
        $this->newIdentifier = $newIdentifier;
    }

    /**
     * @return string
     */
    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    /**
     * @return EntityIdentifier|null
     */
    public function getOldIdentifier(): ?EntityIdentifier
    {
        return $this->oldIdentifier;
    }

    /**
     * @return EntityIdentifier|null
     */
    public function getNewIdentifier(): ?EntityIdentifier
    {
        return $this->newIdentifier;
    }

    /**
     * @inheritDoc
     */
    public function accept(FieldVisitorInterface $visitor): void
    {
        $visitor->visitEntityField($this);
    }
}
