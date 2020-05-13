<?php

namespace Doctrine\Tests\ChangeSet\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class EntityC
 * @package Doctrine\Tests\ChangeSet\Entity
 * @ORM\Entity()
 * @ORM\Table(name="test_entity_c")
 */
class EntityC
{
    /**
     * @var int
     *
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(name="id", type="integer")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(name="integer", type="integer")
     */
    private $integer;

    /**
     * @var string
     *
     * @ORM\Column(name="string", type="string")
     */
    private $string;

    /**
     * @var bool
     *
     * @ORM\Column(name="boolean", type="boolean")
     */
    private $boolean;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="date", type="date")
     */
    private $date;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="time", type="time")
     */
    private $time;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="date_time", type="datetime")
     */
    private $dateTime;

    /**
     * @var integer
     *
     * @ORM\Column(name="small_int", type="smallint")
     */
    private $smallInt;

    /**
     * @var integer
     *
     * @ORM\Column(name="big_int", type="bigint")
     */
    private $bigInt;

    /**
     * @var float
     *
     * @ORM\Column(name="float", type="float")
     */
    private $float;

    /**
     * @var float
     *
     * @ORM\Column(type="decimal", name="decimal")
     */
    private $decimal;

    /**
     * @var EntityA|null
     *
     * @ORM\ManyToOne(targetEntity="Doctrine\Tests\ChangeSet\Entity\EntityB", inversedBy="cCollection")
     * @ORM\JoinColumn(name="b_entity_id", referencedColumnName="id")
     */
    private $bEntity;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getInteger(): int
    {
        return $this->integer;
    }

    /**
     * @param int $integer
     */
    public function setInteger(int $integer): void
    {
        $this->integer = $integer;
    }

    /**
     * @return string
     */
    public function getString(): string
    {
        return $this->string;
    }

    /**
     * @param string $string
     */
    public function setString(string $string): void
    {
        $this->string = $string;
    }

    /**
     * @return \DateTime
     */
    public function getDate(): \DateTime
    {
        return $this->date;
    }

    /**
     * @param \DateTime $date
     */
    public function setDate(\DateTime $date): void
    {
        $this->date = $date;
    }

    /**
     * @return \DateTime
     */
    public function getTime(): \DateTime
    {
        return $this->time;
    }

    /**
     * @param \DateTime $time
     */
    public function setTime(\DateTime $time): void
    {
        $this->time = $time;
    }

    /**
     * @return \DateTime
     */
    public function getDateTime(): \DateTime
    {
        return $this->dateTime;
    }

    /**
     * @param \DateTime $dateTime
     */
    public function setDateTime(\DateTime $dateTime): void
    {
        $this->dateTime = $dateTime;
    }

    /**
     * @return int
     */
    public function getSmallInt(): int
    {
        return $this->smallInt;
    }

    /**
     * @param int $smallInt
     */
    public function setSmallInt(int $smallInt): void
    {
        $this->smallInt = $smallInt;
    }

    /**
     * @return float
     */
    public function getFloat(): float
    {
        return $this->float;
    }

    /**
     * @param float $float
     */
    public function setFloat(float $float): void
    {
        $this->float = $float;
    }

    /**
     * @return float
     */
    public function getDecimal(): float
    {
        return $this->decimal;
    }

    /**
     * @param float $decimal
     */
    public function setDecimal(float $decimal): void
    {
        $this->decimal = $decimal;
    }

    /**
     * @return bool
     */
    public function isBoolean(): bool
    {
        return $this->boolean;
    }

    /**
     * @param bool $boolean
     */
    public function setBoolean(bool $boolean): void
    {
        $this->boolean = $boolean;
    }

    /**
     * @return int
     */
    public function getBigInt(): int
    {
        return $this->bigInt;
    }

    /**
     * @param int $bigInt
     */
    public function setBigInt(int $bigInt): void
    {
        $this->bigInt = $bigInt;
    }

    /**
     * @return EntityA|null
     */
    public function getBEntity(): ?EntityA
    {
        return $this->bEntity;
    }

    /**
     * @param EntityA $bEntity
     */
    public function setBEntity(EntityA $bEntity): void
    {
        $this->bEntity = $bEntity;
    }
}
