<?php

declare(strict_types=1);

namespace AGUI\CLI\Template;

use InvalidArgumentException;
use RuntimeException;

class TemplateManager
{
    private array $templates = [
        'basic' => BasicTemplate::class,
        'laravel' => LaravelTemplate::class,
        'symfony' => SymfonyTemplate::class,
    ];

    public function hasTemplate(string $name): bool
    {
        return isset($this->templates[$name]);
    }

    public function getAvailableTemplates(): array
    {
        return array_keys($this->templates);
    }

    public function createApp(string $appName, string $template, string $directory, bool $force = false): void
    {
        if (!$this->hasTemplate($template)) {
            throw new InvalidArgumentException(sprintf('Template "%s" does not exist', $template));
        }

        $templateClass = $this->templates[$template];
        $templateInstance = new $templateClass();

        if (!$templateInstance instanceof TemplateInterface) {
            throw new RuntimeException(sprintf('Template class "%s" must implement TemplateInterface', $templateClass));
        }

        $templateInstance->create($appName, $directory, $force);
    }
}
