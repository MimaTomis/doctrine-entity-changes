<?php

namespace Doctrine\ORM\ChangeSet\Processor;

/**
 * Class ProcessorFieldVisitorFactory
 * @package Doctrine\ORM\ChangeSet\Processor
 */
class ProcessorFieldVisitorFactory implements ProcessorFieldVisitorFactoryInterface
{
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
    private $acceptedFieldByEntityClasses;

    /**
     * AbstractCommonFieldVisitor constructor.
     * @param array $dateFormats
     * @param array $booleanFormats
     * @param array $acceptedFieldByEntityClasses
     */
    public function __construct(
        array $dateFormats = [],
        array $booleanFormats = [],
        array $acceptedFieldByEntityClasses = []
    )
    {
        $this->dateFormats = $dateFormats;
        $this->booleanFormats = $booleanFormats;
        $this->acceptedFieldByEntityClasses = $acceptedFieldByEntityClasses;
    }

    /**
     * @inheritDoc
     */
    public function createVisitor(string $entityClassName): ProcessorFieldVisitorInterface
    {
        return new ProcessorFieldVisitor(
            $this->dateFormats,
            $this->booleanFormats,
            $this->acceptedFieldByEntityClasses[$entityClassName] ?? []
        );
    }
}