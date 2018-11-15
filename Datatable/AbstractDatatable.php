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

use Sg\DatatablesBundle\Datatable\Column\ColumnBuilder;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Doctrine\ORM\EntityManagerInterface;
use Twig_Environment;
use Exception;

/**
 * Class AbstractDatatable
 *
 * @package Sg\DatatablesBundle\Datatable
 */
abstract class AbstractDatatable implements DatatableInterface
{
    /** @var AuthorizationCheckerInterface */
    protected $authorizationChecker;

    /** @var TokenStorageInterface */
    protected $securityToken;

    /** @var TranslatorInterface */
    protected $translator;

    /** @var RouterInterface */
    protected $router;

    /** @var EntityManagerInterface */
    protected $em;

    /** @var Twig_Environment */
    protected $twig;

    /** @var ColumnBuilder */
    protected $columnBuilder;

    /** @var Ajax */
    protected $ajax;

    /** @var Options */
    protected $options;

    /** @var Features */
    protected $features;

    /** @var Callbacks */
    protected $callbacks;

    /** @var Events */
    protected $events;

    /** @var Extensions */
    protected $extensions;

    /** @var Language */
    protected $language;

    /** @var int */
    protected $uniqueId;

    /** @var PropertyAccessor */
    protected $accessor;

    /**
     * @var array
     */
    protected static $uniqueCounter = [];

    /**
     * @param AuthorizationCheckerInterface $authorizationChecker
     * @param TokenStorageInterface $securityToken
     * @param TranslatorInterface $translator
     * @param RouterInterface $router
     * @param EntityManagerInterface $em
     * @param Twig_Environment $twig
     * @param Extensions|null $registry
     *
     * @throws Exception
     */
    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
        TokenStorageInterface $securityToken,
        TranslatorInterface $translator,
        RouterInterface $router,
        EntityManagerInterface $em,
        Twig_Environment $twig,
        Extensions $registry = null
    ) {
        $this->validateName();

        if (isset(self::$uniqueCounter[$this->getName()])) {
            $this->uniqueId = ++self::$uniqueCounter[$this->getName()];
        } else {
            $this->uniqueId = self::$uniqueCounter[$this->getName()] = 1;
        }

        $this->authorizationChecker = $authorizationChecker;
        $this->securityToken = $securityToken;
        $this->translator = $translator;
        $this->router = $router;
        $this->em = $em;
        $this->twig = $twig;

        $metadata = $em->getClassMetadata($this->getEntity());
        $this->columnBuilder = new ColumnBuilder($metadata, $twig, $this->getName(), $em);

        $this->ajax = new Ajax();
        $this->options = new Options();
        $this->features = new Features();
        $this->callbacks = new Callbacks();
        $this->events = new Events();
        $this->extensions = $registry instanceof Extensions ? $registry : new Extensions();
        $this->language = new Language();

        $this->accessor = PropertyAccess::createPropertyAccessor();
    }

    /**
     * {@inheritdoc}
     */
    public function getLineFormatter()
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getColumnBuilder()
    {
        return $this->columnBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function getAjax()
    {
        return $this->ajax;
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * {@inheritdoc}
     */
    public function getFeatures()
    {
        return $this->features;
    }

    /**
     * {@inheritdoc}
     */
    public function getCallbacks()
    {
        return $this->callbacks;
    }

    /**
     * {@inheritdoc}
     */
    public function getEvents()
    {
        return $this->events;
    }

    /**
     * {@inheritdoc}
     */
    public function getExtensions()
    {
        return $this->extensions;
    }

    /**
     * {@inheritdoc}
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * {@inheritdoc}
     */
    public function getEntityManager()
    {
        return $this->em;
    }

    /**
     * {@inheritdoc}
     */
    public function getOptionsArrayFromEntities($entities, $keyFrom = 'id', $valueFrom = 'name')
    {
        $options = [];

        foreach ($entities as $entity) {
            if (true === $this->accessor->isReadable($entity, $keyFrom) && true === $this->accessor->isReadable($entity,
                    $valueFrom)) {
                $options[$this->accessor->getValue($entity, $keyFrom)] = $this->accessor->getValue($entity, $valueFrom);
            }
        }

        return $options;
    }

    /**
     * {@inheritdoc}
     */
    public function getUniqueId()
    {
        return $this->uniqueId;
    }

    /**
     * {@inheritdoc}
     */
    public function getUniqueName()
    {
        return $this->getName() . ($this->getUniqueId() > 1 ? '-' . $this->getUniqueId() : '');
    }

    /**
     * @throws Exception
     */
    protected function validateName()
    {
        if (1 !== preg_match(self::NAME_REGEX, $this->getName())) {
            throw new Exception('AbstractDatatable::validateName(): The result of the getName method can only contain letters, numbers, underscore and dashes.');
        }
    }
}
