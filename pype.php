#!/usr/bin/env php
<?php

/**
 * Pype Framework CLI
 * 
 * Version: 2.5.0
 * Description: Command-line interface for Pype PHP Framework
 */

// Load composer autoloader (only if it exists)
$autoloadFile = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadFile)) {
    require $autoloadFile;
}

class PypeCLI
{
    private $projectRoot;
    private $commands = [];

    public function __construct()
    {
        $this->projectRoot = dirname(__FILE__);
        $this->registerCommands();
    }

    /**
     * Register all available commands
     */
    private function registerCommands()
    {
        $this->commands = [
            'init' => [
                'callback' => [$this, 'initCommand'],
                'description' => 'Initialize a new Pype project structure'
            ],
            'createview' => [
                'callback' => [$this, 'createViewCommand'],
                'description' => 'Create a new Twig view file'
            ],
            'createcontroller' => [
                'callback' => [$this, 'createControllerCommand'],
                'description' => 'Create a new controller file'
            ],
            'createmodel' => [
                'callback' => [$this, 'createModelCommand'],
                'description' => 'Create a new model file'
            ],
            'make:model' => [
                'callback' => [$this, 'createModelCommand'],
                'description' => 'Create a new model file (alias for createmodel)'
            ],
            'createmiddleware' => [
                'callback' => [$this, 'createMiddlewareCommand'],
                'description' => 'Create a new middleware file'
            ],
            'make:migration' => [
                'callback' => [$this, 'makeMigrationCommand'],
                'description' => 'Create a new migration file'
            ],
            'migrate' => [
                'callback' => [$this, 'migrateCommand'],
                'description' => 'Run pending migrations'
            ],
            'migrate:rollback' => [
                'callback' => [$this, 'migrateRollbackCommand'],
                'description' => 'Rollback the last migration'
            ],
            'migrate:fresh' => [
                'callback' => [$this, 'migrateFreshCommand'],
                'description' => 'Drop all tables and re-run all migrations'
            ],
            'migrate:fresh:model' => [
                'callback' => [$this, 'migrateFreshModelCommand'],
                'description' => 'Drop and re-create a specific model\'s table'
            ],
            'migrate:status' => [
                'callback' => [$this, 'migrateStatusCommand'],
                'description' => 'Show the status of all migrations'
            ],
            'make:seeder' => [
                'callback' => [$this, 'makeSeederCommand'],
                'description' => 'Create a new seeder file'
            ],
            'db:seed' => [
                'callback' => [$this, 'dbSeedCommand'],
                'description' => 'Run database seeders'
            ],
            'serve' => [
                'callback' => [$this, 'serveCommand'],
                'description' => 'Serve the website locally (default port: 8000)'
            ],
            'help' => [
                'callback' => [$this, 'helpCommand'],
                'description' => 'Display help information'
            ]
        ];
    }

    /**
     * Run the CLI
     */
    public function run($argv)
    {
        if (count($argv) < 2) {
            $this->showHelp();
            return;
        }

        $command = $argv[1] ?? null;

        if (!$command || $command === 'help') {
            $this->showHelp();
            return;
        }

        if (!isset($this->commands[$command])) {
            $this->error("Unknown command: {$command}");
            $this->showHelp();
            return;
        }

        // Pass additional arguments to the command
        $arguments = array_slice($argv, 2);
        $callback = $this->commands[$command]['callback'];
        call_user_func($callback, ...$arguments);
    }

     /**
       * Initialize command - Set up project structure
       */
      private function initCommand(...$args)
      {
          // Check for --dev flag to skip interactive prompts and use dist packages
          $devMode = false;
          foreach ($args as $arg) {
              if ($arg === '--dev') {
                  $devMode = true;
                  break;
              }
          }
  
          $this->line('🚀 Initializing Pype Project...');
          $this->line('');
  
          // Step 1: Create App folder structure
          $this->line('📁 Creating App directory structure...');
          $appFolders = [
              'App/Controller',
              'App/Middleware',
              'App/Models',
              'App/Helpers'
          ];
  
          foreach ($appFolders as $folder) {
              $path = $this->projectRoot . '/' . $folder;
              if (!is_dir($path)) {
                  mkdir($path, 0755, true);
                  $this->success("  ✓ Created: {$folder}");
              } else {
                  $this->info("  • Already exists: {$folder}");
              }
          }
  
          $this->line('');
  
          // Step 2: Create Assets folder structure
          $this->line('📁 Creating assets directory structure...');
          $assetsFolders = [
              'assets/css',
              'assets/js',
              'assets/images'
          ];
  
          foreach ($assetsFolders as $folder) {
              $path = $this->projectRoot . '/' . $folder;
              if (!is_dir($path)) {
                  mkdir($path, 0755, true);
                  $this->success("  ✓ Created: {$folder}");
              } else {
                  $this->info("  • Already exists: {$folder}");
              }
          }
  
          $this->line('');
  
          // Step 2.5: Create Storage and migrations directories
          $this->line('📁 Creating Storage and migrations directories...');
          $storageFolders = [
              'Storage/logs',
              'Storage/uploads',
              'migrations'
          ];
  
          foreach ($storageFolders as $folder) {
              $path = $this->projectRoot . '/' . $folder;
              if (!is_dir($path)) {
                  mkdir($path, 0755, true);
                  $this->success("  ✓ Created: {$folder}");
              } else {
                  $this->info("  • Already exists: {$folder}");
              }
          }
  
          $this->line('');
  
          // Step 3: Remove Git traces
          $this->line('🧹 Cleaning up Git traces...');
  
          $gitFolder = $this->projectRoot . '/.git';
          if (is_dir($gitFolder)) {
              $this->removeDirectory($gitFolder);
              $this->success('  ✓ Removed .git folder');
          } else {
              $this->info('  • .git folder not found');
          }
  
          $gitIgnore = $this->projectRoot . '/.gitignore';
          if (file_exists($gitIgnore)) {
              unlink($gitIgnore);
              $this->success('  ✓ Removed .gitignore');
          } else {
              $this->info('  • .gitignore not found');
          }
  
          $pypephpFolder = $this->projectRoot . '/PYPE-PHP-V2.5';
          if (is_dir($pypephpFolder)) {
              $this->removeDirectory($pypephpFolder);
              $this->success('  ✓ Removed PYPE-PHP-V2.5 folder');
          } else {
              $this->info('  • PYPE-PHP-V2.5 folder not found');
          }
  
          $this->line('');
  
          // Step 4: Copy .env.example to .env
          $this->line('📋 Setting up environment configuration...');
  
          $envExample = $this->projectRoot . '/.env.example';
          $envFile = $this->projectRoot . '/.env';
  
          if (file_exists($envExample)) {
              if (!file_exists($envFile)) {
                  copy($envExample, $envFile);
                  $this->success('  ✓ Created .env from .env.example');
              } else {
                  $this->info('  • .env already exists');
              }
          } else {
              $this->warning('  ⚠ .env.example not found, skipping .env creation');
          }
  
          $this->line('');
  
          // Step 5: Install composer dependencies
          $this->line('📦 Installing Composer dependencies...');
  
          $composerFile = $this->projectRoot . '/composer.json';
          if (file_exists($composerFile)) {
              $this->info('  Installing packages...');
              $composerCommand = 'cd ' . escapeshellarg($this->projectRoot) . ' && composer install';
              if ($devMode) {
                  $composerCommand .= ' --prefer-dist --no-interaction';
              }
              $composerCommand .= ' 2>&1';
              $output = shell_exec($composerCommand);
              if (strpos($output, 'error') === false && strpos($output, 'Error') === false) {
                  $this->success('  ✓ Composer dependencies installed');
              } else {
                  $this->warning('  ⚠ Some issues occurred during composer install');
                  $this->line($output);
              }
          } else {
              $this->info('  • composer.json not found');
          }
  
          $this->line('');
          $this->success('✅ Project initialized successfully!');
          $this->line('');
          $this->line('Next steps:');
          $this->line('  1. Update your .env file with proper configuration');
          $this->line('  2. Start building your application!');
          $this->line('');
      }
    private function createViewCommand($viewName = null)
    {
        if (!$viewName) {
            $this->error('View name is required!');
            $this->line('Usage: php pype.php createview <viewname>');
            $this->line('Example: php pype.php createview home');
            $this->line('Example with nested folder: php pype.php createview admin.dashboard');
            return;
        }

        $this->createFile('view', $viewName, $this->getViewTemplate());
    }

    /**
     * Create Controller command
     */
    private function createControllerCommand($controllerName = null)
    {
        if (!$controllerName) {
            $this->error('Controller name is required!');
            $this->line('Usage: php pype.php createcontroller <controllername>');
            $this->line('Example: php pype.php createcontroller UserController');
            $this->line('Example with nested folder: php pype.php createcontroller Admin.UserController');
            return;
        }

        $this->createFile('controller', $controllerName, $this->getControllerTemplate($controllerName));
    }

    /**
     * Create Model command
     */
    private function createModelCommand($modelName = null)
    {
        if (!$modelName) {
            $this->error('Model name is required!');
            $this->line('Usage: php pype.php createmodel <modelname>');
            $this->line('Example: php pype.php createmodel User');
            $this->line('Example with nested folder: php pype.php createmodel Admin.User');
            return;
        }

        $this->createFile('model', $modelName, $this->getModelTemplate($modelName));
    }

    /**
     * Create Middleware command
     */
    private function createMiddlewareCommand($middlewareName = null)
    {
        if (!$middlewareName) {
            $this->error('Middleware name is required!');
            $this->line('Usage: php pype.php createmiddleware <middlewarename>');
            $this->line('Example: php pype.php createmiddleware AuthMiddleware');
            return;
        }

        $this->createFile('middleware', $middlewareName, $this->getMiddlewareTemplate($middlewareName));
    }

    /**
     * Make Migration command - Create a new migration file
     */
    private function makeMigrationCommand(...$migrationNames)
    {
        if (empty($migrationNames)) {
            $this->error('Migration name(s) required!');
            $this->line('Usage: php pype.php make:migration <name1> [name2] [name3]...');
            $this->line('Examples:');
            $this->line('  php pype.php make:migration User');
            $this->line('  php pype.php make:migration User Post Category');
            $this->line('  php pype.php make:migration create_posts_table');
            return;
        }

        $createdCount = 0;
        
        foreach ($migrationNames as $migrationName) {
            // Check if it's a model name (capitalize first letter)
            $modelName = ucfirst($migrationName);
            $modelsDir = $this->projectRoot . '/App/Models';
            
            // Check if model exists
            if (is_dir($modelsDir) && file_exists($modelsDir . '/' . $modelName . '.php')) {
                // Create migration for model
                $this->createMigrationForModel($modelName);
                $createdCount++;
            } else {
                // Create custom migration - try to pluralize if it looks like a model name
                $tableName = $this->modelToTable($migrationName);
                $this->createMigration('create_' . $tableName . '_table');
                $createdCount++;
            }
        }
        
        if ($createdCount > 1) {
            $this->line('');
            $this->success("âœ… {$createdCount} migration(s) created!");
            $this->line('');
            $this->line('Next: Run "php pype.php migrate" to run all migrations');
        }
    }

    /**
     * Migrate command - Run all pending migrations
     */
    private function migrateCommand()
    {
        $this->line('');
        $this->info('ðŸ”„ Running migrations...');
        $this->line('');

        // Load environment variables before anything else
        if (file_exists($this->projectRoot . '/vendor/autoload.php')) {
            require_once $this->projectRoot . '/vendor/autoload.php';

            if (class_exists('Dotenv\Dotenv')) {
                try {
                    $dotenv = \Dotenv\Dotenv::createImmutable($this->projectRoot);
                    $dotenv->load();
                } catch (\Exception $e) {
                    // Continue without dotenv if it fails
                }
            }
        }

        // Load all required classes before running migrations
        require_once $this->projectRoot . '/Framework/Database/DatabaseInterface.php';
        require_once $this->projectRoot . '/Framework/Database/MySQLConnection.php';
        require_once $this->projectRoot . '/Framework/Database/PostgreSQLConnection.php';
        require_once $this->projectRoot . '/Framework/Database/SQLiteConnection.php';
        require_once $this->projectRoot . '/Framework/Database/DatabaseFactory.php';
        require_once $this->projectRoot . '/Framework/Database/Connect.php';
        require_once $this->projectRoot . '/Framework/Database/DatabaseQuery.php';
        require_once $this->projectRoot . '/Framework/Database/Migration.php';
        require_once $this->projectRoot . '/Framework/Model/Model.php';

        try {
            $this->ensureMigrationsTable();
        } catch (\Exception $e) {
            $this->error('âŒ Database Error:');
            $this->line('');
            $this->line($e->getMessage());
            $this->line('');
            return;
        }

        $migrationDir = $this->projectRoot . '/migrations';
        if (!is_dir($migrationDir)) {
            $this->warning('âš  No migrations folder found');
            return;
        }

        $migratedFiles = $this->getMigratedFiles();
        $files = glob($migrationDir . '/*.php');
        sort($files);

        $this->line("  Found " . count($files) . " migration file(s), " . count($migratedFiles) . " already run");

        $ran = 0;
        foreach ($files as $file) {
            $fileName = basename($file);

            if (!in_array($fileName, $migratedFiles)) {
                try {
                    $this->line("  Running: {$fileName}");
                    include_once $file;

                    // Extract class name from filename
                    $className = $this->getClassFromFile($file);

                    if ($className && class_exists($className)) {
                        $migration = new $className();
                        $migration->up();

                        $this->recordMigration($fileName);
                        $this->success("    âœ“ Migrated: {$fileName}");
                        $ran++;
                    }
                } catch (\Exception $e) {
                    $this->error("    âœ— Error: " . $e->getMessage());
                }
            } else {
                $this->line("  Skipping: {$fileName} (already run)");
            }
        }

        if ($ran === 0) {
            $this->info('âœ“ Nothing to migrate');
        } else {
            $this->line('');
            $this->success("âœ… {$ran} migration(s) executed successfully!");
        }
        $this->line('');
    }

    /**
     * Pluralize a word (handles irregular plurals)
     */
    private function pluralize($word)
    {
        // Irregular plurals
        $irregular = [
            'category' => 'categories',
            'person' => 'people',
            'child' => 'children',
            'man' => 'men',
            'woman' => 'women',
            'foot' => 'feet',
            'tooth' => 'teeth',
            'goose' => 'geese',
            'mouse' => 'mice',
            'datum' => 'data',
            'phenomenon' => 'phenomena',
            'criterion' => 'criteria',
            'analysis' => 'analyses',
            'basis' => 'bases',
            'crisis' => 'crises',
            'thesis' => 'theses',
            'hypothesis' => 'hypotheses',
            'oasis' => 'oases',
            'diagnosis' => 'diagnoses',
            'parenthesis' => 'parentheses',
            'synthesis' => 'syntheses',
        ];

        $lowerWord = strtolower($word);

        // Check irregular plurals
        if (isset($irregular[$lowerWord])) {
            return $irregular[$lowerWord];
        }

        // Words ending in 'y' preceded by consonant â†’ change 'y' to 'ies'
        if (preg_match('/[aeiou]y$/', $lowerWord)) {
            return $word . 's';  // vowel + y, just add s (e.g., day â†’ days)
        }
        if (preg_match('/[^aeiou]y$/', $lowerWord)) {
            return substr($word, 0, -1) . 'ies';  // consonant + y (e.g., category â†’ categories)
        }

        // Words ending in 's', 'ss', 'sh', 'ch', 'x', 'z', 'o' â†’ add 'es'
        if (preg_match('/(s|ss|sh|ch|x|z|o)$/i', $lowerWord)) {
            return $word . 'es';
        }

        // Words ending in 'f' or 'fe' â†’ change to 'ves'
        if (preg_match('/f$/', $lowerWord)) {
            return substr($word, 0, -1) . 'ves';
        }
        if (preg_match('/fe$/', $lowerWord)) {
            return substr($word, 0, -2) . 'ves';
        }

        // Default: just add 's'
        return $word . 's';
    }

    /**
     * Create migration for a specific model
     */
    private function createMigrationForModel($modelName)
    {
        $migrationDir = $this->projectRoot . '/migrations';
        $timestamp = date('Y_m_d_His');
        
        // Pass model name directly so getMigrationTemplate can find it and use explicit table name
        $fileName = "{$timestamp}_{$modelName}.php";
        $filePath = $migrationDir . '/' . $fileName;

        $className = $modelName;
        
        $this->line("  Creating migration for {$modelName}...");
        
        // Pass model name as migrationName so it matches directly and uses explicit $table
        $template = $this->getMigrationTemplate($className, $modelName);

        file_put_contents($filePath, $template);

        $this->success("    âœ“ Created: {$fileName}");
    }

    /**
     * Convert model name to table name (handles plurals)
     */
    private function modelToTable($modelName)
    {
        // Convert PascalCase to snake_case and pluralize
        $snakeCase = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $modelName));
        
        // Get the last word (main noun) for pluralization
        $parts = explode('_', $snakeCase);
        $lastPart = array_pop($parts);
        
        // Pluralize the last part
        $pluralized = $this->pluralize($lastPart);
        
        // Rebuild the table name
        $parts[] = $pluralized;
        
        return implode('_', $parts);
    }

    /**
     * Migrate Rollback command - Rollback the last migration
     */
    private function migrateRollbackCommand()
    {
        $this->line('');
        $this->info('â® Rolling back migrations...');
        $this->line('');

        $this->ensureMigrationsTable();

        $lastMigration = $this->getLastMigration();

        if (!$lastMigration) {
            $this->warning('âš  No migrations to rollback');
            return;
        }

        try {
            $migrationDir = $this->projectRoot . '/migrations';
            $file = $migrationDir . '/' . $lastMigration;

            if (file_exists($file)) {
                include_once $file;

                $className = $this->getClassFromFile($file);

                if ($className && class_exists($className)) {
                    $this->line("  Rolling back: {$lastMigration}");
                    $migration = new $className();
                    $migration->down();

                    $this->removeMigration($lastMigration);
                    $this->success("    âœ“ Rolled back: {$lastMigration}");
                }
            }
        } catch (\Exception $e) {
            $this->error("    âœ— Error: " . $e->getMessage());
        }

        $this->line('');
    }

    /**
     * Migrate Fresh command - Drop all tables and re-run all migrations
     */
    private function migrateFreshCommand()
    {
        $this->line('');
        $this->warning('âš   DROPPING ALL TABLES - This action cannot be undone!');
        $this->line('');
        
        // Confirm before proceeding
        $this->line('Are you sure you want to drop all tables? (y/n): ');
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);
        
        if (trim(strtolower($line)) !== 'y') {
            $this->info('Migration fresh cancelled.');
            return;
        }

        $this->line('');
        $this->info('ðŸ”„ Fresh migrating all tables...');
        $this->line('');

        // Load environment and required files
        $this->loadRequiredFiles();

        try {
            // Get all tables and drop them (except migrations table)
            $this->dropAllTables();
            $this->success('  âœ“ All tables dropped');
            
            // Clear migrations table
            $this->clearMigrationsTable();
            $this->success('  âœ“ Migrations table cleared');
            
            // Re-run all migrations
            $this->line('');
            $this->runAllMigrations();
            
        } catch (\Exception $e) {
            $this->error('âŒ Error: ' . $e->getMessage());
        }
        
        $this->line('');
    }

    /**
     * Migrate Fresh Model command - Drop and re-create a specific model's table
     */
    private function migrateFreshModelCommand($modelName = null)
    {
        if (!$modelName) {
            $this->error('Model name is required!');
            $this->line('Usage: php pype.php migrate:fresh:model <ModelName>');
            $this->line('Example: php pype.php migrate:fresh:model Admin');
            $this->line('         php pype.php migrate:fresh:model all');
            return;
        }

        $this->line('');
        $this->info("ðŸ”„ Fresh migrating model: {$modelName}...");
        $this->line('');

        // Load environment and required files
        $this->loadRequiredFiles();

        try {
            if (strtolower($modelName) === 'all') {
                // Fresh migrate all models
                $this->dropAllTables();
                $this->clearMigrationsTable();
                $this->line('');
                $this->runAllMigrations();
            } else {
                // Find the model and get its table name
                $modelsDir = $this->projectRoot . '/App/Models';
                $modelPath = $modelsDir . '/' . $modelName . '.php';
                
                if (!file_exists($modelPath)) {
                    $this->error("Model '{$modelName}' not found!");
                    return;
                }

                // Get table name from model
                $modelContent = file_get_contents($modelPath);
                $tableName = null;
                
                if (preg_match('/protected\s+static\s+\$table\s*=\s*[\'"]([^\'"]+)[\'"]/', $modelContent, $matches)) {
                    $tableName = $matches[1];
                } else {
                    $tableName = strtolower($modelName) . 's';
                }

                // Find and run the migration for this model
                $migrationDir = $this->projectRoot . '/migrations';
                $files = glob($migrationDir . '/*.php');
                
                $foundMigration = false;
                foreach ($files as $file) {
                    $content = file_get_contents($file);
                    if (strpos($content, "'{$tableName}'") !== false || strpos($content, "\"{$tableName}\"") !== false) {
                        $fileName = basename($file);
                        
                        // Check if already migrated
                        $migratedFiles = $this->getMigratedFiles();
                        if (in_array($fileName, $migratedFiles)) {
                            // Rollback this migration first
                            $this->line("  Rolling back: {$fileName}");
                            include_once $file;
                            $className = $this->getClassFromFile($file);
                            if ($className && class_exists($className)) {
                                $migration = new $className();
                                $migration->down();
                                $this->removeMigration($fileName);
                                $this->success("    âœ“ Rolled back: {$fileName}");
                            }
                        }
                        
                        // Run the migration again
                        $this->line("  Running: {$fileName}");
                        include_once $file;
                        $className = $this->getClassFromFile($file);
                        if ($className && class_exists($className)) {
                            $migration = new $className();
                            $migration->up();
                            $this->recordMigration($fileName);
                            $this->success("    âœ“ Migrated: {$fileName}");
                        }
                        
                        $foundMigration = true;
                        break;
                    }
                }
                
                if (!$foundMigration) {
                    $this->warning("âš  No migration found for model '{$modelName}'");
                    $this->line('Creating new migration...');
                    $this->createMigration(strtolower($modelName));
                }
            }
            
        } catch (\Exception $e) {
            $this->error('âŒ Error: ' . $e->getMessage());
        }
        
        $this->line('');
        $this->success('âœ… Model migration completed!');
        $this->line('');
    }

    /**
     * Migrate Status command - Show the status of all migrations
     */
    private function migrateStatusCommand()
    {
        $this->line('');
        $this->info('ðŸ“Š Migration Status');
        $this->line('');

        // Load environment and required files
        $this->loadRequiredFiles();

        $migrationDir = $this->projectRoot . '/migrations';
        if (!is_dir($migrationDir)) {
            $this->warning('âš  No migrations folder found');
            return;
        }

        // Get all migration files
        $files = glob($migrationDir . '/*.php');
        sort($files);

        if (empty($files)) {
            $this->warning('âš  No migration files found');
            return;
        }

        // Get migrated files
        $migratedFiles = $this->getMigratedFiles();

        $this->line('  ' . str_repeat('-', 80));
        $this->line('  | ' . str_pad('Status', 10) . ' | ' . str_pad('Migration', 50) . ' | Batch |');
        $this->line('  ' . str_repeat('-', 80));

        $total = 0;
        $migrated = 0;
        $pending = 0;

        foreach ($files as $file) {
            $fileName = basename($file);
            $total++;

            if (in_array($fileName, $migratedFiles)) {
                // Get batch number
                $batch = $this->getMigrationBatch($fileName);
                $this->line('  | ' . $this->colorize('âœ“ Ran', 'green') . '    | ' . str_pad(substr($fileName, 0, 50), 50) . ' | ' . str_pad($batch, 5) . ' |');
                $migrated++;
            } else {
                $this->line('  | ' . $this->colorize('Pending', 'yellow') . ' | ' . str_pad(substr($fileName, 0, 50), 50) . ' | ' . str_pad('-', 5) . ' |');
                $pending++;
            }
        }

        $this->line('  ' . str_repeat('-', 80));
        $this->line('');
        $this->line("  Total: {$total} | Migrated: {$migrated} | Pending: {$pending}");
        $this->line('');
    }

    /**
     * Get batch number for a specific migration
     */
    private function getMigrationBatch($fileName)
    {
        require_once $this->projectRoot . '/Framework/Database/Connect.php';
        require_once $this->projectRoot . '/Framework/Database/DatabaseFactory.php';
        require_once $this->projectRoot . '/Framework/Database/DatabaseInterface.php';
        require_once $this->projectRoot . '/Framework/Database/DatabaseQuery.php';

        $connect = new \Framework\Database\Connect();

        try {
            if ($connect->connection instanceof \mysqli) {
                $stmt = $connect->connection->prepare("SELECT batch FROM migrations WHERE migration = ?");
                $stmt->bind_param("s", $fileName);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    return $row['batch'];
                }
            } elseif ($connect->connection instanceof \PDO) {
                $stmt = $connect->connection->prepare("SELECT batch FROM migrations WHERE migration = ?");
                $stmt->execute([$fileName]);
                if ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    return $row['batch'];
                }
            }
        } catch (\Exception $e) {
            return '-';
        }

        return '-';
    }

    /**
     * Load required files for migration operations
     */
    private function loadRequiredFiles()
    {
        if (file_exists($this->projectRoot . '/vendor/autoload.php')) {
            require_once $this->projectRoot . '/vendor/autoload.php';

            if (class_exists('Dotenv\Dotenv')) {
                try {
                    $dotenv = \Dotenv\Dotenv::createImmutable($this->projectRoot);
                    $dotenv->load();
                } catch (\Exception $e) {
                    // Continue without dotenv if it fails
                }
            }
        }

        require_once $this->projectRoot . '/Framework/Database/DatabaseInterface.php';
        require_once $this->projectRoot . '/Framework/Database/MySQLConnection.php';
        require_once $this->projectRoot . '/Framework/Database/PostgreSQLConnection.php';
        require_once $this->projectRoot . '/Framework/Database/SQLiteConnection.php';
        require_once $this->projectRoot . '/Framework/Database/DatabaseFactory.php';
        require_once $this->projectRoot . '/Framework/Database/Connect.php';
        require_once $this->projectRoot . '/Framework/Database/DatabaseQuery.php';
        require_once $this->projectRoot . '/Framework/Database/Migration.php';
        require_once $this->projectRoot . '/Framework/Model/Model.php';
        require_once $this->projectRoot . '/Framework/Helper/DB.php';
    }

    /**
     * Drop all tables except migrations table
     */
    private function dropAllTables()
    {
        $dbType = $_ENV['DB_TYPE'] ?? $_SERVER['DB_TYPE'] ?? 'mysql';
        $this->line("  Database type: {$dbType}");

        if ($dbType === 'sqlite') {
            // SQLite: Get all tables
            $result = \Framework\Helper\DB::rawQuery("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' AND name != 'migrations'");
            $tables = $result->fetchAll(\PDO::FETCH_COLUMN);
            $this->line("  Found " . count($tables) . " table(s) to drop");
            foreach ($tables as $table) {
                \Framework\Helper\DB::rawQuery("DROP TABLE IF EXISTS {$table}");
                $this->success("    âœ“ Dropped table: {$table}");
            }
        } else {
            // MySQL/PostgreSQL: Get all tables
            $result = \Framework\Helper\DB::rawQuery("SHOW TABLES");
            $tables = $result->fetchAll(\PDO::FETCH_COLUMN);
            $this->line("  Found " . count($tables) . " table(s) to drop");
            foreach ($tables as $table) {
                if ($table !== 'migrations') {
                    \Framework\Helper\DB::rawQuery("DROP TABLE IF EXISTS {$table}");
                    $this->success("    âœ“ Dropped table: {$table}");
                }
            }
        }
    }

    /**
     * Clear migrations table
     */
    private function clearMigrationsTable()
    {
        $this->line("  Clearing migrations table...");
        \Framework\Helper\DB::rawQuery("DELETE FROM migrations");
        $this->success("    âœ“ Migrations table cleared");
    }

    /**
     * Run all migrations from scratch
     */
    private function runAllMigrations()
    {
        $this->line('  Setting up migrations table...');
        $this->ensureMigrationsTable();

        $migrationDir = $this->projectRoot . '/migrations';
        if (!is_dir($migrationDir)) {
            $this->warning('âš  No migrations folder found');
            return;
        }

        $files = glob($migrationDir . '/*.php');
        $this->line('  Found ' . count($files) . ' migration file(s)');
        
        if (empty($files)) {
            $this->warning('âš  No migration files found');
            return;
        }
        
        sort($files);

        $ran = 0;
        foreach ($files as $file) {
            $fileName = basename($file);
            try {
                $this->line("  Running: {$fileName}");
                include_once $file;

                $className = $this->getClassFromFile($file);

                if ($className && class_exists($className)) {
                    $migration = new $className();
                    $migration->up();

                    $this->recordMigration($fileName);
                    $this->success("    âœ“ Migrated: {$fileName}");
                    $ran++;
                } else {
                    $this->error("    âœ— Class '{$className}' not found");
                }
            } catch (\Exception $e) {
                $this->error("    âœ— Error: " . $e->getMessage());
            }
        }

        if ($ran === 0) {
            $this->info('âœ“ Nothing to migrate');
        } else {
            $this->line('');
            $this->success("âœ… {$ran} migration(s) executed successfully!");
        }
    }

    /**
     * Make Seeder command - Create a new seeder file
     */
    private function makeSeederCommand($seederName = null)
    {
        if (!$seederName) {
            $this->error('Seeder name is required!');
            $this->line('Usage: php pype.php make:seeder <SeederName>');
            $this->line('Example: php pype.php make:seeder UserSeeder');
            $this->line('         php pype.php make:seeder DatabaseSeeder');
            return;
        }

        $seederDir = $this->projectRoot . '/Database/Seeders';
        if (!is_dir($seederDir)) {
            mkdir($seederDir, 0755, true);
        }

        // Remove "Seeder" suffix if present, then add it back
        $className = rtrim($seederName, 'Seeder') . 'Seeder';
        $fileName = $className . '.php';
        $filePath = $seederDir . '/' . $fileName;

        // Check if file already exists
        if (file_exists($filePath)) {
            $this->error("Seeder '{$className}' already exists!");
            return;
        }

        $template = $this->getSeederTemplate($className);
        file_put_contents($filePath, $template);

        $this->line('');
        $this->success("âœ“ Seeder created successfully!");
        $this->line("Path: Database/Seeders/{$fileName}");
        $this->line('');
        $this->line('Next steps:');
        $this->line('  1. Edit the seeder file to define your sample data');
        $this->line('  2. Run: php pype.php db:seed');
        $this->line('');
    }

    /**
     * Get Seeder template
     */
    private function getSeederTemplate($className)
    {
        return <<<EOT
<?php

namespace Database\Seeders;

use Framework\Database\Seeder;

class {$className} extends Seeder
{
    /**
     * Run the database seeds
     */
    public function run()
    {
        // Example: Insert sample data
        // \$this->insert('table_name', [
        //     ['column1' => 'value1', 'column2' => 'value2'],
        //     ['column1' => 'value3', 'column2' => 'value4'],
        // ]);

        // Example: Using factory for multiple records
        // \$this->factory('table_name', 10, function(\$index) {
        //     return [
        //         'name' => 'User ' . \$index,
        //         'email' => 'user' . \$index . '@example.com',
        //     ];
        // });
    }
}
EOT;
    }

    /**
     * DB Seed command - Run database seeders
     */
    private function dbSeedCommand($seederName = null)
    {
        $this->line('');
        $this->info('ðŸŒ± Seeding database...');
        $this->line('');

        // Load environment and required files
        $this->loadRequiredFiles();
        require_once $this->projectRoot . '/Framework/Database/Seeder.php';
        require_once $this->projectRoot . '/Framework/Helper/DB.php';

        $seederDir = $this->projectRoot . '/Database/Seeders';

        if (!is_dir($seederDir)) {
            $this->warning('âš  No seeders directory found');
            $this->line('Create a seeder first: php pype.php make:seeder UserSeeder');
            return;
        }

        try {
            if ($seederName) {
                // Run specific seeder
                $seederFile = $seederDir . '/' . $seederName . '.php';
                if (!file_exists($seederFile)) {
                    // Try with "Seeder" suffix
                    $seederFile = $seederDir . '/' . rtrim($seederName, 'Seeder') . 'Seeder.php';
                }

                if (file_exists($seederFile)) {
                    $this->line("  Running: " . basename($seederFile));
                    require_once $seederFile;
                    
                    // Get namespace from file
                    $namespace = $this->getNamespaceFromFile($seederFile);
                    $className = $this->getClassFromFile($seederFile);
                    $fullClassName = $namespace ? "\\{$namespace}\\{$className}" : "\\{$className}";
                    
                    $this->line("  Namespace: {$namespace}");
                    $this->line("  Class: {$className}");
                    $this->line("  Full class: {$fullClassName}");
                    
                    if ($className && class_exists($fullClassName)) {
                        $this->line("  Instantiating {$fullClassName}...");
                        $seeder = new $fullClassName();
                        $this->line("  Calling run() method...");
                        $seeder->run();
                        $this->success("    âœ“ Seeded: {$className}");
                    } else {
                        $this->error("  Class '{$fullClassName}' not found!");
                        $this->line("  Available classes: " . implode(', ', get_declared_classes()));
                    }
                } else {
                    $this->error("Seeder '{$seederName}' not found!");
                }
            } else {
                // Run all seeders
                $files = glob($seederDir . '/*.php');
                sort($files);

                if (empty($files)) {
                    $this->warning('âš  No seeders found');
                    $this->line('Create a seeder first: php pype.php make:seeder UserSeeder');
                    return;
                }

                $this->line("  Found " . count($files) . " seeder file(s)");

                foreach ($files as $file) {
                    $fileName = basename($file);
                    $this->line("  Running: {$fileName}");
                    require_once $file;

                    // Get namespace from file
                    $namespace = $this->getNamespaceFromFile($file);
                    $className = $this->getClassFromFile($file);
                    $fullClassName = $namespace ? "\\{$namespace}\\{$className}" : "\\{$className}";
                    
                    $this->line("    Namespace: {$namespace}");
                    $this->line("    Class: {$className}");
                    
                    if ($className && class_exists($fullClassName)) {
                        $seeder = new $fullClassName();
                        $seeder->run();
                        $this->success("      âœ“ Seeded: {$className}");
                    } else {
                        $this->error("    âœ— Class '{$fullClassName}' not found!");
                    }
                }
            }

            $this->line('');
            $this->success('âœ… Database seeded successfully!');

        } catch (\Exception $e) {
            $this->error('âŒ Error: ' . $e->getMessage());
            $this->line('Stack trace: ' . $e->getTraceAsString());
        }

        $this->line('');
    }

    /**
     * Extract namespace from PHP file
     */
    private function getNamespaceFromFile($file)
    {
        $contents = file_get_contents($file);
        
        if (preg_match('/namespace\s+([^;]+);/', $contents, $matches)) {
            return trim($matches[1]);
        }
        
        return null;
    }

    /**
     * Serve command - Start local development server
     */
    private function serveCommand($port = '8000')
    {
        $this->line('');
        $this->success('ðŸš€ Starting Pype Development Server');
        $this->line('');
        $this->line("Local:   <fg=cyan>http://localhost:{$port}</>");
        $this->line('');
        $this->info('Press Ctrl+C to stop the server');
        $this->line('');

        $publicDir = $this->projectRoot . '/';

        // If no public directory, use root
        if (!is_dir($publicDir)) {
            $publicDir = $this->projectRoot;
        }

        // Build the command
        $command = "php -S localhost:{$port} -t " . escapeshellarg($publicDir);
        passthru($command);
    }

    /**
     * Help command
     */
    private function helpCommand()
    {
        $this->showHelp();
    }

    /**
     * Display help information
     */
    private function showHelp()
    {
        $this->line('');
        $this->line('<fg=cyan>Pype Framework CLI - Version 1.0.0</>');
        $this->line('');
        $this->line('Usage:');
        $this->line('  php pype.php <command> [arguments]');
        $this->line('');
        $this->line('Available Commands:');
        $this->line('');

        foreach ($this->commands as $name => $details) {
            $coloredName = $this->parseColors(sprintf("<fg=green>%-15s</>", $name));
            echo "  {$coloredName} {$details['description']}" . PHP_EOL;
        }

        $this->line('');
    }

    /**
     * Generic file creation method
     */
    private function createFile($type, $name, $template)
    {
        // Parse the name to handle nested directories (e.g., admin.user -> admin/user)
        $parts = explode('.', $name);
        $fileName = array_pop($parts);
        $subdirs = implode(DIRECTORY_SEPARATOR, $parts);
        $subdirsPath = implode('\\', $parts); // For namespace

        $baseDir = '';
        $fileExtension = '';
        $displayName = '';
        $baseNamespace = '';

        switch ($type) {
            case 'view':
                $baseDir = $this->projectRoot . '/Resources/views';
                $fileExtension = '.twig';
                $displayName = 'View';
                break;
            case 'controller':
                $baseDir = $this->projectRoot . '/App/Controller';
                $fileExtension = '.php';
                $displayName = 'Controller';
                $baseNamespace = 'App\\Controller';
                // Ensure filename ends with Controller
                if (!str_ends_with($fileName, 'Controller')) {
                    $fileName .= 'Controller';
                }
                break;
            case 'model':
                $baseDir = $this->projectRoot . '/App/Models';
                $fileExtension = '.php';
                $displayName = 'Model';
                $baseNamespace = 'App\\Models';
                break;
            case 'middleware':
                $baseDir = $this->projectRoot . '/App/Middleware';
                $fileExtension = '.php';
                $displayName = 'Middleware';
                $baseNamespace = 'App\\Middleware';
                break;
        }

        // Build the namespace
        $namespace = $baseNamespace;
        if ($subdirsPath) {
            $namespace .= '\\' . $subdirsPath;
        }

        // Create the full path
        if ($subdirs) {
            $fullPath = $baseDir . DIRECTORY_SEPARATOR . $subdirs;
            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0755, true);
            }
            $fullPath .= DIRECTORY_SEPARATOR . $fileName . $fileExtension;
            $relativePath = $type . 's' . DIRECTORY_SEPARATOR . str_replace(DIRECTORY_SEPARATOR, '/', $subdirs . DIRECTORY_SEPARATOR . $fileName);
        } else {
            $fullPath = $baseDir . DIRECTORY_SEPARATOR . $fileName . $fileExtension;
            $relativePath = $type . 's' . DIRECTORY_SEPARATOR . $fileName;
        }

        // Check if file already exists
        if (file_exists($fullPath)) {
            $this->error("{$displayName} already exists!");
            $this->line("Path: {$relativePath}{$fileExtension}");
            return;
        }

        // Create the file with proper namespace
        if (is_callable($template)) {
            $fileContent = $template($namespace, $fileName);
        } else {
            $fileContent = $template;
        }
        file_put_contents($fullPath, $fileContent);

        $this->line('');
        $this->success("âœ“ {$displayName} created successfully!");
        $this->line("Path: {$relativePath}{$fileExtension}");
        $this->line('');
    }

    /**
     * Get Twig view template
     */
    private function getViewTemplate()
    {
        return <<<'EOT'
{# Twig View Template #}

<div>
    {# Your content here #}
</div>
EOT;
    }

    /**
     * Get Controller template
     */
    private function getControllerTemplate($name)
    {
        return function ($namespace, $fileName) {
            if (!str_ends_with($fileName, 'Controller')) {
                $className = $fileName . 'Controller';
            } else {
                $className = $fileName;
            }

            return <<<EOT
<?php

namespace {$namespace};

class {$className}
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // Initialize your controller
    }

    /**
     * Example method
     */
    public function index()
    {
        // Your code here
    }
}
EOT;
        };
    }

    /**
     * Get Model template
     */
    private function getModelTemplate($name)
    {
        return function ($namespace, $fileName) {
            $className = $fileName;
            $tableName = strtolower($className) . 's';

            return <<<EOT
<?php

namespace {$namespace};

use Framework\Model\Model;

class {$className} extends Model
{
    protected static \$table = '{$tableName}';
    protected static \$primaryKey = 'id';

    public static function schema(\$table)
    {
        \$table->id();
        \$table->timestamps();
    }
}
EOT;
        };
    }

    /**
     * Get Middleware template
     */
    private function getMiddlewareTemplate($name)
    {
        return function ($namespace, $fileName) {
            $className = $fileName;

            return <<<EOT
<?php

namespace {$namespace};

class {$className}
{
    /**
     * Handle the request
     */
    public function handle()
    {
        // Your middleware logic here
    }
}
EOT;
        };
    }

    /**
     * Recursively remove a directory
     */
    private function removeDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        // Use system command for Windows to handle locked files
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $dir = str_replace('/', '\\', $dir);
            shell_exec("rmdir /s /q \"{$dir}\" 2>nul");
        } else {
            // Unix-like systems
            $items = array_diff(scandir($dir), ['.', '..']);

            foreach ($items as $item) {
                $path = $dir . '/' . $item;
                if (is_dir($path)) {
                    $this->removeDirectory($path);
                } else {
                    @unlink($path);
                }
            }

            @rmdir($dir);
        }
    }

    /**
     * Print a line with formatting
     */
    private function line($message = '')
    {
        $message = $this->parseColors($message);
        echo $message . PHP_EOL;
    }

    /**
     * Print success message (green)
     */
    private function success($message)
    {
        echo "\033[32m" . $message . "\033[0m" . PHP_EOL;
    }

    /**
     * Print info message (cyan)
     */
    private function info($message)
    {
        echo "\033[36m" . $message . "\033[0m" . PHP_EOL;
    }

    /**
     * Print warning message (yellow)
     */
    private function warning($message)
    {
        echo "\033[33m" . $message . "\033[0m" . PHP_EOL;
    }

    /**
     * Print error message (red)
     */
    private function error($message)
    {
        echo "\033[31m" . $message . "\033[0m" . PHP_EOL;
    }

    /**
     * Parse color tags in messages
     */
    private function parseColors($message)
    {
        $patterns = [
            '/<fg=cyan>(.*?)<\/>/' => "\033[36m$1\033[0m",
            '/<fg=green>(.*?)<\/>/' => "\033[32m$1\033[0m",
            '/<fg=red>(.*?)<\/>/' => "\033[31m$1\033[0m",
            '/<fg=yellow>(.*?)<\/>/' => "\033[33m$1\033[0m",
        ];

        foreach ($patterns as $pattern => $replacement) {
            $message = preg_replace($pattern, $replacement, $message);
        }

        return $message;
    }

    /**
     * Create a new migration file
     */
    private function createMigration($migrationName)
    {
        $migrationDir = $this->projectRoot . '/migrations';
        if (!is_dir($migrationDir)) {
            mkdir($migrationDir, 0755, true);
        }

        $timestamp = date('Y_m_d_His');
        $fileName = "{$timestamp}_{$migrationName}.php";
        $filePath = $migrationDir . '/' . $fileName;

        $className = $this->camelCase($migrationName);
        $template = $this->getMigrationTemplate($className, $migrationName);

        file_put_contents($filePath, $template);

        $this->line('');
        $this->success("âœ“ Migration created successfully!");
        $this->line("Path: migrations/{$fileName}");
        $this->line('');
        $this->line('Next steps:');
        $this->line('  1. Edit the migration file to define your schema');
        $this->line('  2. Run: php pype.php migrate');
        $this->line('');
    }

    /**
     * Get the migration template
     */
    private function getMigrationTemplate($className, $migrationName)
    {
        $this->line("  getMigrationTemplate called with: className={$className}, migrationName={$migrationName}");
        
        // Check if this is a create_*_table migration
        $isCreateTable = strpos($migrationName, 'create_') === 0 && strpos($migrationName, '_table') !== false;
        $this->line("  isCreateTable: " . ($isCreateTable ? 'true' : 'false'));

        // Also check if the migration name matches a model name directly
        $directModelMatch = null;
        $modelsDir = $this->projectRoot . '/App/Models';

        if (is_dir($modelsDir)) {
            $files = scandir($modelsDir);
            $this->line("  Scanning models directory, found " . count($files) . " files");
            
            foreach ($files as $file) {
                if (substr($file, -4) === '.php') {
                    $modelName = substr($file, 0, -4);
                    // Check if migration name matches model name exactly
                    if (strtolower($migrationName) === strtolower($modelName)) {
                        $directModelMatch = $modelName;
                        $this->line("  âœ“ Direct model match found: {$modelName}");
                        break;
                    }
                }
            }
        }

        $foundModel = null;
        $tableName = null;

        // If it's a create_*_table pattern, try to match to model
        if ($isCreateTable) {
            // Extract table name from migration name (e.g., create_users_table -> users)
            $tableName = str_replace(['create_', '_table'], '', $migrationName);
            $this->line("    Looking for model with table: {$tableName}");

            if (is_dir($modelsDir)) {
                $files = scandir($modelsDir);
                foreach ($files as $file) {
                    if (substr($file, -4) === '.php') {
                        $modelName = substr($file, 0, -4);
                        // Check if model table name matches (use modelToTable for proper pluralization)
                        $modelTableName = $this->modelToTable($modelName);
                        $this->line("    Checking {$modelName} -> table: {$modelTableName}");
                        if ($modelTableName === $tableName) {
                            $foundModel = $modelName;
                            $this->line("    âœ“ Found matching model: {$foundModel}");
                            break;
                        }
                    }
                }
            }
        }
        // If it's a direct model name match
        elseif ($directModelMatch) {
            $this->line("    Direct model match: {$directModelMatch}");
            $foundModel = $directModelMatch;

            // Get the actual table name from the model - ALWAYS use explicit $table property
            $modelPath = $modelsDir . '/' . $directModelMatch . '.php';
            if (file_exists($modelPath)) {
                $modelContent = file_get_contents($modelPath);

                // Extract table name from the model file - MUST find explicit $table property
                if (preg_match('/protected\s+static\s+\$table\s*=\s*[\'"]([^\'"]+)[\'"]/', $modelContent, $matches)) {
                    $tableName = $matches[1];
                    $this->line("    âœ“ Using explicit table name: {$tableName}");
                } else {
                    // Fallback: Use the pluralize function for proper pluralization
                    $tableName = $this->modelToTable($directModelMatch);
                    $this->line("    âš  No explicit table name found, generated: {$tableName}");
                }
            }
        }

        if ($foundModel && $tableName) {
            // Read the model file to extract the schema method content
            $modelPath = $modelsDir . '/' . $foundModel . '.php';
            $schemaMethodContent = "// Unable to extract schema - using default\n            \$table->id();\n            \$table->timestamps();";

            if (file_exists($modelPath)) {
                $modelCode = file_get_contents($modelPath);
                $this->line("    Reading model: {$modelPath}");
                $this->line("    Model code length: " . strlen($modelCode) . " bytes");

                // Extract the schema method content - simple line by line parsing
                $lines = file($modelPath, FILE_IGNORE_NEW_LINES);
                $inSchema = false;
                $schemaLines = [];
                $braceCount = 0;
                
                $this->line("    Parsing " . count($lines) . " lines...");
                
                foreach ($lines as $lineNum => $line) {
                    // Debug: show schema-related lines
                    if (strpos($line, 'schema') !== false || strpos($line, '$table->') !== false) {
                        $this->line("    Line " . ($lineNum + 1) . ": " . trim($line));
                    }
                    
                    // Check if we're entering the schema method
                    if (!$inSchema && (strpos($line, 'function schema($table)') !== false || 
                        strpos($line, 'function schema( $table )') !== false ||
                        preg_match('/function\s+schema\s*\(\s*\$table\s*\)/', $line))) {
                        $inSchema = true;
                        $braceCount = 0;
                        $this->line("    â†’ Found schema method start");
                        continue;
                    }
                    
                    // If we're in the schema method
                    if ($inSchema) {
                        // Count braces to track nested blocks
                        $braceCount += substr_count($line, '{');
                        $braceCount -= substr_count($line, '}');
                        
                        $trimmedLine = trim($line);
                        
                        // Check if we've exited the schema method (all braces closed)
                        if ($braceCount <= 0 && strpos($trimmedLine, '}') !== false) {
                            $this->line("    â†’ Found schema method end (braceCount: {$braceCount})");
                            break;
                        }
                        
                        // Only keep lines with table builder calls
                        if (strpos($trimmedLine, '$table->') === 0) {
                            // Remove any trailing comments
                            $trimmedLine = preg_replace('/\s*\/\/.*$/', '', $trimmedLine);
                            $schemaLines[] = '            ' . $trimmedLine;
                            $this->line("    â†’ Added: {$trimmedLine}");
                        }
                    }
                }
                
                $this->line("    Found " . count($schemaLines) . " schema lines");
                
                if (!empty($schemaLines)) {
                    $schemaMethodContent = implode("\n", $schemaLines);
                } else {
                    $this->line("    âš  No schema lines found, using default");
                }
            } else {
                $this->line("    âš  Model file not found: {$modelPath}");
            }

            $template = <<<EOT
<?php

use Framework\Database\Migration;

class {$className} extends Migration
{
    /**
     * Run the migrations
     * Using schema from the {$foundModel} model
     */
    public function up()
    {
        // Create {$tableName} table based on model schema
        \$this->createTable('{$tableName}', function(\$table) {
{$schemaMethodContent}
        });
    }

    /**
     * Revert the migrations
     */
    public function down()
    {
        // Drop the table
        \$this->dropTable('{$tableName}');
    }
}
EOT;

            return $template;
        }

        // Default template for other migrations
        $template = <<<'EOT'
<?php

use Framework\Database\Migration;

class {ClassName} extends Migration
{
    /**
     * Run the migrations
     */
    public function up()
    {
        // Create table
        $this->createTable('example_table', function($table) {
            $table->id();
            $table->string('name', 255);
            $table->string('email', 255)->unique();
            $table->text('description')->nullable();
            $table->integer('status')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Available column methods:
        // $table->id()                                    - Auto-incrementing ID
        // $table->string('name', 255)                   - VARCHAR(255)
        // $table->integer('count')                      - INT
        // $table->text('bio')                           - TEXT
        // $table->boolean('active')                     - BOOLEAN
        // $table->double('price', 10, 2)                - DECIMAL(10,2)
        // $table->date('birth_date')                    - DATE
        // $table->datetime('published_at')              - DATETIME
        // $table->timestamp('created_at')               - TIMESTAMP
        // $table->json('metadata')                      - JSON
        //
        // Available modifiers:
        // ->nullable()                                  - Allow NULL values
        // ->default($value)                             - Set default value
        // ->unique()                                    - Add UNIQUE constraint
        // ->timestamps()                                - Add created_at, updated_at
        // ->softDeletes()                               - Add deleted_at (soft deletes)
    }

    /**
     * Revert the migrations
     */
    public function down()
    {
        // Drop the table
        $this->dropTable('example_table');
    }
}
EOT;

        return str_replace('{ClassName}', $className, $template);
    }

    /**
     * Convert string to CamelCase
     */
    private function camelCase($string)
    {
        $words = explode('_', $string);
        $camelCase = '';

        foreach ($words as $word) {
            $camelCase .= ucfirst($word);
        }

        return $camelCase;
    }

    /**
     * Ensure migrations table exists
     */
    private function ensureMigrationsTable()
    {
        // Load all required database classes in correct dependency order
        require_once $this->projectRoot . '/Framework/Database/DatabaseInterface.php';
        require_once $this->projectRoot . '/Framework/Database/MySQLConnection.php';
        require_once $this->projectRoot . '/Framework/Database/PostgreSQLConnection.php';
        require_once $this->projectRoot . '/Framework/Database/SQLiteConnection.php';
        require_once $this->projectRoot . '/Framework/Database/DatabaseFactory.php';
        require_once $this->projectRoot . '/Framework/Database/Connect.php';
        require_once $this->projectRoot . '/Framework/Database/DatabaseQuery.php';

        $connect = new \Framework\Database\Connect();
        try {
            $result = $connect->connection->query("SELECT 1 FROM migrations LIMIT 1");
        } catch (\Exception $e) {
            // Table doesn't exist, create it
            // Determine database type to use appropriate SQL syntax
            if ($connect->connection instanceof \mysqli) {
                // MySQL with mysqli
                $createTableSQL = "CREATE TABLE IF NOT EXISTS migrations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL UNIQUE,
                    batch INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";
            } elseif ($connect->connection instanceof \PDO) {
                // Check if it's a MySQL PDO connection by inspecting driver
                $driver = $connect->connection->getAttribute(\PDO::ATTR_DRIVER_NAME);
                if ($driver === 'mysql') {
                    // MySQL with PDO
                    $createTableSQL = "CREATE TABLE IF NOT EXISTS migrations (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        migration VARCHAR(255) NOT NULL UNIQUE,
                        batch INT NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )";
                } else {
                    // Likely SQLite with PDO
                    $createTableSQL = "CREATE TABLE IF NOT EXISTS migrations (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        migration TEXT NOT NULL UNIQUE,
                        batch INTEGER NOT NULL,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    )";
                }
            } else {
                // Fallback to SQLite syntax
                $createTableSQL = "CREATE TABLE IF NOT EXISTS migrations (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    migration TEXT NOT NULL UNIQUE,
                    batch INTEGER NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )";
            }

            $connect->connection->query($createTableSQL);
        }
    }

    /**
     * Get list of migrated files
     */
    private function getMigratedFiles()
    {
        try {
            require_once $this->projectRoot . '/Framework/Database/Connect.php';
            require_once $this->projectRoot . '/Framework/Database/DatabaseFactory.php';
            require_once $this->projectRoot . '/Framework/Database/DatabaseInterface.php';
            require_once $this->projectRoot . '/Framework/Database/DatabaseQuery.php';

            $connect = new \Framework\Database\Connect();

            // Check if migrations table exists first
            $tableExists = false;
            if ($connect->connection instanceof \mysqli) {
                $result = $connect->connection->query("SHOW TABLES LIKE 'migrations'");
                $tableExists = $result && $result->num_rows > 0;
            } elseif ($connect->connection instanceof \PDO) {
                try {
                    $connect->connection->query("SELECT 1 FROM migrations LIMIT 1");
                    $tableExists = true;
                } catch (\Exception $e) {
                    $tableExists = false;
                }
            } elseif ($connect->connection instanceof \SQLite3) {
                $result = $connect->connection->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='migrations'", true);
                $tableExists = !empty($result);
            }

            if (!$tableExists) {
                return [];
            }

            if ($connect->connection instanceof \mysqli) {
                $result = $connect->connection->query("SELECT migration FROM migrations");
                $migrations = [];
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $migrations[] = $row['migration'];
                    }
                }
                return $migrations;
            } elseif ($connect->connection instanceof \SQLite3) {
                $result = $connect->connection->query("SELECT migration FROM migrations");
                $migrations = [];
                if ($result) {
                    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                        $migrations[] = $row['migration'];
                    }
                }
                return $migrations;
            } elseif ($connect->connection instanceof \PDO) {
                $stmt = $connect->connection->query("SELECT migration FROM migrations");
                $migrations = [];
                if ($stmt) {
                    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                        $migrations[] = $row['migration'];
                    }
                }
                return $migrations;
            }
        } catch (\Exception $e) {
            return [];
        }

        return [];
    }

    /**
     * Record a migration as run
     */
    private function recordMigration($fileName)
    {
        require_once $this->projectRoot . '/Framework/Database/Connect.php';
        require_once $this->projectRoot . '/Framework/Database/DatabaseFactory.php';
        require_once $this->projectRoot . '/Framework/Database/DatabaseInterface.php';
        require_once $this->projectRoot . '/Framework/Database/DatabaseQuery.php';

        $connect = new \Framework\Database\Connect();

        $batch = $this->getNextBatch();

        if ($connect->connection instanceof \mysqli) {
            $stmt = $connect->connection->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)");
            $stmt->bind_param("si", $fileName, $batch);
            $stmt->execute();
        } elseif ($connect->connection instanceof \SQLite3) {
            $stmt = $connect->connection->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)");
            $stmt->bindValue(1, $fileName, SQLITE3_TEXT);
            $stmt->bindValue(2, $batch, SQLITE3_INTEGER);
            $stmt->execute();
        } elseif ($connect->connection instanceof \PDO) {
            $stmt = $connect->connection->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)");
            $stmt->execute([$fileName, $batch]);
        }
    }

    /**
     * Get the next batch number
     */
    private function getNextBatch()
    {
        require_once $this->projectRoot . '/Framework/Database/Connect.php';
        require_once $this->projectRoot . '/Framework/Database/DatabaseFactory.php';
        require_once $this->projectRoot . '/Framework/Database/DatabaseInterface.php';
        require_once $this->projectRoot . '/Framework/Database/DatabaseQuery.php';

        $connect = new \Framework\Database\Connect();

        try {
            if ($connect->connection instanceof \mysqli) {
                $result = $connect->connection->query("SELECT MAX(batch) as batch FROM migrations");
                $row = $result->fetch_assoc();
                return ($row['batch'] ?? 0) + 1;
            } elseif ($connect->connection instanceof \SQLite3) {
                $result = $connect->connection->querySingle("SELECT MAX(batch) as batch FROM migrations", true);
                return ($result['batch'] ?? 0) + 1;
            } elseif ($connect->connection instanceof \PDO) {
                $stmt = $connect->connection->query("SELECT MAX(batch) as batch FROM migrations");
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                return ($row['batch'] ?? 0) + 1;
            }
        } catch (\Exception $e) {
            return 1;
        }

        return 1;
    }

    /**
     * Get the last migration
     */
    private function getLastMigration()
    {
        require_once $this->projectRoot . '/Framework/Database/Connect.php';
        require_once $this->projectRoot . '/Framework/Database/DatabaseFactory.php';
        require_once $this->projectRoot . '/Framework/Database/DatabaseInterface.php';
        require_once $this->projectRoot . '/Framework/Database/DatabaseQuery.php';

        $connect = new \Framework\Database\Connect();

        try {
            if ($connect->connection instanceof \mysqli) {
                $result = $connect->connection->query("SELECT migration FROM migrations ORDER BY batch DESC, id DESC LIMIT 1");
                $row = $result->fetch_assoc();
                return $row['migration'] ?? null;
            } elseif ($connect->connection instanceof \SQLite3) {
                $result = $connect->connection->querySingle("SELECT migration FROM migrations ORDER BY batch DESC, id DESC LIMIT 1", true);
                return $result['migration'] ?? null;
            }
        } catch (\Exception $e) {
            return null;
        }

        return null;
    }

    /**
     * Remove a migration from the tracking table
     */
    private function removeMigration($fileName)
    {
        require_once $this->projectRoot . '/Framework/Database/Connect.php';
        require_once $this->projectRoot . '/Framework/Database/DatabaseFactory.php';
        require_once $this->projectRoot . '/Framework/Database/DatabaseInterface.php';
        require_once $this->projectRoot . '/Framework/Database/DatabaseQuery.php';

        $connect = new \Framework\Database\Connect();

        if ($connect->connection instanceof \mysqli) {
            $stmt = $connect->connection->prepare("DELETE FROM migrations WHERE migration = ?");
            $stmt->bind_param("s", $fileName);
            $stmt->execute();
        } elseif ($connect->connection instanceof \SQLite3) {
            $stmt = $connect->connection->prepare("DELETE FROM migrations WHERE migration = ?");
            $stmt->bindValue(1, $fileName, SQLITE3_TEXT);
            $stmt->execute();
        } elseif ($connect->connection instanceof \PDO) {
            $stmt = $connect->connection->prepare("DELETE FROM migrations WHERE migration = ?");
            $stmt->execute([$fileName]);
        }
    }

    /**
     * Get class name from migration file
     */
    private function getClassFromFile($file)
    {
        $contents = file_get_contents($file);

        if (preg_match('/class\s+(\w+)\s+extends/', $contents, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Colorize output text
     */
    private function colorize($text, $color)
    {
        $colors = [
            'green' => "\033[32m",
            'yellow' => "\033[33m",
            'red' => "\033[31m",
            'blue' => "\033[34m",
            'cyan' => "\033[36m",
            'reset' => "\033[0m"
        ];

        if (!isset($colors[$color])) {
            $color = 'reset';
        }

        return $colors[$color] . $text . $colors['reset'];
    }
}

// Run the CLI
if (php_sapi_name() === 'cli') {
    $cli = new PypeCLI();
    $cli->run($argv);
} else {
    die('This script can only be run from the command line.');
}

