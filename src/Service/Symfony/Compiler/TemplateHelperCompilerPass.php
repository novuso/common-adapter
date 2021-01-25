<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Service\Symfony\Compiler;

use Exception;
use Novuso\Common\Application\Templating\TemplateEngine;
use Novuso\Common\Application\Templating\TemplateHelper;
use Novuso\System\Exception\RuntimeException;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class TemplateHelperCompilerPass
 */
final class TemplateHelperCompilerPass implements CompilerPassInterface
{
    /**
     * Processes template helper tags
     *
     * @throws Exception When a compiler error occurs
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(TemplateEngine::class)) {
            return;
        }

        $definition = $container->findDefinition(TemplateEngine::class);
        $taggedServices = $container->findTaggedServiceIds(
            'common.template_helper'
        );

        foreach ($taggedServices as $id => $tags) {
            $serviceDefinition = $container->getDefinition($id);

            $serviceClass = $container->getParameterBag()
                ->resolveValue($serviceDefinition->getClass());
            $reflection = new ReflectionClass($serviceClass);

            if (!$reflection->implementsInterface(TemplateHelper::class)) {
                $message = sprintf(
                    'Service "%s" must implement interface "%s"',
                    $id,
                    TemplateHelper::class
                );
                throw new RuntimeException($message);
            }

            $definition->addMethodCall('addHelper', [new Reference($id)]);
        }
    }
}
