<?php

class structure {
	// properties
	public $name;
    public $lon;
    public $lat ;
    public $count;
         
    // constructor
    public function __construct($a, $b, $c, $d) {
    	$this->name = $a;
        $this->lon = $b;
        $this->lat = $c;
        $this->count = $d;
        
    }
}

require_once __DIR__.'/../vendor/autoload.php';

$app = new Silex\Application();

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/views',
));


$csvFile = new Keboola\Csv\CsvFile(__DIR__ . '/assets/data/dati_prova.csv', ",");
$data = array();

//$cols_names[] = $csvFile->getHeader();


$geocoder = new \Geocoder\Geocoder();
$adapter = new \Geocoder\HttpAdapter\SocketHttpAdapter();

$apiKey = 'AIzaSyDTXsR4Id1Hk4xowxa9yCKeEn1Q6C2buEo';

$provider = new \Geocoder\Provider\GoogleMapsProvider($adapter);//, null, null, true, $apiKey);
$geocoder->registerProvider($provider);

$geocoded = new \Geocoder\Result\Geocoded();



foreach($csvFile as $row) {

	// 2000000 = 2sec
	usleep(200);

    try {
    	$geocoded = $geocoder->geocode($row[0]);

    	$data[] = new structure($row[0], $geocoded->getLongitude(), $geocoded->getLatitude(), $row[1]);

    } catch (Exception $e) {
    	echo $e->getMessage();
    }
}

$fp = fopen('assets/data/data.json', 'w');
fwrite($fp, json_encode($data));
fclose($fp);

$app->get('/', function () use ($app) {
    $filename = __DIR__ . '/assets/data/fonti.json';
    $fonti = json_decode(file_get_contents($filename), true);

    return $app['twig']->render('index.html', array(
        'fonti' => $fonti,
        )
    );
    
});

$app->get('/add', function () use ($app) {
    return $app['twig']->render('add.html');
});

$app->get('/map', function () use ($app) {
    return $app['twig']->render('map.html');
});


$app->run();
?>