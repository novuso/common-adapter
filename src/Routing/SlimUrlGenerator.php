<?php declare(strict_types=1);

namespace Novuso\Common\Adapter\Routing;

use Novuso\Common\Application\Routing\Exception\UrlGenerationException;
use Novuso\Common\Application\Routing\UrlGeneratorInterface;
use Slim\Router;
use Throwable;

/**
 * SlimUrlGenerator is an adapter for a Slim URL generator
 *
 * @copyright Copyright (c) 2017, Novuso. <http://novuso.com>
 * @license   http://opensource.org/licenses/MIT The MIT License
 * @author    John Nickell <email@johnnickell.com>
 */
class SlimUrlGenerator implements UrlGeneratorInterface
{
    /**
     * Slim router
     *
     * @var Router
     */
    protected $router;

    /**
     * Base URL path
     *
     * @var string
     */
    protected $basePath;

    /**
     * Constructs SlimUrlGenerator
     *
     * @param Router $router   The router
     * @param string $basePath The base path for absolute paths
     */
    public function __construct(Router $router, string $basePath)
    {
        $this->router = $router;
        $this->basePath = $basePath;
    }

    /**
     * {@inheritdoc}
     */
    public function generate(string $name, array $parameters = [], array $query = [], bool $absolute = false): string
    {
        try {
            $relativePath = $this->router->relativePathFor($name, $parameters, $query);

            if ($absolute) {
                return $this->basePath.$relativePath;
            }

            return $relativePath;
        } catch (Throwable $e) {
            throw new UrlGenerationException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
