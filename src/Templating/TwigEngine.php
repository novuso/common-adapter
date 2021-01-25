<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Templating;

use Novuso\Common\Application\Templating\Exception\DuplicateHelperException;
use Novuso\Common\Application\Templating\Exception\TemplatingException;
use Novuso\Common\Application\Templating\TemplateEngine;
use Novuso\Common\Application\Templating\TemplateHelper;
use Throwable;
use Twig\Environment;

/**
 * Class TwigEngine
 */
final class TwigEngine implements TemplateEngine
{
    protected array $helpers = [];

    /**
     * Constructs TwigEngine
     */
    public function __construct(protected Environment $environment)
    {
    }

    /**
     * @inheritDoc
     */
    public function render(string $template, array $data = []): string
    {
        try {
            return $this->environment->render($template, $data);
        } catch (Throwable $e) {
            throw new TemplatingException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function exists(string $template): bool
    {
        return $this->environment->getLoader()->exists($template);
    }

    /**
     * @inheritDoc
     */
    public function supports(string $template): bool
    {
        return pathinfo($template, PATHINFO_EXTENSION) === 'twig';
    }

    /**
     * @inheritDoc
     */
    public function addHelper(TemplateHelper $helper): void
    {
        $name = $helper->getName();

        if (isset($this->helpers[$name])) {
            throw DuplicateHelperException::fromName($name);
        }

        $this->helpers[$name] = $helper;
        $this->environment->addGlobal($name, $helper);
    }

    /**
     * @inheritDoc
     */
    public function hasHelper(TemplateHelper $helper): bool
    {
        $name = $helper->getName();

        if (isset($this->helpers[$name])) {
            return true;
        }

        return false;
    }
}
