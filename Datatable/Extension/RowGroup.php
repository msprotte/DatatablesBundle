<?php

/**
 * This file is part of the SgDatatablesBundle package.
 *
 * (c) stwe <https://github.com/stwe/DatatablesBundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sg\DatatablesBundle\Datatable\Extension;

use Symfony\Component\OptionsResolver\OptionsResolver;

class RowGroup extends AbstractExtension
{
    /** @var string */
    protected $dataSrc;

    /** @var array */
    protected $startRender;

    /** @var array */
    protected $endRender;

    /** @var string */
    protected $className;

    /** @var string */
    protected $emptyDataGroup;

    /** @var string */
    protected $endClassName;

    /** @var string */
    protected $startClassName;

    public function __construct()
    {
        parent::__construct('rowGroup');
    }

    /**
     * @param OptionsResolver $resolver
     *
     * @return $this
     */
    public function configureOptions(OptionsResolver $resolver): ExtensionInterface
    {
        $resolver->setRequired('data_src');
        $resolver->setDefined('start_render');
        $resolver->setDefined('end_render');
        $resolver->setDefined('enable');
        $resolver->setDefined('class_name');
        $resolver->setDefined('empty_data_group');
        $resolver->setDefined('end_class_name');
        $resolver->setDefined('start_class_name');

        $resolver->setDefaults([
            'enable' => true,
        ]);

        $resolver->setAllowedTypes('data_src', ['string']);
        $resolver->setAllowedTypes('start_render', ['array']);
        $resolver->setAllowedTypes('end_render', ['array']);
        $resolver->setAllowedTypes('enable', ['bool']);
        $resolver->setAllowedTypes('class_name', ['string']);
        $resolver->setAllowedTypes('empty_data_group', ['string']);
        $resolver->setAllowedTypes('end_class_name', ['string']);
        $resolver->setAllowedTypes('start_class_name', ['string']);

        return $this;
    }

    /**
     * @return string|null
     */
    public function getDataSrc()
    {
        return $this->dataSrc;
    }

    /**
     * @param string $dataSrc
     *
     * @return $this
     */
    public function setDataSrc($dataSrc): self
    {
        if (\is_string($dataSrc) && empty($dataSrc)) {
            throw new \UnexpectedValueException(
                'RowGroup::setDataSrc(): the column name is empty.'
            );
        }

        $this->dataSrc = $dataSrc;

        return $this;
    }

    /**
     * @return array|null
     */
    public function getStartRender()
    {
        return $this->startRender;
    }

    /**
     * @param array $startRender
     *
     * @return RowGroup
     */
    public function setStartRender($startRender): self
    {
        if (false === array_key_exists('template', $startRender)) {
            throw new \UnexpectedValueException(
                'RowGroup::setStartRender(): The "template" option is required.'
            );
        }

        foreach ($startRender as $key => $value) {
            if (false === \in_array($key, ['template', 'vars',])) {
                throw new \UnexpectedValueException(
                    'RowGroup::setStartRender(): ' . $key . ' is not a valid option.'
                );
            }
        }

        $this->startRender = $startRender;

        return $this;
    }

    /**
     * @return array|null
     */
    public function getEndRender()
    {
        return $this->endRender;
    }

    /**
     * @param array $endRender
     *
     * @return RowGroup
     */
    public function setEndRender($endRender): self
    {
        if (false === array_key_exists('template', $endRender)) {
            throw new \UnexpectedValueException(
                'RowGroup::setEndRender(): The "template" option is required.'
            );
        }

        foreach ($endRender as $key => $value) {
            if (false === \in_array($key, ['template', 'vars',])) {
                throw new \UnexpectedValueException(
                    'RowGroup::setEndRender(): ' . $key . ' is not a valid option.'
                );
            }
        }

        $this->endRender = $endRender;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getClassName()
    {
        return $this->className;
    }

    /**
     * @param string $className
     *
     * @return RowGroup
     */
    public function setClassName($className): self
    {
        if (\is_string($className) && empty($className)) {
            throw new \UnexpectedValueException(
                'RowGroup::setClassName(): the class name is empty.'
            );
        }

        $this->className = $className;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getEmptyDataGroup()
    {
        return $this->emptyDataGroup;
    }

    /**
     * @param string $emptyDataGroup
     *
     * @return RowGroup
     */
    public function setEmptyDataGroup($emptyDataGroup): self
    {
        if (\is_string($emptyDataGroup) && empty($emptyDataGroup)) {
            throw new \UnexpectedValueException(
                'RowGroup::setEmptyDataGroup(): the empty data group text is empty.'
            );
        }

        $this->emptyDataGroup = $emptyDataGroup;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getEndClassName()
    {
        return $this->endClassName;
    }

    /**
     * @param string $endClassName
     *
     * @return RowGroup
     */
    public function setEndClassName($endClassName): self
    {
        if (\is_string($endClassName) && empty($endClassName)) {
            throw new \UnexpectedValueException(
                'RowGroup::setEndClassName(): the end class name is empty.'
            );
        }

        $this->endClassName = $endClassName;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getStartClassName()
    {
        return $this->startClassName;
    }

    /**
     * @param string $startClassName
     *
     * @return RowGroup
     */
    public function setStartClassName($startClassName): self
    {
        if (\is_string($startClassName) && empty($startClassName)) {
            throw new \UnexpectedValueException(
                'RowGroup::setStartClassName(): the start class name is empty.'
            );
        }

        $this->startClassName = $startClassName;

        return $this;
    }

    /**
     * @param array $config
     *
     * @return array
     */
    public function getJavaScriptConfiguration(array $config = []): array
    {
        if (null !== $this->getDataSrc()) {
            $config['dataSrc'] = $this->getDataSrc();
        }

        if (null !== $this->getEmptyDataGroup()) {
            $config['emptyDataGroup'] = $this->getEmptyDataGroup();
        }

        if (null !== $this->getEndClassName()) {
            $config['endClassName'] = $this->getEndClassName();
        }

        if (null !== $this->getEndRender()) {
            $config['endRender'] = $this->getEndRender();
        }

        if (null !== $this->getStartClassName()) {
            $config['startClassName'] = $this->getStartClassName();
        }

        if (null !== $this->getStartRender()) {
            $config['startRender'] = $this->getStartRender();
        }

        return parent::getJavaScriptConfiguration($config); // TODO: Change the autogenerated stub
    }
}
