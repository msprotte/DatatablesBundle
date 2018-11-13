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

use Sg\DatatablesBundle\Datatable\DatatableInterface;
use Sg\DatatablesBundle\Datatable\Column\ColumnInterface;
use Sg\DatatablesBundle\Response\AbstractDatatableQueryBuilder;
use Sg\DatatablesBundle\Response\AbstractDatatableResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\Tools\Pagination\Paginator;

class DatatableResponse extends AbstractDatatableResponse
{
    /**
     * The current Request.
     *
     * @var Request
     */
    protected $request;

    /**
     * $_GET or $_POST parameters.
     *
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

    /** @var bool */
    protected $countAllResults;

    /** @var bool */
    protected $outputWalkers;

    /** @var bool */
    protected $fetchJoinCollection;

    /**
     * @param bool $countAllResults
     *
     * @return DatatableResponse
     */
    public function setCountAllResults(bool $countAllResults): DatatableResponse
    {
        $this->countAllResults = $countAllResults;

        return $this;
    }

    /**
     * @param bool $outputWalkers
     *
     * @return DatatableResponse
     */
    public function setOutputWalkers(bool $outputWalkers): DatatableResponse
    {
        $this->outputWalkers = $outputWalkers;

        return $this;
    }

    /**
     * @param bool $fetchJoinCollection
     *
     * @return DatatableResponse
     */
    public function setFetchJoinCollection(bool $fetchJoinCollection): DatatableResponse
    {
        $this->fetchJoinCollection = $fetchJoinCollection;

        return $this;
    }

    /**
     * @param DatatableInterface $datatable
     *
     * @return $this
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
     * @return AbstractDatatableQueryBuilder
     * @throws \Exception
     */
    public function getDatatableQueryBuilder()
    {
        return $this->datatableQueryBuilder ?: $this->createDatatableQueryBuilder();
    }

    public function resetResponseOptions()
    {
        $this->countAllResults = true;
        $this->outputWalkers = false;
        $this->fetchJoinCollection = true;
    }

    /**
     * @param bool $countAllResults
     * @param bool $outputWalkers
     * @param bool $fetchJoinCollection
     *
     * @return JsonResponse
     * @throws \Exception
     */
    public function getResponse($countAllResults = true, $outputWalkers = false, $fetchJoinCollection = true)
    {
        $this->countAllResults = $countAllResults;
        $this->outputWalkers = $outputWalkers;
        $this->fetchJoinCollection = $fetchJoinCollection;

        return $this->getJsonResponse();
    }

    /**
     * @inheritdoc
     */
    public function getJsonResponse(): JsonResponse
    {
        $paginator = new Paginator($this->datatableQueryBuilder->execute(), $this->fetchJoinCollection);
        $paginator->setUseOutputWalkers($this->outputWalkers);

        $formatter = new DatatableFormatter();
        $formatter->runFormatter($paginator, $this->datatable);

        $outputHeader = [
            'draw' => (int)$this->requestParams['draw'],
            'recordsFiltered' => count($paginator),
            'recordsTotal' => true === $this->countAllResults ? (int)$this->datatableQueryBuilder->getCountAllResults() : 0,
        ];

        $response = new JsonResponse(array_merge($outputHeader, $formatter->getOutput()));
        $this->resetResponseOptions();

        return $response;
    }

    /**
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
