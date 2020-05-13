<?php

namespace Doctrine\ORM\ChangeSet\Field;

use Doctrine\ORM\ChangeSet\AbstractField;
use Doctrine\ORM\ChangeSet\FieldVisitorInterface;

/**
 * Class FloatField
 * @package Doctrine\ORM\ChangeSet\Field
 */
class FloatField extends AbstractField
{
    /**
     * @var float|null
     */
    private $oldValue;

    /**
     * @var float|null
     */
    private $newValue;

    /**
     * DoubleField constructor.
     * @param string $name
     * @param float|null $oldValue
     * @param float|null $newValue
     */
    public function __construct(string $name, ?float $oldValue, ?float $newValue)
    {
        parent::__construct($name);

        $this->oldValue = $oldValue;
        $this->newValue = $newValue;
    }

    /**
     * @return float|null
     */
    public function getOldValue(): ?float
    {
        return $this->oldValue;
    }

    /**
     * @return float|null
     */
    public function getNewValue(): ?float
    {
        return $this->newValue;
    }

    /**
     * @param FieldVisitorInterface $visitor
     */
    public function accept(FieldVisitorInterface $visitor): void
    {
        $visitor->visitFloatField($this);
    }
}
