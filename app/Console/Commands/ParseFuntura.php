<?php

namespace App\Console\Commands;

use App\Models\Performances;
use App\Models\Theaters;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;

class ParseFuntura extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'funtura';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    const HOST = 'https://funtura-cinema.ru';
    const DOMAIN = 'funtura-cinema.ru';

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
     */
    public function handle()
    {
        self::$performances = Performances::where('type', Performances::TYPE_MOVIE)->get()->toArray();

        if (!(self::$theaterId = @Theaters::where('domain_name', self::DOMAIN)->first()->id))
            throw new \Exception('Funtura not found');

        self::parseMainPage();
    }

    public static function parseMainPage()
    {
        //$response = Http::withOptions(['proxy' => 'socks5h://wU78vY:BLsSgz@185.220.35.242:46008'])->get(self::HOST . '/repertoire');
        $response = Http::get(self::HOST . '/repertoire');
        if (!$response)
            throw new \Exception('Error reading page, stopped');
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($response);
        $finder = new \DomXPath($doc);
        $root = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), 'tab-pane fade active show')]");
        $divList = $finder->query(".//div/div[@data-hall-1='0']", $root->item(0));
        foreach ($divList as $divItem) {
            $item = $finder->query(".//div[@class='movie-container-inner']/div/a", $divItem);
            $href = trim($item->item(0)->attributes->item(0)->value, ".");
            $performanceData = self::parsePerformance(self::HOST . $href);
            $isNew = false;
            if (in_array($performanceData['title'], array_column(self::$performances, 'title'))) {
                print "Edit performance\n";
                $performance = Performances::where('title', $performanceData['title'])->first();
                foreach (json_decode($performance->images) as $item) {
                    @unlink(Storage::path("images/{$item}"));
                    @unlink(Storage::path("images/thumb_{$item}"));
                }
            } else {
                print "Create performance\n";
                $performance = new Performances;
                $isNew = true;
            }
            $performance->title = $performanceData['title'];
            $performance->description = $performanceData['description'];
            $performance->age_limit = $performanceData['age_limit'];
            //$performance->image_urls = $performanceData['image_urls'];
            $performance->images = json_encode([self::saveImage($performanceData['image_url'])]);
            $performance->type = Performances::TYPE_MOVIE;
            $performance->save();
            $performance->theaters()->sync([self::$theaterId => [
                'seance_dt_list' => $performanceData['seance_dt_list']
            ]]);

            if ($isNew)
                self::$performances[] = $performance;
        }
    }

    private static function parsePerformance($url)
    {
        $imgSrc = null;
        $response = Http::get($url);
        if (!$response)
            throw new \Exception('Error reading page, stopped');
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($response);

        $finder = new \DomXPath($doc);
        $root = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), 'container-fluid px-0')]");
        $divMovie = $finder->query(".//div/div[@class='col-12 p-3 col-md-8 p-lg-5']", $root->item(0));
        $imgItem = $finder->query(".//div/div/a/img", $divMovie->item(0));
        foreach ($imgItem->item(0)->attributes as $attribute) {
            if ($attribute->name == 'src') {
                $imgSrc =  'https:' . $attribute->value;
                break;
            }
        }
        $item = $finder->query(".//div/div/h1", $divMovie->item(0));
        $title = trim(str_replace('ПРЕМЬЕРА', '', $item->item(0)->nodeValue));

        $item = $finder->query(".//div/div/p", $divMovie->item(0));
        $ageLimit = trim(str_replace('Возрастные ограничения: ', '', $item->item(4)->nodeValue), '+');

        $description = $item->item(5)->nodeValue;

        return [
            'title' => $title,
            'description' => $description,
            'age_limit' => $ageLimit,
            'seance_dt_list' => json_encode(self::parseDates($finder, $root)),
            'image_url' => $imgSrc,
        ];
    }

    public static function parseDates($finder, $root)
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

        $seancesMovie = $finder->query(".//div/div[@class='col-12 p-3 col-md-4 p-lg-5']", $root->item(0));
        $list = $finder->query(".//div/div/div/div[@id='days']/div/ul/li/a", $seancesMovie->item(0));
        $days = [];
        foreach ($list as $item) {
            $entry = [];
            foreach ($item->attributes as $attribute) {
                if ($attribute->name == 'href')
                    $entry['id'] = trim($attribute->value, '#');
                if ($attribute->name == 'data-date') {
                    $temp = explode(' ', $attribute->value);
                    $date = $temp[1] . ' ' . $month[mb_strtolower($temp[2], 'UTF-8')] . ' ' . date('Y');
                    $entry['date'] =  date('Y-m-d', strtotime($date));
                }
            }
            $days[] = $entry;
        }

        $item = $finder->query(".//div/div[@class='tab-content']", $seancesMovie->item(0));
        foreach ($days as &$day) {
            $div = $finder->query(".//div[@id='{$day['id']}']/div/div[@class='time col-md-auto']", $item->item(0));
            $day['start_time_1'] = trim($div->item(0)->nodeValue);
            $day['start_time_2'] = @trim($div->item(1)->nodeValue);
        }
        $data = [];
        foreach ($days as $day) {
            $startTimes = [];
            $startTimes[] = $day['start_time_1'];
            if (!empty($day['start_time_2']))
                $startTimes[] = $day['start_time_2'];
            $data[] = [
                'date' => $day['date'],
                'start_times' => $startTimes,
            ];
        }
        return $data;
    }

    public static function saveImage($srcUrl)
    {
        Image::configure(array('driver' => 'gd'));
        $imageContent = Http::get($srcUrl);
        $fileName = uniqid() . ".jpeg";
        Storage::disk('local')->put("images/$fileName", '');
        $path = Storage::path("images/$fileName");
        $imageTmp = imagecreatefromstring($imageContent);
        imagejpeg($imageTmp, $path, 80);
        imagedestroy($imageTmp);

        $img = Image::make($path)->resize(150, null, function ($constraint) {
            $constraint->aspectRatio();
        });

        $thumbFileName = 'thumb_' . $fileName;
        Storage::disk('local')->put("images/$thumbFileName", '');
        $path = Storage::path("images/$thumbFileName");
        $img->save($path);

        return $fileName;
    }
}
