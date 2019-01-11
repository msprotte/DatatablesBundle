<?php

namespace Sg\DatatablesBundle\Response\Elastica;

use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Elastica\Query;
use Elastica\Query\BoolQuery;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use FOS\ElasticaBundle\HybridResult;
use Sg\DatatablesBundle\Datatable\Column\AbstractColumn;
use Sg\DatatablesBundle\Datatable\Column\TextColumn;
use Sg\DatatablesBundle\Model\ModelDefinitionInterface;
use Sg\DatatablesBundle\Response\AbstractDatatableQueryBuilder;

abstract class DatatableQueryBuilder extends AbstractDatatableQueryBuilder
{
    /** @var PaginatedFinderInterface */
    protected $paginatedFinder;

    /** @var ModelDefinitionInterface $modelDefinition */
    protected $modelDefinition;

    /**
     * @param BoolQuery $query
     *
     * @return BoolQuery
     */
    abstract protected function setTermsFilters(BoolQuery $query): BoolQuery;

    /**
     * @param PaginatedFinderInterface $paginatedFinder
     */
    public function setPaginatedFinder(PaginatedFinderInterface $paginatedFinder)
    {
        $this->paginatedFinder = $paginatedFinder;
    }

    /**
     * @param ModelDefinitionInterface $modelDefinition
     */
    public function setModelDefinition(ModelDefinitionInterface $modelDefinition)
    {
        $this->modelDefinition = $modelDefinition;
    }

    /** nothing needed more than in abstract */
    protected function loadIndividualConstructSettings()
    {
    }

    /**
     * @return AbstractDatatableQueryBuilder
     */
    protected function initColumnArrays(): AbstractDatatableQueryBuilder
    {
        foreach ($this->columns as $key => $column) {
            if (true === $this->accessor->getValue($column, 'customDql')) {
                $this->addOrderColumn($column);
                $this->addSearchColumn($column);
            } elseif (true === $this->accessor->getValue($column, 'selectColumn')) {
                $this->addSearchOrderColumn($column);
            } else {
                if (
                    $this->accessor->isReadable($column, 'orderColumn') &&
                    true === $this->accessor->getValue($column, 'orderable')
                ) {
                    $orderColumn = $this->accessor->getValue($column, 'orderColumn');
                    $this->orderColumns[] = $orderColumn;
                } else {
                    $this->orderColumns[] = null;
                }

                if (
                    $this->accessor->isReadable($column, 'searchColumn') &&
                    true === $this->accessor->getValue($column, 'searchable')
                ) {
                    $searchColumn = $this->accessor->getValue($column, 'searchColumn');
                    if ((substr_count($searchColumn, '.') + 1) < 2) {
                        $searchColumn = $this->entityShortName . '.' . $searchColumn;
                    }
                    $this->searchColumns[] = $searchColumn;
                } else {
                    $this->searchColumns[] = null;
                }
            }
        }

        return $this;
    }

    /**
     * @param BoolQuery $query
     *
     * @return ElasticaDatatableTransactionQueryBuilder
     */
    protected function addGlobalSearchTerms(BoolQuery $query): self
    {
        if ($this->modelDefinition->hasSearch()) {
            $searchParams = $this->modelDefinition->getSearch();
            $filterQueries = new BoolQuery();
            foreach ($this->searchColumns as $key => $column) {
                if ($column === null) {
                    continue;
                }
                $currentCol = $this->columns[$key];
                if (!$currentCol instanceof Column) {
                    continue;
                }

                switch ($currentCol->getTypeOfField()) {
                    case 'integer':
                        $this->createIntegerShouldTerm($searchParams['value'], $column, $filterQueries);
                        break;
                    case 'string':
                        $filterQueries->addShould(new Query\Regexp($column, '.*' . $searchParams['value'] . '.*'));
                        break;
                    default:
                        break;
                }
            }
            $query->addMust($filterQueries);
        }

        return $this;
    }

    /**
     * @param int $value
     * @param string $column
     * @param BoolQuery $filterQueries
     */
    protected function createIntegerShouldTerm($value, string $column, BoolQuery $filterQueries)
    {
        if ((int)$value !== 0) {
            $integerTerm = new Terms();
            $integerTerm->setTerms($column, [(int)$value]);
            $filterQueries->addShould($integerTerm);
        }
    }

    /**
     * @param AbstractColumn $column
     *
     * @return $this
     */
    protected function addOrderColumn(AbstractColumn $column): AbstractDatatableQueryBuilder
    {
        $col = null;
        if (true === $this->accessor->getValue($column, 'orderable')) {
            if ($column instanceof TextColumn) {
                $col = $column->getData() . '.keyword';
            } else {
                $col = $column->getData();
            }
        }

        $this->orderColumns[] = str_replace('[,]', '', $col);

        return $this;
    }

    /**
     * @param AbstractColumn $column
     *
     * @return $this
     */
    protected function addSearchColumn(AbstractColumn $column): AbstractDatatableQueryBuilder
    {
        $col = null;
        if (true === $this->accessor->getValue($column, 'searchable')) {
            $col = $column->getData();
        }
        $this->searchColumns[] = str_replace('[,]', '', $col);

        return $this;
    }

    /**
     * @param AbstractColumn $column
     *
     * @return $this
     */
    protected function addSearchOrderColumn(AbstractColumn $column): AbstractDatatableQueryBuilder
    {
        $this->addOrderColumn($column);
        $this->addSearchColumn($column);

        return $this;
    }

    /**
     * @return int
     */
    public function getCountAllResults(): int
    {
        return (int)$this->paginatedFinder->createRawPaginatorAdapter($this->getQuery())->getTotalHits();
    }

    /**
     * @param Query $query
     */
    protected function setOrderBy(Query $query)
    {
        if (isset($this->requestParams['order']) && \count($this->requestParams['order'])) {
            $counter = \count($this->requestParams['order']);

            for ($i = 0; $i < $counter; $i++) {
                $columnIdx = (int)$this->requestParams['order'][$i]['column'];
                $requestColumn = $this->requestParams['columns'][$columnIdx];

                if ('true' === $requestColumn['orderable']) {
                    $columnName = $this->orderColumns[$columnIdx];
                    $orderDirection = $this->requestParams['order'][$i]['dir'];

                    $query->setSort([$columnName => $orderDirection]);
                }
            }
        }
    }

    /**
     * @return Query
     */
    protected function getQuery(): Query
    {
        $query = new Query();

        $boolQuery = new BoolQuery();
        $this->setTermsFilters($boolQuery);
        $query->setQuery($boolQuery);
        $this->setOrderBy($query);

        return $query;
    }

    /**
     * @return ElasticaEntries
     */
    public function execute(): ElasticaEntries
    {
        $results = $this->paginatedFinder->createHybridPaginatorAdapter($this->getQuery())->getResults(
            $this->requestParams['start'],
            $this->requestParams['length']
        );

        $resultEntries = [];
        /** @var HybridResult $result */
        foreach ($results->toArray() as $result) {
            $resultEntries[] = $result->getResult()->getSource();
        }

        $entries = new ElasticaEntries();
        $entries->setCount($results->getTotalHits());
        $entries->setEntries($resultEntries);

        return $entries;
    }

    /**
     * @param ClassMetadata $metadata
     *
     * @return string
     */
    protected function getEntityShortName(ClassMetadata $metadata): string
    {
        return strtolower($metadata->getReflectionClass()->getShortName());
    }
}
