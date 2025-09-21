<?php

declare(strict_types=1);

namespace AGUI\CLI\Template;

use RuntimeException;

class LaravelTemplate implements TemplateInterface
{
    public function create(string $appName, string $directory, bool $force = false): void
    {
        if (is_dir($directory) && !$force) {
            throw new RuntimeException(sprintf('Directory "%s" already exists', $directory));
        }

        // Create directory structure
        $this->createDirectory($directory);
        $this->createDirectory($directory . '/app/Http/Controllers');
        $this->createDirectory($directory . '/config');
        $this->createDirectory($directory . '/routes');
        $this->createDirectory($directory . '/resources/views');

        // Create Laravel-specific files
        $this->createComposerJson($appName, $directory);
        $this->createServiceProvider($directory);
        $this->createController($directory);
        $this->createRoutes($directory);
        $this->createConfig($directory);
        $this->createReadme($appName, $directory);
    }

    public function getDescription(): string
    {
        return 'Laravel application with AG-UI integration and service provider';
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
            'description' => sprintf('%s - AG-UI Laravel Application', $appName),
            'type' => 'project',
            'license' => 'MIT',
            'require' => [
                'php' => '>=8.1',
                'laravel/framework' => '^10.0',
                'ag-ui/core' => '*',
                'ag-ui/client' => '*',
                'ag-ui/encoder' => '*',
                'ag-ui/laravel-integration' => '*',
            ],
            'require-dev' => [
                'phpunit/phpunit' => '^10.0',
                'laravel/sail' => '^1.0',
            ],
            'autoload' => [
                'psr-4' => [
                    'App\\' => 'app/',
                    'Database\\Factories\\' => 'database/factories/',
                    'Database\\Seeders\\' => 'database/seeders/',
                ],
            ],
            'scripts' => [
                'serve' => 'php artisan serve',
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

    private function createServiceProvider(string $directory): void
    {
        $content = <<<PHP
<?php

declare(strict_types=1);

namespace App\Providers;

use AGUI\Laravel\AGUIServiceProvider as BaseAGUIServiceProvider;

class AGUIServiceProvider extends BaseAGUIServiceProvider
{
    public function boot(): void
    {
        parent::boot();
        
        // Publish configuration
        \$this->publishes([
            __DIR__ . '/../../config/agui.php' => config_path('agui.php'),
        ], 'config');
        
        // Register routes
        \$this->loadRoutesFrom(__DIR__ . '/../../routes/agui.php');
    }
    
    public function register(): void
    {
        parent::register();
        
        // Merge configuration
        \$this->mergeConfigFrom(__DIR__ . '/../../config/agui.php', 'agui');
    }
}
PHP;

        file_put_contents($directory . '/app/Providers/AGUIServiceProvider.php', $content);
    }

    private function createController(string $directory): void
    {
        $content = <<<PHP
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use AGUI\Core\Client;
use AGUI\Core\Event\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller as BaseController;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AGUIController extends BaseController
{
    private Client \$client;
    
    public function __construct(Client \$client)
    {
        \$this->client = \$client;
    }
    
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'app' => config('app.name'),
            'ag_ui_version' => '1.0.0',
        ]);
    }
    
    public function handleEvent(Request \$request): JsonResponse
    {
        \$eventData = \$request->json()->all();
        
        // Process the incoming event
        \$event = new Event(\$eventData['type'] ?? 'unknown', \$eventData['data'] ?? []);
        
        // Handle different event types
        \$response = match (\$event->getType()) {
            'message' => \$this->handleMessage(\$event),
            'state_update' => \$this->handleStateUpdate(\$event),
            default => \$this->handleDefault(\$event),
        };
        
        return response()->json(\$response);
    }
    
    public function streamEvents(Request \$request): StreamedResponse
    {
        return response()->stream(function () {
            // Set headers for SSE
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            
            // Send initial event
            \$this->sendEvent([
                'type' => 'connection',
                'data' => [
                    'message' => 'Connected to AG-UI Laravel app',
                    'timestamp' => now()->toISOString(),
                ],
            ]);
            
            // Keep connection alive
            while (true) {
                \$this->sendEvent([
                    'type' => 'heartbeat',
                    'data' => ['timestamp' => now()->toISOString()],
                ]);
                
                flush();
                sleep(30);
            }
        });
    }
    
    private function handleMessage(Event \$event): array
    {
        return [
            'type' => 'message_response',
            'data' => [
                'content' => 'Message received: ' . (\$event->getData()['content'] ?? 'No content'),
                'timestamp' => now()->toISOString(),
            ],
        ];
    }
    
    private function handleStateUpdate(Event \$event): array
    {
        return [
            'type' => 'state_acknowledged',
            'data' => [
                'message' => 'State update processed',
                'timestamp' => now()->toISOString(),
            ],
        ];
    }
    
    private function handleDefault(Event \$event): array
    {
        return [
            'type' => 'acknowledgment',
            'data' => [
                'message' => sprintf('Event type "%s" processed', \$event->getType()),
                'timestamp' => now()->toISOString(),
            ],
        ];
    }
    
    private function sendEvent(array \$event): void
    {
        echo "data: " . json_encode(\$event) . "\\n\\n";
        ob_flush();
        flush();
    }
}
PHP;

        file_put_contents($directory . '/app/Http/Controllers/AGUIController.php', $content);
    }

    private function createRoutes(string $directory): void
    {
        $content = <<<PHP
<?php

use App\Http\Controllers\AGUIController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/agui')->group(function () {
    Route::get('/health', [AGUIController::class, 'health']);
    Route::post('/events', [AGUIController::class, 'handleEvent']);
    Route::get('/events', [AGUIController::class, 'streamEvents']);
});
PHP;

        file_put_contents($directory . '/routes/agui.php', $content);
    }

    private function createConfig(string $directory): void
    {
        $content = <<<PHP
<?php

return [
    'endpoint' => env('AGUI_ENDPOINT', 'http://localhost:8000/api/agui/events'),
    'timeout' => env('AGUI_TIMEOUT', 30),
    'retry_attempts' => env('AGUI_RETRY_ATTEMPTS', 3),
    
    'cors' => [
        'allowed_origins' => explode(',', env('AGUI_CORS_ORIGINS', 'http://localhost:3000')),
        'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization'],
    ],
    
    'events' => [
        'max_listeners' => env('AGUI_MAX_LISTENERS', 100),
        'buffer_size' => env('AGUI_BUFFER_SIZE', 1024),
    ],
];
PHP;

        file_put_contents($directory . '/config/agui.php', $content);
    }

    private function createReadme(string $appName, string $directory): void
    {
        $content = <<<MD
# $appName

AG-UI Laravel Application generated with create-ag-ui-app.

## Getting Started

1. Install dependencies:
   ```bash
   composer install
   ```

2. Copy environment file:
   ```bash
   cp .env.example .env
   ```

3. Generate application key:
   ```bash
   php artisan key:generate
   ```

4. Start the development server:
   ```bash
   php artisan serve
   ```

5. Your application is now running at http://localhost:8000

## AG-UI Endpoints

- `GET /api/agui/health` - Health check
- `GET /api/agui/events` - Server-sent events stream
- `POST /api/agui/events` - Handle incoming events

## Configuration

Add these environment variables to your `.env` file:

```
AGUI_ENDPOINT=http://localhost:8000/api/agui/events
AGUI_TIMEOUT=30
AGUI_RETRY_ATTEMPTS=3
AGUI_CORS_ORIGINS=http://localhost:3000
AGUI_MAX_LISTENERS=100
AGUI_BUFFER_SIZE=1024
```

## Project Structure

```
$directory/
├── app/
│   ├── Http/Controllers/
│   │   └── AGUIController.php    # AG-UI event handling
│   └── Providers/
│       └── AGUIServiceProvider.php # Service provider
├── config/
│   └── agui.php                  # AG-UI configuration
├── routes/
│   └── agui.php                  # AG-UI routes
├── composer.json                 # Dependencies
└── README.md                     # This file
```

## Development

Run tests:
```bash
php artisan test
```

## AG-UI Documentation

Visit [ag-ui.com](https://ag-ui.com) for full documentation.
MD;

        file_put_contents($directory . '/README.md', $content);
    }
}
