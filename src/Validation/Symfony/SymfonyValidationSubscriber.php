<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Validation\Symfony;

use Exception;
use Novuso\Common\Application\Attribute\Validation;
use Novuso\Common\Application\Validation\ValidationService;
use ReflectionClass;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class SymfonyValidationSubscriber
 */
final class SymfonyValidationSubscriber implements EventSubscriberInterface
{
    /**
     * Constructs SymfonyValidationSubscriber
     */
    public function __construct(protected ValidationService $validationService)
    {
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }

    /**
     * Handles attribute validation on the controller event
     *
     * @throws Exception When an error occurs
     */
    public function onKernelController(ControllerEvent $event): void
    {
        $controller = $event->getController();
        $request = $event->getRequest();

        if (!is_array($controller) && method_exists($controller, '__invoke')) {
            $controller = [$controller, '__invoke'];
        }

        if (!is_array($controller) || !is_object($controller[0])) {
            return;
        }

        $className = get_class($controller[0]);
        $reflection = new ReflectionClass($className);
        $reflection = $reflection->getMethod($controller[1]);

        $attributes = $reflection->getAttributes(Validation::class);
        foreach ($attributes as $attribute) {
            /** @var Validation $validation */
            $validation = $attribute->newInstance();
            $inputData = $request->isMethodSafe() ? $request->query->all() : $request->request->all();
            $this->validationService->validate($inputData, $validation->rules());
        }
    }
}
