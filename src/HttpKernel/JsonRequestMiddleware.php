<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\HttpKernel;

use Novuso\Common\Application\HttpFoundation\HttpMethod;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

use function Novuso\Common\json_string;
use function Novuso\Common\string;

/**
 * Class JsonRequestMiddleware
 */
final class JsonRequestMiddleware implements HttpKernelInterface, TerminableInterface
{
    /**
     * Constructs JsonRequestMiddleware
     */
    public function __construct(protected HttpKernelInterface $kernel)
    {
    }

    /**
     * @inheritDoc
     */
    public function handle(
        Request $request,
        int $type = self::MASTER_REQUEST,
        bool $catch = true
    ): Response {
        $stateChangeMethods = [
            HttpMethod::POST,
            HttpMethod::PUT,
            HttpMethod::PATCH,
            HttpMethod::DELETE
        ];

        $contentType = string($request->headers->get('Content-Type', ''));

        if (
            in_array($request->getMethod(), $stateChangeMethods)
            && $contentType->startsWith('application/json')
        ) {
            $data = json_string($request->getContent())->toData();
            $request->request->replace(is_array($data) ? $data : []);
        }

        return $this->kernel->handle($request, $type, $catch);
    }

    /**
     * @inheritDoc
     */
    public function terminate(Request $request, Response $response): void
    {
        if ($this->kernel instanceof TerminableInterface) {
            $this->kernel->terminate($request, $response);
        }
    }
}
