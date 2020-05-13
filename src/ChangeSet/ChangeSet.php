<?php

namespace Doctrine\ORM\ChangeSet;

use Doctrine\ORM\ChangeSet\Exception\InvalidChangeSetException;

/**
 * Class ChangeSet
 * @package Doctrine\ORM\ChangeSet
 */
class ChangeSet
{
    /**
     * @var array
     */
    private $fieldsIndex = [];

    /**
     * @var AbstractField[]
     */
    private $fields = [];

    /**
     * @var ChangeSet[]
     */
    private $relatedChangeSets = [];

    /**
     * @var array
     */
    private $relatedChangeSetsIndex = [];

    /**
     * @var string
     */
    private $namespace;

    /**
     * @var array
     */
    private $identifiers;

    /**
     * @var string
     */
    private $className;

    /**
     * ChangeSet constructor.
     * @param string $className
     * @param array $identifiers
     * @param string $namespace
     */
    public function __construct(string $className, array $identifiers, string $namespace = '')
    {
        $this->namespace = $namespace;
        $this->identifiers = $identifiers;
        $this->className = $className;
    }

    /**
     * @param string $className
     * @return bool
     */
    public function isInstanceOf(string $className): bool
    {
        return $this->className === $className;
    }

    /**
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * @return array
     */
    public function getIdentifiers(): array
    {
        return $this->identifiers;
    }

    /**
     * @param string $fieldName
     * @return string|null
     */
    public function getIdentifierValue(string $fieldName): ?string
    {
        return $this->identifiers[$fieldName] ?? null;
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->fieldsIndex)
            && empty($this->relatedChangeSetsIndex);
    }

    /**
     * @param AbstractField $field
     */
    public function addField(AbstractField $field): void
    {
        $field->setNamespace($this->namespace);

        $this->fields[] = $field;
        $this->fieldsIndex[] = $field->getName();
    }

    /**
     * @param ChangeSet $changeSet
     * @return ChangeSet
     * @throws InvalidChangeSetException
     */
    public function addRelatedChangeSet(ChangeSet $changeSet): self
    {
        $key = $changeSet->getNamespace();

        if (empty($key)) {
            throw new InvalidChangeSetException('Related change set must be create with non empty "name"');
        }

        $this->relatedChangeSets[] = $changeSet;
        $this->relatedChangeSetsIndex[] = $key;

        return $this;
    }

    /**
     * @param string $fieldPath
     * @return bool
     */
    public function hasField(string $fieldPath): bool
    {
        $fieldPath = explode('.', $fieldPath);
        $fieldName = array_shift($fieldPath);
        $hasField = false;

        if (!empty($fieldPath)) {
            $fieldPath = implode('.', $fieldPath);
            $changeSets = $this->getChangesSetsByName((string) $fieldName);

            foreach ($changeSets as $changeSet) {
                if ($changeSet->hasField($fieldPath)) {
                    $hasField = true;
                    break;
                }
            }
        } elseif (!empty($fieldName)) {
            $hasField = in_array($fieldName, $this->fieldsIndex);
        }

        return $hasField;
    }

    /**
     * @return ChangeSet[]
     */
    public function getRelatedChangeSets(): array
    {
        return $this->relatedChangeSets;
    }

    /**
     * Apply visitor to ChangeSet fields. By default visitor will be applied only fields in current change set.
     * If pass fieldPath argument, then visitor will be applied to concrete field (can search field in related ChangeSet's, stays.modifiers.modifier as example).
     *
     * @param FieldVisitorInterface $visitor
     * @param string $fieldPath
     */
    public function applyVisitor(FieldVisitorInterface $visitor, string $fieldPath = ''): void
    {
        $fields = [];
        $changeSets = [];

        $currentNamespace = $this->getNamespace();
        $fieldPath = !empty($fieldPath) && !empty($currentNamespace) ?
            trim((string) preg_replace('/^' . $currentNamespace .'/', '', $fieldPath), '.') :
            $fieldPath;

        if (!empty($fieldPath) && strpos($fieldPath, '.') !== false) {
            $fieldPath = explode('.', $fieldPath);
            $changeSets = $this->getChangesSetsByName((string) array_shift($fieldPath));
            $fieldPath = implode('.', $fieldPath);
        } elseif (!empty($fieldPath)) {
            $fields = $this->getFieldsByName((string) $fieldPath);
            $changeSets = $this->getChangesSetsByName((string) $fieldPath);
            $fieldPath = '';
        } else {
            $fields = $this->fields;
        }

        foreach ($fields as $field) {
            $field->accept($visitor);
        }

        foreach ($changeSets as $changeSet) {
            $changeSet->applyVisitor($visitor, $fieldPath);
        }
    }

    /**
     * @param string $fieldName
     * @return AbstractField[]
     */
    private function getFieldsByName(string $fieldName): array
    {
        $fields = [];
        $keys = array_keys($this->fieldsIndex, $fieldName);

        foreach ($keys as $key) {
            $fields[] = $this->fields[$key];
        }

        return $fields;
    }

    /**
     * @param string $changeSetName
     * @return ChangeSet[]
     */
    private function getChangesSetsByName(string $changeSetName): array
    {
        $changeSets = [];
        $currentNamespace = $this->getNamespace();

        $changeSetName = !empty($currentNamespace) ? $currentNamespace . '.' . $changeSetName : $changeSetName;
        $keys = array_keys($this->relatedChangeSetsIndex, $changeSetName);

        foreach ($keys as $key) {
            $changeSets[] = $this->relatedChangeSets[$key];
        }

        return $changeSets;
    }
}
