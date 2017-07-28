<?php declare(strict_types=1);

namespace Novuso\Common\Adapter\Service;

use Aura\Di\Container;
use Novuso\Common\Application\Service\Exception\ServiceContainerException;
use Novuso\Common\Application\Service\Exception\ServiceNotFoundException;
use Psr\Container\ContainerInterface;
use Throwable;

/**
 * AuraContainer is a PSR-11 adapter for an Aura container
 *
 * @copyright Copyright (c) 2017, Novuso. <http://novuso.com>
 * @license   http://opensource.org/licenses/MIT The MIT License
 * @author    John Nickell <email@johnnickell.com>
 */
class AuraContainer implements ContainerInterface
{
    /**
     * Aura container
     *
     * @var Container
     */
    protected $container;

    /**
     * Constructs AuraContainer
     *
     * @param Container $container The Aura container
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
