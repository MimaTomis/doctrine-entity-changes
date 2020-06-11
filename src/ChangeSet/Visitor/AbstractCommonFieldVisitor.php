<?php

namespace Doctrine\ORM\ChangeSet\Visitor;

use Doctrine\ORM\ChangeSet\Field\BooleanField;
use Doctrine\ORM\ChangeSet\Field\DateField;
use Doctrine\ORM\ChangeSet\Field\EntityField;
use Doctrine\ORM\ChangeSet\EntityIdentifier;
use Doctrine\ORM\ChangeSet\Field\FloatField;
use Doctrine\ORM\ChangeSet\Field\IntegerField;
use Doctrine\ORM\ChangeSet\Field\StringField;
use Doctrine\ORM\ChangeSet\FieldVisitorInterface;

/**
 * Class AbstractCommonFieldVisitor
 * @package Doctrine\ORM\ChangeSet\Visitor
 */
abstract class AbstractCommonFieldVisitor implements FieldVisitorInterface
{
    public const BOOLEAN_CHECKED = 1;
    public const BOOLEAN_UNCHECKED = 0;

    /**
     * @var array
     */
    private $dateFormats;

    /**
     * @var array
     */
    private $booleanFormats;

    /**
     * @var array
     */
    private $acceptedFields;

    /**
     * AbstractCommonFieldVisitor constructor.
     * @param array $dateFormats
     * @param array $booleanFormats
     * @param array $acceptedFields
     */
    public function __construct(
        array $dateFormats,
        array $booleanFormats,
        array $acceptedFields = []
    )
    {
        $this->dateFormats = $dateFormats;
        $this->booleanFormats = $booleanFormats;
        $this->acceptedFields = $acceptedFields;
    }

    /**
     * @inheritDoc
     */
    public function visitDateField(DateField $field) : void
    {
        if ($this->isAcceptedField($field->getName(true))) {
            $this->processField(
                $field->getName(),
                $field->getNamespace(),
                $this->formatDate($field->getOldValue(), $field->getType()),
                $this->formatDate($field->getNewValue(), $field->getType())
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function visitIntegerField(IntegerField $field) : void
    {
        if ($this->isAcceptedField($field->getName(true))) {
            $oldValue = $field->getOldValue();
            $newValue = $field->getNewValue();

            $this->processField(
                $field->getName(),
                $field->getNamespace(),
                $oldValue !== null ? (string) $oldValue : null,
                $newValue !== null ? (string) $newValue : null
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function visitStringField(StringField $field) : void
    {
        if ($this->isAcceptedField($field->getName(true))) {
            $this->processField(
                $field->getName(),
                $field->getNamespace(),
                $field->getOldValue(),
                $field->getNewValue()
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function visitBooleanField(BooleanField $field) : void
    {
        if ($this->isAcceptedField($field->getName(true))) {
            $this->processField(
                $field->getName(),
                $field->getNamespace(),
                $this->formatBoolean($field->getOldValue()),
                $this->formatBoolean($field->getNewValue())
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function visitFloatField(FloatField $field) : void
    {
        if ($this->isAcceptedField($field->getName(true))) {
            $oldValue = $field->getOldValue();
            $newValue = $field->getNewValue();

            $this->processField(
                $field->getName(),
                $field->getNamespace(),
                $oldValue !== null ? number_format($oldValue, 2) : null,
                $newValue !== null ? number_format($newValue, 2) : null
            );
        }
    }

    /**
     * @param EntityField $field
     */
    public function visitEntityField(EntityField $field): void
    {
        if ($this->isAcceptedField($field->getName(true))) {
            $oldIdentifier = $field->getOldIdentifier();
            $newIdentifier = $field->getNewIdentifier();

            $oldValue = $oldIdentifier ? $this->getEntityFieldValue($field->getEntityClass(), $oldIdentifier) : null;
            $newValue = $newIdentifier ? $this->getEntityFieldValue($field->getEntityClass(), $newIdentifier) : null;

            $this->processField(
                $field->getName(),
                $field->getNamespace(),
                $oldValue !== null ? $oldValue : null,
                $newValue !== null ? $newValue : null
            );
        }
    }

    /**
     * @param string $entityClass
     * @param EntityIdentifier $identifier
     * @return string|null
     */
    abstract protected function getEntityFieldValue(string $entityClass, EntityIdentifier $identifier): ?string;

    /**
     * @param string $fieldName
     * @param string $namespace
     * @param string|null $oldValue
     * @param string|null $newValue
     */
    abstract protected function processField(string $fieldName, string $namespace, ?string $oldValue, ?string $newValue): void;

    /**
     * @param \DateTime|null $date
     * @param string $type
     * @return string|null
     */
    private function formatDate(?\DateTime $date, string $type): ?string
    {
        if (!empty($this->dateFormats[$type])) {
            $format = $this->dateFormats[$type];
        } else {
            switch (true) {
                case DateField::TYPE_TIME:
                    $format = 'H:i:s';
                    break;

                case DateField::TYPE_DATETIME:
                    $format = 'Y-m-d H:i:s';
                    break;

                case DateField::TYPE_DATE:
                    $format = 'Y-m-d';
                    break;

                default:
                    $format = null;
                    break;
            }
        }

        return $format !== null && $date !== null ?
            $date->format($format) :
            null;
    }

    /**
     * @param bool|null $value
     * @return string|null
     */
    private function formatBoolean(?bool $value): ?string
    {
        switch ($value) {
            case true:
                $format = self::BOOLEAN_CHECKED;
                $default = 'Checked';
                break;

            case false:
                $format = self::BOOLEAN_UNCHECKED;
                $default = 'Unchecked';
                break;

            default:
                $format = null;
                $default = null;
        }

        return $format !== null ?
            ($this->booleanFormats[$format] ?? $default) :
            null;
    }

    /**
     * @param string $fieldName
     * @return bool
     */
    protected function isAcceptedField(string $fieldName): bool
    {
        return empty($this->acceptedFields) || in_array($fieldName, $this->acceptedFields);
    }
}