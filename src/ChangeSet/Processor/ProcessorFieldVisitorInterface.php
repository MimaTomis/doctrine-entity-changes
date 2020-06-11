<?php

namespace Doctrine\ORM\ChangeSet\Processor;

use Doctrine\ORM\ChangeSet\Field\StringField;
use Doctrine\ORM\ChangeSet\FieldVisitorInterface;

/**
 * Interface ProcessorFieldVisitorInterface
 * @package Doctrine\ORM\ChangeSet\Processor
 */
interface ProcessorFieldVisitorInterface extends FieldVisitorInterface
{
    /**
     * Get string fields
     *
     * @return StringField[]
     */
    public function getFields(): array;
}
