<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Service\Symfony\Compiler;

use Exception;
use Novuso\Common\Application\Messaging\Event\ServiceAwareEventDispatcher;
use Novuso\Common\Domain\Messaging\Event\EventSubscriber;
use Novuso\System\Exception\RuntimeException;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class EventSubscriberCompilerPass
 */
final class EventSubscriberCompilerPass implements CompilerPassInterface
{
    /**
     * Processes event subscriber tags
     *
     * @throws Exception When a compiler error occurs
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(ServiceAwareEventDispatcher::class)) {
            return;
        }

        $definition = $container->findDefinition(
            ServiceAwareEventDispatcher::class
        );
        $taggedServices = $container->findTaggedServiceIds(
            'common.event_subscriber',
            $throwOnAbstract = true
        );

        foreach ($taggedServices as $id => $tags) {
            $serviceDefinition = $container->getDefinition($id);

            if (!$serviceDefinition->isPublic()) {
                $message = sprintf(
                    'The service "%s" must be public as event subscribers are lazy-loaded',
                    $id
                );
                throw new RuntimeException($message);
            }

            /** @var EventSubscriber|string $serviceClass */
            $serviceClass = $container->getParameterBag()
                ->resolveValue($serviceDefinition->getClass());
            $reflection = new ReflectionClass($serviceClass);

            if (!$reflection->implementsInterface(EventSubscriber::class)) {
                $message = sprintf(
                    'Service "%s" must implement interface "%s"',
                    $id,
                    EventSubscriber::class
                );
                throw new RuntimeException($message);
            }

            $definition->addMethodCall('registerService', [$serviceClass, $id]);
        }
    }
}
