<?php declare(strict_types=1);

namespace Novuso\Common\Adapter\Routing;

use Novuso\Common\Application\Routing\Exception\InvalidParameterException;
use Novuso\Common\Application\Routing\Exception\MissingParametersException;
use Novuso\Common\Application\Routing\Exception\RouteNotFoundException;
use Novuso\Common\Application\Routing\Exception\UrlGenerationException;
use Novuso\Common\Application\Routing\UrlGeneratorInterface;
use Symfony\Component\Routing\Exception\InvalidParameterException as ParameterException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException as MissingException;
use Symfony\Component\Routing\Exception\RouteNotFoundException as RouteException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface as UrlGenerator;
use Throwable;

/**
 * SymfonyUrlGenerator is an adapter for a Symfony URL generator
 *
 * @copyright Copyright (c) 2017, Novuso. <http://novuso.com>
 * @license   http://opensource.org/licenses/MIT The MIT License
 * @author    John Nickell <email@johnnickell.com>
 */
class SymfonyUrlGenerator implements UrlGeneratorInterface
{
    /**
     * Symfony UrlGenerator
     *
     * @var UrlGenerator
     */
    protected $urlGenerator;

    /**
     * Constructs SymfonyUrlGenerator
     *
     * @param UrlGenerator $urlGenerator The Symfony URL generator
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
            if ($absolute) {
                $referenceType = UrlGenerator::ABSOLUTE_URL;
            } else {
                $referenceType = UrlGenerator::ABSOLUTE_PATH;
            }

            $url = $this->urlGenerator->generate($name, $parameters, $referenceType);

            if (!empty($query)) {
                $url .= '?'.http_build_query($query);
            }

            return $url;
        } catch (RouteException $e) {
            throw new RouteNotFoundException($e->getMessage(), $e->getCode(), $e);
        } catch (MissingException $e) {
            throw new MissingParametersException($e->getMessage(), $e->getCode(), $e);
        } catch (ParameterException $e) {
            throw new InvalidParameterException($e->getMessage(), $e->getCode(), $e);
        } catch (Throwable $e) {
            throw new UrlGenerationException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
