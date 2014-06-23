<?php

use Silex\Provider\FormServiceProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\HttpKernel\HttpKernelInterface;

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

function custom_geocoding($file_path) {

    $csvFile = new Keboola\Csv\CsvFile($file_path, ",");
    $dati_strutturati = array();
    //$cols_names[] = $csvFile->getHeader();

    $geocoder = new \Geocoder\Geocoder();
    $adapter = new \Geocoder\HttpAdapter\SocketHttpAdapter();

    //$apiKey = 'AIzaSyDTXsR4Id1Hk4xowxa9yCKeEn1Q6C2buEo';

    $provider = new \Geocoder\Provider\GoogleMapsProvider($adapter);//, null, null, true, $apiKey);
    $geocoder->registerProvider($provider);

    $geocoded = new \Geocoder\Result\Geocoded();

    foreach($csvFile as $row) {

        // 2000000 = 2sec
        usleep(200);

        try {
            $geocoded = $geocoder->geocode($row[0]);

            $dati_strutturati[] = new structure($row[0], $geocoded->getLongitude(), $geocoded->getLatitude(), $row[1]);

        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }

    return $dati_strutturati; // è un array di structure (definita da utente)

    $fp = fopen('assets/data/data.json', 'w');
    fwrite($fp, json_encode($dati_strutturati));
    fclose($fp);
}

require_once __DIR__.'/../vendor/autoload.php';

$app = new Silex\Application();
$app['debug'] = true;

/**
 * Register Twig template engine
 */
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/views',
));

/**
 * Register form and validation service providers
 */
$app->register(new FormServiceProvider());
$app->register(new Silex\Provider\ValidatorServiceProvider());

/**
 * Register translation provider
 */
$app->register(new Silex\Provider\TranslationServiceProvider(), array(
    'translator.messages' => array(),
    'translator.domains' => array()
));


$app->match('/', function (Request $request) use ($app) {

    $filename = __DIR__ . '/assets/data/fonti.json';
    $fonti = json_decode(file_get_contents($filename), true);

    $form = $app['form.factory']->createBuilder('form')
    ->add('path', 'file', array(
        'constraints' => array(
            new Assert\NotBlank()
        )
    ))
    ->getForm();
    
    /*
    $form = $app['form.factory']->createBuilder('form')
    ->add('path', 'file', array(
        'constraints' => array(
            new Assert\NotBlank(),
            new Assert\File(array(
                'mimeTypes' => array(
                    'text/csv',
                ),
                'mimeTypesMessage' => 'Please upload a valid CSV',
            ))
        )
    ))
    ->getForm();
    */
    
    $form->handleRequest($request);
    
    /**
     * Redirect on /add page
     */
    if ($form->isValid()) {
        $data = $form->getData();

        // funzione per la laura che sennò si dimentica
        // $dati_elaborati =custom_geocoding($data["path"]->getPathName());

        $csvPath = $data["path"]->getPathName();
        $csvData = new Keboola\Csv\CsvFile($csvPath, ",");

        // redirect somewhere
        $subRequest = Request::create(
            '/add', 
            'POST',
            array('csv' => $csvData)
        );
        return $app->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
    }

    // display the form
    return $app['twig']->render('index.html', array(
        'form' => $form->createView(),
        'fonti' => $fonti
        ));
});

$app->match('/add', function (Request $request) use ($app) {
    $csvFile = $request->request->get('csv');
    foreach($csvFile as $row) {
        var_dump($row);
    }
    return $app['twig']->render('add.html');
});


$app->get('/map', function () use ($app) {
    return $app['twig']->render('map.html');
});


$app->run();
?>