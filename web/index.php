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


use Controllers\indexController;

/**
* Create app
*/
$loader = require __DIR__.'/../vendor/autoload.php';
$loader->add('Controllers', __DIR__.'/src');

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
    
    $filename = __DIR__ . '/assets/data/sources.json';
    $sources = json_decode(file_get_contents($filename), true);

    return $app['twig']->render('index.html', array(
        'sources' => $sources,
        ));
});

//$app->match('/',          'Controllers\indexController::indexAction');

/**
* Controller add step 1/3
*/
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

/**
* Controller add step 2/3
*/
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

        array_shift($computedData);
        array_shift($rawData);

        // Carica i dati computati in sessione
        $app['session']->set('newData', array(
            'data' => $computedData,
            'userFileName' => $data["heatmapName"]
            ));
        
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
* Controller add step 3/3
*/
$app->match('/add/03', function (Request $request) use ($app) {
    
    if (null === $newData = $app['session']->get('newData')) {
        return $app->redirect('/add/01');
    }


    // Geocoding

    $geocoder = new \Geocoder\Geocoder();
    $adapter = new \Geocoder\HttpAdapter\SocketHttpAdapter();

    //$apiKey = 'AIzaSyDTXsR4Id1Hk4xowxa9yCKeEn1Q6C2buEo';

    $provider = new \Geocoder\Provider\GoogleMapsProvider($adapter);//, null, null, true, $apiKey);
    $geocoder->registerProvider($provider);

    $tmpGeocoded = new \Geocoder\Result\Geocoded();


    $geocodedData = array();

    
    foreach($newData['data'] as $row) {

        $tmpGeocodedRow = array(
        "name" => "",
        "count" => "",
        "lng" => "null",
        "lat" => "null",
        "result" => "success"
        );

        $tmpGeocodedRow["name"] = $row["city"];
        $tmpGeocodedRow["count"] = $row["value"];

        try {
            $tmpGeocoded = $geocoder->geocode($row["city"]);
            
            $tmpGeocodedRow["lng"] = $tmpGeocoded->getLatitude();
            $tmpGeocodedRow["lat"] = $tmpGeocoded->getLongitude();

        } catch (Exception $e) {
            $tmpGeocodedRow["result"] = $e->getMessage();
        }

         $geocodedData[] = $tmpGeocodedRow;
    }

    // // Salvataggio dati acquisti con geocoding in file json

    // $jsontoHeat = fopen(__DIR__ . "/assets/data/sources/" . $newData['userFileName'], 'w');
    // fwrite($jsontoHeat, json_encode($geocodedData, JSON_PRETTY_PRINT));
    // fclose($jsontoHeat);

    // // Modifica del file delle fonti

    // $sources = file_get_contents(__DIR__ . '/assets/data/sources.json');
    // $dataSources = json_decode($sources);
    
    // $dataSources[] = array('href'=> '#', 'caption' => $newData['userFileName']);
    
    // file_put_contents(__DIR__ . '/assets/data/sources.json',json_encode($dataSources));

    // Carica i dati computati in sessione
        $app['session']->set('geocodingResults', array(
            'data' => $geocodedData, 
            'name' => $newData["userFileName"]
            ));



    // Presenta la tabella geocodata (city - value - lng - lat - result)
    return $app['twig']->render('add_03.html', array(
        'geocoded' => $geocodedData,
    ));
});

/**
* Controller add end
*/
$app->get('add/end', function() use ($app) {

    if (null === $geocodingResults = $app['session']->get('geocodingResults')) {
        return $app->redirect('/add/01');
    }

    // Salvataggio dati acquisti con geocoding in file json

    $jsontoHeat = fopen(__DIR__ . "/assets/data/sources/" . $geocodingResults['name'] . ".json", 'w');
    fwrite($jsontoHeat, json_encode($geocodingResults['data'], JSON_PRETTY_PRINT));
    fclose($jsontoHeat);

    // Modifica del file delle fonti

    $sources = file_get_contents(__DIR__ . '/assets/data/sources.json');
    $dataSources = json_decode($sources);
    
    $dataSources[] = array('href'=> '#', 'caption' => $geocodingResults['name']);
    
    file_put_contents(__DIR__ . '/assets/data/sources.json',json_encode($dataSources));

    return $app['twig']->render('add_end.html', array(
        'name' => $geocodingResults['name'],
    ));
});


/**
* Controller map
*/
$app->get('/map/{name}', function ($name) use ($app) {
    return $app['twig']->render('map.html', array(
        'name' => $name,
    ));
});

/**
* Run app
*/
$app->run();
?>