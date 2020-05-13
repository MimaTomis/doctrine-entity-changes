<?php

namespace Doctrine\ORM\ChangeSet\Visitor;

use Doctrine\ORM\ChangeSet\AbstractField;
use Doctrine\ORM\ChangeSet\Field\BooleanField;
use Doctrine\ORM\ChangeSet\Field\DateField;
use Doctrine\ORM\ChangeSet\Field\EntityField;
use Doctrine\ORM\ChangeSet\Field\FloatField;
use Doctrine\ORM\ChangeSet\Field\IntegerField;
use Doctrine\ORM\ChangeSet\Field\StringField;
use Doctrine\ORM\ChangeSet\FieldVisitorInterface;

/**
 * Class CallbackFieldVisitor
 * @package Doctrine\ORM\ChangeSet\Visitor
 */
class CallbackFieldVisitor implements FieldVisitorInterface
{
    /**
     * @var string
     */
    private $fieldType;

    /**
     * @var callable
     */
    private $callback;

    /**
     * CallbackFieldVisitor constructor.
     * @param string $fieldType
     * @param callable $callback
     */
    public function __construct(string $fieldType, callable $callback)
    {
        $this->fieldType = $fieldType;
        $this->callback = $callback;
    }

    /**
     * @inheritDoc
     */
    public function visitDateField(DateField $field): void
    {
        $this->executeCallback($field);
    }

    /**
     * @inheritDoc
     */
    public function visitIntegerField(IntegerField $field): void
    {
        $this->executeCallback($field);
    }

    /**
     * @inheritDoc
     */
    public function visitStringField(StringField $field): void
    {
        $this->executeCallback($field);
    }

    /**
     * @inheritDoc
     */
    public function visitEntityField(EntityField $field): void
    {
        $this->executeCallback($field);
    }

    /**
     * @inheritDoc
     */
    public function visitBooleanField(BooleanField $field): void
    {
        $this->executeCallback($field);
    }

    /**
     * @inheritDoc
     */
    public function visitFloatField(FloatField $field): void
    {
        $this->executeCallback($field);
    }

    /**
     * @param AbstractField $field
     */
    private function executeCallback(AbstractField $field): void
    {
        if (get_class($field) === $this->fieldType) {
            call_user_func($this->callback, $field);
        }
    }
}
