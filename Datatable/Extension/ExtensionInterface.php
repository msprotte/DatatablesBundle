<?php

namespace Sg\DatatablesBundle\Datatable\Extension;

use Symfony\Component\OptionsResolver\OptionsResolver;

interface ExtensionInterface
{
    /**
     * @return string
     */
    public function setName(string $name): self;

    /**
     * @return string
     */
    public function getName(): string;

    /**
     * @return bool
     */
    public function isEnabled(): bool;

    /**
     * @param bool $enabled
     *
     * @return ExtensionInterface
     */
    public function setEnabled(bool $enabled): self;

    /**
     * @param array $options
     *
     * @return ExtensionInterface
     */
    public function setOptions(array $options): self;

    /**
     * @param OptionsResolver $resolver
     *
     * @return ExtensionInterface
     */
    public function configureOptions(OptionsResolver $resolver): self;

    /**
     * @param array $config
     *
     * @return array
     */
    public function getJavaScriptConfiguration(array $config = []): array;
}
