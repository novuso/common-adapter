<?php declare(strict_types=1);

namespace Novuso\Common\Adapter\Routing;

use Aura\Router\Generator;
use Novuso\Common\Application\Routing\Exception\UrlGenerationException;
use Novuso\Common\Application\Routing\UrlGeneratorInterface;
use Throwable;

/**
 * AuraUrlGenerator is an adapter for an Aura URL generator
 *
 * @copyright Copyright (c) 2017, Novuso. <http://novuso.com>
 * @license   http://opensource.org/licenses/MIT The MIT License
 * @author    John Nickell <email@johnnickell.com>
 */
class AuraUrlGenerator implements UrlGeneratorInterface
{
    /**
     * Aura URL generator
     *
     * @var Generator
     */
    protected $urlGenerator;

    /**
     * Base URL path
     *
     * @var string
     */
    protected $basePath;

    /**
     * Constructs AuraUrlGenerator
     *
     * @param Generator $urlGenerator The Aura URL generator
     * @param string    $basePath     The base path for absolute paths
     */
    public function __construct(Generator $urlGenerator, string $basePath)
    {
        $this->urlGenerator = $urlGenerator;
        $this->basePath = $basePath;
    }

    /**
     * {@inheritdoc}
     */
    public function generate(string $name, array $parameters = [], array $query = [], bool $absolute = false): string
    {
        try {
            $url = $this->urlGenerator->generate($name, $parameters);

            if (!empty($query)) {
                $url .= '?'.http_build_query($query);
            }

            if ($absolute) {
                $url = $this->basePath.$url;
            }

            return $url;
        } catch (Throwable $e) {
            throw new UrlGenerationException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
