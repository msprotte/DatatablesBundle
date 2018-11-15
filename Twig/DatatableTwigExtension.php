<?php

/**
 * This file is part of the SgDatatablesBundle package.
 *
 * (c) stwe <https://github.com/stwe/DatatablesBundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sg\DatatablesBundle\Twig;

use Sg\DatatablesBundle\Datatable\DatatableInterface;
use Sg\DatatablesBundle\Datatable\Column\ColumnInterface;
use Sg\DatatablesBundle\Datatable\Extensions;
use Sg\DatatablesBundle\Datatable\Filter\FilterInterface;
use Sg\DatatablesBundle\Datatable\Action\Action;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Twig_Environment;
use Twig_Extension;
use Twig_SimpleFunction;
use Closure;

/**
 * Class DatatableTwigExtension
 *
 * @package Sg\DatatablesBundle\Twig
 */
class DatatableTwigExtension extends Twig_Extension
{
    /** @var PropertyAccessor */
    protected $accessor;

    public function __construct()
    {
        $this->accessor = PropertyAccess::createPropertyAccessor();
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'sg_datatables_twig_extension';
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions(): array
    {
        return [
            new Twig_SimpleFunction(
                'sg_datatables_render_html',
                [$this, 'datatablesRenderHtml'],
                ['is_safe' => ['html'], 'needs_environment' => true]
            ),
            new Twig_SimpleFunction(
                'sg_datatables_render',
                [$this, 'datatablesRender'],
                ['is_safe' => ['html'], 'needs_environment' => true]
            ),
            new Twig_SimpleFunction(
                'sg_datatables_render_js',
                [$this, 'datatablesRenderJs'],
                ['is_safe' => ['html'], 'needs_environment' => true]
            ),
            new Twig_SimpleFunction(
                'sg_datatable_extensions_render',
                [$this, 'datatablesRenderExtensions'],
                ['is_safe' => ['html'], 'needs_environment' => true]
            ),
            new Twig_SimpleFunction(
                'sg_datatables_render_filter',
                [$this, 'datatablesRenderFilter'],
                ['is_safe' => ['html'], 'needs_environment' => true]
            ),
            new Twig_SimpleFunction(
                'sg_datatables_render_multiselect_actions',
                [$this, 'datatablesRenderMultiselectActions'],
                ['is_safe' => ['html'], 'needs_environment' => true]
            ),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return [
            new \Twig_SimpleFilter('sg_datatables_bool_var', [$this, 'boolVar']),
        ];
    }

    /**
     * @param Twig_Environment $twig
     * @param DatatableInterface $datatable
     *
     * @return string
     */
    public function datatablesRender(Twig_Environment $twig, DatatableInterface $datatable): string
    {
        return $twig->render(
            '@SgDatatables/datatable/datatable.html.twig',
            [
                'sg_datatables_view' => $datatable,
            ]
        );
    }

    /**
     * @param Twig_Environment $twig
     * @param DatatableInterface $datatable
     *
     * @return string
     */
    public function datatablesRenderHtml(Twig_Environment $twig, DatatableInterface $datatable): string
    {
        return $twig->render(
            '@SgDatatables/datatable/datatable_html.html.twig',
            [
                'sg_datatables_view' => $datatable,
            ]
        );
    }

    /**
     * @param Twig_Environment $twig
     * @param DatatableInterface $datatable
     *
     * @return string
     */
    public function datatablesRenderJs(Twig_Environment $twig, DatatableInterface $datatable): string
    {
        return $twig->render(
            '@SgDatatables/datatable/datatable_js.html.twig',
            [
                'sg_datatables_view' => $datatable,
            ]
        );
    }

    /**
     * @param Twig_Environment $twig
     * @param DatatableInterface $datatable
     *
     * @return string
     */
    public function datatablesRenderExtensions(Twig_Environment $twig, DatatableInterface $datatable): string
    {
        /** @var Extensions $extensionRegistry */
        $extensionRegistry = $datatable->getExtensions();
        $jsParts = [];

        foreach ($extensionRegistry->getExtensions() as $extension) {
            if (!$extension->isEnabled()) {
                continue;
            }

            $jsParts[] = json_encode($extension->getJavaScriptConfiguration());
        }

        return implode('\n', $jsParts);
    }

    /**
     * @param Twig_Environment $twig
     * @param DatatableInterface $datatable
     * @param ColumnInterface $column
     * @param string $position
     *
     * @return string
     */
    public function datatablesRenderFilter(
        Twig_Environment $twig,
        DatatableInterface $datatable,
        ColumnInterface $column,
        $position
    ): string {
        /** @var FilterInterface $filter */
        $filter = $this->accessor->getValue($column, 'filter');
        $index = $this->accessor->getValue($column, 'index');
        $searchColumn = $this->accessor->getValue($filter, 'searchColumn');

        if (null !== $searchColumn) {
            $columns = $datatable->getColumnNames();
            $searchColumnIndex = $columns[$searchColumn];
        } else {
            $searchColumnIndex = $index;
        }

        return $twig->render(
            $filter->getTemplate(),
            [
                'column' => $column,
                'search_column_index' => $searchColumnIndex,
                'datatable_name' => $datatable->getName(),
                'position' => $position,
            ]
        );
    }

    /**
     * @param Twig_Environment $twig
     * @param ColumnInterface $multiselectColumn
     * @param int $pipeline
     *
     * @return string
     */
    public function datatablesRenderMultiselectActions(
        Twig_Environment $twig,
        ColumnInterface $multiselectColumn,
        $pipeline
    ): string {
        $parameters = [];
        $values = [];
        $actions = $this->accessor->getValue($multiselectColumn, 'actions');
        $domId = $this->accessor->getValue($multiselectColumn, 'renderActionsToId');
        $datatableName = $this->accessor->getValue($multiselectColumn, 'datatableName');

        /** @var Action $action */
        foreach ($actions as $actionKey => $action) {
            $routeParameters = $action->getRouteParameters();
            if (is_array($routeParameters)) {
                foreach ($routeParameters as $key => $value) {
                    $parameters[$actionKey][$key] = $value;
                }
            } elseif ($routeParameters instanceof Closure) {
                $parameters[$actionKey] = call_user_func($routeParameters);
            } else {
                $parameters[$actionKey] = [];
            }

            if ($action->isButton()) {
                if (null !== $action->getButtonValue()) {
                    $values[$actionKey] = $action->getButtonValue();

                    if (is_bool($values[$actionKey])) {
                        $values[$actionKey] = (int)$values[$actionKey];
                    }

                    if (true === $action->isButtonValuePrefix()) {
                        $values[$actionKey] = 'sg-datatables-' . $datatableName . '-multiselect-button-' . $actionKey . '-' . $values[$actionKey];
                    }
                } else {
                    $values[$actionKey] = null;
                }
            }
        }

        return $twig->render(
            '@SgDatatables/datatable/multiselect_actions.html.twig',
            [
                'actions' => $actions,
                'route_parameters' => $parameters,
                'values' => $values,
                'datatable_name' => $datatableName,
                'dom_id' => $domId,
                'pipeline' => $pipeline,
            ]
        );
    }

    /**
     * Renders: {{ var ? 'true' : 'false' }}
     *
     * @param mixed $value
     *
     * @return string
     */
    public function boolVar($value)
    {
        if ($value) {
            return 'true';
        } else {
            return 'false';
        }
    }
}
