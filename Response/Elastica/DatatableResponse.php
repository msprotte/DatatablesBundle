<?php

namespace Sg\DatatablesBundle\Response\Elastica;

use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use Sg\DatatablesBundle\Model\ModelDefinitionInterface;
use Sg\DatatablesBundle\Response\AbstractDatatableQueryBuilder;
use Sg\DatatablesBundle\Response\AbstractDatatableResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;

class DatatableResponse extends AbstractDatatableResponse
{
    /** @var PaginatedFinderInterface $paginatedFinder */
    protected $paginatedFinder;

    /** @var AbstractDatatableQueryBuilder $datatableQueryBuilder */

    /** @var string */
    protected $datatableQueryBuilderClass;

    /** @var ModelDefinitionInterface */
    protected $modelDefinition;

    /** @var bool */
    protected $countAllResults;

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

    public function __construct(RequestStack $requestStack)
    {
        parent::__construct($requestStack);
    }

    public function resetResponseOptions()
    {
        $this->countAllResults = true;
    }

    /**
     * @param bool $countAllResults
     * @param bool $outputWalkers
     * @param bool $fetchJoinCollection
     *
     * @return JsonResponse
     * @throws \Exception
     */
    public function getResponse($countAllResults = true)
    {
        $this->countAllResults = $countAllResults;

        return $this->getJsonResponse();
    }

    /**
     * @return JsonResponse
     * @throws \Exception
     */
    public function getJsonResponse(): JsonResponse
    {
        $this->getDatatableQueryBuilder()->setPaginatedFinder($this->paginatedFinder);
        $this->getDatatableQueryBuilder()->setModelDefinition($this->modelDefinition);

        $entries = $this->getDatatableQueryBuilder()->execute();

        $formatter = new DatatableFormatter();
        $formatter->runFormatter($entries, $this->datatable);

        $outputHeader = [
            'draw' => (int)$this->requestParams['draw'],
            'recordsFiltered' => $entries->getCount(),
            'recordsTotal' => true === $this->countAllResults ? (int)$this->datatableQueryBuilder->getCountAllResults() : 0,
        ];

        $response = new JsonResponse(array_merge($outputHeader, $formatter->getOutput()));
        $this->resetResponseOptions();

        return $response;
    }

    /**
     * @return ElasticaDatatableQueryBuilder
     * @throws \Exception
     */
    public function getDatatableQueryBuilder(): AbstractDatatableQueryBuilder
    {
        return $this->datatableQueryBuilder ?: $this->createDatatableQueryBuilder();
    }

    /**
     * @return ElasticaDatatableQueryBuilder
     * @throws \Exception
     */
    protected function createDatatableQueryBuilder(): AbstractDatatableQueryBuilder
    {
        if (null === $this->datatable) {
            throw new \Exception('DatatableResponse::getDatatableQueryBuilder(): Set a Datatable class with setDatatable().');
        }

        $this->requestParams = $this->getRequestParams();
        $this->datatableQueryBuilder = new $this->datatableQueryBuilderClass($this->requestParams, $this->datatable);

        return $this->datatableQueryBuilder;
    }
}
