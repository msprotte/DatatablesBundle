<?php

/**
 * This file is part of the SgDatatablesBundle package.
 *
 * (c) stwe <https://github.com/stwe/DatatablesBundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sg\DatatablesBundle\Datatable;

use Sg\DatatablesBundle\Datatable\Extension\Buttons;
use Sg\DatatablesBundle\Datatable\Extension\Exception\ExtensionAlreadyRegisteredException;
use Sg\DatatablesBundle\Datatable\Extension\ExtensionInterface;
use Sg\DatatablesBundle\Datatable\Extension\FixedHeaderFooter;
use Sg\DatatablesBundle\Datatable\Extension\Responsive;
use Sg\DatatablesBundle\Datatable\Extension\Select;
use Sg\DatatablesBundle\Datatable\Extension\RowGroup;

use Symfony\Component\OptionsResolver\OptionsResolver;

class Extensions
{
    use OptionsTrait;

    /**
     * The Buttons extension.
     * Default: null
     *
     * @var null|array|bool|Buttons
     */
    protected $buttons;

    /**
     * The Responsive Extension.
     * Automatically optimise the layout for different screen sizes.
     * Default: null
     *
     * @var null|array|bool|Responsive
     */
    protected $responsive;

    /**
     * The Select Extension.
     * Select adds item selection capabilities to a DataTable.
     * Default: null
     *
     * @var null|array|bool|Select
     */
    protected $select;

    /**
     * The RowGroup Extension.
     * Automatically group rows.
     * Default: null
     *
     * @var null|array|bool|RowGroup
     */
    protected $rowGroup;

    /** @var array|ExtensionInterface[] */
    protected $extensions = [];

    /** @var FixedHeaderFooter */
    protected $fixedHeaderFooter;

    public function __construct()
    {
        $this->initOptions();
    }

    /**
     * @param OptionsResolver $resolver
     *
     * @return $this
     */
    public function configureOptions(OptionsResolver $resolver): self
    {
        $resolver->setDefaults([
            'buttons' => null,
            'responsive' => null,
            'select' => null,
            'row_group' => null,
        ]);

        $resolver->setAllowedTypes('buttons', ['null', 'array', 'bool']);
        $resolver->setAllowedTypes('responsive', ['null', 'array', 'bool']);
        $resolver->setAllowedTypes('select', ['null', 'array', 'bool']);
        $resolver->setAllowedTypes('row_group', ['null', 'array', 'bool']);

        foreach ($this->extensions as $name => $extension) {
            $resolver->setDefault($name, null);
            $resolver->addAllowedTypes($name, ['null', 'array', 'bool']);
        }

        return $this;
    }

    /**
     * @return null|array|bool|Buttons
     */
    public function getButtons()
    {
        return $this->buttons;
    }

    /**
     * @param null|array|bool $buttons
     *
     * @return $this
     * @throws \Exception
     */
    public function setButtons($buttons)
    {
        if (\is_array($buttons)) {
            $newButton = new Buttons();
            $this->buttons = $newButton->set($buttons);
        } else {
            $this->buttons = $buttons;
        }

        return $this;
    }

    /**
     * @return null|array|bool|Responsive
     */
    public function getResponsive()
    {
        return $this->responsive;
    }

    /**
     * @param null|array|bool $responsive
     *
     * @return $this
     */
    public function setResponsive($responsive)
    {
        if (is_array($responsive)) {
            $newResponsive = new Responsive();
            $this->responsive = $newResponsive->set($responsive);
        } else {
            $this->responsive = $responsive;
        }

        return $this;
    }

    /**
     * @param ExtensionInterface $extension
     *
     * @return Extensions
     * @throws \Exception
     */
    public function addExtension(ExtensionInterface $extension): self
    {
        $extName = $extension->getName();
        if ($this->hasExtension($extName)) {
            throw new ExtensionAlreadyRegisteredException(
                sprintf(
                    'Extension with name "%s" already registered',
                    $extName
                )
            );
        }

        $this->extensions[$extName] = $extension;

        return $this;
    }

    /**
     * @param string $name
     *
     * @return ExtensionInterface
     * @throws \Exception
     */
    public function getExtension(string $name): ExtensionInterface
    {
        if (!$this->hasExtension($name)) {
            throw new ExtensionAlreadyRegisteredException(
                sprintf(
                    'Extension with name "%s" already registered',
                    $name
                )
            );
        }

        return $this->extensions[$name];
    }

    /**
     * @param string $name
     *
     * @throws \Exception
     */
    public function enableExtension(string $name)
    {
        $this->getExtension($name)->setEnabled(true);
    }

    /**
     * @param string $name
     * @param array|bool $options
     *
     * @return $this
     * @throws \Exception
     */
    public function setExtensionOptions(string $name, array $options): self
    {
        $extension = $this->getExtension($name);
        $extension->setEnabled(true);
        $extension->setOptions($options);

        return $this;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasExtension(string $name): bool
    {
        return array_key_exists($name, $this->extensions);
    }

    /**
     * @return array|ExtensionInterface[]
     */
    public function getExtensions(): array
    {
        return $this->extensions;
    }

    /**
     * @return null|array|bool|Select
     */
    public function getSelect()
    {
        return $this->select;
    }

    /**
     * @param null|array|bool $select
     *
     * @return $this
     */
    public function setSelect($select)
    {
        if (is_array($select)) {
            $newSelect = new Select();
            $this->select = $newSelect->set($select);
        } else {
            $this->select = $select;
        }

        return $this;
    }

    /**
     * Get rowGroup.
     *
     * @return null|array|bool|RowGroup
     */
    public function getRowGroup()
    {
        return $this->rowGroup;
    }

    /**
     * Set rowGroup.
     *
     * @param null|array|bool $rowGroup
     *
     * @return $this
     * @throws \Exception
     */
    public function setRowGroup($rowGroup)
    {
        if (is_array($rowGroup)) {
            $newRowGroup = new RowGroup();
            $this->rowGroup = $newRowGroup->set($rowGroup);
        } else {
            $this->rowGroup = $rowGroup;
        }

        return $this;
    }
}
