<?php

namespace Doctrine\ORM\ChangeSet;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\ChangeSet\EntityIdentifier;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\Proxy\Proxy;
use Doctrine\ORM\UnitOfWork;
use Doctrine\ORM\ChangeSet\Exception\InvalidEntityException;
use Doctrine\ORM\ChangeSet\Field\BooleanField;
use Doctrine\ORM\ChangeSet\Field\DateField;
use Doctrine\ORM\ChangeSet\Field\EntityField;
use Doctrine\ORM\ChangeSet\Field\FloatField;
use Doctrine\ORM\ChangeSet\Field\IntegerField;
use Doctrine\ORM\ChangeSet\Field\StringField;

/**
 * Class ChangeSetCollector
 * @package Doctrine\ORM\ChangeSet
 */
class ChangeSetCollector
{
    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var UnitOfWork
     */
    private $unitOfWork;

    /**
     * @var array
     */
    private $scheduledEntitiesUpdates;

    /**
     * @var array
     */
    private $scheduledEntitiesInsertions;

    /**
     * @var array
     */
    private $scheduledEntitiesDeletions;

    /**
     * @var array
     */
    private $processedEntities;

    /**
     * @var array
     */
    private $associations;

    /**
     * @var ChangeSetFactoryInterface
     */
    private $changeSetFactory;

    /**
     * ChangeSetCollector constructor.
     * @param EntityManager $entityManager
     * @param ChangeSetFactoryInterface|null $changeSetFactory
     */
    public function __construct(EntityManager $entityManager, ?ChangeSetFactoryInterface $changeSetFactory = null)
    {
        $this->entityManager = $entityManager;
        $this->changeSetFactory = $changeSetFactory ?: new ChangeSetFactory($entityManager);
        $this->unitOfWork = $this->entityManager->getUnitOfWork();
    }

    /**
     * @param object[] $entities
     * @return ChangeSet[]
     * @throws Exception\InvalidChangeSetException
     * @throws MappingException
     * @throws InvalidEntityException
     */
    public function collectChanges(...$entities): array
    {
        if (empty($entities)) {
            return [];
        }

        $this->unitOfWork->computeChangeSets();
        $this->processedEntities = [];
        $changeSets = [];

        try {
            $className = ClassUtils::getClass(reset($entities));
            $this->indexScheduledEntitiesAndAssociations($className);

            foreach ($entities as $entity) {
                if ($className !== ClassUtils::getClass($entity)) {
                    throw new InvalidEntityException('All entities must be instance of same class');
                }

                $changeSet = $this->createChangeSet($entity);

                // For non-persistent entities we need collect full changes
                $this->unitOfWork->getEntityState($entity) === UnitOfWork::STATE_NEW ?
                    $this->collectFullEntityData($entity, $changeSet, true) :
                    $this->collectEntityChanges($entity, $changeSet, $this->associations);

                if (!$changeSet->isEmpty()) {
                    $changeSets[] = $changeSet;
                }
            }
        } finally {
            $this->scheduledEntitiesInsertions = [];
            $this->scheduledEntitiesDeletions = [];
            $this->scheduledEntitiesUpdates = [];
            $this->processedEntities = [];
            $this->associations = [];
        }

        return $changeSets;
    }

    /**
     * @param object $entity
     * @param ChangeSet $changeSet
     * @param array $associations
     * @throws Exception\InvalidChangeSetException
     * @throws MappingException
     */
    private function collectEntityChanges($entity, ChangeSet $changeSet, array $associations): void
    {
        $rootEntityHash = $this->getObjectHash($entity);

        if (isset($this->processedEntities[$rootEntityHash])) {
            return;
        }

        $this->processedEntities[$rootEntityHash] = $entity;

        $metaData = $this->entityManager->getClassMetadata(get_class($entity));
        $entityChanges = $this->unitOfWork->getEntityChangeSet($entity);
        $processedFields = [];

        foreach ($entityChanges as $fieldName => $values) {
            [$oldValue, $newValue] = $values;

            if ($oldValue === $newValue) {
                continue;
            }

            $field = $this->createChangeSetField($metaData, $fieldName, $oldValue, $newValue);

            if ($field !== null) {
                $changeSet->addField($field);
                $processedFields[$fieldName] = $fieldName;
            }
        }

        if (!empty($this->scheduledEntitiesUpdates)) {
            foreach ($associations as $fieldName => $options) {
                $value = $this->getAssociationValue($metaData, $entity, $fieldName);
                $value = $value !== null && !is_iterable($value) ? [$value] : $value;

                if (!empty($value)) {
                    foreach ($value as $item) {
                        $objectHash = $this->getObjectHash($item);
                        $isInserted = in_array($objectHash, $this->scheduledEntitiesInsertions);

                        if ($isInserted) {
                            $this->setInsertedOrDeletedRelationChangeSet(
                                $item,
                                $fieldName,
                                $changeSet,
                                true
                            );
                        } else {
                            $this->setUpdatedRelationChangeSet(
                                $item,
                                $fieldName,
                                $changeSet,
                                $options['fields']
                            );
                        }
                    }
                }
            }
        }

        $deletedEntities = $this->scheduledEntitiesDeletions[$rootEntityHash] ?? [];

        foreach ($deletedEntities as $fieldName => $entities) {
            foreach ($entities as $item) {
                $this->setInsertedOrDeletedRelationChangeSet(
                    $item,
                    $fieldName,
                    $changeSet,
                    false
                );
            }
        }
    }

    /**
     * @param object $entity
     * @param ChangeSet $changeSet
     * @param bool $isInsert
     * @throws Exception\InvalidChangeSetException
     * @throws MappingException
     */
    private function collectFullEntityData($entity, ChangeSet $changeSet, bool $isInsert): void
    {
        $rootEntityHash = $this->getObjectHash($entity);

        if (isset($this->processedEntities[$rootEntityHash])) {
            return;
        }

        $this->processedEntities[$rootEntityHash] = $entity;
        $metaData = $this->entityManager->getClassMetadata(get_class($entity));

        foreach ($metaData->getFieldNames() as $fieldName) {
            $value = $metaData->hasAssociation($fieldName) ?
                $this->getAssociationValue($metaData, $entity, $fieldName) :
                $metaData->getFieldValue($entity, $fieldName);

            if ($this->isEntity($value) || is_iterable($value)) {
                $value = !is_iterable($value) ? [$value] : $value;

                foreach ($value as $item) {
                    $relatedChangeSet = $this->createChangeSet(
                        $item,
                        $this->createChangeSetNamespace($changeSet->getNamespace(), $fieldName)
                    );

                    $this->collectFullEntityData($item, $relatedChangeSet, $isInsert);

                    if (!$relatedChangeSet->isEmpty()) {
                        $changeSet->addRelatedChangeSet($relatedChangeSet);
                    }
                }
            } else {
                $field = $this->createChangeSetField(
                    $metaData,
                    $fieldName,
                    $isInsert ? null : $value,
                    $isInsert ? $value : null
                );

                if ($field !== null) {
                    $changeSet->addField($field);
                }
            }
        }
    }

    /**
     * @param object $entity
     * @param string $fieldName
     * @param ChangeSet $rootChangeSet
     * @param array $associations
     * @throws Exception\InvalidChangeSetException
     * @throws MappingException
     */
    private function setUpdatedRelationChangeSet($entity, string $fieldName, ChangeSet $rootChangeSet, array $associations): void
    {
        $changeSet = $this->createChangeSet(
            $entity,
            $this->createChangeSetNamespace($rootChangeSet->getNamespace(), $fieldName)
        );

        $this->collectEntityChanges($entity, $changeSet, $associations);

        if (!$changeSet->isEmpty()) {
            $rootChangeSet->addRelatedChangeSet($changeSet);
        }
    }

    /**
     * @param object $entity
     * @param string $fieldName
     * @param ChangeSet $rootChangeSet
     * @param bool $isInserted
     * @throws Exception\InvalidChangeSetException
     * @throws MappingException
     */
    private function setInsertedOrDeletedRelationChangeSet($entity, string $fieldName, ChangeSet $rootChangeSet, bool $isInserted): void
    {
        $changeSet = $this->createChangeSet(
            $entity,
            $this->createChangeSetNamespace($rootChangeSet->getNamespace(), $fieldName)
        );

        $this->collectFullEntityData($entity, $changeSet, $isInserted);

        if (!$changeSet->isEmpty()) {
            $rootChangeSet->addRelatedChangeSet($changeSet);
        }
    }

    /**
     * @param ClassMetadata $metaData
     * @param string $fieldName
     * @param mixed $oldValue
     * @param mixed $newValue
     * @return AbstractField|null
     * @throws MappingException
     */
    private function createChangeSetField(ClassMetadata $metaData, string $fieldName, $oldValue, $newValue): ?AbstractField
    {
        if ($metaData->isIdentifier($fieldName)) {
            return null;
        }

        $field = null;
        $type = (string) $metaData->getTypeOfField($fieldName);

        // this is one-to-one or many to one relations
        if (empty($type) && $metaData->hasAssociation($fieldName)) {
            $type = 'entity';
        }

        switch ($type) {
            case 'string':
                $oldValue = $oldValue !== null ? (string) $oldValue : null;
                $newValue = $newValue !== null ? (string) $newValue : null;

                if ($oldValue !== $newValue) {
                    $field = new StringField($fieldName, $oldValue, $newValue);
                }
                break;

            case 'boolean':
                $oldValue = $oldValue !== null ? (boolean) $oldValue : null;
                $newValue = $newValue !== null ? (boolean) $newValue : null;

                if ($oldValue !== $newValue) {
                    $field = new BooleanField($fieldName, $oldValue, $newValue);
                }
                break;

            case 'double':
            case 'float':
            case 'decimal':
                $oldValue = $oldValue !== null ? (float) $oldValue : null;
                $newValue = $newValue !== null ? (float) $newValue : null;

                if ($this->isRealDifferentFloat($oldValue, $newValue)) {
                    $field = new FloatField($fieldName, $oldValue, $newValue);
                }
                break;

            case 'bigint':
            case 'smallint':
            case 'integer':
                $oldValue = $oldValue !== null ? (int) $oldValue : null;
                $newValue = $newValue !== null ? (int) $newValue : null;

                if ($oldValue !== $newValue) {
                    $field = new IntegerField($fieldName, $oldValue, $newValue);
                }
                break;

            case 'date':
            case 'datetime':
            case 'time':
                if ($this->isRealDifferentDates($oldValue, $newValue, $type)) {
                    $field = new DateField(
                        $fieldName,
                        $type,
                        $oldValue,
                        $newValue
                    );
                }
                break;

            case 'entity':
                $mapping = $metaData->getAssociationMapping($fieldName);
                $isNewEntity = $newValue !== null && !$this->entityManager->contains($newValue);

                if ($isNewEntity) {
                    if (!$mapping['isCascadePersist']) {
                        $processedFields[$fieldName] = $fieldName;
                    }

                    // IF this is new entity THEN
                    //    must be collected as related changes because identifiers is not available now
                    break;
                }

                $targetClass = $mapping['targetEntity'];
                $entityMetadata = $this->entityManager->getClassMetadata($targetClass);

                $field = new EntityField(
                    $fieldName,
                    $targetClass,
                    $oldValue !== null ?
                        new EntityIdentifier($entityMetadata->getIdentifierValues($oldValue)) :
                        null,
                    $newValue !== null ?
                        new EntityIdentifier($entityMetadata->getIdentifierValues($newValue)) :
                        null
                );
                break;

            default:
                break;
        }

        return $field;
    }

    /**
     * Return association value only if it's real loaded
     *
     * @param ClassMetadata $metadata
     * @param object $entity
     * @param string $fieldName
     * @return object|Collection|null
     */
    private function getAssociationValue(ClassMetadata $metadata, $entity, string $fieldName)
    {
        $value = $metadata->getFieldValue($entity, $fieldName);

        if ($this->isLoadedAssociation($value)) {
            return $value;
        }

        return null;
    }

    /**
     * @param object|Collection|null $value
     * @return bool
     */
    private function isLoadedAssociation($value): bool
    {
        if ($value instanceof PersistentCollection) {
            $isInitialized = $value->isInitialized();
        } elseif ($value instanceof Proxy) {
            $isInitialized = $value->__isInitialized();
        } elseif ($value instanceof ArrayCollection) {
            $isInitialized = $value->count() > 0;
        } elseif (is_object($value)) {
            $isInitialized = true;
        } else {
            $isInitialized = false;
        }

        return $isInitialized;
    }

    /**
     * @param string $entityClass
     * @return void
     */
    private function indexScheduledEntitiesAndAssociations(string $entityClass): void
    {
        $this->scheduledEntitiesUpdates = [];
        $this->scheduledEntitiesInsertions = [];
        $this->scheduledEntitiesDeletions = [];

        $scheduledEntities = array_merge(
            $this->unitOfWork->getScheduledEntityInsertions(),
            $this->unitOfWork->getScheduledEntityUpdates()
        );

        /** @var PersistentCollection $collection */
        foreach ($this->unitOfWork->getScheduledCollectionUpdates() as $collection) {
            foreach ($collection->getInsertDiff() as $entity) {
                if (!in_array($entity, $scheduledEntities, true)) {
                    $scheduledEntities[] = $entity;
                }

                $this->scheduledEntitiesInsertions[] = $this->getObjectHash($entity);
            }

            $ownerHash = $this->getObjectHash($collection->getOwner());
            $mapping = $collection->getMapping();

            foreach ($collection->getDeleteDiff() as $entity) {
                if (!isset($this->scheduledEntitiesDeletions[$ownerHash][$mapping['fieldName']])) {
                    $this->scheduledEntitiesDeletions[$ownerHash][$mapping['fieldName']] = [];
                }

                $this->scheduledEntitiesDeletions[$ownerHash][$mapping['fieldName']][] = $entity;
            }
        }

        foreach ($scheduledEntities as $entity) {
            $className = $this->entityManager
                ->getClassMetadata(get_class($entity))
                ->getName();

            if (!isset($this->scheduledEntitiesUpdates[$className])) {
                $this->scheduledEntitiesUpdates[$className] = [];
            }

            $entityHash = $this->getObjectHash($entity);
            $this->scheduledEntitiesUpdates[$className][$entityHash] = $entity;
        }

        $this->associations = $this->findAssociationsMustBeProcessed($entityClass);
    }

    /**
     * @param string $entityClass
     * @param array $mappedClasses
     * @return array
     */
    private function findAssociationsMustBeProcessed(string $entityClass, array &$mappedClasses = []): array
    {
        $metaData = $this->entityManager->getClassMetadata($entityClass);
        $mappedClasses[] = $metaData->getName();
        $fieldsPath = [];

        foreach ($metaData->getAssociationMappings() as $mapping) {
            $fieldName = $mapping['fieldName'];
            $targetEntityClass = $mapping['targetEntity'];

            if (in_array($targetEntityClass, $mappedClasses)) {
                continue;
            }

            $subFields = $this->findAssociationsMustBeProcessed($targetEntityClass, $mappedClasses);

            if (
                isset($this->scheduledEntitiesUpdates[$targetEntityClass])
                || !empty($subFields)
            ) {
                $fieldsPath[$fieldName] = [
                    'className' => $targetEntityClass,
                    'fields' => $subFields,
                ];
            }
        }

        return $fieldsPath;
    }

    /**
     * @param object $entity
     * @return bool
     */
    private function isEntity($entity): bool
    {
        return is_object($entity)
            && !$this->entityManager
                ->getMetadataFactory()
                ->isTransient(ClassUtils::getClass($entity));
    }

    /**
     * @param object $entity
     * @return string
     */
    private function getObjectHash($entity): string
    {
        return spl_object_hash($entity);
    }

    /**
     * @param object $entity
     * @param string $namespace
     * @return ChangeSet
     */
    private function createChangeSet($entity, string $namespace = ''): ChangeSet
    {
        return $this->changeSetFactory->createChangeSet($entity, $namespace);
    }

    /**
     * @param string $parentNamespace
     * @param string $fieldName
     * @return string
     */
    private function createChangeSetNamespace(string $parentNamespace, string $fieldName): string
    {
        return $parentNamespace ?
            $parentNamespace . '.' . $fieldName :
            $fieldName;
    }

    /**
     * @param \DateTime|null $oldValue
     * @param \DateTime|null $newValue
     * @param string $type
     * @return bool
     */
    private function isRealDifferentDates(?\DateTime $oldValue, ?\DateTime $newValue, string $type): bool
    {
        $isSame = true;

        if (
            $oldValue === null && $newValue !== null
            || $oldValue !== null && $newValue === null
        ) {
            $isSame = false;
        } elseif ($newValue !== null && $oldValue !== null) {
            switch ($type) {
                case 'date':
                    $format = 'Y-m-d';
                    break;

                case 'time':
                    $format = 'H:i:s';
                    break;

                default:
                    $format = 'Y-m-d H:i:s';
                    break;
            }

            $isSame = $oldValue->format($format) === $newValue->format($format);
        }

        return !$isSame;
    }

    /**
     * @param float|null $oldValue
     * @param float|null $newValue
     * @return bool
     */
    private function isRealDifferentFloat(?float $oldValue, ?float $newValue): bool
    {
        $isSame = true;
        $epsilon = 0.00001;

        if (
            ($oldValue === null && $newValue !== null)
            || ($oldValue !== null && $newValue === null)
            || (
                $oldValue !== null
                && $newValue !== null
                && abs($oldValue - $newValue) >= $epsilon
            )
        ) {
            $isSame = false;
        }

        return !$isSame;
    }
}
