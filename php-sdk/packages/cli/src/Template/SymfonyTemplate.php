<?php

declare(strict_types=1);

namespace AGUI\CLI\Template;

use RuntimeException;

class SymfonyTemplate implements TemplateInterface
{
    public function create(string $appName, string $directory, bool $force = false): void
    {
        if (is_dir($directory) && !$force) {
            throw new RuntimeException(sprintf('Directory "%s" already exists', $directory));
        }

        // Create directory structure
        $this->createDirectory($directory);
        $this->createDirectory($directory . '/src/Controller');
        $this->createDirectory($directory . '/src/Service');
        $this->createDirectory($directory . '/config');
        $this->createDirectory($directory . '/public');

        // Create Symfony-specific files
        $this->createComposerJson($appName, $directory);
        $this->createController($directory);
        $this->createService($directory);
        $this->createConfig($directory);
        $this->createKernel($directory);
        $this->createIndex($directory);
        $this->createReadme($appName, $directory);
    }

    public function getDescription(): string
    {
        return 'Symfony application with AG-UI integration and dependency injection';
    }

    private function createDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    private function createComposerJson(string $appName, string $directory): void
    {
        $composerData = [
            'name' => strtolower(str_replace([' ', '_'], '-', $appName)),
            'description' => sprintf('%s - AG-UI Symfony Application', $appName),
            'type' => 'project',
            'license' => 'MIT',
            'require' => [
                'php' => '>=8.1',
                'symfony/console' => '^7.0',
                'symfony/framework-bundle' => '^7.0',
                'symfony/yaml' => '^7.0',
                'symfony/dotenv' => '^7.0',
                'ag-ui/core' => '*',
                'ag-ui/client' => '*',
                'ag-ui/encoder' => '*',
                'ag-ui/symfony-integration' => '*',
            ],
            'require-dev' => [
                'phpunit/phpunit' => '^10.0',
                'symfony/phpunit-bridge' => '^7.0',
            ],
            'autoload' => [
                'psr-4' => [
                    'App\\' => 'src/',
                ],
            ],
            'scripts' => [
                'serve' => 'symfony server:start',
                'test' => 'phpunit',
            ],
            'config' => [
                'sort-packages' => true,
            ],
            'minimum-stability' => 'stable',
            'prefer-stable' => true,
        ];

        file_put_contents(
            $directory . '/composer.json',
            json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private function createController(string $directory): void
    {
        $content = <<<PHP
<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AGUIService;
use AGUI\Core\Event\Event;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/agui')]
class AGUIController extends AbstractController
{
    public function __construct(
        private AGUIService \$aguiService
    ) {}

    #[Route('/health', name: 'agui_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return \$this->json([
            'status' => 'ok',
            'app' => 'Symfony AG-UI App',
            'ag_ui_version' => '1.0.0',
        ]);
    }

    #[Route('/events', name: 'agui_handle_event', methods: ['POST'])]
    public function handleEvent(Request \$request): JsonResponse
    {
        \$eventData = json_decode(\$request->getContent(), true);
        
        if (!\$eventData) {
            return \$this->json(['error' => 'Invalid JSON'], 400);
        }

        \$event = new Event(\$eventData['type'] ?? 'unknown', \$eventData['data'] ?? []);
        \$response = \$this->aguiService->handleEvent(\$event);

        return \$this->json(\$response);
    }

    #[Route('/events', name: 'agui_stream_events', methods: ['GET'])]
    public function streamEvents(): StreamedResponse
    {
        return new StreamedResponse(function () {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');

            // Send initial connection event
            \$this->sendEvent([
                'type' => 'connection',
                'data' => [
                    'message' => 'Connected to AG-UI Symfony app',
                    'timestamp' => date('c'),
                ],
            ]);

            // Keep connection alive with periodic heartbeats
            while (true) {
                \$this->sendEvent([
                    'type' => 'heartbeat',
                    'data' => ['timestamp' => date('c')],
                ]);

                flush();
                sleep(30);
            }
        });
    }

    private function sendEvent(array \$event): void
    {
        echo "data: " . json_encode(\$event) . "\\n\\n";
        ob_flush();
        flush();
    }
}
PHP;

        file_put_contents($directory . '/src/Controller/AGUIController.php', $content);
    }

    private function createService(string $directory): void
    {
        $content = <<<PHP
<?php

declare(strict_types=1);

namespace App\Service;

use AGUI\Core\Client;
use AGUI\Core\Event\Event;

class AGUIService
{
    public function __construct(
        private Client \$client
    ) {}

    public function handleEvent(Event \$event): array
    {
        return match (\$event->getType()) {
            'message' => \$this->handleMessage(\$event),
            'state_update' => \$this->handleStateUpdate(\$event),
            'command' => \$this->handleCommand(\$event),
            default => \$this->handleDefault(\$event),
        };
    }

    private function handleMessage(Event \$event): array
    {
        \$data = \$event->getData();
        \$content = \$data['content'] ?? 'No content provided';

        return [
            'type' => 'message_response',
            'data' => [
                'content' => "Echo: " . \$content,
                'timestamp' => date('c'),
                'processed_by' => 'Symfony AG-UI Service',
            ],
        ];
    }

    private function handleStateUpdate(Event \$event): array
    {
        \$data = \$event->getData();
        
        // Process state update logic here
        
        return [
            'type' => 'state_acknowledged',
            'data' => [
                'message' => 'State update processed successfully',
                'updated_fields' => array_keys(\$data),
                'timestamp' => date('c'),
            ],
        ];
    }

    private function handleCommand(Event \$event): array
    {
        \$data = \$event->getData();
        \$command = \$data['command'] ?? 'unknown';

        // Process command logic here
        
        return [
            'type' => 'command_result',
            'data' => [
                'command' => \$command,
                'result' => sprintf('Command "%s" executed successfully', \$command),
                'timestamp' => date('c'),
            ],
        ];
    }

    private function handleDefault(Event \$event): array
    {
        return [
            'type' => 'acknowledgment',
            'data' => [
                'message' => sprintf('Event type "%s" received and processed', \$event->getType()),
                'timestamp' => date('c'),
            ],
        ];
    }
}
PHP;

        file_put_contents($directory . '/src/Service/AGUIService.php', $content);
    }

    private function createConfig(string $directory): void
    {
        $configContent = <<<YAML
# config/services.yaml
services:
  _defaults:
    autowire: true
    autoconfigure: true

  App\\:
    resource: '../src/'
    exclude:
      - '../src/DependencyInjection/'
      - '../src/Entity/'
      - '../src/Kernel.php'

  AGUI\\Core\\Client:
    arguments:
      \$config: '%agui_config%'

parameters:
  agui_config:
    endpoint: '%env(AGUI_ENDPOINT)%'
    timeout: '%env(int:AGUI_TIMEOUT)%'
    retry_attempts: '%env(int:AGUI_RETRY_ATTEMPTS)%'
    cors:
      allowed_origins: !split '%env(AGUI_CORS_ORIGINS)%'
      allowed_methods: ['GET', 'POST', 'OPTIONS']
      allowed_headers: ['Content-Type', 'Authorization']
YAML;

        file_put_contents($directory . '/config/services.yaml', $configContent);

        $routesContent = <<<YAML
# config/routes.yaml
controllers:
  resource: ../src/Controller/
  type: attribute
YAML;

        file_put_contents($directory . '/config/routes.yaml', $routesContent);

        $envContent = <<<ENV
# .env
APP_ENV=dev
APP_SECRET=change_me

AGUI_ENDPOINT=http://localhost:8000/api/agui/events
AGUI_TIMEOUT=30
AGUI_RETRY_ATTEMPTS=3
AGUI_CORS_ORIGINS=http://localhost:3000
ENV;

        file_put_contents($directory . '/.env', $envContent);
    }

    private function createKernel(string $directory): void
    {
        $content = <<<PHP
<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;
}
PHP;

        file_put_contents($directory . '/src/Kernel.php', $content);
    }

    private function createIndex(string $directory): void
    {
        $content = <<<PHP
<?php

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__) . '/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');

\$kernel = new Kernel(\$_SERVER['APP_ENV'], (bool) \$_SERVER['APP_DEBUG']);
\$request = Request::createFromGlobals();
\$response = \$kernel->handle(\$request);
\$response->send();
\$kernel->terminate(\$request, \$response);
PHP;

        file_put_contents($directory . '/public/index.php', $content);
    }

    private function createReadme(string $appName, string $directory): void
    {
        $content = <<<MD
# $appName

AG-UI Symfony Application generated with create-ag-ui-app.

## Getting Started

1. Install dependencies:
   ```bash
   composer install
   ```

2. Install Symfony CLI (recommended):
   ```bash
   wget https://get.symfony.com/cli/installer -O - | bash
   ```

3. Start the development server:
   ```bash
   symfony server:start
   ```

   Or use PHP built-in server:
   ```bash
   php -S localhost:8000 -t public
   ```

4. Your application is now running at http://localhost:8000

## AG-UI Endpoints

- `GET /api/agui/health` - Health check
- `GET /api/agui/events` - Server-sent events stream
- `POST /api/agui/events` - Handle incoming events

## Configuration

Environment variables in `.env`:

```
APP_ENV=dev
APP_SECRET=change_me

AGUI_ENDPOINT=http://localhost:8000/api/agui/events
AGUI_TIMEOUT=30
AGUI_RETRY_ATTEMPTS=3
AGUI_CORS_ORIGINS=http://localhost:3000
```

## Project Structure

```
$directory/
├── src/
│   ├── Controller/
│   │   └── AGUIController.php    # AG-UI event handling
│   ├── Service/
│   │   └── AGUIService.php       # AG-UI business logic
│   └── Kernel.php               # Application kernel
├── config/
│   ├── services.yaml            # Service configuration
│   └── routes.yaml              # Route configuration
├── public/
│   └── index.php               # Web entry point
├── .env                        # Environment variables
├── composer.json               # Dependencies
└── README.md                   # This file
```

## Development

Run tests:
```bash
composer test
```

Console commands:
```bash
php bin/console list
```

## AG-UI Documentation

Visit [ag-ui.com](https://ag-ui.com) for full documentation.
MD;

        file_put_contents($directory . '/README.md', $content);
    }
}
