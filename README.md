# Doctrine Entity Changes

[![Build Status](https://travis-ci.org/MimaTomis/doctrine-entity-changes.svg)](https://travis-ci.org/MimaTomis/doctrine-entity-changes)

Library makes it easy to get changes of Doctrine entities.

* [Installation](#installation)
* [How to use](#how-to-use)
    * [Simple way to get changes](#simple-way-to-get-changes)
    * [Collect changes](#collect-changes)
    * [Field iteration](#field-iteration)
        * [Abstract visitors](#abstract-visitors)
        * [Visitor with callback](#visitor-with-callback)
        * [Full implementation of visitor](#full-implementation-of-visitor)
    * [Getting field name](#getting-field-name)
    * [Getting relation changes](#getting-relation-changes)
    * [Apply visitor to concrete field](#apply-visitor-to-concrete-field)
* [How to help](#how-to-help)

## Installation

Run command:

```bash
composer require mima/doctrine-entity-changes
```

Add dependency to your composer.json:

```json
{
    "require": {
        "mima/doctrine-entity-changes": "~1.0"
    }
}
```

## How to use

*Note that getting changes must be earlier then flush.*
For example, declare entity class:

```php
<?php
use Doctrine\ORM\Mapping as ORM;

/**
 * Class ChangeLog
 * @ORM\Entity()
 * @ORM\Table(name="example")
 */
class Example
{
    /**
     * @var int
     * @ORM\Column(type="integer")
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;
    
    /**
     * @var string
     * @ORM\Column(type="string") 
     */
    private $title;
    
    // ... another columns definition
    
    /**
     * @return int
     */
    public  function getId(): int
    {
        return $this->id;
    }
    
    /**
     * @return string
     */ 
    public  function getTitle(): string
    {
        return $this->title;
    }
    
    /**
     * @param string $title
     */
    public  function setTitle(string $title): void 
    {
        $this->title = $title;
    }
}
```

### Simple way to get changes

```php
<?php
/**
 * @var Example $entity - instance of entity class retrieved from DB
 * @var \Doctrine\ORM\EntityManager $entityManager - мэнэджер сущностей Doctrine
 */

$processor = new \Doctrine\ORM\ChangeSet\Processor\ChangeSetProcessor(
    new \Doctrine\ORM\ChangeSet\ChangeSetCollector($entityManager),
    new \Doctrine\ORM\ChangeSet\Processor\ProcessorFieldVisitorFactory()
);

$fields = $processor->getChanges($entity);
 
foreach ($fields as $field) {
    // $field instanceof StringField
    $name = $field->getName(); // name of field relative to entity
    $fullName = $field->getName(true); // name of field relative to relation
    $oldValue = $field->getOldValue(); // null or string
    $newValue = $field->getNewValue(); // null or string
}
```

If field is part of association, then `$fullName` equals to <associationFieldName>.<fieldName>. If field is part or root object, then `$fullName` equals to `$name`. Old and New value converted from DateTime, Boolean, Integer and another fields by rules described above.

#### Convert dates to string

By default used this formats:
* date -> Y-m-d
* time -> H:i:s
* datetime -> Y-m-d H:i:s

You cat declare custom formats in first argument of ProcessorFieldVisitorFactory:

```php
<?php
$factory = new \Doctrine\ORM\ChangeSet\Processor\ProcessorFieldVisitorFactory(
    [
        \Doctrine\ORM\ChangeSet\Field\DateField::TYPE_DATE => 'm/d/Y',
        \Doctrine\ORM\ChangeSet\Field\DateField::TYPE_DATETIME => 'm/d/Y H:i A',
        \Doctrine\ORM\ChangeSet\Field\DateField::TYPE_TIME => 'H:i A',
    ]
);
```

#### Convert booleans to string

By default boolean converts to this strings:
* true -> 'Checked'
* false|null -> 'Unchecked'

You can declare custom rules in second argument of ProcessorFieldVisitorFactory:

```php
<?php
$factory = new \Doctrine\ORM\ChangeSet\Processor\ProcessorFieldVisitorFactory(
    [],
    [
        \Doctrine\ORM\ChangeSet\Visitor\AbstractCommonFieldVisitor::BOOLEAN_CHECKED => 'Yes',
        \Doctrine\ORM\ChangeSet\Visitor\AbstractCommonFieldVisitor::BOOLEAN_UNCHECKED => 'No',
    ]
);
```

#### Convert floats to string

Float always converted to string accurate to 2 digits after the dot.
* 1 => '1.00'
* 10.3748 => '10.37'
* 2.3490 => '2.34'

#### Convert entities to string

Entity field values presents as serialized array:
```php
<?php
/**
 * @var string $oldValue - see above
 */
$value = unserialize($oldValue);
$className = $value['class']; // name of entity class
$identifier = $value['identifier']; // instanceof \Doctrine\ORM\ChangeSet\EntityIdentifier
```

You can use this fields for fetching entity.

```php
<?php
/**
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var string $className
 * @var \Doctrine\ORM\ChangeSet\EntityIdentifier $identifier 
 */
$entity = $entityManager->find($className, $identifier->toArray());

// Or if you sure that identifier is single field

$entity = $entityManager->find($className, $identifier->getSingleValue());
```

### Collect changes

Collect changes for example entity:

```php
<?php
/**
 * @var Example $entity - instance of entity class retrieved from DB
 * @var \Doctrine\ORM\EntityManager $entityManager - мэнэджер сущностей Doctrine
 */ 

$changeSetCollector = new \Doctrine\ORM\ChangeSet\ChangeSetCollector($entityManager);
$changeSet = $changeSetCollector->collectChanges($entity);

$entityManager->persist($entity);
$entityManager->flush($entity);
```` 

### Field iteration

Field iteration is implemented using visitors.
For example make ChangeLog entity

```php
<?php
//--------------
// ChangeLog.php
//--------------

use Doctrine\ORM\Mapping as ORM;

/**
 * Class ChangeLog
 * @ORM\Entity()
 * @ORM\Table(name="change_log")
 */
class ChangeLog
{
    /**
     * @var int
     * @ORM\Column(type="integer")
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;
    
    /**
     * @var Example
     * @ORM\ManyToOne(targetEntity="Example")
     * @ORM\JoinColumn(name="related_entity_id", referencedColumnName="id")
     */
    private $relatedEntity;
    
    /**
     * @var string
     * @ORM\Column(type="string") 
     */
    private $field;
    
    /**
     * @var string|null
     * @ORM\Column(type="string", name="old_value", nullable=true) 
     */
    private $oldValue;
    
    /**
     * @var string|null 
     * @ORM\Column(type="string", name="new_value", nullable=true) 
     */
    private $newValue;
    
    /**
     * @var \DateTime
     * @ORM\Column(name="created_date", type="datetime", nullable=false) 
     */
    private $createdDate;
    
    /**
     * ChangeLog constructor.
     * @param Example $relatedEntity
     * @param string $field
     * @param string|null $oldValue
     * @param string|null $newValue
     * @throws Exception
     */
    public function __construct(Example $relatedEntity, string $field, ?string $oldValue, ?string $newValue) 
    {
        $this->relatedEntity = $relatedEntity;
        $this->createdDate = new \DateTime();
        $this->field = $field;
        $this->oldValue = $oldValue;
        $this->newValue = $newValue;
    }
    
    /**
     * @return int
     */
    public  function getId(): int
    {
        return $this->id;
    }

    /**
     * @return DateTime
     */
    public  function getCreatedDate(): DateTime
    {
        return $this->createdDate;
    }

    /**
     * @return Example
     */
    public  function getRelatedEntity(): Example
    {
        return $this->relatedEntity;
    }

    /**
     * @return string|null
     */
    public  function getOldValue(): ?string
    {
        return $this->oldValue;
    }

    /**
     * @return string|null
     */
    public  function getNewValue(): ?string
    {
        return $this->newValue;
    }

    /**
     * @return string
     */
    public  function getField(): string
    {
        return $this->field;
    }
}
```

To simplify you can extend you visitor of:

1. *\Doctrine\ORM\ChangeSet\Visitor\AbstractEmptyFieldVisitor* - full clear field visitor (with empty methods)
2. *\Doctrine\ORM\ChangeSet\Visitor\AbstractCommonFieldVisitor* - visitor with logic to "pre-formatting" fields, like in example above

Otherwise you can implement *\Doctrine\ORM\ChangeSetFieldVisitorInterface*. For certain tasks you can use *\Doctrine\ORM\ChangeSet\Visitor\CallbackFieldVisitor*

#### Abstract visitors

Let's try to implement simple EntityFieldVisitor with AbstractCommonFieldVisitor:

```php
<?php
use Doctrine\ORM\ChangeSet\Field\DateField;
use Doctrine\ORM\ChangeSet\Visitor\AbstractCommonFieldVisitor;

class EntityFieldVisitor extends AbstractCommonFieldVisitor
{
    /**
     * @var Example
     */
    private $relatedEntity;
    
    /**
     * @var ChangeLog[]
     */
    private $logEntities = [];
    
    /**
     * EntityFieldVisitor constructor.
     * @param Example $relatedEntity
    */
    public function __construct(Example $relatedEntity) 
    {
        $this->relatedEntity = $relatedEntity;
        parent::__construct(
            [
                DateField::TYPE_DATE => 'm/d/Y',
                DateField::TYPE_DATETIME => 'm/d/Y H:m A',
                DateField::TYPE_TIME => 'H:m A',
            ],
            [
                AbstractCommonFieldVisitor::BOOLEAN_CHECKED => 'Checked',
                AbstractCommonFieldVisitor::BOOLEAN_UNCHECKED => 'Unchecked',                
            ],
        );
    }
    
    /**
     * @param string $field
     * @param string|null $oldValue
     * @param string|null $newValue
     */
    protected function processField(string $field,?string $oldValue,?string $newValue) : void
    {
         $this->logEntities[] = new ChangeLog(
            $this->relatedEntity,
            $field,
            $oldValue,
            $newValue
        );
    }
}
```

You can add list of accepted fields in third argument of parent class constructor. By default accepted all fields.
Example of saving received logs:

```php
<?php
/**
 * @var \Doctrine\ORM\ChangeSet\ChangeSet $changeSet
 * @var Example $entity
 * @var \Doctrine\ORM\EntityManager $em
 */
$visitor = new EntityFieldVisitor($entity);
$changeSet->applyVisitor($visitor);

$changeLogEntities = $visitor->getChangeLogEntities();

if (!empty($changeLogEntities)) {
    foreach ($changeLogEntities as $changeLogEntity) {
        $em->persist($changeLogEntity);
    }
    
    $em->flush($changeLogEntities);
}
```

#### Visitor with callback

Callback visitor allows you to walk through field changes with a specific type.

```php
<?php
/**
 * @var \Doctrine\ORM\ChangeSet\ChangeSet $changeSet 
 */

use Doctrine\ORM\ChangeSet\Field\StringField;
use Doctrine\ORM\ChangeSet\Visitor\CallbackFieldVisitor;

$changeSet->applyVisitor(
    new CallbackFieldVisitor(
        StringField::class,
        function (StringField $field) {
            if ($field->getNewValue() === 'ALARM') {
                // do anything...
            }
        }
    )
);
```

#### Full implementation of visitor

Further you need it's implement FieldVisitorInterface:

```php
<?php
//-----------------------
// EntityFieldVisitor.php
//-----------------------

use Doctrine\ORM\ChangeSet\Field\BooleanField;
use Doctrine\ORM\ChangeSet\Field\DateField;
use Doctrine\ORM\ChangeSet\Field\EntityField;
use Doctrine\ORM\ChangeSet\Field\FloatField;
use Doctrine\ORM\ChangeSet\Field\IntegerField;
use Doctrine\ORM\ChangeSet\Field\StringField;
use Doctrine\ORM\ChangeSet\FieldVisitorInterface;

/**
 * Class EntityFieldVisitor implement FieldVisitorInterface
 */
class EntityFieldVisitor implements FieldVisitorInterface
{
    /**
     * @var Example
     */
    private $relatedEntity;
    
    /**
     * @var ChangeLog[]
     */
    private $logEntities = [];
    
    /**
     * EntityFieldVisitor constructor.
     * @param Example $relatedEntity
    */
    public function __construct(Example $relatedEntity) 
    {
        $this->relatedEntity = $relatedEntity;
    }
    
    /**
     * @inheritDoc
     */
    public function visitDateField(DateField $field) : void
    {
        $this->addChangeLog(
            $field->getName(true),
            $this->formatDate($field->getOldValue(), $field->getType()),
            $this->formatDate($field->getNewValue(), $field->getType())
        );
    }
    
    /**
     * @inheritDoc
     */
    public function visitIntegerField(IntegerField $field) : void
    {
        $oldValue = $field->getOldValue();
        $newValue = $field->getNewValue();
        
        $this->addChangeLog(
            $field->getName(true),
            $oldValue !== null ? (string) $oldValue : null,
            $newValue !== null ? (string) $newValue : null
        );
    }
    
    /**
     * @inheritDoc
     */
    public function visitStringField(StringField $field) : void
    {
        $this->addChangeLog(
            $field->getName(true),
            $field->getOldValue(),
            $field->getNewValue()
        );
    }
    
    /**
     * @inheritDoc
     */
    public function visitEntityField(EntityField $field) : void
    {
        
    }
    
    /**
     * @inheritDoc
     */
    public function visitBooleanField(BooleanField $field) : void
    {
        $this->addChangeLog(
            $field->getName(true),
            $this->formatBoolean($field->getOldValue()),
            $this->formatBoolean($field->getNewValue())
        );
    }
    
    /**
     * @inheritDoc
     */
    public function visitFloatField(FloatField $field) : void
    {
        $oldValue = $field->getOldValue();
        $newValue = $field->getNewValue();
        
        $this->addChangeLog(
            $field->getName(true),
            $oldValue !== null ? number_format($oldValue, 2) : null,
            $newValue !== null ? number_format($newValue, 2) : null
        );
    }
    
    /**
     * @return array
     */
    public function getChangeLogEntities(): array
    {
        return $this->logEntities;
    }
    
    /**
     * @param string $field
     * @param string|null $oldValue
     * @param string|null $newValue
     */
    private function addChangeLog(string $field, ?string $oldValue, ?string $newValue): void
    {
        $this->logEntities[] = new ChangeLog(
            $this->relatedEntity,
            $field,
            $oldValue,
            $newValue
        );
    }
    
    /**
     * @param DateTime|null $date
     * @param string $type
     * @return string|null
     */
    private function formatDate(?\DateTime $date, string $type): ?string
    {
        switch ($type) {
            case DateField::TYPE_TIME:
                $format = 'h:i A';
                break;
                
            case DateField::TYPE_DATETIME:
                $format = 'm/d/Y h:i A';
                break;
                
            case DateField::TYPE_DATE:
                $format = 'm/d/Y';
                break;
                
            default:
                $format = null;
                break;
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
                $result = 'checked';
                break;
                
            case false:
                $result = 'unchecked';
                break;
                
            default:
                $result = null;
        }
        
        return $result;
    }
}
```

### Getting field name

For getting field name relative to the class where the field is declared all of you need:

```php
<?php
/**
 * @var \Doctrine\ORM\ChangeSet\AbstractField $field 
 */
$fieldName = $field->getName();
```

This code return convrete field name: *title*, *createdAt*, etc.
For getting full path of field, relative to root class in a chain to relations, need to do the following:

```php
<?php
/**
 * @var \Doctrine\ORM\ChangeSet\AbstractField $field 
 */
$fieldName = $field->getName(true);
```

This code return field name like: *relation.subRelation.title*

### Getting relation changes

The visitor enters only fields. You can getting relations changes through call:

```php
<?php
/**
 * @var \Doctrine\ORM\ChangeSet\ChangeSet $changeSet
 * @var \Doctrine\ORM\ChangeSet\FieldVisitorInterface $visitor
 */
$relatedChanges = $changeSet->getRelatedChangeSets();

foreach ($relatedChanges as $relatedChangeSet) {
    $relatedChangeSet->applyVisitor($visitor);
}
```

As another way you can apply visitor to concrete related change set:

```php
<?php
/**
 * @var \Doctrine\ORM\ChangeSet\ChangeSet $changeSet
 * @var \Doctrine\ORM\ChangeSet\FieldVisitorInterface $visitor
 */
$relatedChanges = $changeSet->applyVisitor($visitor, 'relatedFieldName');
```

In this case *relatedFieldName* is name of field for which the association is declared.
You can get access to any relation level: *relatedFieldName.subRelatedField*

### Apply visitor to concrete field

You can apply visitor to conreate field:

```php
<?php
/**
 * @var \Doctrine\ORM\ChangeSet\ChangeSet $changeSet
 * @var \Doctrine\ORM\ChangeSet\FieldVisitorInterface $visitor
 */
$relatedChanges = $changeSet->applyVisitor($visitor, 'fieldName');
```

In this case *fieldName* is name of target field. You can get access to field in related changes:

```php
<?php

/**
 * @var \Doctrine\ORM\ChangeSet\ChangeSet $changeSet
 * @var \Doctrine\ORM\ChangeSet\FieldVisitorInterface $visitor
 */
$relatedChanges = $changeSet->applyVisitor($visitor, 'relation.fieldName');
```

# How to help

1. Improve README 
2. Fix text in README (My English is poor)
3. Improve library: any suggestions, corrections are accepted
