<?php

namespace Doctrine\ORM\ChangeSet\Visitor;

use Doctrine\ORM\ChangeSet\Field\BooleanField;
use Doctrine\ORM\ChangeSet\Field\DateField;
use Doctrine\ORM\ChangeSet\Field\EntityField;
use Doctrine\ORM\ChangeSet\Field\FloatField;
use Doctrine\ORM\ChangeSet\Field\IntegerField;
use Doctrine\ORM\ChangeSet\Field\StringField;
use Doctrine\ORM\ChangeSet\FieldVisitorInterface;

/**
 * Class AbstractEmptyFieldVisitor
 * @package Doctrine\ORM\ChangeSet\Visitor
 */
abstract class AbstractEmptyFieldVisitor implements FieldVisitorInterface
{
    /**
     * @inheritDoc
     */
    public function visitDateField(DateField $field): void
    {

    }

    /**
     * @inheritDoc
     */
    public function visitIntegerField(IntegerField $field): void
    {

    }

    /**
     * @inheritDoc
     */
    public function visitEntityField(EntityField $field): void
    {

    }

    /**
     * @inheritDoc
     */
    public function visitStringField(StringField $field): void
    {

    }

    /**
     * @inheritDoc
     */
    public function visitBooleanField(BooleanField $field): void
    {

    }

    /**
     * @inheritDoc
     */
    public function visitFloatField(FloatField $field): void
    {

    }
}
