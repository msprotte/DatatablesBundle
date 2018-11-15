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

class Button extends AbstractExtension
{
    /** @var array|null */
    protected $action;

    /** @var array|null */
    protected $available;

    /** @var string|null */
    protected $className;

    /** @var array|null */
    protected $destroy;

    /** @var string|null */
    protected $extend;

    /** @var array|null */
    protected $init;

    /** @var string|null */
    protected $key;

    /** @var string|null */
    protected $namespace;

    /** @var string|null */
    protected $text;

    /** @var string|null */
    protected $titleAttr;

    /** @var array|null */
    protected $buttonOptions;

    public function __construct()
    {
        parent::__construct('button');
    }

    /**
     * @param OptionsResolver $resolver
     *
     * @return $this
     */
    public function configureOptions(OptionsResolver $resolver): ExtensionInterface
    {
        $resolver->setDefaults([
            'action' => null,
            'available' => null,
            'class_name' => null,
            'destroy' => null,
            'enabled' => null,
            'extend' => null,
            'init' => null,
            'key' => null,
            'name' => null,
            'namespace' => null,
            'text' => null,
            'title_attr' => null,
            'button_options' => null,
        ]);

        $resolver->setAllowedTypes('action', ['array', 'null']);
        $resolver->setAllowedTypes('available', ['array', 'null']);
        $resolver->setAllowedTypes('class_name', ['string', 'null']);
        $resolver->setAllowedTypes('destroy', ['array', 'null']);
        $resolver->setAllowedTypes('enabled', ['bool', 'null']);
        $resolver->setAllowedTypes('extend', ['string', 'null']);
        $resolver->setAllowedTypes('init', ['array', 'null']);
        $resolver->setAllowedTypes('key', ['string', 'null']);
        $resolver->setAllowedTypes('name', ['string', 'null']);
        $resolver->setAllowedTypes('namespace', ['string', 'null']);
        $resolver->setAllowedTypes('text', ['string', 'null']);
        $resolver->setAllowedTypes('title_attr', ['string', 'null']);
        $resolver->setAllowedTypes('button_options', ['array', 'null']);

        return $this;
    }

    /**
     * @return array|null
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * @param array|null $action
     *
     * @return $this
     */
    public function setAction($action): self
    {
        if (\is_array($action)) {
            $this->validateArrayForTemplateAndOther($action);
        }

        $this->action = $action;

        return $this;
    }

    /**
     * @return array|null
     */
    public function getAvailable()
    {
        return $this->available;
    }

    /**
     * @param array|null $available
     *
     * @return $this
     */
    public function setAvailable($available): self
    {
        if (\is_array($available)) {
            $this->validateArrayForTemplateAndOther($available);
        }

        $this->available = $available;

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
     * @return array|null
     */
    public function getDestroy()
    {
        return $this->destroy;
    }

    /**
     * @param array|null $destroy
     *
     * @return $this
     */
    public function setDestroy($destroy): self
    {
        if (\is_array($destroy)) {
            $this->validateArrayForTemplateAndOther($destroy);
        }

        $this->destroy = $destroy;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getExtend()
    {
        return $this->extend;
    }

    /**
     * @param null|string $extend
     *
     * @return $this
     */
    public function setExtend($extend): self
    {
        $this->extend = $extend;

        return $this;
    }

    /**
     * @return array|null
     */
    public function getInit()
    {
        return $this->init;
    }

    /**
     * @param array|null $init
     *
     * @return $this
     */
    public function setInit($init): self
    {
        if (\is_array($init)) {
            $this->validateArrayForTemplateAndOther($init);
        }

        $this->init = $init;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @param null|string $key
     *
     * @return $this
     */
    public function setKey($key): self
    {
        $this->key = $key;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * @param null|string $namespace
     *
     * @return $this
     */
    public function setNamespace($namespace): self
    {
        $this->namespace = $namespace;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * @param null|string $text
     *
     * @return $this
     */
    public function setText($text): self
    {
        $this->text = $text;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getTitleAttr()
    {
        return $this->titleAttr;
    }

    /**
     * @param null|string $titleAttr
     *
     * @return $this
     */
    public function setTitleAttr($titleAttr): self
    {
        $this->titleAttr = $titleAttr;

        return $this;
    }

    /**
     * @return array|null
     */
    public function getButtonOptions()
    {
        return $this->buttonOptions;
    }

    /**
     * @param array|null $buttonOptions
     *
     * @return $this
     */
    public function setButtonOptions($buttonOptions): self
    {
        $this->buttonOptions = $buttonOptions;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getJavaScriptConfiguration(array $config = []): array
    {
        if (null !== $this->getAction()) {
            $config['action'] = $this->getAction();
        }

        if (null !== $this->getAvailable()) {
            $config['available'] = $this->getAvailable();
        }

        if (null !== $this->getButtonOptions()) {
            $config['buttonOptions'] = $this->getButtonOptions();
        }

        if (null !== $this->getClassName()) {
            $config['className'] = $this->getClassName();
        }

        if (null !== $this->getDestroy()) {
            $config['destroy'] = $this->getDestroy();
        }

        if (null !== $this->getExtend()) {
            $config['extend'] = $this->getExtend();
        }

        if (null !== $this->getInit()) {
            $config['init'] = $this->getInit();
        }

        if (null !== $this->getKey()) {
            $config['key'] = $this->getKey();
        }

        if (null !== $this->getNamespace()) {
            $config['nameSpace'] = $this->getNamespace();
        }

        if (null !== $this->getText()) {
            $config['text'] = $this->getText();
        }

        if (null !== $this->getTitleAttr()) {
            $config['titleAttr'] = $this->getTitleAttr();
        }

        return parent::getJavaScriptConfiguration($config);
    }
}
