<?php

namespace Sg\DatatablesBundle\Response\Elastica;

use Traversable;

class ElasticaEntries implements \IteratorAggregate
{
    /** @var int */
    protected $count = 0;

    /** @var array */
    protected $entries = [];

    /** @return int */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * @param int $count
     *
     * @return ElasticaEntries
     */
    public function setCount(int $count): ElasticaEntries
    {
        $this->count = $count;

        return $this;
    }

    /**
     * @return array
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    /**
     * @param array $entries
     *
     * @return ElasticaEntries
     */
    public function setEntries(array $entries): ElasticaEntries
    {
        $this->entries = $entries;

        return $this;
    }

    /**
     * @return \ArrayIterator|Traversable
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->getEntries());
    }
}
