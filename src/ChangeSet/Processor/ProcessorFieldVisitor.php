<?php

namespace Doctrine\ORM\ChangeSet\Processor;

use Doctrine\ORM\ChangeSet\EntityIdentifier;
use Doctrine\ORM\ChangeSet\Field\StringField;
use Doctrine\ORM\ChangeSet\Visitor\AbstractCommonFieldVisitor;

/**
 * Class CommonProcessorFieldVisitor
 * @package Doctrine\ORM\ChangeSet\Processor
 */
class ProcessorFieldVisitor extends AbstractCommonFieldVisitor implements ProcessorFieldVisitorInterface
{
    /**
     * @var array
     */
    private $fields = [];

    /**
     * @inheritDoc
     * @return StringField[]
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @param string $fieldName
     * @param string $namespace
     * @param string|null $oldValue
     * @param string|null $newValue
     */
    protected function processField(string $fieldName, string $namespace, ?string $oldValue, ?string $newValue): void
    {
        $field = new StringField($fieldName, $oldValue, $newValue);
        $field->setNamespace($namespace);

        $this->fields[] = $field;
    }

    /**
     * @param string $entityClass
     * @param EntityIdentifier $identifier
     * @return string|null
     */
    protected function getEntityFieldValue(string $entityClass, EntityIdentifier $identifier): ?string
    {
        return serialize([
            'class' => $entityClass,
            'identifier' => $identifier,
        ]);
    }
}
