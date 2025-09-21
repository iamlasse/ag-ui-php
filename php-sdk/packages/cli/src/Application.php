<?php

declare(strict_types=1);

namespace AGUI\CLI;

use AGUI\CLI\Command\CreateAppCommand;
use Symfony\Component\Console\Application as BaseApplication;

class Application extends BaseApplication
{
    public function __construct()
    {
        parent::__construct('AG-UI CLI', '1.0.0');
        
        $this->addCommands([
            new CreateAppCommand(),
        ]);
    }
}
