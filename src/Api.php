<?php
namespace GoogleCloudVision;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Promise\Promise;
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

    /**
     * @param string $apiKey
     * @param GuzzleClientInterface|null $guzzleClient
     */
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
    /**
     * @param string $apiKey
     * @return Api
     */
    public function setApiKey(string $apiKey) : Api
    {
        $this->apiKey = $apiKey;
        $this->apiUrl = $this->apiEndpoint . "/images:annotate?key=" . $this->apiKey;
        return $this;
    }
    /**
     * @param string $image
     * @param string $name
     * @return Api
     */
    public function addRawImage(string $image, string $name) : Api
    {
        if(isset($this->images[$name])) {
            throw new Exception("Image '{$name}' already added");
        }
        $this->images[$name] = base64_encode($image);
        return $this;
    }
    /**
     * @param string $filename
     * @return Api
     */
    public function addImageByFilename(string $filename) : Api
    {
        if(isset($this->images[$filename])) {
            throw new Exception("Image '{$filename}' already added");
        }
        if(!file_exists($filename)) {
            throw new Exception("Image '{$filename}' not found");
        }
        $this->images[$filename] = base64_encode(file_get_contents($filename));
        return $this;
    }
    /**
     * @param string $url
     * @return Api
     */
    public function addImageByUrl(string $url) : Api
    {
        if(isset($this->images[$url])) {
            throw new Exception("Image {$url} already added");
        }
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new Exception("'{$url}' is not a valid URL");
        }
        $this->images[$url] = base64_encode($this->guzzleClient->get($url)->getBody());
        return $this;
    }
    /**
     * @param string $name
     * @return string
     */
    public function getImage(string $name) : string
    {
        if(!isset($this->images[$name])) {
            throw new Exception("Image '{$name}' has not been registered");
        }
        return $this->images[$name];
    }
    /**
     * @param string $feature
     * @param int $maxResults
     * @return Api
     */
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

        if (empty($this->images)) {
            throw new Exception("Images cannot by empty");
        }
    }
    /**
     * @return array
     */
    protected function createRequestBody() : array
    {
        $features = [];
        $requests = [];
        foreach($this->features as $feature=>$maxResults) {
            $features[] = ['type'=>$feature,'maxResults'=>$maxResults];
        }
        foreach($this->images as $image) {
            $requests[] =                 [
                'image' => ['content'=>$image],
                'features' => $features
            ];
        }
        $body = [
            'requests' => $requests
        ];
        return $body;
    }
    /**
     * @return \stdClass
     */
    public function request() : \stdClass
    {
        $this->checkRequirements();
        $response = $this->guzzleClient->post($this->apiUrl,['json'=>$this->createRequestBody()]);
        return json_decode($response->getBody());
    }
    /**
     * @return Promise
     */
    public function requestAsync() : Promise
    {
        $this->checkRequirements();
        $promise =  $this->guzzleClient->postAsync($this->apiUrl,['json'=>$this->createRequestBody()]);
        return $promise->then(
            function (\Psr\Http\Message\ResponseInterface $res) {
                return json_decode($res->getBody());
            }
        );
    }
}