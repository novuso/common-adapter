<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Service\Symfony\Compiler;

use Exception;
use Novuso\Common\Application\Messaging\Command\Routing\ServiceAwareCommandMap;
use Novuso\Common\Domain\Messaging\Command\CommandHandler;
use Novuso\System\Exception\RuntimeException;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class CommandHandlerCompilerPass
 */
final class CommandHandlerCompilerPass implements CompilerPassInterface
{
    /**
     * Processes command handler tags
     *
     * @throws Exception When a compiler error occurs
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(ServiceAwareCommandMap::class)) {
            return;
        }

        $definition = $container->findDefinition(ServiceAwareCommandMap::class);
        $taggedServices = $container->findTaggedServiceIds(
            'common.command_handler',
            $throwOnAbstract = true
        );

        foreach ($taggedServices as $id => $tags) {
            $serviceDefinition = $container->getDefinition($id);

            if (!$serviceDefinition->isPublic()) {
                $message = sprintf(
                    'The service "%s" must be public as command handlers are lazy-loaded',
                    $id
                );
                throw new RuntimeException($message);
            }

            /** @var CommandHandler|string $serviceClass */
            $serviceClass = $container->getParameterBag()
                ->resolveValue($serviceDefinition->getClass());
            $reflection = new ReflectionClass($serviceClass);

            if (!$reflection->implementsInterface(CommandHandler::class)) {
                $message = sprintf(
                    'Service "%s" must implement interface "%s"',
                    $id,
                    CommandHandler::class
                );
                throw new RuntimeException($message);
            }

            $command = $serviceClass::commandRegistration();

            $definition->addMethodCall('registerHandler', [$command, $id]);
        }
    }
}
