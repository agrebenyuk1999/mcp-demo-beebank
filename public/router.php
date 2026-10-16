<?php

declare(strict_types=1);

if ('/mcp' === parse_url($_SERVER['REQUEST_URI'], \PHP_URL_PATH)) {
    require __DIR__.'/mcp.php';

    return true;
}

return false;
