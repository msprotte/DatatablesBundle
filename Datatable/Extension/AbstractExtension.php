<?php

namespace Sg\DatatablesBundle\Datatable\Extension;

use Sg\DatatablesBundle\Datatable\OptionsTrait;

abstract class AbstractExtension implements ExtensionInterface
{
    use OptionsTrait;

    /** @var string */
    protected $name;

    /** @var bool */
    protected $enabled;

    /**
     * @param $name
     */
    public function __construct($name)
    {
        $this->name = $name;
        $this->enabled = false;

        $this->initOptions();
    }

    /**
     * @inheritdoc
     */
    public function setName(string $name): ExtensionInterface
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritdoc
     */
    public function setOptions(array $options): ExtensionInterface
    {
        $this->set($options);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @inheritdoc
     */
    public function setEnabled(bool $enabled): ExtensionInterface
    {
        $this->enabled = $enabled;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getJavaScriptConfiguration(array $config = []): array
    {
        return [
            $this->getName() => \count($config) > 0 ? $config : true,
        ];
    }
}
