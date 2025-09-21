<?php

declare(strict_types=1);

namespace AGUI\CLI\Command;

use AGUI\CLI\Template\TemplateManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'create:app',
    description: 'Create a new AG-UI PHP application',
    aliases: ['create']
)]
class CreateAppCommand extends Command
{
    private TemplateManager $templateManager;

    public function __construct()
    {
        parent::__construct();
        $this->templateManager = new TemplateManager();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'The name of your AG-UI application'
            )
            ->addOption(
                'template',
                't',
                InputOption::VALUE_OPTIONAL,
                'Template to use (basic, laravel, symfony)',
                'basic'
            )
            ->addOption(
                'directory',
                'd',
                InputOption::VALUE_OPTIONAL,
                'Directory to create the app in',
                null
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Overwrite existing directory if it exists'
            )
            ->setHelp(
                <<<'EOF'
The <info>create:app</info> command creates a new AG-UI PHP application with the specified template.

<info>php bin/create-ag-ui-app create:app my-app</info>

Available templates:
  <comment>basic</comment>    - Basic PHP application with AG-UI core
  <comment>laravel</comment>  - Laravel application with AG-UI integration
  <comment>symfony</comment>  - Symfony application with AG-UI integration

You can also specify a custom directory:
<info>php bin/create-ag-ui-app create:app my-app --directory /path/to/custom/location</info>
EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $appName = $input->getArgument('name');
        $template = $input->getOption('template');
        $directory = $input->getOption('directory') ?? $appName;
        $force = $input->getOption('force');

        $io->title('AG-UI Application Generator');
        
        // Validate template
        if (!$this->templateManager->hasTemplate($template)) {
            $io->error(sprintf('Template "%s" does not exist. Available templates: %s', 
                $template, 
                implode(', ', $this->templateManager->getAvailableTemplates())
            ));
            return Command::FAILURE;
        }

        // Check if directory exists
        if (is_dir($directory) && !$force) {
            $io->error(sprintf('Directory "%s" already exists. Use --force to overwrite.', $directory));
            return Command::FAILURE;
        }

        $io->section('Creating AG-UI application');
        $io->text([
            sprintf('Application name: <info>%s</info>', $appName),
            sprintf('Template: <info>%s</info>', $template),
            sprintf('Directory: <info>%s</info>', realpath('.') . '/' . $directory),
        ]);

        if (!$io->confirm('Do you want to continue?', true)) {
            $io->text('Aborted.');
            return Command::SUCCESS;
        }

        try {
            $this->templateManager->createApp($appName, $template, $directory, $force);
            
            $io->success([
                'AG-UI application created successfully!',
                'Next steps:',
                sprintf('  cd %s', $directory),
                '  composer install',
                '  php -S localhost:8000 -t public',
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to create application: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
