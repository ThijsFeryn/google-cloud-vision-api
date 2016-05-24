<?php
require dirname(__DIR__).'/vendor/autoload.php';
$api = new GoogleCloudVision\Api(getenv('apiKey'));
$api->addImageByUrl('https://pbs.twimg.com/profile_images/638779332354265088/Nsf1xLhH.jpg');
$api->addFeature($api::FEATURE_LABEL_DETECTION,10);
$api->addFeature($api::FEATURE_FACE_DETECTION);
//$api->addFeature($api::FEATURE_LOGO_DETECTION);
//$api->addFeature($api::FEATURE_TEXT_DETECTION);
//$api->addFeature($api::FEATURE_LANDMARK_DETECTION);
$result = $api->request(10);

foreach($result as $key=>$value) {
    echo "### $key ###".PHP_EOL;
    if(isset($value->labelAnnotations)) {
        echo "== LABEL ANNOTATIONS ==".PHP_EOL;
        foreach($value->labelAnnotations as $annotation){
            echo "\t* {$annotation->description}".PHP_EOL;
        }
    }

    if(isset($value->landmarkAnnotations)) {
        echo PHP_EOL."== LANDMARK ANNOTATIONS ==".PHP_EOL;
        foreach($value->landmarkAnnotations as $annotation){
            echo "\t* {$annotation->description} ({$annotation->locations[0]->latLng->latitude},{$annotation->locations[0]->latLng->longitude})".PHP_EOL;
        }
    }

    if(isset($value->logoAnnotations)) {
        echo PHP_EOL."== LOGO ANNOTATIONS ==".PHP_EOL;
        foreach($value->logoAnnotations as $annotation){
            echo "\t* {$annotation->description}".PHP_EOL;
        }
    }

    if(isset($value->textAnnotations)) {
        echo PHP_EOL."== TEXT ANNOTATIONS ==".PHP_EOL;
        foreach($value->textAnnotations as $annotation){
            echo "\t* {$annotation->description}".PHP_EOL;
        }
    }

    if(isset($value->faceAnnotations)) {
        echo PHP_EOL."== FACE ANNOTATIONS ==".PHP_EOL;
        foreach($value->faceAnnotations as $annotation){
            echo "\t* Joy: {$annotation->joyLikelihood}".PHP_EOL;
            echo "\t* Sorrow: {$annotation->sorrowLikelihood}".PHP_EOL;
            echo "\t* Anger: {$annotation->angerLikelihood}".PHP_EOL;
            echo "\t* Suprise: {$annotation->surpriseLikelihood}".PHP_EOL;
            echo "\t* Underexposed: {$annotation->underExposedLikelihood}".PHP_EOL;
            echo "\t* Blurred: {$annotation->blurredLikelihood}".PHP_EOL;
            echo "\t* Headware: {$annotation->headwearLikelihood}".PHP_EOL;
        }
    }
}
