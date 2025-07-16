<?php

declare(strict_types=1);

namespace Shavonn\GooglePubSub\Console\Commands;

use Illuminate\Console\Command;
use Shavonn\GooglePubSub\Schema\SchemaValidator;

class ValidateSchemaCommand extends Command
{
    protected $signature = 'pubsub:schema:validate 
                            {schema : Schema name}
                            {data? : JSON data to validate (or pipe from stdin)}';

    protected $description = 'Validate JSON data against a configured schema';

    public function handle(): int
    {
        $schemaName = $this->argument('schema');
        $dataInput = $this->argument('data');

        // Get data from argument or stdin
        if (!$dataInput && !posix_isatty(STDIN)) {
            $dataInput = file_get_contents('php://stdin');
        }

        if (!$dataInput) {
            $this->error('No data provided. Pass JSON as argument or pipe from stdin.');
            return Command::FAILURE;
        }

        // Parse JSON data
        $data = json_decode($dataInput);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid JSON: ' . json_last_error_msg());
            return Command::FAILURE;
        }

        // Validate
        $validator = new SchemaValidator(config('pubsub'));

        try {
            $validator->validate($data, $schemaName);
            $this->info("✓ Data is valid against schema '{$schemaName}'");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("✗ Validation failed: {$e->getMessage()}");

            if ($errors = $validator->getErrors($data, $schemaName)) {
                $this->line('');
                $this->line('Errors:');
                $this->line(json_encode($errors, JSON_PRETTY_PRINT));
            }

            return Command::FAILURE;
        }
    }
}
