<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OpenAIService;

class RetailAIChatCommand extends Command
{
    /**
     * Command name
     */
    protected $signature = 'ai:chat';

    /**
     * Command description
     */
    protected $description = 'Retail AI Assistant CLI';

    /**
     * Execute command
     */
    public function handle()
    {
        $this->info('');
        $this->info('===================================');
        $this->info('   Retail AI Assistant Started');
        $this->info('===================================');
        $this->info('');

        $this->comment('Type "exit" to quit.');
        $this->info('');

        $ai = new OpenAIService();

        while (true) {

            /*
            |--------------------------------------------------------------------------
            | Get User Input
            |--------------------------------------------------------------------------
            */
            $userInput = $this->ask('Please type your query');

            /*
            |--------------------------------------------------------------------------
            | Exit Command
            |--------------------------------------------------------------------------
            */
            if (
                strtolower(trim($userInput)) === 'exit'
            ) {

                $this->info('');
                $this->info('Goodbye!');
                $this->info('');

                break;
            }

            /*
            |--------------------------------------------------------------------------
            | Empty Input
            |--------------------------------------------------------------------------
            */
            if (empty(trim($userInput))) {

                $this->error('Please enter a message.');

                continue;
            }

            try {

                /*
                |--------------------------------------------------------------------------
                | AI Response
                |--------------------------------------------------------------------------
                */
                $response = $ai->chat($userInput);

                $this->info('');
                $this->line('AI Assistant:');
                $this->info($response);
                $this->info('');

            } catch (\Exception $e) {

                $this->error('');
                $this->error('Error: ' . $e->getMessage());
                $this->error('');
            }
        }

        return Command::SUCCESS;
    }
}