<?php

/**
 * This file is part of the SgDatatablesBundle package.
 * (c) stwe <https://github.com/stwe/DatatablesBundle>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sg\DatatablesBundle\Datatable\Extension;

use Symfony\Component\OptionsResolver\OptionsResolver;

class Select extends AbstractExtension
{
    /**
     * Indicate if the selected items will be removed when clicking outside of the table
     *
     * @var boolean|null
     */
    protected $blurable;

    /**
     * Set the class name that will be applied to selected items
     *
     * @var string|null
     */
    protected $className;

    /**
     * Enable / disable the display for item selection information in the table summary
     *
     * @var boolean|null
     */
    protected $info;

    /**
     * Set which table items to select (rows, columns or cells)
     *
     * @var string|null
     */
    protected $items;

    /**
     * Set the element selector used for mouse event capture to select items
     *
     * @var string|null
     */
    protected $selector;

    /**
     * Set the selection style for end user interaction with the table
     *
     * @var string|null
     */
    protected $style;

    public function __construct()
    {
        parent::__construct('select');
    }

    /**
     * @param OptionsResolver $resolver
     *
     * @return $this
     */
    public function configureOptions(OptionsResolver $resolver): ExtensionInterface
    {
        $resolver->setDefaults(
            [
                'blurable' => null,
                'class_name' => null,
                'info' => null,
                'items' => null,
                'selector' => null,
                'style' => null,
            ]
        );

        $resolver->setAllowedTypes('blurable', ['boolean', 'null']);
        $resolver->setAllowedTypes('class_name', ['string', 'null']);
        $resolver->setAllowedTypes('info', ['boolean', 'null']);
        $resolver->setAllowedTypes('items', ['string', 'null']);
        $resolver->setAllowedValues('items', ['row', 'column', 'cell']);
        $resolver->setAllowedTypes('selector', ['string', 'null']);
        $resolver->setAllowedTypes('style', ['string', 'null']);
        $resolver->setAllowedValues('style', ['api', 'single', 'multi', 'os', 'multi+shift']);

        return $this;
    }

    /**
     * @return boolean|null
     */
    public function getBlurable()
    {
        return $this->blurable;
    }

    /**
     * @param string|null $blurable
     *
     * @return $this
     */
    public function setBlurable($blurable): self
    {
        $this->blurable = $blurable;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getClassName()
    {
        return $this->className;
    }

    /**
     * @param null|string $className
     *
     * @return $this
     */
    public function setClassName($className): self
    {
        $this->className = $className;

        return $this;
    }

    /**
     * @return boolean|null
     */
    public function getInfo()
    {
        return $this->info;
    }

    /**
     * @param boolean|null $info
     *
     * @return $this
     */
    public function setInfo($info): self
    {
        $this->info = $info;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * @param string|null $items
     *
     * @return $this
     */
    public function setItems($items): self
    {
        $this->items = $items;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getSelector()
    {
        return $this->selector;
    }

    /**
     * @param string|null $selector
     *
     * @return $this
     */
    public function setSelector($selector): self
    {
        $this->selector = $selector;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getStyle()
    {
        return $this->style;
    }

    /**
     * @param string|null $style
     *
     * @return $this
     */
    public function setStyle($style): self
    {
        $this->style = $style;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getJavaScriptConfiguration(array $config = []): array
    {
        if ($this->getBlurable() !== null) {
            $config['blurable'] = $this->getBlurable();
        }

        if ($this->getClassName() !== null) {
            $config['className'] = $this->getClassName();
        }

        if ($this->getInfo() !== null) {
            $config['info'] = $this->getInfo();
        }

        if ($this->getItems() !== null) {
            $config['items'] = $this->getItems();
        }

        if ($this->getSelector() !== null) {
            $config['selector'] = $this->getSelector();
        }

        if ($this->getStyle() !== null) {
            $config['style'] = $this->getStyle();
        }

        return parent::getJavaScriptConfiguration($config);
    }
}
