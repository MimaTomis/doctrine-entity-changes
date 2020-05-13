<?php

namespace Doctrine\ORM\ChangeSet\Field;

use Doctrine\ORM\ChangeSet\AbstractField;
use Doctrine\ORM\ChangeSet\FieldVisitorInterface;

/**
 * Class IntegerField
 * @package Doctrine\ORM\ChangeSet\Field
 */
class IntegerField extends AbstractField
{
    /**
     * @var int|null
     */
    private $oldValue;

    /**
     * @var int|null
     */
    private $newValue;

    /**
     * IntegerField constructor.
     * @param string $name
     * @param int|null $oldValue
     * @param int|null $newValue
     */
    public function __construct(string $name, ?int $oldValue, ?int $newValue)
    {
        parent::__construct($name);

        $this->oldValue = $oldValue;
        $this->newValue = $newValue;
    }

    /**
     * @return int|null
     */
    public function getOldValue(): ?int
    {
        return $this->oldValue;
    }

    /**
     * @return int|null
     */
    public function getNewValue(): ?int
    {
        return $this->newValue;
    }

    /**
     * @inheritDoc
     */
    public function accept(FieldVisitorInterface $visitor): void
    {
        $visitor->visitIntegerField($this);
    }
}
