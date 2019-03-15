<?php

namespace Sg\DatatablesBundle\Response\Elastica;

use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Elastica\Query;
use Elastica\Query\Nested;
use Elastica\Query\Terms;
use Elastica\Query\BoolQuery;
use Elastica\Query\AbstractQuery;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use FOS\ElasticaBundle\HybridResult;
use FOS\ElasticaBundle\Paginator\PartialResultsInterface;
use Sg\DatatablesBundle\Datatable\Column\ColumnInterface;
use Sg\DatatablesBundle\Model\ModelDefinitionInterface;
use Sg\DatatablesBundle\Response\AbstractDatatableQueryBuilder;
use Sg\DatatablesBundle\Datatable\Filter\FilterInterface;
use Sg\DatatablesBundle\Datatable\Filter\SelectFilter;

abstract class DatatableQueryBuilder extends AbstractDatatableQueryBuilder
{
    const CONDITION_TYPE_SHOULD = 'should';
    const CONDITION_TYPE_MUST = 'must';

    const QUERY_TYPE_TERMS = 'terms';
    const QUERY_TYPE_MATCH = 'match';
    const QUERY_TYPE_EXACT_MATCH = 'exact_match';
    const QUERY_TYPE_REGEXP = 'regexp';

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
     * @param mixed $query
     *
     * @return bool
     */
    protected function isQueryValid($query): bool
    {
        if (!$query instanceof AbstractQuery) {
            return false;
        }

        if (empty($query->toArray())) {
            return false;
        }

        if (\is_object($query->getParams())) {
            return false;
        }

        return true;
    }

    /**
     * @param BoolQuery $query
     *
     * @return $this
     */
    protected function addGlobalFilteringSearchTerms(BoolQuery $query): self
    {
        if (isset($this->requestParams['search']) && '' !== $this->requestParams['search']['value']) {
            /** @var BoolQuery $filterQueries */
            $filterQueries = new BoolQuery();

            $searchValue = $this->requestParams['search']['value'];

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

                    /** @var FilterInterface $filter */
                    $filter = $this->accessor->getValue($column, 'filter');

                    if ($filter instanceof SelectFilter) {
                        continue;
                    }

                    $this->addColumnSearchTerm(
                        $filterQueries,
                        self::CONDITION_TYPE_SHOULD,
                        $column,
                        $columnAlias,
                        $searchValue
                    );
                }
            }

            if ($this->isQueryValid($filterQueries)) {
                $query->addFilter($filterQueries);
            }
        }

        return $this;
    }

    /**
     * @param BoolQuery $query
     *
     * @return $this
     */
    protected function addIndividualFilteringSearchTerms(BoolQuery $query): self
    {
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

            if ($this->isQueryValid($filterQueries)) {
                $query->addFilter($filterQueries);
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
        $this->addGlobalFilteringSearchTerms($query);
        $this->addIndividualFilteringSearchTerms($query);

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
            $this->addColumnSearchTerm(
                $groupFilterQueries,
                self::CONDITION_TYPE_SHOULD,
                $this->columns[$key],
                $this->searchColumns[$key],
                $searchValue
            );
        }

        if ($this->isQueryValid($groupFilterQueries)) {
            $filterQueries->addMust($groupFilterQueries);
        }

        return $this;
    }

    /**
     * @param BoolQuery $filterQueries
     * @param string $conditionType
     * @param ColumnInterface $column
     * @param string $columnAlias
     * @param int|string|bool $searchValue
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
        /** @var null|AbstractQuery $filterSubQuery */
        $filterSubQuery = null;

        switch ($column->getTypeOfField()) {
            case 'boolean':
            case 'integer':
                if (is_numeric($searchValue) || is_bool($searchValue)) {
                    $filterSubQuery = $this->createIntegerFilterTerm(
                        $columnAlias,
                        (int)$searchValue
                    );
                }
                break;
            case 'string':
                /** @var null|array $searchValues */
                $searchValues = null;

                /** @var string $queryType */
                $queryType = self::QUERY_TYPE_MATCH;

                /** @var FilterInterface $filter */
                $filter = $this->accessor->getValue($column, 'filter');

                if ($filter instanceof SelectFilter) {
                    $queryType = self::QUERY_TYPE_EXACT_MATCH;

                    if (true === $filter->isMultiple()) {
                        $conditionType = self::CONDITION_TYPE_SHOULD;
                        $searchValues = explode(',', $searchValue);
                    }
                }

                if (is_array($searchValues) && count($searchValues) > 1) {
                    $filterSubQuery = $this->createStringMultiFilterTerm(
                        $columnAlias,
                        $queryType,
                        $conditionType,
                        (array)$searchValues
                    );
                } else {
                    $filterSubQuery = $this->createStringFilterTerm(
                        $columnAlias,
                        $queryType,
                        $conditionType,
                        (string)$searchValue
                    );
                }
                break;
            default:
                break;
        }

        if ($this->isQueryValid($filterSubQuery)) {
            if (self::CONDITION_TYPE_MUST === $conditionType) {
                $filterQueries->addMust($filterSubQuery);
            } elseif (self::CONDITION_TYPE_SHOULD === $conditionType) {
                $filterQueries->addShould($filterSubQuery);
            }
        }

        return $this;
    }

    /**
     * @param string $columnAlias
     * @param int $searchValue
     *
     * @return null|AbstractQuery
     */
    protected function createIntegerFilterTerm(
        string $columnAlias,
        int $searchValue
    ) {
        if ('' !== $columnAlias) {
            $integerTerm = $this->createFilterTerm($columnAlias, $searchValue);

            if ($this->isQueryValid($integerTerm)) {
                /** @var string|null $nestedPath */
                $nestedPath = $this->getNestedPath($columnAlias);
                if (null !== $nestedPath) {
                    /** @var Nested $nested */
                    $nested = new Nested();
                    $nested->setPath($nestedPath);
                    /** @var BoolQuery $boolQuery */
                    $boolQuery = new BoolQuery();
                    $boolQuery->addMust($integerTerm);
                    $nested->setQuery($boolQuery);

                    return $nested;
                }

                return $integerTerm;
            }
        }

        return null;
    }

    /**
     * @param string $columnAlias
     * @param string $queryType
     * @param string $conditionType
     * @param array $searchValues
     *
     * @return null|AbstractQuery
     */
    protected function createStringMultiFilterTerm(
        string $columnAlias,
        string $queryType,
        string $conditionType,
        array $searchValues
    ) {
        if ('' !== $columnAlias && is_array($searchValues) && !empty($searchValues)) {
            /** @var BoolQuery $filterQueries */
            $filterQueries = new BoolQuery();

            foreach ($searchValues as $searchValue) {
                $filterSubQuery = $this->createStringFilterTerm(
                    $columnAlias,
                    $queryType,
                    $conditionType,
                    (string)$searchValue
                );
                if ($this->isQueryValid($filterSubQuery)) {
                    $filterQueries->addShould($filterSubQuery);
                }
            }

            if ($this->isQueryValid($filterQueries)) {
                return $filterQueries;
            }
        }

        return null;
    }

    /**
     * @param string $columnAlias
     * @param string $queryType
     * @param string $conditionType
     * @param string $searchValue
     *
     * @return null|AbstractQuery
     */
    protected function createStringFilterTerm(
        string $columnAlias,
        string $queryType,
        string $conditionType,
        string $searchValue
    ) {
        $searchValue = trim($searchValue);
        if ('' !== $columnAlias && '' !== $searchValue && 'null' !== $searchValue) {

            if (self::QUERY_TYPE_MATCH === $queryType) {
                $fieldQuery = $this->createFilterMatchTerm($columnAlias, $searchValue, $conditionType);
            } elseif (self::QUERY_TYPE_EXACT_MATCH === $queryType) {
                $fieldQuery = $this->createFilterExactMatchTerm($columnAlias, $searchValue, $conditionType);
            } elseif (self::QUERY_TYPE_REGEXP === $queryType) {
                $fieldQuery = $this->createFilterRegexpTerm($columnAlias, $searchValue, $conditionType);
            } else {
                $fieldQuery = $this->createFilterTerm($columnAlias, $searchValue, $conditionType);
            }

            if ($this->isQueryValid($fieldQuery)) {
                /** @var string|null $nestedPath */
                $nestedPath = $this->getNestedPath($columnAlias);
                if (null !== $nestedPath) {
                    /** @var Nested $nested */
                    $nested = new Nested();
                    $nested->setPath($nestedPath);
                    $nested->setQuery($fieldQuery);

                    return $nested;
                }

                return $fieldQuery;
            }
        }

        return null;
    }

    /**
     * @param string $columnAlias
     * @param string|int $searchValue
     * @param string $conditionType
     *
     * @return null|AbstractQuery
     */
    protected function createFilterTerm(string $columnAlias, $searchValue, string $conditionType = null)
    {
        if ('' !== $columnAlias) {
            /** @var Query\Term() $query */
            $query = new Query\Term();
            $query->setTerm($columnAlias, $searchValue);

            return $query;
        }

        return null;
    }

    /**
     * @param string $columnAlias
     * @param string|int $searchValue
     * @param string $conditionType
     *
     * @return null|AbstractQuery
     */
    protected function createFilterMatchTerm(string $columnAlias, $searchValue, string $conditionType = null)
    {
        if ('' !== $columnAlias) {
            /** @var Query\Match $query */
            $query = new Query\Match();
            $query->setFieldQuery($columnAlias, $searchValue);
            $query->setFieldMinimumShouldMatch($columnAlias, 1);
            if ($conditionType === self::CONDITION_TYPE_MUST) {
                $query->setFieldOperator($columnAlias, Query\Match::OPERATOR_AND);
            }

            return $query;
        }

        return null;
    }

    /**
     * @param string $columnAlias
     * @param string|int $searchValue
     * @param string $conditionType
     *
     * @return null|AbstractQuery
     */
    protected function createFilterExactMatchTerm(string $columnAlias, $searchValue, string $conditionType = null)
    {
        $query = $this->createFilterMatchTerm($columnAlias, $searchValue, $conditionType);

        if ($this->isQueryValid($query)) {
            $query->setFieldMinimumShouldMatch($columnAlias, '100%');
        }

        return $query;
    }

    /**
     * @param string $columnAlias
     * @param string|int $searchValue
     * @param string $conditionType
     *
     * @return null|AbstractQuery
     */
    protected function createFilterRegexpTerm(string $columnAlias, $searchValue, string $conditionType = null)
    {
        if ('' !== $columnAlias) {
            /** @var Query\Regexp $query */
            $query = new Query\Regexp($columnAlias . '.raw', '.*' . $searchValue . '.*');

            return $query;
        }

        return null;
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
        $col = null;
        if ($data !== null && $this->isOrderableColumn($column)) {
            $typeOfField = $column->getTypeOfField();

            if ($this->accessor->isReadable($column, 'orderColumnTypeOfField')) {
                $typeOfField = $this
                        ->accessor
                        ->getValue($column, 'orderColumnTypeOfField') ??
                    $column->getTypeOfField();
            }

            if ($typeOfField === 'string') {
                $col = $data . '.' . $this->getSortFieldSuffix();
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
     * @return string
     */
    protected function getSortFieldSuffix(): string
    {
        return 'keyword';
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

        if (\is_array($this->sourceFields) && !empty($this->sourceFields)) {
            $query->setSource($this->sourceFields);
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
}
