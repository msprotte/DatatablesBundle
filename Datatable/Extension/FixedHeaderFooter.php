<?php

namespace Sg\DatatablesBundle\Datatable\Extension;

use Symfony\Component\OptionsResolver\OptionsResolver;

class FixedHeaderFooter extends AbstractExtension
{
    /** @var bool */
    protected $header;

    /** @var bool */
    protected $footer;

    /** @var int */
    protected $headerOffset;

    public function __construct()
    {
        parent::__construct('fixedHeader');
    }

    /**
     * @param OptionsResolver $resolver
     *
     * @return $this
     */
    public function configureOptions(OptionsResolver $resolver): ExtensionInterface
    {
        $resolver->setDefaults([
            'header' => false,
            'footer' => false,
            'headerOffset' => 0,
        ]);

        $resolver->setAllowedTypes('header', ['bool', 'false']);
        $resolver->setAllowedTypes('footer', ['bool', 'false']);
        $resolver->setAllowedTypes('headerOffset', 'int');

        return $this;
    }

    /**
     * @param bool $enabled
     *
     * @return FixedHeaderFooter
     */
    public function setHeader(bool $enabled): self
    {
        $this->header = $enabled;

        return $this;
    }

    /**
     * @return bool
     */
    public function getHeader(): bool
    {
        return $this->header;
    }

    /**
     * @return bool
     */
    public function getFooter(): bool
    {
        return $this->footer;
    }

    /**
     * @param bool $footer
     *
     * @return FixedHeaderFooter
     */
    public function setFooter(bool $footer): self
    {
        $this->footer = $footer;

        return $this;
    }

    /**
     * @return int
     */
    public function getHeaderOffset(): int
    {
        return $this->headerOffset;
    }

    /**
     * @param int $headerOffset
     *
     * @return FixedHeaderFooter
     */
    public function setHeaderOffset(int $headerOffset): self
    {
        $this->headerOffset = $headerOffset;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getJavaScriptConfiguration(array $config = []): array
    {
        $config['header'] = $this->getHeader();
        $config['footer'] = $this->getFooter();
        $config['headerOffset'] = $this->getHeaderOffset();

        return parent::getJavaScriptConfiguration($config);
    }
}
