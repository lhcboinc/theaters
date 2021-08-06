<?php

namespace App\Console\Commands;

use App\Models\Performances;
use App\Models\Theaters;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;

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

    const HOST = 'https://muzdrama.ru';
    const DOMAIN = 'muzdrama.ru';

    private static $theaterId;
    private static $performances;

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
        self::$performances = Performances::where('type', Performances::TYPE_THEATER)->get()->toArray();

        if (!(self::$theaterId = @Theaters::where('domain_name', self::DOMAIN)->first()->id))
            throw new \Exception('Muzdrama not found');

        for ($i=1; $i<=12; $i++)
            self::parseMonth(self::HOST . "/?month=$i");
    }

    private static function parseMonth($url)
    {
        $response = Http::get($url);
        if (!$response)
            throw new \Exception('Error reading page, stopped');
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

            $performanceData = self::parsePerformance(self::HOST . $href);
            $isNew = false;
            if (in_array($performanceData['title'], array_column(self::$performances, 'title'))) {
                print "Edit performance\n";
                $performance = Performances::where('title', $performanceData['title'])->first();
                foreach (json_decode($performance->images) as $item) {
                    unlink(Storage::path("images/{$item}"));
                    unlink(Storage::path("images/thumb_{$item}"));
                }
            } else {
                print "Create performance\n";
                $performance = new Performances;
                $isNew = true;
            }
            $performance->title = $performanceData['title'];
            $performance->description = $performanceData['description'];
            $performance->duration = $performanceData['duration'];
            $performance->age_limit = $performanceData['age_limit'];
            $performance->images = json_encode([self::saveImage($performanceData['image_url'])]);
            $performance->type = Performances::TYPE_THEATER;
            $performance->save();
            $performance->theaters()->sync([self::$theaterId => [
                'seance_dt_list' => $performanceData['seance_dt_list'],
                'price' => $price
            ]]);

            if ($isNew)
                self::$performances[] = $performance;
        }
    }

    private static function parsePerformance($url)
    {
        $month = [
            'января' => 'january',
            'февраля' => 'february',
            'марта' => 'march',
            'апреля' => 'april',
            'майа' => 'may',
            'июня' => 'june',
            'июля' => 'july',
            'августа' => 'august',
            'сентября' => 'september',
            'октября' => 'october',
            'ноября' => 'november',
            'декабря' => 'december',
        ];
        $imgSrc = null;
        $response = Http::get($url);
        if (!$response)
            throw new \Exception('Error reading page, stopped');
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($response);
        $finder = new \DomXPath($doc);
        $root = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), 'afisha__item afisha__item_content flex flex-wrap')]");
        $item = $finder->query(".//div[@class='afisha__image-wrapper afisha__image-wrapper_content']/a/img", $root->item(0));
        foreach ($item->item(0)->attributes as $attribute) {
            if ($attribute->name == 'src') {
                $imgSrc = self::HOST . $attribute->value;
                break;
            }
        }
        $element = $finder->query(".//div[@class='afisha__info afisha__info_content performance flex flex-column']/ul[@class='performance__icons flex flex-wrap']/li/span", $root->item(0));
        $ageLimit = intval($element->item(0)->nodeValue);

        $liElements = $finder->query(".//div[@class='afisha__info afisha__info_content performance flex flex-column']/ul[@class='performance__dates']/li", $root->item(0));
        $dateTimes = null;
        if ($liElements->length > 0) {
            $dateTimes = [];
            foreach ($liElements as $element) {
                list($date, $startTime) = explode(' в ', trim($element->nodeValue, ';'));
                //$dateTimes[] = date("Y-m-d H:i", strtotime(str_replace(array_keys($month), $month, $date)." ".$startTime));
                $dateTimes[] = [
                    'date' => date("Y-m-d", strtotime(str_replace(array_keys($month), $month, $date))),
                    'start_times' => [$startTime]
                ];
            }
        }
        $element = $finder->query(".//div[@class='afisha__info afisha__info_content performance flex flex-column']/div[@class='performance__duration']/span", $root->item(0));
        @(list($hours, $minutes) = explode(':', trim(@$element->item(0)->nodeValue, ' ч.')));
        $duration = $hours * 60 + $minutes;
        $element = $finder->query(".//div[@class='afisha__info afisha__info_content performance flex flex-column']/h1[@class='performance__title']", $root->item(0));
        $title = @trim($element->item(0)->nodeValue);

        $element = $finder->query(".//div[@class='afisha__info afisha__info_content performance flex flex-column']/div[@class='afisha__content content']", $root->item(0));
        $description = trim(@$element->item(0)->nodeValue);

        return [
            'title' => $title,
            'description' => $description,
            'duration' => $duration,
            'age_limit' => $ageLimit,
            'seance_dt_list' => json_encode($dateTimes),
            'image_url' => $imgSrc,
        ];
    }

    public static function saveImage($srcUrl)
    {
        Image::configure(array('driver' => 'gd'));
        $imageContent = Http::get($srcUrl);
        $fileName = uniqid() . ".jpeg";
        Storage::disk('local')->put("images/$fileName", '');
        $path = Storage::path("images/$fileName");
        $imageTmp = imagecreatefromstring($imageContent);
        imagejpeg($imageTmp, $path);
        imagedestroy($imageTmp);

        $img = Image::make($path)->resize(250, null, function ($constraint) {
            $constraint->aspectRatio();
        });

        $thumbFileName = 'thumb_' . $fileName;
        Storage::disk('local')->put("images/$thumbFileName", '');
        $path = Storage::path("images/$thumbFileName");
        $img->save($path);

        return $fileName;
    }
}
