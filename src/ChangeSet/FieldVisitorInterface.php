<?php

namespace Doctrine\ORM\ChangeSet;

use Doctrine\ORM\ChangeSet\Field\BooleanField;
use Doctrine\ORM\ChangeSet\Field\DateField;
use Doctrine\ORM\ChangeSet\Field\FloatField;
use Doctrine\ORM\ChangeSet\Field\IntegerField;
use Doctrine\ORM\ChangeSet\Field\EntityField;
use Doctrine\ORM\ChangeSet\Field\StringField;

/**
 * Interface FieldVisitorInterface
 * @package Doctrine\ORM\ChangeSet
 */
interface FieldVisitorInterface
{
    /**
     * @param DateField $field
     */
    public function visitDateField(DateField $field): void;

    /**
     * @param IntegerField $field
     */
    public function visitIntegerField(IntegerField $field): void;

    /**
     * @param StringField $field
     */
    public function visitStringField(StringField $field): void;

    /**
     * @param EntityField $field
     */
    public function visitEntityField(EntityField $field): void;

    /**
     * @param BooleanField $field
     */
    public function visitBooleanField(BooleanField $field): void;

    /**
     * @param FloatField $field
     */
    public function visitFloatField(FloatField $field): void;
}
