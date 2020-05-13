<?php

namespace Doctrine\ORM\ChangeSet\Field;

use Doctrine\ORM\ChangeSet\AbstractField;
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
     * @var array|null
     */
    private $oldIdentifiers;

    /**
     * @var array|null
     */
    private $newIdentifiers;

    /**
     * EntityField constructor.
     * @param string $name
     * @param string $entityClass
     * @param array|null $oldIdentifiers
     * @param array|null $newIdentifiers
     */
    public function __construct(string $name, string $entityClass, ?array $oldIdentifiers, ?array $newIdentifiers)
    {
        parent::__construct($name);
        $this->entityClass = $entityClass;
        $this->oldIdentifiers = $oldIdentifiers;
        $this->newIdentifiers = $newIdentifiers;
    }

    /**
     * @return string
     */
    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    /**
     * @return array|null
     */
    public function getOldIdentifiers(): ?array
    {
        return $this->oldIdentifiers;
    }

    /**
     * @param string $fieldName
     * @return string|null
     */
    public function getOldIdentifier(string $fieldName): ?string
    {
        return $this->oldIdentifiers[$fieldName] ?? null;
    }

    /**
     * @return array|null
     */
    public function getNewIdentifiers(): ?array
    {
        return $this->newIdentifiers;
    }

    /**
     * @param string $fieldName
     * @return string|null
     */
    public function getNewIdentifier(string $fieldName): ?string
    {
        return $this->newIdentifiers[$fieldName] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function accept(FieldVisitorInterface $visitor): void
    {
        $visitor->visitEntityField($this);
    }
}
