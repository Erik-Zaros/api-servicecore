<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

require_once __DIR__ . '/../db.php';

return function (App $app) {

    function jsonResponse(Response $response, $data, int $status = 200): Response {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    // busca
    $app->get('/tickets', function (Request $request, Response $response) {
        $pdo = getDBConnection();

        $params = $request->getQueryParams();
        $sql = "SELECT * FROM tbl_ticket WHERE exportado = false";
        $queryParams = [];

        if (!empty($params['data_inicio'])) {
            $sql .= " AND data_input::date >= :data_inicio";
            $queryParams[':data_inicio'] = $params['data_inicio'];
        }

        if (!empty($params['data_fim'])) {
            $sql .= " AND data_input::date <= :data_fim";
            $queryParams[':data_fim'] = $params['data_fim'];
        }

        $sql .= " ORDER BY data_input ASC LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($queryParams);
        $ticket = $stmt->fetch();

        if ($ticket) {
            $ticket['dados'] = !empty($ticket['request']) ? json_decode($ticket['request'], true) : null;
            unset($ticket['request']);
            return jsonResponse($response, [
                "success" => true,
                "data" => $ticket
            ]);
        } else {
            return jsonResponse($response, [
                "success" => false,
                "error" => [
                    "code" => 404,
                    "message" => "Nenhum ticket pendente de exportação!"
                ]
            ], 404);
        }
    });

    // busca id
    $app->get('/tickets/{id}', function (Request $request, Response $response, array $args) {
    $pdo = getDBConnection();
    $id = (int) $args['id'];

    if ($id <= 0) {
        return jsonResponse($response, [
            "success" => false,
            "error" => ["code" => 400, "message" => "ID inválido"]
        ], 400);
    }

    $stmt = $pdo->prepare("SELECT * FROM tbl_ticket WHERE ticket = :id");
    $stmt->execute([':id' => $id]);
    $ticket = $stmt->fetch();

        if ($ticket) {
            $ticket['dados'] = !empty($ticket['request']) ? json_decode($ticket['request'], true) : null;
            unset($ticket['request']);
            return jsonResponse($response, [
                "success" => true,
                "data" => $ticket
            ]);
        } else {
            return jsonResponse($response, [
                "success" => false,
                "error" => [
                    "code" => 404,
                    "message" => "Ticket não encontrado"
                ]
            ], 404);
        }
    });

    // atualiza
    $app->put('/tickets/{id}', function (Request $request, Response $response, array $args) {
        $pdo = getDBConnection();
        $id = (int) $args['id'];
        $body = $request->getParsedBody();

        if ($id <= 0) {
            return jsonResponse($response, [
                "success" => false,
                "error" => ["code" => 400, "message" => "ID inválido"]
            ], 400);
        }

        if (empty($body)) {
            return jsonResponse($response, [
                "success" => false,
                "error" => ["code" => 400, "message" => "Body não enviado"]
            ], 400);
        }

        $stmt = $pdo->prepare("SELECT exportado, status FROM tbl_ticket WHERE ticket = :id");
        $stmt->execute([':id' => $id]);
        $ticketAtual = $stmt->fetch();

        if (!$ticketAtual) {
            return jsonResponse($response, [
                "success" => false,
                "error" => ["code" => 404, "message" => "Ticket não encontrado"]
            ], 404);
        }

        if (isset($body['status'])) {
            if (in_array($ticketAtual['status'], ['FINALIZADO', 'CANCELADO'])) {
                $finalizado_cancelado_retorno = $ticketAtual['status'] == 'FINALIZADO' ? 'finalizado' : 'cancelado';
                return jsonResponse($response, [
                    "success" => false,
                    "error" => ["code" => 409, "message" => "Ticket está $finalizado_cancelado_retorno não é possível alterar o status!"]
                ], 409);
            }
        }

        if ($body['exportado'] == true && $ticketAtual['exportado'] == true) {
            return jsonResponse($response, [
                "success" => false,
                "error" => ["code" => 409, "message" => "Ticket já exportado não é possível exportar novamente!"]
            ], 409);
        }

        $updates = [];
        $params = [':id' => $id];

        if (isset($body['exportado'])) {
            if (!is_bool($body['exportado']) && !in_array($body['exportado'], ["true", "false", 0, 1], true)) {
                return jsonResponse($response, [
                    "success" => false,
                    "error" => ["code" => 422, "message" => "Campo exportado inválido"]
                ], 422);
            }

            $isExportado = filter_var($body['exportado'], FILTER_VALIDATE_BOOLEAN);
            $updates[] = "exportado = :exportado";
            $params[':exportado'] = $isExportado;

            if ($isExportado) {
                $updates[] = "status = :status";
                $params[':status'] = "EM_ANDAMENTO";
            }
        }

        if (isset($body['status'])) {
            if (!$ticketAtual['exportado']) {
                return jsonResponse($response, [
                    "success" => false,
                    "error" => ["code" => 403, "message" => "Não é permitido alterar o status antes de exportar"]
                ], 403);
            }

            $status = strtoupper(trim($body['status']));
            $statusPermitidos = ["ABERTO", "EM_ANDAMENTO", "FINALIZADO", "CANCELADO"];

            if (!in_array($status, $statusPermitidos)) {
                return jsonResponse($response, [
                    "success" => false,
                    "error" => ["code" => 422, "message" => "Status inválido"]
                ], 422);
            }

            $updates[] = "status = :status";
            $params[':status'] = $status;
        }

        if (empty($updates)) {
            return jsonResponse($response, [
                "success" => false,
                "error" => ["code" => 400, "message" => "Nenhum campo enviado"]
            ], 400);
        }

        $sql = "UPDATE tbl_ticket SET " . implode(", ", $updates) . " WHERE ticket = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return jsonResponse($response, [
            "success" => true,
            "data" => ["ticket_id" => $id, "updated" => true]
        ]);
    });
};
