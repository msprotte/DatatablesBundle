<?php

namespace Sg\DatatablesBundle\Response;

use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Sg\DatatablesBundle\Datatable\Ajax;
use Sg\DatatablesBundle\Datatable\Column\ColumnInterface;
use Sg\DatatablesBundle\Datatable\DatatableInterface;
use Sg\DatatablesBundle\Datatable\Features;
use Sg\DatatablesBundle\Datatable\Options;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

abstract class AbstractDatatableQueryBuilder
{
    const DISABLE_PAGINATION = -1;
    const INIT_PARAMETER_COUNTER = 100;

    /** @var array */
    protected $requestParams;

    /** @var EntityManagerInterface */
    protected $em;

    /** @var string */
    protected $entityName;

    /** @var string */
    protected $entityShortName;

    /** @var ClassMetadata */
    protected $metadata;

    /** @var mixed */
    protected $rootEntityIdentifier;

    /** @var QueryBuilder */
    protected $qb;

    /** @var PropertyAccessor */
    protected $accessor;

    /** @var array */
    protected $columns;

    /** @var array */
    protected $selectColumns;

    /** @var array */
    protected $searchColumns;

    /** @var array */
    protected $orderColumns;

    /** @var Options */
    protected $options;

    /** @var Features */
    protected $features;

    /** @var Ajax */
    protected $ajax;

    abstract protected function loadIndividualConstructSettings();

    abstract protected function initColumnArrays();

    /**
     * @return int
     */
    abstract public function getCountAllResults(): int;

    /**
     * @param ClassMetadata $metadata
     *
     * @return string
     */
    abstract protected function getEntityShortName(ClassMetadata $metadata): string;

    /**
     * @param array $requestParams
     * @param DatatableInterface $datatable
     *
     * @throws \Exception
     */
    public function __construct(array $requestParams, DatatableInterface $datatable)
    {
        $this->requestParams = $requestParams;

        $this->em = $datatable->getEntityManager();
        $this->entityName = $datatable->getEntity();

        $this->metadata = $this->getMetadata($this->entityName);
        $this->entityShortName = $this->getEntityShortName($this->metadata);
        $this->rootEntityIdentifier = $this->getIdentifier($this->metadata);

        $this->loadIndividualConstructSettings();

        $this->accessor = PropertyAccess::createPropertyAccessor();

        $this->columns = $datatable->getColumnBuilder()->getColumns();

        $this->options = $datatable->getOptions();
        $this->features = $datatable->getFeatures();
        $this->ajax = $datatable->getAjax();

        $this->initColumnArrays();
    }

    /**
     * @param string $entityName
     *
     * @return ClassMetadata
     * @throws \Exception
     */
    protected function getMetadata($entityName): ClassMetadata
    {
        try {
            $metadata = $this->em->getMetadataFactory()->getMetadataFor($entityName);
        } catch (MappingException $e) {
            throw new \Exception('DatatableQueryBuilder::getMetadata(): Given object ' . $entityName . ' is not a Doctrine Entity.');
        }

        return $metadata;
    }

    /**
     * @param ClassMetadata $metadata
     *
     * @return mixed
     */
    protected function getIdentifier(ClassMetadata $metadata)
    {
        $identifiers = $metadata->getIdentifierFieldNames();

        return array_shift($identifiers);
    }

    /**
     * @param ColumnInterface $column
     *
     * @return bool
     */
    protected function isSearchableColumn(ColumnInterface $column): bool
    {
        $searchColumn = null !== $this->accessor->getValue($column,
                'dql') && true === $this->accessor->getValue($column, 'searchable');

        if (false === $this->options->isSearchInNonVisibleColumns()) {
            return $searchColumn && true === $this->accessor->getValue($column, 'visible');
        }

        return $searchColumn;
    }
}
