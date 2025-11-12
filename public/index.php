<?php
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$errorMiddleware = $app->addErrorMiddleware(true, true, true);

$errorMiddleware->setErrorHandler(
    Slim\Exception\HttpNotFoundException::class,
    function ($request, $handler) use ($app) {
        $response = $app->getResponseFactory()->createResponse();
        $response->getBody()->write(json_encode([
            "error" => "Rota não encontrada",
            "status" => 404
        ]));
        return $response->withHeader("Content-Type", "application/json")->withStatus(404);
    }
);

(require __DIR__ . '/../src/routes/tickets.php')($app);
(require __DIR__ . '/../src/routes/tecnicos.php')($app);

$app->run();
