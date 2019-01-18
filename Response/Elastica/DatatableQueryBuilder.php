<?php

namespace Sg\DatatablesBundle\Response\Elastica;

use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Elastica\Query;
use Elastica\Query\Nested;
use Elastica\Query\Terms;
use Elastica\Query\BoolQuery;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use FOS\ElasticaBundle\HybridResult;
use Sg\DatatablesBundle\Datatable\Column\ColumnInterface;
use Sg\DatatablesBundle\Model\ModelDefinitionInterface;
use Sg\DatatablesBundle\Response\AbstractDatatableQueryBuilder;

abstract class DatatableQueryBuilder extends AbstractDatatableQueryBuilder
{
    const CONDITION_TYPE_SHOULD = 'should';
    const CONDITION_TYPE_MUST = 'must';

    /** @var PaginatedFinderInterface */
    protected $paginatedFinder;

    /** @var ModelDefinitionInterface $modelDefinition */
    protected $modelDefinition;

    /** @var array */
    protected $nestedPaths;

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
        $this->nestedPaths = [];
        $this->selectColumns = [];
        $this->searchColumns = [];
        $this->orderColumns = [];
    }

    /**
     * @param string $columnAlias
     * @param string $path
     * @return $this
     */
    protected function addNestedPath(string $columnAlias, $path): self
    {
        if ($columnAlias !== null && $columnAlias !== '' && $path !== null && strpos($path, '.') !== false) {
            $pathParts = explode('.', $path);
            if (count($pathParts) > 1) {
                $this->nestedPaths[$columnAlias] = implode('.', array_slice($pathParts, 0, -1));
            }
        }

        return $this;
    }

    /**
     * @param string $columnAlias
     * @return string|null
     */
    protected function getNestedPath(string $columnAlias)
    {
        if ($columnAlias !== '' && isset($this->nestedPaths[$columnAlias])) {
            return $this->nestedPaths[$columnAlias];
        }
        return null;
    }

    /**
     * @return $this
     */
    protected function initColumnArrays(): self
    {
        /**
         * @var int|string $key
         * @var ColumnInterface $column
         */
        foreach ($this->columns as $key => $column) {
            $dql = $this->accessor->getValue($column, 'dql');
            $data = $this->accessor->getValue($column, 'data');

            if ($this->isCustomDql($column)) {
                $this->addOrderColumn($column, $dql);
                $this->addSearchColumn($column, $dql);
            } elseif ($this->isSelectColumn($column)) {
                $this->addOrderColumn($column, $data);
                $this->addSearchColumn($column, $data);
            } else {
                if ($this->accessor->isReadable($column, 'orderColumn') &&
                    $this->isOrderableColumn($column)
                ) {
                    $orderColumn = $this->accessor->getValue($column, 'orderColumn');
                    $this->addOrderColumn($column, $orderColumn);
                } elseif ($this->isOrderableColumn($column)) {
                    $this->addOrderColumn($column, $data);
                } else {
                    $this->addOrderColumn($column, null);
                }

                if ($this->accessor->isReadable($column, 'searchColumn') &&
                    $this->isSearchableColumn($column)
                ) {
                    $searchColumn = $this->accessor->getValue($column, 'searchColumn');
                    if ((substr_count($searchColumn, '.') + 1) < 2) {
                        $searchColumn = $this->entityShortName . '.' . $searchColumn;
                    }
                    $this->addSearchColumn($column, $searchColumn);
                } elseif ($this->isSearchableColumn($column)) {
                    $this->addSearchColumn($column, $data);
                } else {
                    $this->addSearchColumn($column, null);
                }
            }
        }

        return $this;
    }

    /**
     * @param BoolQuery $query
     *
     * @return $this
     */
    protected function addSearchTerms(BoolQuery $query): self
    {
        // global filtering
        if (isset($this->requestParams['search']) && '' != $this->requestParams['search']['value']) {
            $searchParams = $this->requestParams['search'];
            $filterQueries = new BoolQuery();
            foreach ($this->searchColumns as $key => $column) {
                if ($column === null) {
                    continue;
                }

                /** @var ColumnInterface $currentCol */
                $currentCol = $this->columns[$key];

                $this->addColumnSearchTerm($filterQueries, $currentCol, $column, $searchParams['value']);
            }
            if (!empty($filterQueries->getParams())) {
                $query->addFilter($filterQueries);
            }
        }

        // individual filtering
        if (true === $this->accessor->getValue($this->options, 'individualFiltering')) {
            $filterQueries = new BoolQuery();
            foreach ($this->searchColumns as $key => $column) {
                if ($column === null) {
                    continue;
                }

                /** @var ColumnInterface $currentCol */
                $currentCol = $this->columns[$key];

                $searchParams = $this->requestParams['columns'][$key]['search'];

                $this->addColumnSearchTerm(
                    $filterQueries,
                    $currentCol,
                    $column,
                    $searchParams['value'],
                    self::CONDITION_TYPE_MUST
                );
            }
            if (!empty($filterQueries->getParams())) {
                $query->addFilter($filterQueries);
            }
        }

        return $this;
    }

    /**
     * @param BoolQuery $filterQueries
     * @param ColumnInterface $column
     * @param string $columnAlias
     * @param int|string $value
     * @param string $conditionType
     * @return $this
     */
    protected function addColumnSearchTerm(
        BoolQuery $filterQueries,
        ColumnInterface $column,
        string $columnAlias,
        $value,
        string $conditionType = self::CONDITION_TYPE_SHOULD
    ): self {
        switch ($column->getTypeOfField()) {
            case 'integer':
                $this->createIntegerShouldTerm($value, $columnAlias, $filterQueries);
                break;
            case 'string':
                $this->createStringFilterTerm($value, $columnAlias, $filterQueries, $conditionType);
                break;
            default:
                break;
        }

        return $this;
    }

    /**
     * @param int $value
     * @param string $columnAlias
     * @param BoolQuery $filterQueries
     */
    protected function createIntegerShouldTerm($value, string $columnAlias, BoolQuery $filterQueries)
    {
        if (trim("{$columnAlias}") !== '' && (int)$value !== 0) {
            /** @var string|null $nestedPath */
            $nestedPath = $this->getNestedPath($columnAlias);
            if ($nestedPath !== null) {
                $currentNested = new Nested();
                $currentNested->setPath($nestedPath);
                $currentNestedQuery = new BoolQuery();
                $integerTerm = new Terms();
                $integerTerm->setTerms($columnAlias, [(int)$value]);
                $currentNestedQuery->addShould($integerTerm);
                $currentNested->setQuery($currentNestedQuery);
                $filterQueries->addShould($currentNested);
            } else {
                $integerTerm = new Terms();
                $integerTerm->setTerms($columnAlias, [(int)$value]);
                $filterQueries->addShould($integerTerm);
            }
        }
    }

    /**
     * @param int|string $value
     * @param string $columnAlias
     * @param BoolQuery $filterQueries
     * @param string $conditionType
     */
    protected function createStringFilterTerm($value, string $columnAlias, BoolQuery $filterQueries, string $conditionType)
    {
        if (trim("{$columnAlias}") !== '' && trim("{$value}") !== '') {
            $conditionFunc = $conditionType === self::CONDITION_TYPE_MUST ? 'addMust' : 'addShould';
            $regexQuery = new Query\Regexp($columnAlias, '.*' . strtolower($value) . '.*');
            /** @var string|null $nestedPath */
            $nestedPath = $this->getNestedPath($columnAlias);
            if ($nestedPath !== null) {
                $currentNested = new Nested();
                $currentNested->setPath($nestedPath);
                $currentNestedQuery = new BoolQuery();
                $currentNestedQuery->addShould($regexQuery);
                $currentNested->setQuery($currentNestedQuery);
                $filterQueries->$conditionFunc($currentNested);
            } else {
                $filterQueries->$conditionFunc($regexQuery);
            }
        }
    }

    /**
     * @param ColumnInterface
     * @return $this
     */
    protected function addOrderColumn(ColumnInterface $column, $data): self
    {
        $col = null;
        if ($data !== null && $this->isOrderableColumn($column)) {
            if ($column->getTypeOfField() == 'string') {
                $col = $data . '.keyword';
            } else {
                $col = $data;
            }
        }

        $col = str_replace('[,]', '', $col);

        $this->orderColumns[] = $col;

        $this->addNestedPath($col, $data);

        return $this;
    }

    /**
     * @param ColumnInterface $column
     *
     * @return $this
     */
    protected function addSearchColumn(ColumnInterface $column, $data): self
    {
        $col = $this->isSearchableColumn($column) ?  $data : null;
        $col = str_replace('[,]', '', $col);

        $this->searchColumns[] = $col;

        $this->addNestedPath($col, $data);

        return $this;
    }

    /**
     * @param Query $query
     * @return $this
     */
    protected function setOrderBy(Query $query): self
    {
        if (isset($this->requestParams['order']) && \count($this->requestParams['order'])) {
            $counter = \count($this->requestParams['order']);

            for ($i = 0; $i < $counter; $i++) {
                $columnIdx = (int)$this->requestParams['order'][$i]['column'];
                $requestColumn = $this->requestParams['columns'][$columnIdx];

                if ('true' === $requestColumn['orderable']) {
                    $columnName = $this->orderColumns[$columnIdx];
                    $orderOptions = ['order' => $this->requestParams['order'][$i]['dir']];

                    /** @var string|null $nestedPath */
                    $nestedPath = $this->getNestedPath($columnName);
                    if ($nestedPath !== null) {
                        $orderOptions['nested_path'] = $nestedPath;
                    }

                    $query->setSort([$columnName => $orderOptions]);
                }
            }
        }
        return $this;
    }


    /**
     * @param bool $countQuery
     * @return Query
     */
    protected function getQuery($countQuery=false): Query
    {
        $query = new Query();

        $boolQuery = new BoolQuery();
        $this->setTermsFilters($boolQuery);
        if (!$countQuery) {
            $this->addSearchTerms($boolQuery);
        }
        $query->setQuery($boolQuery);
        if (!$countQuery) {
            $this->setOrderBy($query);
        }

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
     * @return int
     */
    public function getCountAllResults(): int
    {
        return (int)$this->paginatedFinder->createRawPaginatorAdapter($this->getQuery(true))->getTotalHits();
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

    /**
     * @param ColumnInterface $column
     * @return bool
     */
    private function isCustomDql(ColumnInterface $column): bool
    {
        return true === $this->accessor->getValue($column, 'customDql');
    }

    /**
     * @param ColumnInterface $column
     * @return bool
     */
    private function isSelectColumn(ColumnInterface $column): bool
    {
        return true === $this->accessor->getValue($column, 'selectColumn');
    }

    /**
     * @param ColumnInterface $column
     * @return bool
     */
    private function isOrderableColumn(ColumnInterface $column): bool
    {
        return true === $this->accessor->getValue($column, 'orderable');
    }
}
