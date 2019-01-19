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
                $this->nestedPaths[$columnAlias] =
                    implode('.', array_slice($pathParts, 0, -1));
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

            if ($this->hasCustomDql($column)) {
                $this->addSearchOrderColumn($column, $dql);
            } elseif ($this->isSelectColumn($column)) {
                $this->addSearchOrderColumn($column, $data);
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
     * @return $this
     */
    protected function addSearchTerms(BoolQuery $query): self
    {
        // global filtering
        if (isset($this->requestParams['search']) && '' != $this->requestParams['search']['value']) {
            /** @var BoolQuery $filterQueries */
            $filterQueries = new BoolQuery();

            /**
             * @var int|string $key
             * @var ColumnInterface $column
             */
            foreach ($this->searchColumns as $key => $columnAlias) {
                if ($columnAlias === null || $columnAlias === '') {
                    continue;
                }

                /** @var ColumnInterface $column */
                $column = $this->columns[$key];

                $this->addColumnSearchTerm(
                    $filterQueries,
                    self::CONDITION_TYPE_SHOULD,
                    $column,
                    $columnAlias,
                    $this->requestParams['search']['value']
                );
            }
            if (!empty($filterQueries->getParams())) {
                $query->addFilter($filterQueries);
            }
        }

        // individual filtering
        if (true === $this->accessor->getValue($this->options, 'individualFiltering')) {
            /** @var BoolQuery $filterQueries */
            $filterQueries = new BoolQuery();

            /**
             * @var int|string $key
             * @var ColumnInterface $column
             */
            foreach ($this->searchColumns as $key => $columnAlias) {
                if ($columnAlias === null || $columnAlias === '') {
                    continue;
                }

                /** @var ColumnInterface $column */
                $column = $this->columns[$key];

                $this->addColumnSearchTerm(
                    $filterQueries,
                    self::CONDITION_TYPE_MUST,
                    $column,
                    $columnAlias,
                    $this->requestParams['columns'][$key]['search']['value']
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
     * @param string $conditionType
     * @param ColumnInterface $column
     * @param string $columnAlias
     * @param int|string $value
     * @return $this
     */
    protected function addColumnSearchTerm(
        BoolQuery $filterQueries,
        string $conditionType,
        ColumnInterface $column,
        string $columnAlias,
        $value
    ): self {
        switch ($column->getTypeOfField()) {
            case 'integer':
                $this->createIntegerShouldTerm(
                    $filterQueries,
                    $columnAlias,
                    (int)$value
                );
                break;
            case 'string':
                $this->createStringFilterTerm(
                    $filterQueries,
                    $conditionType,
                    $columnAlias,
                    (string)$value
                );
                break;
            default:
                break;
        }

        return $this;
    }

    /**
     * @param BoolQuery $filterQueries
     * @param string $columnAlias
     * @param int $value
     */
    protected function createIntegerShouldTerm(
        BoolQuery $filterQueries,
        string $columnAlias,
        int $value
    ) {
        if ($columnAlias !== '' && $value !== 0) {
            /** @var Terms $integerTerm */
            $integerTerm = new Terms();
            $integerTerm->setTerms($columnAlias, [$value]);

            /** @var string|null $nestedPath */
            $nestedPath = $this->getNestedPath($columnAlias);
            if ($nestedPath !== null) {
                /** @var Nested $nested */
                $nested = new Nested();
                $nested->setPath($nestedPath);
                /** @var BoolQuery $boolQuery */
                $boolQuery = new BoolQuery();
                $boolQuery->addShould($integerTerm);
                $nested->setQuery($boolQuery);
                $filterQueries->addShould($nested);
            } else {
                $filterQueries->addShould($integerTerm);
            }
        }
    }

    /**
     * @param BoolQuery $filterQueries
     * @param string $conditionType
     * @param string $columnAlias
     * @param string $value
     */
    protected function createStringFilterTerm(
        BoolQuery $filterQueries,
        string $conditionType,
        string $columnAlias,
        string $value
    ) {
        if ($columnAlias !== '' && $value !== '') {
            /** @var Query\Regexp $regexQuery */
            $regexQuery = new Query\Regexp($columnAlias, '.*' . strtolower($value) . '.*');

            /** @var string|null $nestedPath */
            $nestedPath = $this->getNestedPath($columnAlias);
            if ($nestedPath !== null) {
                /** @var Nested $nested */
                $nested = new Nested();
                $nested->setPath($nestedPath);
                /** @var BoolQuery $boolQuery */
                $boolQuery = new BoolQuery();
                $boolQuery->addShould($regexQuery);
                $nested->setQuery($boolQuery);
                $stringFilterQuery = $nested;
            } else {
                $stringFilterQuery = $regexQuery;
            }

            if ($conditionType === self::CONDITION_TYPE_MUST) {
                $filterQueries->addMust($stringFilterQuery);
            } elseif ($conditionType === self::CONDITION_TYPE_SHOULD) {
                $filterQueries->addShould($stringFilterQuery);
            }
        }
    }

    /**
     *
     * @param ColumnInterface $column
     * @param string $data
     * @return $this
     */
    protected function addSearchOrderColumn(ColumnInterface $column, $data): self
    {
        $this->addSearchColumn($column, $data);
        $this->addOrderColumn($column, $data);

        return $this;
    }

    /**
     * @param ColumnInterface $column
     * @param string $data
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
     * @param string $data
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
        if (isset($this->requestParams['order']) &&
            \count($this->requestParams['order'])
        ) {
            $counter = \count($this->requestParams['order']);

            for ($i = 0; $i < $counter; $i++) {
                $columnIdx = (int)$this->requestParams['order'][$i]['column'];
                $requestColumn = $this->requestParams['columns'][$columnIdx];

                if ('true' === $requestColumn['orderable']) {
                    $columnName = $this->orderColumns[$columnIdx];
                    $orderOptions = [
                        'order' => $this->requestParams['order'][$i]['dir']
                    ];

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
        /** @var Query $query */
        $query = new Query();

        /** @var BoolQuery $boolQuery */
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

        /** @var ElasticaEntries $entries */
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
    private function hasCustomDql(ColumnInterface $column): bool
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
