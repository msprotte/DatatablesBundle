<?php

namespace Sg\DatatablesBundle\Datatable\Column;

class TextColumn extends Column
{
    /**
     * @return string
     */
    public function getTypeOfField()
    {
        return 'string';
    }
}
