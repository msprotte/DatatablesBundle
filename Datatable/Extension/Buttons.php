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
use Exception;

class Buttons extends AbstractExtension
{
    /** @var array|null */
    protected $showButtons;

    /** @var array|Button[]|null */
    protected $createButtons;

    public function __construct()
    {
        parent::__construct('buttons');
    }

    /**
     * @param OptionsResolver $resolver
     *
     * @return $this
     */
    public function configureOptions(OptionsResolver $resolver): ExtensionInterface
    {
        $resolver->setDefaults([
            'show_buttons' => null,
            'create_buttons' => null,
        ]);

        $resolver->setAllowedTypes('show_buttons', ['null', 'array']);
        $resolver->setAllowedTypes('create_buttons', ['null', 'array']);

        return $this;
    }

    /**
     * @return array|null
     */
    public function getShowButtons()
    {
        if (\is_array($this->showButtons)) {
            return $this->optionToJson($this->showButtons);
        }

        return $this->showButtons;
    }

    /**
     * @param array|null $showButtons
     *
     * @return $this
     */
    public function setShowButtons($showButtons): self
    {
        $this->showButtons = $showButtons;

        return $this;
    }

    /**
     * @return array|Button[]|null
     */
    public function getCreateButtons()
    {
        return $this->createButtons;
    }

    /**
     * @return array|null
     */
    public function getCreateButtonsForJavaScriptConfiguration()
    {
        $createButtons = [];
        if (\is_array($this->getCreateButtons())) {
            foreach ($this->getCreateButtons() as $button) {
                /** @var Button $button */
                $createButtons[] = $button->getJavaScriptConfiguration();
            }
        }

        return $createButtons;
    }

    /**
     * @param array|null $createButtons
     *
     * @return $this
     * @throws Exception
     */
    public function setCreateButtons($createButtons): self
    {
        if (\is_array($createButtons)) {
            if (\count($createButtons) > 0) {
                foreach ($createButtons as $button) {
                    $newButton = new Button();
                    $this->createButtons[] = $newButton->set($button);
                }
            } else {
                throw new \UnexpectedValueException('Buttons::setCreateButtons(): The createButtons array should contain at least one element.');
            }
        } else {
            $this->createButtons = $createButtons;
        }

        return $this;
    }

    /**
     * @param array $config
     *
     * @return array
     */
    public function getJavaScriptConfiguration(array $config = []): array
    {
        if (null !== $this->getCreateButtons()) {
            $config['createButtons'] = $this->getCreateButtonsForJavaScriptConfiguration();
        }

        if (null !== $this->getShowButtons()) {
            $config['showButtons'] = $this->getShowButtons();
        }

        return parent::getJavaScriptConfiguration($config);
    }
}
