<?php

namespace Doctrine\ORM\ChangeSet;

/**
 * Interface ChangeSetFactoryInterface
 * @package Doctrine\ORM\ChangeSet
 */
interface ChangeSetFactoryInterface
{
    /**
     * @param object $entity
     * @param string $namespace
     * @return ChangeSet
     */
    public function createChangeSet($entity, string $namespace): ChangeSet;
}
