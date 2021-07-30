<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ParseMuzdrama extends Command
{
    protected static $host = 'https://muzdrama.ru';

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
        self::parseMonth(self::$host . "/?month=7");
        return 0;
    }

    private static function parseMonth($url)
    {
        $response = Http::get($url);
        if (!$response) throw new \Exception('Error reading page, stopped');
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($response);
        $finder = new \DomXPath($doc);
        $nodes = $finder->query("//*[contains(concat(' ', normalize-space(@id), ' '), 'afisha')]/div");
        foreach ($nodes as $node) {
            $item = $finder->query(".//div/a", $node);
            $href = $item->item(0)->attributes->item(0)->value;
            $item = $finder->query(".//div/div[@class='booking__price']/b", $node);
            $price = $item->item(0)->nodeValue;
            self::parsePerformance(self::$host . $href);
        }
    }

    private static function parsePerformance($url)
    {
        $month = ['сентября' => 'september'];
        $imgSrc = null;
        $response = Http::get($url);
        if (!$response) throw new \Exception('Error reading page, stopped');
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($response);
        $finder = new \DomXPath($doc);
        $root = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), 'afisha__item afisha__item_content flex flex-wrap')]");
        $item = $finder->query(".//div[@class='afisha__image-wrapper afisha__image-wrapper_content']/a/img", $root->item(0));
        foreach ($item->item(0)->attributes as $attribute) {
            if ($attribute->name == 'src') {
                $imgSrc = $attribute->value;
            }
        }
        print $imgSrc."\n";
        $liElements = $finder->query(".//div[@class='afisha__info afisha__info_content performance flex flex-column']/ul[@class='performance__dates']/li", $root->item(0));
        $dateTimes = null;
        if ($liElements->length > 0) {
            $dateTimes = [];
            foreach ($liElements as $element) {
                list($date, $startTime) = explode(' в ', trim($element->nodeValue, ';'));
                $dateTimes[] = date("Y-m-d H:i", strtotime(str_replace(array_keys($month), $month, $date)." ".$startTime));
            }
        }
        $element = $finder->query(".//div[@class='afisha__info afisha__info_content performance flex flex-column']/div[@class='performance__duration']/span", $root->item(0));
        $duration = trim(@$element->item(0)->nodeValue, ' ч.');
        $element = $finder->query(".//div[@class='afisha__info afisha__info_content performance flex flex-column']/h1[@class='performance__title']", $root->item(0));
        $title = @$element->item(0)->nodeValue;
        $element = $finder->query(".//div[@class='afisha__info afisha__info_content performance flex flex-column']/h1[@class='performance__title']", $root->item(0));
    }
}
