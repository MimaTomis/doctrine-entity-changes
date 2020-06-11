<?php

namespace Doctrine\ORM\ChangeSet\Processor;

use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\ChangeSet\ChangeSet;
use Doctrine\ORM\ChangeSet\ChangeSetCollector;
use Doctrine\ORM\ChangeSet\Exception;
use Doctrine\ORM\ChangeSet\Field\StringField;
use Doctrine\ORM\Mapping\MappingException;

/**
 * Class ChangeSetProcessor
 * @package Doctrine\ORM\ChangeSet\Processor
 */
class ChangeSetProcessor
{
    /**
     * @var ChangeSetCollector
     */
    private $collector;

    /**
     * @var ProcessorFieldVisitorFactoryInterface
     */
    private $visitorFactory;

    /**
     * ChangeSetProcessor constructor.
     * @param ChangeSetCollector $collector
     * @param ProcessorFieldVisitorFactoryInterface $visitorFactory
     */
    public function __construct(ChangeSetCollector $collector, ProcessorFieldVisitorFactoryInterface $visitorFactory)
    {
        $this->collector = $collector;
        $this->visitorFactory = $visitorFactory;
    }

    /**
     * @param object $entity
     * @return StringField[]
     * @throws Exception\InvalidChangeSetException
     * @throws Exception\InvalidEntityException
     * @throws MappingException
     */
    public function getChanges($entity): array
    {
        $changeSets = $this->collector->collectChanges($entity);
        $visitor = $this->visitorFactory->createVisitor(ClassUtils::getClass($entity));

        $this->processChangeSets($changeSets, $visitor);

        return $visitor->getFields();
    }

    /**
     * @param ChangeSet[] $changeSets ;
     * @param ProcessorFieldVisitorInterface $visitor
     */
    private function processChangeSets(array $changeSets, ProcessorFieldVisitorInterface $visitor): void
    {
        foreach ($changeSets as $changeSet) {
            $changeSet->applyVisitor($visitor);
            $relatedChanges = $changeSet->getRelatedChangeSets();

            if (!empty($relatedChanges)) {
                $this->processChangeSets($relatedChanges, $visitor);
            }
        }
    }
}
