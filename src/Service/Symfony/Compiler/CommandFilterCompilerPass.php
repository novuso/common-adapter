<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Service\Symfony\Compiler;

use Exception;
use Novuso\Common\Application\Messaging\Command\CommandPipeline;
use Novuso\Common\Domain\Messaging\Command\CommandFilter;
use Novuso\System\Exception\RuntimeException;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class CommandFilterCompilerPass
 */
final class CommandFilterCompilerPass implements CompilerPassInterface
{
    /**
     * Processes command filter tags
     *
     * @throws Exception When a compiler error occurs
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(CommandPipeline::class)) {
            return;
        }

        $definition = $container->findDefinition(CommandPipeline::class);
        $taggedServices = $container->findTaggedServiceIds(
            'common.command_filter',
            $throwOnAbstract = true
        );

        foreach ($taggedServices as $id => $tags) {
            $serviceDefinition = $container->getDefinition($id);

            $serviceClass = $container->getParameterBag()
                ->resolveValue($serviceDefinition->getClass());
            $reflection = new ReflectionClass($serviceClass);

            if (!$reflection->implementsInterface(CommandFilter::class)) {
                $message = sprintf(
                    'Service "%s" must implement interface "%s"',
                    $id,
                    CommandFilter::class
                );
                throw new RuntimeException($message);
            }

            $definition->addMethodCall('addFilter', [new Reference($id)]);
        }
    }
}
