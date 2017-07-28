<?php declare(strict_types=1);

namespace Novuso\Common\Adapter\Service;

use Novuso\Common\Application\Service\Exception\ServiceContainerException;
use Novuso\Common\Application\Service\Exception\ServiceNotFoundException;
use Psr\Container\ContainerInterface;
use Throwable;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * ZendContainer is a PSR-11 adapter for a Zend service manager
 *
 * @copyright Copyright (c) 2017, Novuso. <http://novuso.com>
 * @license   http://opensource.org/licenses/MIT The MIT License
 * @author    John Nickell <email@johnnickell.com>
 */
class ZendContainer implements ContainerInterface
{
    /**
     * Zend service manager
     *
     * @var ServiceLocatorInterface
     */
    protected $serviceManager;

    /**
     * Constructs ZendContainer
     *
     * @param ServiceLocatorInterface $serviceManager The Zend service manager
     */
    public function __construct(ServiceLocatorInterface $serviceManager)
    {
        $this->serviceManager = $serviceManager;
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
        if (!$this->serviceManager->has($id)) {
            throw ServiceNotFoundException::fromName($id);
        }

        try {
            return $this->serviceManager->get($id);
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
        return $this->serviceManager->has($id);
    }
}
