<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Service\Symfony\Compiler;

use Exception;
use Novuso\Common\Application\Messaging\Query\QueryPipeline;
use Novuso\Common\Domain\Messaging\Query\QueryFilter;
use Novuso\System\Exception\RuntimeException;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class QueryFilterCompilerPass
 */
final class QueryFilterCompilerPass implements CompilerPassInterface
{
    /**
     * Processes query filter tags
     *
     * @throws Exception When a compiler error occurs
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(QueryPipeline::class)) {
            return;
        }

        $definition = $container->findDefinition(QueryPipeline::class);
        $taggedServices = $container->findTaggedServiceIds(
            'common.query_filter',
            $throwOnAbstract = true
        );

        foreach ($taggedServices as $id => $tags) {
            $serviceDefinition = $container->getDefinition($id);

            $serviceClass = $container->getParameterBag()
                ->resolveValue($serviceDefinition->getClass());
            $reflection = new ReflectionClass($serviceClass);

            if (!$reflection->implementsInterface(QueryFilter::class)) {
                $message = sprintf(
                    'Service "%s" must implement interface "%s"',
                    $id,
                    QueryFilter::class
                );
                throw new RuntimeException($message);
            }

            $definition->addMethodCall('addFilter', [new Reference($id)]);
        }
    }
}
