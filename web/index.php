<?php

/**
* Define namespaces to use
*/
use Silex\Provider\FormServiceProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
* Structure definition
*/
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

/**
* Geocoding function
*/
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

    return $dati_strutturati; // è un array di structure (oggetto definito da utente)

    $fp = fopen('assets/data/data.json', 'w');
    fwrite($fp, json_encode($dati_strutturati));
    fclose($fp);
}


/**
* Create app
*/
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

/**
 * Register session provider
 */
$app->register(new Silex\Provider\SessionServiceProvider());

/**
* Controller index
*/
$app->match('/', function (Request $request) use ($app) {
    
    $filename = __DIR__ . '/assets/data/fonti.json';
    $fonti = json_decode(file_get_contents($filename), true);

    return $app['twig']->render('index.html', array(
        'fonti' => $fonti
        ));
});

$app->match('/add/01', function (Request $request) use ($app) {
    
    // Crea il form per la gestione dell'upload
    $uploadForm = $app['form.factory']->createBuilder('form')
        ->add('path', 'file', array(
            'constraints' => array(
                new Assert\NotBlank()
            )
        ))
        ->getForm();
        
    $uploadForm->handleRequest($request);
        
    if ($uploadForm->isValid()) {
        // Legge il CSV
        $data = $uploadForm->getData();
        $csvPath = $data["path"]->getPathName();
        $csvData = new Keboola\Csv\CsvFile($csvPath, ";");
        $originalName = $data["path"]->getClientOriginalName();
        
        // Crea l'oggetto dati
        $array = array();
        foreach($csvData as $row) {
            $tmpRow = array();
            foreach($row as $col) {
                $tmpRow[]=$col;
            }
            $array[]=$tmpRow;
        }
        
        // Carica il CSV in sessione
        $app['session']->set('newHeatmap', array('analyticsData' => $array));
        
        // Mostra la preview del CSV
        return $app['twig']->render('add_01_preview.html', array(
            'file_uploaded' => $array,
            'fileName' => $originalName
        ));
    }
    
    return $app['twig']->render('add_01.html', array(
        'form' => $uploadForm->createView()
    ));
});

$app->match('/add/02', function (Request $request) use ($app) {
    
    if (null === $heatmapData = $app['session']->get('newHeatmap')) {
        return $app->redirect('/add/01');
    }
    
    // Crea il form per la gestione dell'upload
    $optionsForm = $app['form.factory']->createBuilder('form')
        ->add('heatmapName', 'text', array(
            'constraints' => array(
                new Assert\NotBlank()
            )
        ))
        ->add('cityNameColumn', 'choice', array(
          'choices' => $heatmapData['analyticsData'][0]
        ))
        ->add('valueColumn', 'choice', array(
          'choices' => $heatmapData['analyticsData'][0]
        ))
        ->add('valueFormat', 'choice', array(
          'choices' => array('Integer','Float')
        ))
        ->getForm();
        
    $optionsForm->handleRequest($request);
    
    if ($optionsForm->isValid()) {
        $data = $optionsForm->getData();
        
        // array(4) { ["heatmapName"]=> string(6) "Poppa!" ["cityNameColumn"]=> int(0) ["valueColumn"]=> int(1) ["valueFormat"]=> int(0) }
        $computedData = array();
        $rawData = array();
        
        foreach($heatmapData['analyticsData'] as $row) {
            
            // Dati computati
            
            $tempComputedRow = array();

            $tempComputedRow['city'] = $row[$data["cityNameColumn"]];
            if($data["valueFormat"]==0) {
                $tempComputedRow['value'] = (int)$row[$data["valueColumn"]];
            } else if ($data["valueFormat"]==1) {
                $tempComputedRow['value'] = (float)$row[$data["valueColumn"]];
            }
            
            $computedData[] = $tempComputedRow;
            
            // Dati grezzi
            
            $tempRawRow = array();
            
            $tempRawRow['city'] = $row[$data["cityNameColumn"]];
            $tempRawRow['value'] = $row[$data["valueColumn"]];
            
            $rawData[] = $tempRawRow;
        }
        
        return $app['twig']->render('add_02_preview.html', array(
                'form' => $optionsForm->createView(),
                'raw' => $rawData,
                'computed' => $computedData
            ));
    }
    
    return $app['twig']->render('add_02.html', array(
            'form' => $optionsForm->createView(),
            'table' => $heatmapData['analyticsData']
        ));
});

/**
* Controller map
*/
$app->get('/map', function () use ($app) {
    return $app['twig']->render('map.html');
});

/**
* Run app
*/
$app->run();
?>