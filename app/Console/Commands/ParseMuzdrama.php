<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ParseMuzdrama extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'muzdrama';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     * @throws \Exception
     */
    public function handle()
    {
        $response = Http::get('https://muzdrama.ru/');
        if (!$response) throw new \Exception('Error reading page, stopped');
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($response);
        $finder = new \DomXPath($doc);
        $node = $finder->query("//*[contains(concat(' ', normalize-space(@id), ' '), 'afisha')]/div");
        print_r($node);
        return 0;
    }
}
