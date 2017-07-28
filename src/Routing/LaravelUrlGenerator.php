<?php declare(strict_types=1);

namespace Novuso\Common\Adapter\Routing;

use Illuminate\Contracts\Routing\UrlGenerator;
use Novuso\Common\Application\Routing\Exception\UrlGenerationException;
use Novuso\Common\Application\Routing\UrlGeneratorInterface;
use Throwable;

/**
 * LaravelUrlGenerator is an adapter for a Laravel URL generator
 *
 * @copyright Copyright (c) 2017, Novuso. <http://novuso.com>
 * @license   http://opensource.org/licenses/MIT The MIT License
 * @author    John Nickell <email@johnnickell.com>
 */
class LaravelUrlGenerator implements UrlGeneratorInterface
{
    /**
     * Laravel URL generator
     *
     * @var UrlGenerator
     */
    protected $urlGenerator;

    /**
     * LaravelUrlGenerator constructor.
     *
     * @param UrlGenerator $urlGenerator
     */
    public function __construct(UrlGenerator $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * {@inheritdoc}
     */
    public function generate(string $name, array $parameters = [], array $query = [], bool $absolute = false): string
    {
        try {
            $url = $this->urlGenerator->route($name, $parameters, $absolute);

            if (!empty($query)) {
                $url .= '?'.http_build_query($query);
            }

            return $url;
        } catch (Throwable $e) {
            throw new UrlGenerationException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
