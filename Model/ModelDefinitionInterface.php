<?php

namespace Sg\DatatablesBundle\Model;

interface ModelDefinitionInterface
{
    /**
     * @return bool
     *
     * @deprecated
     */
    public function hasSearch(): bool;

    /**
     * @param array $search
     *
     * @return self
     *
     * @deprecated
     */
    public function setSearch(array $search): self;

    /**
     * @return array
     *
     * @deprecated
     */
    public function getSearch(): array;
}
