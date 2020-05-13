# Doctrine Entity Changes

Library makes it easy to get changes of Doctrine entities.

## How to use

*Note that getting changes must be earlier then flush.*

### Collect changes

For example, declare entity class:

```php
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

Collect changes for example entity:

```php
/**
 * @var Example $entity - instance of entity class retrieved from DB
 * @var \Doctrine\ORM\EntityManager $entityManager - мэнэджер сущностей Doctrine
 */ 

$changeSetCollector = new \Doctrine\ORM\ChangeSet\ChangeSetCollector($entityManager);
$changeSet = $changeSetCollector->collectChanges($entity);

$entityManager->persist($entity);
$entityManager->flush($entity);
```` 

### Each field changes

For example make ChangeLog entity

```php
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

Further you need it's implement FieldVisitorInterface (this is full implementation, below there are easier variants):

```php
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

```php
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

### Visitors

#### Abstract visitors

To simplify you can extend you visitor of:
1. *\Doctrine\ORM\ChangeSet\Visitor\AbstractEmptyFieldVisitor* - full clear field visitor (with empty methods)
2. *\Doctrine\ORM\ChangeSet\Visitor\AbstractCommonFieldVisitor* - visitor with logic to "pre-formatting" fields, like in example above

Let's try to simplify EntityFieldVisitor with AbstractCommonFieldVisitor:

```php
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
You can add list of accepted fields in third argument of parent class constructor. By default accepted all fields. List must include full path to field:

```php
/**
 * @var \Doctrine\ORM\ChangeSet\AbstractField $field 
 */
$fieldName = $field->getName(); // return $title
$fieldNameIncludeNamespace = $field->getName(true); // return $title for parent entity and parentEntityField.$title for sub entities
```

#### CallbackVisitor

Callback visitor allows you to walk through field changes with a specific type.

```php
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