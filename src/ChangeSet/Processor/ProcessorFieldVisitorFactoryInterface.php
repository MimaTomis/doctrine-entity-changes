<?php

namespace Doctrine\ORM\ChangeSet\Processor;

/**
 * Interface ProcessorFieldVisitorFactoryInterface
 * @package Doctrine\ORM\ChangeSet\Processor
 */
interface ProcessorFieldVisitorFactoryInterface
{
    /**
     * Create visitor
     *
     * @param string $entityClassName
     * @return ProcessorFieldVisitorInterface
     */
    public function createVisitor(string $entityClassName): ProcessorFieldVisitorInterface;
}
