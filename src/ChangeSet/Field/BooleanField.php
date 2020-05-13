<?php

namespace Doctrine\ORM\ChangeSet\Field;

use Doctrine\ORM\ChangeSet\AbstractField;
use Doctrine\ORM\ChangeSet\FieldVisitorInterface;

/**
 * Class BooleanField
 * @package Doctrine\ORM\ChangeSet\Field
 */
class BooleanField extends AbstractField
{
    /**
     * @var bool|null
     */
    private $oldValue;

    /**
     * @var bool|null
     */
    private $newValue;

    /**
     * BooleanField constructor.
     * @param string $name
     * @param bool|null $oldValue
     * @param bool|null $newValue
     */
    public function __construct(string $name, ?bool $oldValue, ?bool $newValue)
    {
        parent::__construct($name);

        $this->oldValue = $oldValue;
        $this->newValue = $newValue;
    }

    /**
     * @return bool|null
     */
    public function getOldValue(): ?bool
    {
        return $this->oldValue;
    }

    /**
     * @return bool|null
     */
    public function getNewValue(): ?bool
    {
        return $this->newValue;
    }

    /**
     * @param FieldVisitorInterface $visitor
     */
    public function accept(FieldVisitorInterface $visitor): void
    {
        $visitor->visitBooleanField($this);
    }
}
