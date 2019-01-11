<?php

namespace Sg\DatatablesBundle\Model;

interface ModelDefinitionInterface
{
    /**
     * @return bool
     */
    public function hasSearch(): bool;

    /**
     * @param array $search
     *
     * @return self
     */
    public function setSearch(array $search): self;

    /**
     * @return array
     */
    public function getSearch(): array;
}
