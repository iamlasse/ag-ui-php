<?php

declare(strict_types=1);

namespace AGUI\CLI\Template;

interface TemplateInterface
{
    public function create(string $appName, string $directory, bool $force = false): void;
    
    public function getDescription(): string;
}
