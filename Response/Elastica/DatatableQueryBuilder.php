<?php

namespace Sg\DatatablesBundle\Response\Elastica;

use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Elastica\Query;
use Elastica\Query\Nested;
use Elastica\Query\Terms;
use Elastica\Query\BoolQuery;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use FOS\ElasticaBundle\HybridResult;
use FOS\ElasticaBundle\Paginator\PartialResultsInterface;
use Sg\DatatablesBundle\Datatable\Column\Column;
use Sg\DatatablesBundle\Datatable\Column\ColumnInterface;
use Sg\DatatablesBundle\Datatable\Column\VirtualColumn;
use Sg\DatatablesBundle\Model\ModelDefinitionInterface;
use Sg\DatatablesBundle\Response\AbstractDatatableQueryBuilder;
use Sg\DatatablesBundle\Datatable\Filter\FilterInterface;
use Sg\DatatablesBundle\Datatable\Filter\SelectFilter;

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

    /** @var array */
    protected $sourceFields;

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
        $this->searchColumnGroups = [];
    }

    /**
     * @param string $columnAlias
     * @param string $path
     *
     * @return $this
     */
    protected function addNestedPath(string $columnAlias, $path): self
    {
        if (null !== $columnAlias && '' !== $columnAlias && null !== $path && false !== strpos($path, '.')) {
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
     *
     * @return string|null
     */
    protected function getNestedPath(string $columnAlias)
    {
        if ('' !== $columnAlias && isset($this->nestedPaths[$columnAlias])) {
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
                } else {
                    $this->addOrderColumn($column, null);
                }

                if ($this->accessor->isReadable($column, 'searchColumn') &&
                    $this->isSearchableColumn($column)
                ) {
                    $searchColumn = $this->accessor->getValue($column, 'searchColumn');
                    $this->addSearchColumn($column, $searchColumn);
                } else {
                    $this->addSearchColumn($column, null);
                }
            }

            $this->addSearchColumnGroupEntry($column, $key);
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
        if (isset($this->requestParams['search']) && '' !== $this->requestParams['search']['value']) {
            /** @var BoolQuery $filterQueries */
            $filterQueries = new BoolQuery();

            /**
             * @var int|string $key
             * @var ColumnInterface $column
             */
            foreach ($this->columns as $key => $column) {
                if (true === $this->isSearchableColumn($column)) {
                    /** @var string $columnAlias */
                    $columnAlias = $this->searchColumns[$key];
                    if ('' === $columnAlias || null === $columnAlias) {
                        continue;
                    }

                    $searchValue = $this->requestParams['search']['value'];

                    $this->addColumnSearchTerm(
                        $filterQueries,
                        self::CONDITION_TYPE_SHOULD,
                        $column,
                        $columnAlias,
                        $searchValue
                    );
                }
            }

            if (!empty($filterQueries->getParams())) {
                $query->addFilter($filterQueries);
            }
        }

        // individual filtering
        if ($this->isIndividualFiltering()) {
            /** @var BoolQuery $filterQueries */
            $filterQueries = new BoolQuery();

            /**
             * @var int|string $key
             * @var ColumnInterface $column
             */
            foreach ($this->columns as $key => $column) {
                if (true === $this->isSearchableColumn($column)) {
                    if (false === array_key_exists('columns', $this->requestParams)) {
                        continue;
                    }
                    if (false === array_key_exists($key, $this->requestParams['columns'])) {
                        continue;
                    }

                    /** @var string $columnAlias */
                    $columnAlias = $this->searchColumns[$key];
                    if ('' === $columnAlias || null === $columnAlias) {
                        continue;
                    }

                    $searchValue = $this->requestParams['columns'][$key]['search']['value'];

                    if ('' !== $searchValue && 'null' !== $searchValue) {
                        /** @var null|string $hasSearchColumnGroup */
                        $searchColumnGroup = $this->getColumnSearchColumnGroup($column);
                        if ('' !== $searchColumnGroup) {
                            $this->addColumnGroupSearchTerm(
                                $filterQueries,
                                $searchColumnGroup,
                                $searchValue
                            );
                        } else {
                            $this->addColumnSearchTerm(
                                $filterQueries,
                                self::CONDITION_TYPE_MUST,
                                $column,
                                $columnAlias,
                                $searchValue
                            );
                        }
                    }
                }
            }

            if (!empty($filterQueries->getParams())) {
                $query->addFilter($filterQueries);
            }
        }

        return $this;
    }

    /**
     * @param BoolQuery $filterQueries
     * @param string $searchColumnGroup
     * @param int|string $searchValue
     *
     * @return $this
     */
    protected function addColumnGroupSearchTerm(
        BoolQuery $filterQueries,
        string $searchColumnGroup,
        $searchValue
    ): self {
        /** @var BoolQuery $filterQueries */
        $groupFilterQueries = new BoolQuery();

        /** @var int|string $key */
        foreach ($this->searchColumnGroups[$searchColumnGroup] as $key) {
            $column = $this->columns[$key];
            $columnAlias = $this->searchColumns[$key];

            $this->addColumnSearchTerm(
                $groupFilterQueries,
                self::CONDITION_TYPE_SHOULD,
                $column,
                $columnAlias,
                $searchValue
            );
        }

        if (!empty($groupFilterQueries->getParams())) {
            $filterQueries->addMust($groupFilterQueries);
        }

        return $this;
    }

    /**
     * @param BoolQuery $filterQueries
     * @param string $conditionType
     * @param ColumnInterface $column
     * @param string $columnAlias
     * @param int|string $searchValue
     *
     * @return $this
     */
    protected function addColumnSearchTerm(
        BoolQuery $filterQueries,
        string $conditionType,
        ColumnInterface $column,
        string $columnAlias,
        $searchValue
    ): self {
        switch ($column->getTypeOfField()) {
            case 'boolean':
            case 'integer':
                $this->createIntegerShouldTerm(
                    $filterQueries,
                    $columnAlias,
                    (int)$searchValue
                );
                break;
            case 'string':
                /** @var FilterInterface $filter */
                $filter = $this->accessor->getValue($column, 'filter');

                if ($filter instanceof SelectFilter && true === $filter->isMultiple()) {
                    $searchValues = explode(',', $searchValue);
                } else {
                    $searchValues = null;
                }

                if (is_array($searchValues) && count($searchValues) > 1) {
                    $this->createStringMultiFilterTerm(
                        $filterQueries,
                        $conditionType,
                        $columnAlias,
                        (array)$searchValues
                    );
                } else {
                    $this->createStringFilterTerm(
                        $filterQueries,
                        $conditionType,
                        $columnAlias,
                        (string)$searchValue
                    );
                }

                break;
            default:
                break;
        }

        return $this;
    }

    /**
     * @param BoolQuery $filterQueries
     * @param string $columnAlias
     * @param int $searchValue
     */
    protected function createIntegerShouldTerm(
        BoolQuery $filterQueries,
        string $columnAlias,
        int $searchValue
    ) {
        if ('' !== $columnAlias) {
            /** @var Terms $integerTerm */
            $integerTerm = new Terms();
            $integerTerm->setTerms($columnAlias, [$searchValue]);

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
     * @param array $searchValues
     */
    protected function createStringMultiFilterTerm(
        BoolQuery $filterQueries,
        string $conditionType,
        string $columnAlias,
        array $searchValues
    ) {
        if ('' !== $columnAlias && is_array($searchValues) && !empty($searchValues)) {
            /** @var BoolQuery $filterSubQueries */
            $filterSubQueries = new BoolQuery();

            foreach ($searchValues as $searchValue) {
                $this->createStringFilterTerm(
                    $filterSubQueries,
                    self::CONDITION_TYPE_SHOULD,
                    $columnAlias,
                    (string)$searchValue
                );
            }

            if (!empty($filterSubQueries->getParams())) {
                if ($conditionType === self::CONDITION_TYPE_MUST) {
                    $filterQueries->addMust($filterSubQueries);
                } elseif ($conditionType === self::CONDITION_TYPE_SHOULD) {
                    $filterQueries->addShould($filterSubQueries);
                }
            }
        }
    }

    /**
     * @param BoolQuery $filterQueries
     * @param string $conditionType
     * @param string $columnAlias
     * @param string $searchValue
     */
    protected function createStringFilterTerm(
        BoolQuery $filterQueries,
        string $conditionType,
        string $columnAlias,
        string $searchValue
    ) {
        if ('' !== $columnAlias && '' !== $searchValue && 'null' !== $searchValue) {
            /** @var Query\Regexp $regexQuery */
            $regexQuery = new Query\Regexp($columnAlias, '.*' . strtolower($searchValue) . '.*');

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
     *
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
     *
     * @return $this
     */
    protected function addOrderColumn(ColumnInterface $column, $data): self
    {
        /** @var \Sg\DatatablesBundle\Datatable\Column\Column $col */
        $col = null;
        if ($data !== null && $this->isOrderableColumn($column)) {
            $virtualColumnType = $this->getVirtualColumnOrderTypeOfField($column);

            if (($column->getTypeOfField() === 'string' && $virtualColumnType === null) || $virtualColumnType === 'string') {
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
     *
     * @return $this
     */
    protected function addSearchColumn(ColumnInterface $column, $data): self
    {
        $col = $this->isSearchableColumn($column) ? $data : null;
        $col = str_replace('[,]', '', $col);

        $this->searchColumns[] = $col;

        $this->addNestedPath($col, $data);

        return $this;
    }

    /**
     * @param Query $query
     *
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
                        'order' => $this->requestParams['order'][$i]['dir'],
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
     *
     * @return Query
     */
    protected function getQuery($countQuery = false): Query
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

        if (!empty($this->fields)) {
            $query->setSource($this->fields);
        }

        return $query;
    }

    /**
     * @return ElasticaEntries
     */
    public function execute(): ElasticaEntries
    {
        $results = $this->getResultsForOffsetAndLength(
            $this->requestParams['start'],
            $this->requestParams['length']
        );

        return $this->generateElasticaEntriesForResults(
            $results->getTotalHits(),
            $this->extractSourceFromResultset($results)
        );
    }

    /**
     * @param array|string[] $fields
     *
     * @return ElasticaEntries
     */
    public function getAllResultsForFields(array $fields): ElasticaEntries
    {
        $resultEntries = [];
        $this->sourceFields = $fields;
        $result = $this->getResultsForOffsetAndLength(0, 1);
        $countAll = $result->getTotalHits();

        $resultsPerStep = 100;

        for ($i = 0; $i < $countAll / $resultsPerStep; $i++) {
            $partialResults = $this->getResultsForOffsetAndLength(
                $i * $resultsPerStep,
                ($i + 1) * $resultsPerStep
            );
            $resultEntries = $this->extractSourceFromResultset($partialResults, $resultEntries);
        }

        return $this->generateElasticaEntriesForResults($countAll, $resultEntries);
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
     *
     * @return bool
     */
    private function hasCustomDql(ColumnInterface $column): bool
    {
        return true === $this->accessor->getValue($column, 'customDql');
    }

    /**
     * @param ColumnInterface $column
     *
     * @return bool
     */
    private function isSelectColumn(ColumnInterface $column): bool
    {
        return true === $this->accessor->getValue($column, 'selectColumn');
    }

    /**
     * @param ColumnInterface $column
     *
     * @return bool
     */
    private function isOrderableColumn(ColumnInterface $column): bool
    {
        return true === $this->accessor->getValue($column, 'orderable');
    }

    /**
     * @param int $countAll
     * @param array $resultEntries
     *
     * @return ElasticaEntries
     */
    private function generateElasticaEntriesForResults(int $countAll, array $resultEntries): ElasticaEntries
    {
        /** @var ElasticaEntries $entries */
        $entries = new ElasticaEntries();
        $entries->setCount($countAll);
        $entries->setEntries($resultEntries);

        return $entries;
    }

    /**
     * @param int $offset
     * @param int $length
     *
     * @return PartialResultsInterface
     */
    private function getResultsForOffsetAndLength(
        int $offset,
        int $length
    ): PartialResultsInterface {
        return $this->paginatedFinder->createHybridPaginatorAdapter($this->getQuery())->getResults(
            $offset,
            $length
        );
    }

    /**
     * @param PartialResultsInterface $partialResults
     * @param array $resultEntries
     *
     * @return array
     */
    private function extractSourceFromResultset(
        PartialResultsInterface $partialResults,
        array $resultEntries = []
    ): array {
        foreach ($partialResults->toArray() as $item) {
            /** @var HybridResult $item */
            $resultEntries[] = $item->getResult()->getSource();
        }

        return $resultEntries;
    }

    /**
     * @param string $columnName
     *
     * @return Column|null
     */
    private function getColumnByDQL(string $dql)
    {
        foreach ($this->columns as $column) {
            if ($column->getDql() === $dql) {
               return $column;
            }
        }

        return null;
    }

    /**
     * @param ColumnInterface $column
     *
     * @return string|null
     */
    private function getVirtualColumnOrderTypeOfField(ColumnInterface $column)
    {
        if ($column instanceof VirtualColumn) {
            $orderColumn = $column->getOrderColumn();
            $virtualColumn = $this->getColumnByDQL($orderColumn);

            if ($virtualColumn !== null) {
                return $virtualColumn->getTypeOfField();
            }
        }

        return null;
    }
}
