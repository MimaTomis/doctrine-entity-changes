<?php

namespace Doctrine\ORM\ChangeSet;

/**
 * Interface FieldVisitorAcceptingInterface
 * @package Doctrine\ORM\ChangeSet
 */
interface FieldVisitorAcceptingInterface
{
    /**
     * @param FieldVisitorInterface $visitor
     */
    public function accept(FieldVisitorInterface $visitor): void;
}
