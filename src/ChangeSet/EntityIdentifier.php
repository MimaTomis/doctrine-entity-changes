<?php

namespace Doctrine\ORM\ChangeSet;

/**
 * Class EntityIdentifier
 * @package Doctrine\ORM\ChangeSet\Field
 */
class EntityIdentifier
{
    /**
     * @var array
     */
    private $identifiers;

    /**
     * EntityIdentifier constructor.
     * @param array $identifiers
     */
    public function __construct(array $identifiers)
    {
        $this->identifiers = $identifiers;
    }

    /**
     * Get all identifier fields
     *
     * @return array
     */
    public function getFields(): array
    {
        return array_keys($this->identifiers);
    }

    /**
     * Get value of identifier field or null.
     * Always return string or null, if you identifier field is integer, do not forget convert type.
     *
     * @param string $field
     * @return string|null
     */
    public function getValue(string $field): ?string
    {
        return $this->identifiers[$field] ?? null;
    }

    /**
     * Check if is single-field identifier
     *
     * @return bool
     */
    public function isSingleField(): bool
    {
        return count($this->identifiers) === 1;
    }

    /**
     * Get identifier value, if is no single-field identifier return null
     *
     * @return string|null
     */
    public function getSingleValue(): ?string
    {
        return $this->isSingleField() ? reset($this->identifiers) : null;
    }
}
