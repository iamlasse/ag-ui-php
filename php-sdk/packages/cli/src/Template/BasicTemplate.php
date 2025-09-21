<?php

declare(strict_types=1);

namespace AGUI\CLI\Template;

use RuntimeException;

class BasicTemplate implements TemplateInterface
{
    public function create(string $appName, string $directory, bool $force = false): void
    {
        if (is_dir($directory) && !$force) {
            throw new RuntimeException(sprintf('Directory "%s" already exists', $directory));
        }

        // Create directory structure
        $this->createDirectory($directory);
        $this->createDirectory($directory . '/src');
        $this->createDirectory($directory . '/public');
        $this->createDirectory($directory . '/config');

        // Create composer.json
        $this->createComposerJson($appName, $directory);
        
        // Create basic PHP files
        $this->createIndexFile($appName, $directory);
        $this->createAppFile($appName, $directory);
        $this->createConfigFile($directory);
        $this->createReadme($appName, $directory);
    }

    public function getDescription(): string
    {
        return 'Basic PHP application with AG-UI core functionality';
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
            'description' => sprintf('%s - AG-UI PHP Application', $appName),
            'type' => 'project',
            'license' => 'MIT',
            'require' => [
                'php' => '>=8.1',
                'ag-ui/core' => '*',
                'ag-ui/client' => '*',
                'ag-ui/encoder' => '*',
            ],
            'require-dev' => [
                'phpunit/phpunit' => '^10.0',
            ],
            'autoload' => [
                'psr-4' => [
                    'App\\' => 'src/',
                ],
            ],
            'scripts' => [
                'start' => 'php -S localhost:8000 -t public',
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

    private function createIndexFile(string $appName, string $directory): void
    {
        $content = <<<PHP
<?php

require_once '../vendor/autoload.php';

use App\Application;

\$app = new Application();
\$app->run();
PHP;

        file_put_contents($directory . '/public/index.php', $content);
    }

    private function createAppFile(string $appName, string $directory): void
    {
        $className = ucwords(str_replace(['-', '_', ' '], '', $appName));
        
        $content = <<<PHP
<?php

declare(strict_types=1);

namespace App;

use AGUI\Core\Client;
use AGUI\Core\EventEncoder;

class Application
{
    private Client \$client;
    
    public function __construct()
    {
        \$config = require __DIR__ . '/../config/agui.php';
        \$this->client = new Client(\$config);
    }
    
    public function run(): void
    {
        // Basic AG-UI application setup
        header('Content-Type: application/json');
        
        \$method = \$_SERVER['REQUEST_METHOD'] ?? 'GET';
        \$path = parse_url(\$_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        
        switch (\$path) {
            case '/health':
                echo json_encode(['status' => 'ok', 'app' => '$appName']);
                break;
                
            case '/api/events':
                if (\$method === 'POST') {
                    \$this->handleEvent();
                } else {
                    \$this->sendEvents();
                }
                break;
                
            default:
                http_response_code(404);
                echo json_encode(['error' => 'Not found']);
        }
    }
    
    private function handleEvent(): void
    {
        \$input = json_decode(file_get_contents('php://input'), true);
        
        // Process incoming event
        \$response = [
            'type' => 'message',
            'data' => [
                'content' => 'Hello from AG-UI PHP!',
                'timestamp' => date('c'),
            ],
        ];
        
        echo json_encode(\$response);
    }
    
    private function sendEvents(): void
    {
        // Send server-sent events
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        
        \$event = [
            'type' => 'welcome',
            'data' => [
                'message' => 'Connected to $appName',
                'timestamp' => date('c'),
            ],
        ];
        
        echo "data: " . json_encode(\$event) . "\\n\\n";
        flush();
    }
}
PHP;

        file_put_contents($directory . '/src/Application.php', $content);
    }

    private function createConfigFile(string $directory): void
    {
        $content = <<<PHP
<?php

return [
    'ag_ui' => [
        'endpoint' => 'http://localhost:8000/api/events',
        'timeout' => 30,
        'retry_attempts' => 3,
    ],
    
    'cors' => [
        'allowed_origins' => ['http://localhost:3000'],
        'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization'],
    ],
];
PHP;

        file_put_contents($directory . '/config/agui.php', $content);
    }

    private function createReadme(string $appName, string $directory): void
    {
        $content = <<<MD
# $appName

AG-UI PHP Application generated with create-ag-ui-app.

## Getting Started

1. Install dependencies:
   ```bash
   composer install
   ```

2. Start the development server:
   ```bash
   composer start
   ```

3. Your application is now running at http://localhost:8000

## API Endpoints

- `GET /health` - Health check
- `GET /api/events` - Server-sent events stream  
- `POST /api/events` - Handle incoming events

## Project Structure

```
$directory/
├── src/
│   └── Application.php    # Main application class
├── public/
│   └── index.php         # Web entry point
├── config/
│   └── agui.php          # AG-UI configuration
├── composer.json         # Composer dependencies
└── README.md            # This file
```

## Development

Run tests:
```bash
composer test
```

## AG-UI Documentation

Visit [ag-ui.com](https://ag-ui.com) for full documentation.
MD;

        file_put_contents($directory . '/README.md', $content);
    }
}
