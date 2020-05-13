<?php

namespace Doctrine\ORM\ChangeSet\Field;

use Doctrine\ORM\ChangeSet\AbstractField;
use Doctrine\ORM\ChangeSet\FieldVisitorInterface;

/**
 * Class StringField
 * @package Doctrine\ORM\ChangeSet\Field
 */
class StringField extends AbstractField
{
    /**
     * @var string|null
     */
    private $oldValue;

    /**
     * @var string|null
     */
    private $newValue;

    /**
     * StringField constructor.
     * @param string $name
     * @param string|null $oldValue
     * @param string|null $newValue
     */
    public function __construct(string $name, ?string $oldValue, ?string $newValue)
    {
        parent::__construct($name);

        $this->oldValue = $oldValue;
        $this->newValue = $newValue;
    }

    /**
     * @return string|null
     */
    public function getOldValue(): ?string
    {
        return $this->oldValue;
    }

    /**
     * @return string|null
     */
    public function getNewValue(): ?string
    {
        return $this->newValue;
    }

    /**
     * @inheritDoc
     */
    public function accept(FieldVisitorInterface $visitor): void
    {
        $visitor->visitStringField($this);
    }
}
