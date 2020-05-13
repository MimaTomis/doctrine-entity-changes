<?php

namespace Doctrine\ORM\ChangeSet;

/**
 * Class AbstractField
 * @package Doctrine\ORM\ChangeSet
 */
abstract class AbstractField implements FieldVisitorAcceptingInterface
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $namespace;

    /**
     * AbstractField constructor.
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * @param bool $includeNamespace
     * @return string
     */
    public function getName(bool $includeNamespace = false): string
    {
        return $includeNamespace && !empty($this->namespace) ?
            $this->namespace . '.' . $this->name :
            $this->name;
    }

    /**
     * @param string $namespace
     * @return AbstractField
     */
    public function setNamespace(string $namespace): self
    {
        $this->namespace = $namespace;

        return $this;
    }
}
