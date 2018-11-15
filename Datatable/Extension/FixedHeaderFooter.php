<?php

namespace Sg\DatatablesBundle\Datatable\Extension;

use Symfony\Component\OptionsResolver\OptionsResolver;

class FixedHeaderFooter extends AbstractExtension
{
    /** @var bool */
    protected $header;

    /** @var bool */
    protected $footer;

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
        ]);

        $resolver->setAllowedTypes('header', ['bool', 'false']);
        $resolver->setAllowedTypes('footer', ['bool', 'false']);

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
     * @inheritdoc
     */
    public function getJavaScriptConfiguration(array $config = []): array
    {
        if ($this->getHeader() !== null) {
            $config['header'] = $this->getHeader();
        }

        if ($this->getFooter() !== null) {
            $config['footer'] = $this->getFooter();
        }

        return parent::getJavaScriptConfiguration($config);
    }
}
