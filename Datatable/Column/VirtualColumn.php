<?php

/**
 * This file is part of the SgDatatablesBundle package.
 *
 * (c) stwe <https://github.com/stwe/DatatablesBundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sg\DatatablesBundle\Datatable\Column;

use Doctrine\DBAL\Types\Type as DoctrineType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\OptionsResolver\Options;
use Exception;

/**
 * Class VirtualColumn
 *
 * @package Sg\DatatablesBundle\Datatable\Column
 */
class VirtualColumn extends Column
{
    /**
     * Order field.
     *
     * @var null|string
     */
    protected $orderColumn;

    /**
     * Search field.
     *
     * @var null|string
     */
    protected $searchColumn;

    /**
     * Order field type.
     *
     * @var null|string
     */
    protected $orderColumnTypeOfField;

    //-------------------------------------------------
    // Options
    //-------------------------------------------------

    /**
     * Config options.
     *
     * @param OptionsResolver $resolver
     *
     * @return $this
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->remove('data');
        $resolver->remove('join_type');
        $resolver->remove('editable');

        $resolver->setDefaults(array(
            'orderable' => false,
            'searchable' => false,
            'order_column' => null,
            'search_column' => null,
            'order_column_type_of_field' => null,
        ));

        $resolver->setAllowedTypes('order_column', array('null', 'string'));
        $resolver->setAllowedTypes('search_column', array('null', 'string'));

        $resolver->setAllowedValues('order_column_type_of_field', array_merge(array(null), array_keys(DoctrineType::getTypesMap())));

        $resolver->setNormalizer('orderable', function (Options $options, $value) {
            if (null === $options['order_column'] && true === $value) {
                throw new Exception('VirtualColumn::configureOptions(): For the orderable option, order_column should not be null.');
            }

            return $value;
        });

        $resolver->setNormalizer('searchable', function (Options $options, $value) {
            if (null === $options['search_column'] && true === $value) {
                throw new Exception('VirtualColumn::configureOptions(): For the searchable option, search_column should not be null.');
            }

            return $value;
        });

        return $this;
    }

    //-------------------------------------------------
    // ColumnInterface
    //-------------------------------------------------

    /**
     * {@inheritdoc}
     */
    public function isSelectColumn()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getColumnType()
    {
        return parent::VIRTUAL_COLUMN;
    }

    //-------------------------------------------------
    // Getters && Setters
    //-------------------------------------------------

    /**
     * Get orderColumn.
     *
     * @return null|string
     */
    public function getOrderColumn()
    {
        return $this->orderColumn;
    }

    /**
     * Set orderColumn.
     *
     * @param null|string $orderColumn
     *
     * @return $this
     */
    public function setOrderColumn($orderColumn)
    {
        $this->orderColumn = $orderColumn;

        return $this;
    }

    /**
     * Get searchColumn.
     *
     * @return null|string
     */
    public function getSearchColumn()
    {
        return $this->searchColumn;
    }

    /**
     * Set searchColumn.
     *
     * @param null|string $searchColumn
     *
     * @return $this
     */
    public function setSearchColumn($searchColumn)
    {
        $this->searchColumn = $searchColumn;

        return $this;
    }


    /**
     * Get orderColumnTypeOfField
     *
     * @return null|string
     */
    public function getOrderColumnTypeOfField()
    {
        return $this->orderColumnTypeOfField;
    }

    /**
     * Set orderColumnTypeOfField
     *
     * @param null|string $orderColumnTypeOfField
     *
     * @return VirtualColumn
     */
    public function setOrderColumnTypeOfField($orderColumnTypeOfField): self
    {
        $this->orderColumnTypeOfField = $orderColumnTypeOfField;

        return $this;
    }

    /**
     * Get typeOfField
     *
     * @return null|string
     */
    public function getTypeOfField()
    {
        return $this->getOrderColumnTypeOfField() ?? parent::getTypeOfField();
    }
}
