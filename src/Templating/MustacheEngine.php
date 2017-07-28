<?php declare(strict_types=1);

namespace Novuso\Common\Adapter\Templating;

use Mustache_Engine;
use Novuso\Common\Application\Templating\Exception\DuplicateHelperException;
use Novuso\Common\Application\Templating\TemplateEngineInterface;
use Novuso\Common\Application\Templating\TemplateHelperInterface;
use Throwable;

/**
 * MustacheEngine is a Mustache template engine adapter
 *
 * @copyright Copyright (c) 2017, Novuso. <http://novuso.com>
 * @license   http://opensource.org/licenses/MIT The MIT License
 * @author    John Nickell <email@johnnickell.com>
 */
class MustacheEngine implements TemplateEngineInterface
{
    /**
     * Mustache engine
     *
     * @var Mustache_Engine
     */
    protected $engine;

    /**
     * Template helpers
     *
     * @var array
     */
    protected $helpers = [];

    /**
     * Constructs MustacheEngine
     *
     * @param Mustache_Engine $engine The mustache engine
     */
    public function __construct(Mustache_Engine $engine)
    {
        $this->engine = $engine;
    }

    /**
     * {@inheritdoc}
     */
    public function render(string $template, array $data = []): string
    {
        return $this->engine->render($template, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function exists(string $template): bool
    {
        try {
            $this->engine->getLoader()->load($template);
        } catch (Throwable $e) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(string $template): bool
    {
        if ($this->exists($template)) {
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function addHelper(TemplateHelperInterface $helper): void
    {
        $name = $helper->getName();

        if (isset($this->helpers[$name])) {
            throw DuplicateHelperException::fromName($name);
        }

        $this->helpers[$name] = $helper;
        $this->engine->addHelper($name, $helper);
    }

    /**
     * {@inheritdoc}
     */
    public function hasHelper(TemplateHelperInterface $helper): bool
    {
        $name = $helper->getName();

        if (isset($this->helpers[$name])) {
            return true;
        }

        return false;
    }
}
