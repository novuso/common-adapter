<?php declare(strict_types=1);

namespace Novuso\Common\Adapter\Service;

use Novuso\Common\Application\Service\Exception\ServiceContainerException;
use Novuso\Common\Application\Service\Exception\ServiceNotFoundException;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface as Container;
use Throwable;

/**
 * SymfonyContainer is a PSR-11 adapter for a Symfony container
 *
 * @copyright Copyright (c) 2017, Novuso. <http://novuso.com>
 * @license   http://opensource.org/licenses/MIT The MIT License
 * @author    John Nickell <email@johnnickell.com>
 */
class SymfonyContainer implements ContainerInterface
{
    /**
     * Symfony container
     *
     * @var Container
     */
    protected $container;

    /**
     * Constructs SymfonyContainer
     *
     * @param Container $container The Symfony container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Retrieves a service by identifier
     *
     * @param string $id The service ID
     *
     * @return mixed
     *
     * @throws ServiceNotFoundException When the service is not found
     * @throws ServiceContainerException When an error occurs
     */
    public function get($id)
    {
        if (!$this->container->has($id)) {
            throw ServiceNotFoundException::fromName($id);
        }

        try {
            return $this->container->get($id);
        } catch (Throwable $e) {
            throw new ServiceContainerException($e->getMessage(), $id, $e);
        }
    }

    /**
     * Checks if a service is defined
     *
     * @param string $id The service ID
     *
     * @return bool
     */
    public function has($id): bool
    {
        return $this->container->has($id);
    }
}
