<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Service\Symfony\Compiler;

use Exception;
use Novuso\Common\Application\Messaging\Query\Routing\ServiceAwareQueryMap;
use Novuso\Common\Domain\Messaging\Query\QueryHandler;
use Novuso\System\Exception\RuntimeException;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class QueryHandlerCompilerPass
 */
final class QueryHandlerCompilerPass implements CompilerPassInterface
{
    /**
     * Processes query handler tags
     *
     * @throws Exception When a compiler error occurs
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(ServiceAwareQueryMap::class)) {
            return;
        }

        $definition = $container->findDefinition(ServiceAwareQueryMap::class);
        $taggedServices = $container->findTaggedServiceIds(
            'common.query_handler',
            $throwOnAbstract = true
        );

        foreach ($taggedServices as $id => $tags) {
            $serviceDefinition = $container->getDefinition($id);

            if (!$serviceDefinition->isPublic()) {
                $message = sprintf(
                    'The service "%s" must be public as query handlers are lazy-loaded',
                    $id
                );
                throw new RuntimeException($message);
            }

            /** @var QueryHandler|string $serviceClass */
            $serviceClass = $container->getParameterBag()
                ->resolveValue($serviceDefinition->getClass());
            $reflection = new ReflectionClass($serviceClass);

            if (!$reflection->implementsInterface(QueryHandler::class)) {
                $message = sprintf(
                    'Service "%s" must implement interface "%s"',
                    $id,
                    QueryHandler::class
                );
                throw new RuntimeException($message);
            }

            $query = $serviceClass::queryRegistration();

            $definition->addMethodCall('registerHandler', [$query, $id]);
        }
    }
}
