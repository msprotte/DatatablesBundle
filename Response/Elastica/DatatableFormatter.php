<?php

namespace Sg\DatatablesBundle\Response\Elastica;

use Sg\DatatablesBundle\Datatable\Column\ColumnInterface;
use Sg\DatatablesBundle\Datatable\DatatableInterface;
use \Sg\DatatablesBundle\Response\DatatableFormatter as AbstractDatatableFormatter;

class DatatableFormatter extends AbstractDatatableFormatter
{
    /**
     * @param ElasticaEntries $entries
     * @param DatatableInterface $datatable
     */
    public function runFormatter(ElasticaEntries $entries, DatatableInterface $datatable)
    {
        $lineFormatter = $datatable->getLineFormatter();
        $columns = $datatable->getColumnBuilder()->getColumns();

        foreach ($entries->getEntries() as $row) {
            // Format custom DQL fields output ('custom.dql.name' => $row['custom']['dql']['name'] = 'value')
            foreach ($columns as $column) {
                /** @noinspection PhpUndefinedMethodInspection */
                if (true === $column->isCustomDql()) {
                    /** @noinspection PhpUndefinedMethodInspection */
                    $columnAlias = str_replace('.', '_', $column->getData());
                    /** @noinspection PhpUndefinedMethodInspection */
                    $columnPath = '[' . str_replace('.', '][', $column->getData()) . ']';
                    /** @noinspection PhpUndefinedMethodInspection */
                    if ($columnAlias !== $column->getData()) {
                        $this->accessor->setValue($row, $columnPath, $row[$columnAlias]);
                        unset($row[$columnAlias]);
                    }
                }
            }

            // 1. Set (if necessary) the custom data source for the Columns with a 'data' option
            foreach ($columns as $column) {
                /** @noinspection PhpUndefinedMethodInspection */
                $dql = $column->getDql();
                /** @noinspection PhpUndefinedMethodInspection */
                $data = $column->getData();

                /** @noinspection PhpUndefinedMethodInspection */
                if (false === $column->isAssociation()) {
                    if (null !== $dql && $dql !== $data && false === array_key_exists($data, $row)) {
                        $row[$data] = $row[$dql];
                        unset($row[$dql]);
                    }
                }
            }

            // 2. Call the the lineFormatter to format row items
            if (null !== $lineFormatter && is_callable($lineFormatter)) {
                $row = call_user_func($datatable->getLineFormatter(), $row);
            }

            /** @var ColumnInterface $column */
            foreach ($columns as $column) {
                // 3. Add some special data to the output array. For example, the visibility of actions.
                $column->addDataToOutputArray($row);
                // 4. Call Columns renderContent method to format row items (e.g. for images or boolean values)
                $column->renderCellContent($row);
            }

            $this->output['data'][] = $row;
        }
    }
}
