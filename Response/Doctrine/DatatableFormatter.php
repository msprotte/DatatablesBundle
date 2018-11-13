<?php

/**
 * This file is part of the SgDatatablesBundle package.
 *
 * (c) stwe <https://github.com/stwe/DatatablesBundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sg\DatatablesBundle\Response\Doctrine;

use Sg\DatatablesBundle\Response\AbstractDatatableFormatter;

class DatatableFormatter extends AbstractDatatableFormatter
{
    protected function doCustomFormatterForRow(array &$row)
    {
        if (isset($row[0])) {
            $row = array_merge($row, $row[0]);
            unset($row[0]);
        }
    }
}
