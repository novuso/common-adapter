<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Templating;

use Novuso\Common\Application\Templating\Exception\DuplicateHelperException;
use Novuso\Common\Application\Templating\Exception\TemplatingException;
use Novuso\Common\Application\Templating\TemplateEngine;
use Novuso\Common\Application\Templating\TemplateHelper;

/**
 * Class DelegatingEngine
 */
final class DelegatingEngine implements TemplateEngine
{
    protected array $engines = [];
    protected array $helpers = [];

    /**
     * Constructs DelegatingEngine
     *
     * @param TemplateEngine[] $engines A list of TemplateEngine instances
     */
    public function __construct(array $engines = [])
    {
        foreach ($engines as $engine) {
            $this->addEngine($engine);
        }
    }

    /**
     * @inheritDoc
     */
    public function render(string $template, array $data = []): string
    {
        $engine = $this->getEngine($template);

        foreach ($this->helpers as $helper) {
            if (!$engine->hasHelper($helper)) {
                $engine->addHelper($helper);
            }
        }

        return $engine->render($template, $data);
    }

    /**
     * @inheritDoc
     */
    public function exists(string $template): bool
    {
        if (!$this->supports($template)) {
            return false;
        }

        return $this->getEngine($template)->exists($template);
    }

    /**
     * @inheritDoc
     */
    public function supports(string $template): bool
    {
        foreach ($this->engines as $engine) {
            if ($engine->supports($template)) {
                return true;
            }
        }

        return false;
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

    /**
     * Adds a template engine
     */
    public function addEngine(TemplateEngine $engine): void
    {
        $this->engines[] = $engine;
    }

    /**
     * Resolves a template engine for the template
     *
     * @throws TemplatingException When the template is not supported
     */
    protected function getEngine(string $template): TemplateEngine
    {
        foreach ($this->engines as $engine) {
            if ($engine->supports($template)) {
                return $engine;
            }
        }

        $message = sprintf(
            'No template engines loaded to support template: %s',
            $template
        );
        throw new TemplatingException($message);
    }
}
