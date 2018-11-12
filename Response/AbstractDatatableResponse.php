<?php

namespace Sg\DatatablesBundle\Response;

use Sg\DatatablesBundle\Datatable\Column\ColumnInterface;
use Sg\DatatablesBundle\Datatable\DatatableInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

abstract class AbstractDatatableResponse
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var array
     */
    protected $requestParams;

    /**
     * A DatatableInterface instance.
     * Default: null
     *
     * @var null|DatatableInterface
     */
    protected $datatable;

    /**
     * A DatatableQueryBuilder instance.
     * This class generates a Query by given Columns.
     * Default: null
     *
     * @var null|DatatableQueryBuilder
     */
    protected $datatableQueryBuilder;

    /**
     * @param RequestStack $requestStack
     */
    public function __construct(RequestStack $requestStack)
    {
        $this->request = $requestStack->getCurrentRequest();
        $this->datatable = null;
        $this->datatableQueryBuilder = null;
    }

    /**
     * @return DatatableQueryBuilder
     */
    abstract public function getDatatableQueryBuilder();

    /**
     * @param DatatableInterface $datatable
     *
     * @return AbstractDatatableResponse
     * @throws \Exception
     */
    public function setDatatable(DatatableInterface $datatable): AbstractDatatableResponse
    {
        $val = $this->validateColumnsPositions($datatable);
        if (is_int($val)) {
            throw new \Exception("DatatableResponse::setDatatable(): The Column with the index $val is on a not allowed position.");
        };

        $this->datatable = $datatable;
        $this->datatableQueryBuilder = null;

        return $this;
    }

    /**
     * @return JsonResponse
     */
    abstract public function getJsonResponse(): JsonResponse;

    protected function checkResponseDependencies()
    {
        if (null === $this->datatable) {
            throw new \UnexpectedValueException('DatatableResponse::getResponse(): Set a Datatable class with setDatatable().');
        }

        if (null === $this->datatableQueryBuilder) {
            throw new \UnexpectedValueException('DatatableResponse::getResponse(): A DatatableQueryBuilder instance is needed. Call getDatatableQueryBuilder().');
        }
    }

    //-------------------------------------------------
    // protected
    //-------------------------------------------------

    /**
     * Create a new DatatableQueryBuilder instance.
     *
     * @return DatatableQueryBuilder
     * @throws \Exception
     */
    protected function createDatatableQueryBuilder()
    {
        if (null === $this->datatable) {
            throw new \Exception('DatatableResponse::getDatatableQueryBuilder(): Set a Datatable class with setDatatable().');
        }

        $this->requestParams = $this->getRequestParams();
        $this->datatableQueryBuilder = new DatatableQueryBuilder($this->requestParams, $this->datatable);

        return $this->datatableQueryBuilder;
    }

    /**
     * Get request params.
     *
     * @return array
     */
    protected function getRequestParams()
    {
        $parameterBag = null;
        $type = $this->datatable->getAjax()->getType();

        if ('GET' === strtoupper($type)) {
            $parameterBag = $this->request->query;
        }

        if ('POST' === strtoupper($type)) {
            $parameterBag = $this->request->request;
        }

        return $parameterBag->all();
    }

    /**
     * @param DatatableInterface $datatable
     *
     * @return int|bool
     */
    protected function validateColumnsPositions(DatatableInterface $datatable)
    {
        $columns = $datatable->getColumnBuilder()->getColumns();
        $lastPosition = count($columns);

        /** @var ColumnInterface $column */
        foreach ($columns as $column) {
            $allowedPositions = $column->allowedPositions();
            /** @noinspection PhpUndefinedMethodInspection */
            $index = $column->getIndex();
            if (is_array($allowedPositions)) {
                $allowedPositions = array_flip($allowedPositions);
                if (array_key_exists(ColumnInterface::LAST_POSITION, $allowedPositions)) {
                    $allowedPositions[$lastPosition] = $allowedPositions[ColumnInterface::LAST_POSITION];
                    unset($allowedPositions[ColumnInterface::LAST_POSITION]);
                }

                if (false === array_key_exists($index, $allowedPositions)) {
                    return $index;
                }
            }
        }

        return true;
    }
}
