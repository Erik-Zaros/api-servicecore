<?php

namespace Middleware;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ResponseFactory;

class ApiKeyMiddleware implements MiddlewareInterface
{
    private $validKey;
    private $responseFactory;

    public function __construct($validKey)
    {
        $this->validKey = $validKey;
        $this->responseFactory = new ResponseFactory();
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $apiKey = $request->getHeaderLine('X-API-KEY');

        if (!$apiKey || $apiKey !== $this->validKey) {
            $response = $this->responseFactory->createResponse(401);
            $response->getBody()->write(json_encode([
                "success" => false,
                "error"   => "Chave de API inválida"
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json');
        }

        return $handler->handle($request);
    }
}

