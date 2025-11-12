<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../services/JwtService.php';
require_once __DIR__ . '/../helpers/jsonResponse.php';

return function (App $app) {

    // post
    $app->post('/login', function (Request $request, Response $response) {
        $pdo = getDBConnection();
        $body = $request->getParsedBody();

        if (empty($body['login']) || empty($body['senha'])) {
            return jsonResponse($response, [
                "success" => false,
                "error" => "Login e senha são obrigatórios"
            ], 400);
        }

        $login = trim($body['login']);
        $senha = trim($body['senha']);

        $sql = "SELECT usuario, login, senha, nome, ativo, tecnico
                FROM tbl_usuario
                WHERE login = :login
                LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':login' => $login]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            return jsonResponse($response, [
                "success" => false,
                "error" => "Usuário não encontrado"
            ], 404);
        }

        if (!in_array($usuario['ativo'], ['t', true, 1])) {
            return jsonResponse($response, [
                "success" => false,
                "error" => "Usuário inativo"
            ], 403);
        }

        if (!in_array($usuario['tecnico'], ['t', true, 1])) {
            return jsonResponse($response, [
                "success" => false,
                "error" => "Usuário não é técnico"
            ], 403);
        }

        if (!password_verify($senha, $usuario['senha'])) {
            return jsonResponse($response, [
                "success" => false,
                "error" => "Senha inválida"
            ], 401);
        }

        $token = JwtService::gerarToken([
            "usuario" => $usuario['usuario'],
            "login"   => $usuario['login'],
            "nome"    => $usuario['nome'],
            "tecnico" => true
        ]);

        return jsonResponse($response, [
            "success" => true,
            "token" => $token,
            "usuario" => [
                "login" => $usuario['login'],
                "nome" => $usuario['nome']
            ]
        ]);
    });

	//busca
    $app->get('/tecnico/{login}', function (Request $request, Response $response, array $args) {
        $pdo = getDBConnection();
        $login = trim($args['login']);

        $sql = "SELECT usuario, login, nome, ativo, tecnico
                FROM tbl_usuario
                WHERE login = :login
                AND tecnico IS TRUE
                LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':login' => $login]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            return jsonResponse($response, [
                "success" => false,
                "error" => "Técnico não encontrado"
            ], 404);
        }

        return jsonResponse($response, [
            "success" => true,
            "data" => $usuario
        ]);
    });
};
