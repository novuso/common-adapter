<?php declare(strict_types=1);

namespace Novuso\Common\Adapter\Templating;

use League\Plates\Engine;
use Novuso\Common\Application\Templating\Exception\DuplicateHelperException;
use Novuso\Common\Application\Templating\TemplateEngineInterface;
use Novuso\Common\Application\Templating\TemplateHelperInterface;

/**
 * PlatesEngine is a Plates template engine adapter
 *
 * @copyright Copyright (c) 2017, Novuso. <http://novuso.com>
 * @license   http://opensource.org/licenses/MIT The MIT License
 * @author    John Nickell <email@johnnickell.com>
 */
class PlatesEngine implements TemplateEngineInterface
{
    /**
     * Plates engine
     *
     * @var Engine
     */
    protected $engine;

    /**
     * Template helpers
     *
     * @var array
     */
    protected $helpers = [];

    /**
     * Constructs PlatesEngine
     *
     * @param Engine $engine The plates engine
     */
    public function __construct(Engine $engine)
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
        return $this->engine->exists($template);
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
        $this->engine->addData([$name => $helper]);
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
