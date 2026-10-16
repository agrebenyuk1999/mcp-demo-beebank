<?php

declare(strict_types=1);

use BeeBank\Database;
use BeeBank\Domain\BeeBank;
use BeeBank\FileLogger;
use BeeBank\TrafficLog;
use Http\Discovery\Psr17Factory;
use Mcp\Capability\Registry\Container;
use Mcp\Server;
use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Log\LoggerInterface;

require dirname(__DIR__).'/vendor/autoload.php';

$traffic = new TrafficLog(dirname(__DIR__).'/var/mcp-requests.log');
$traffic->request(
    $_SERVER['REQUEST_METHOD'],
    $_SERVER['HTTP_MCP_SESSION_ID'] ?? null,
    $_SERVER['HTTP_USER_AGENT'] ?? null,
    (string) file_get_contents('php://input'),
);

$token = getenv('BEEBANK_MCP_TOKEN');

if (!$token || ($_SERVER['HTTP_AUTHORIZATION'] ?? '') !== 'Bearer '.$token) {
    http_response_code(401);
    $traffic->response(401, null, '');
    exit;
}

$pdo = Database::connect();
$logger = new FileLogger(dirname(__DIR__).'/var/mcp.log');

$container = new Container();
$container->set(BeeBank::class, new BeeBank($pdo));
$container->set(LoggerInterface::class, $logger);

$server = Server::builder()
    ->setServerInfo('BeeBank', '1.0.0', description: 'Фінтех для пасіки 🐝')
    ->setInstructions('BeeBank — банк для вуликів. Валюта — мед у грамах. Правила: ресурс beebank://rules.')
    ->setContainer($container)
    ->setLogger($logger)
    ->setSession(new FileSessionStore(dirname(__DIR__).'/var/mcp-sessions'))
    ->setDiscovery(dirname(__DIR__), ['src/Mcp'])
    ->build();

$response = $server->run(new StreamableHttpTransport(
    (new Psr17Factory())->createServerRequestFromGlobals(),
    logger: $logger,
));

http_response_code($response->getStatusCode());

foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header("{$name}: {$value}", false);
    }
}

$body = $response->getBody();
$sent = '';
while (!$body->eof()) {
    $chunk = $body->read(8192);
    echo $chunk;
    flush();
    $traffic->chunk($chunk);
    $sent .= $chunk;
}

$traffic->response($response->getStatusCode(), $response->getHeaderLine('Mcp-Session-Id') ?: null, $sent);
