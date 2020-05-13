<?php

namespace Doctrine\ORM\ChangeSet\Field;

use Doctrine\ORM\ChangeSet\AbstractField;
use Doctrine\ORM\ChangeSet\FieldVisitorInterface;

/**
 * Class DateField
 * @package Doctrine\ORM\ChangeSet\Field
 */
class DateField extends AbstractField
{
    public const TYPE_DATE = 'date';
    public const TYPE_DATETIME = 'datetime';
    public const TYPE_TIME = 'time';

    /**
     * @var \DateTime|null
     */
    private $oldValue;

    /**
     * @var \DateTime|null
     */
    private $newValue;

    /**
     * @var string
     */
    private $type;

    /**
     * DateField constructor.
     * @param string $name
     * @param string $type
     * @param \DateTime|null $oldValue
     * @param \DateTime|null $newValue
     */
    public function __construct(string $name, string $type, ?\DateTime $oldValue, ?\DateTime $newValue)
    {
        parent::__construct($name);

        $this->oldValue = $oldValue;
        $this->newValue = $newValue;
        $this->type = $type;
    }

    /**
     * @return bool
     */
    public function isDate(): bool
    {
        return $this->type === self::TYPE_DATE;
    }

    /**
     * @return bool
     */
    public function isDateTime(): bool
    {
        return $this->type === self::TYPE_DATETIME;
    }

    /**
     * @return bool
     */
    public function isTime(): bool
    {
        return $this->type === self::TYPE_TIME;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return \DateTime|null
     */
    public function getOldValue(): ?\DateTime
    {
        return $this->oldValue;
    }

    /**
     * @return \DateTime|null
     */
    public function getNewValue(): ?\DateTime
    {
        return $this->newValue;
    }

    /**
     * @param FieldVisitorInterface $visitor
     */
    public function accept(FieldVisitorInterface $visitor): void
    {
        $visitor->visitDateField($this);
    }
}
