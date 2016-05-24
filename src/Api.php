<?php
namespace GoogleCloudVision;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Promise;

class Api
{
    /**
     * @var GuzzleClientInterface
     */
    protected $guzzleClient;
    /**
     * @var string
     */
    protected $apiEndpoint = "https://vision.googleapis.com/v1";
    /**
     * @var string
     */
    protected $apiUrl;
    /**
     * @var string[]
     */
    protected $imageFilenames = [];
    /**
     * @var string[]
     */
    protected $imageUrls = [];
    /**
     * @var string[]
     */
    protected $images = [];
    /**
     * @var string[]
     */
    protected $features = [];
    /**
     * @var string
     */
    protected $apiKey;

    const FEATURE_LABEL_DETECTION = 'LABEL_DETECTION';
    const FEATURE_TEXT_DETECTION = 'TEXT_DETECTION';
    const FEATURE_FACE_DETECTION = 'FACE_DETECTION';
    const FEATURE_LANDMARK_DETECTION = 'LANDMARK_DETECTION';
    const FEATURE_LOGO_DETECTION = 'LOGO_DETECTION';
    const FEATURE_SAFE_SEARCH_DETECTION = 'SAFE_SEARCH_DETECTION';
    const FEATURE_IMAGE_PROPERTIES = 'IMAGE_PROPERTIES';
    /**
     * @var string[]
     */
    protected $availableFeatures = ['LABEL_DETECTION','TEXT_DETECTION','FACE_DETECTION','LANDMARK_DETECTION',
        'LOGO_DETECTION','SAFE_SEARCH_DETECTION','IMAGE_PROPERTIES'];
    public function __construct(string $apiKey, GuzzleClientInterface $guzzleClient = null)
    {
        $this->apiKey = (string)$apiKey;
        $this->apiUrl = $this->apiEndpoint . "/images:annotate?key=" . $this->apiKey;
        if($guzzleClient != null){
            $this->guzzleClient = $guzzleClient;
        } else {
            $this->guzzleClient = new GuzzleClient();
        }
    }
    public function setApiKey(string $apiKey) : Api
    {
        $this->apiKey = $apiKey;
        $this->apiUrl = $this->apiEndpoint . "/images:annotate?key=" . $this->apiKey;
        return $this;
    }
    public function addRawImage(string $image, string $name) : Api
    {
        if(isset($this->images[$name])) {
            throw new Exception("Image '{$name}' already added");
        }
        $this->images[$name] = base64_encode($image);
        return $this;
    }
    public function addImageByFilename(string $filename) : Api
    {
        if(in_array($filename,$this->imageFilenames)) {
            throw new Exception("Image '{$filename}' already added");
        }
        $this->imageFilenames[] = $filename;
        return $this;
    }
    public function addImageByUrl(string $url) : Api
    {
        if(in_array($url,$this->imageUrls)) {
            throw new Exception("Image {$url} already added");
        }
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new Exception("'{$url}' is not a valid URL");
        }
        $this->imageUrls[] = $url;
        return $this;
    }
    public function addFeature(string $feature, int $maxResults = 1) : Api
    {
        if(!in_array($feature,$this->availableFeatures)) {
            throw new Exception("Feature '{$feature}' does not exist");
        }
        $this->features[$feature] = $maxResults;
        return $this;
    }
    protected function checkRequirements()
    {
        if (empty($this->apiKey)) {
            throw new Exception("API Key cannot be empty");
        }

        if (empty($this->features)) {
            throw new Exception("Features cannot be empty");
        }

        if (empty($this->images) && empty($this->imageFilenames) && empty($this->imageUrls)) {
            throw new Exception("Images cannot by empty");
        }
    }
    public function getImagesFromFile() : \Generator
    {
        foreach($this->imageFilenames as $filename) {
            if(!file_exists($filename)) {
                continue;
            }
            yield $filename => base64_encode(file_get_contents($filename));
        }
    }
    protected function yieldAsyncPromisesForImageUrls(): \Generator
    {
        foreach($this->imageUrls as $url) {
            yield $url => $this->guzzleClient->getAsync($url);
        }
    }
    protected function yieldAsyncPromisesForPostRequests(int $batchSize): \Generator
    {
        foreach($this->createRequests($batchSize) as $key => $request) {
            yield $key => $this->guzzleClient->postAsync($this->apiUrl,['headers'=>['Content-Type'=>'application/json'],'body'=>json_encode($request)]);
        }
    }
    public function getImagesFromUrl(): \Generator
    {
        $results = Promise\unwrap($this->yieldAsyncPromisesForImageUrls());
        foreach($results as $index=>$result) {
            yield $index => base64_encode($result->getBody()->getContents());
        }
    }
    protected function getImages(): \Generator
    {
        foreach($this->images as $image) {
            yield $image;
        }
        yield from $this->getImagesFromFile();
        yield from $this->getImagesFromUrl();
    }
    protected function createRequests(int $batchSize=10) : \Generator
    {
        $features = [];
        $requests = [];
        $counter = 0;
        $ids = [];
        foreach($this->features as $feature=>$maxResults) {
            $features[] = ['type'=>$feature,'maxResults'=>$maxResults];
        }
        foreach($this->getImages() as $id=>$image) {
            if($counter == $batchSize) {
                $counter = 0;
                $keys = base64_encode(json_encode($ids));
                yield $keys => ['requests' => $requests];
                $requests = [];
                $ids = [];
            }
            $requests[] =                 [
                'image' => ['content'=>$image],
                'features' => $features
            ];
            $ids[] = $id;
            $counter++;
        }
        $keys = base64_encode(json_encode($ids));
        yield $keys => ['requests' => $requests];
    }
    protected function processResponse(string $index, \stdClass $data) : \Generator
    {
        $indexes = json_decode(base64_decode($index));
        foreach($data->responses as $key=>$response) {
            yield $indexes[$key] => $response;
        }
    }
    public function request(int $batchSize=10)
    {
        $this->checkRequirements();
        $results = Promise\unwrap($this->yieldAsyncPromisesForPostRequests($batchSize));
        foreach($results as $index=>$result) {
            foreach($this->processResponse($index, json_decode($result->getBody()->getContents())) as $key=>$response) {
                yield $key=>$response;
            }
        }
    }
}