<?php
namespace WallapopSearcher;

use RobotUnion\Integration\Task;
use WallapopSearcher\Notifier;
use WallapopSearcher\Item;

define("WALLAPOP_URL", "https://es.wallapop.com/");
define("TASK_NAME", "WallapopSearcher");
define("TELEGRAM_ERROR_USER", "_TELEGRAM_CHAT_ID_"); // Your chat id, will send errors to this chat

class WallapopSearcher extends Task {

  // $status string
  public $status;

  // $notifier Notifier
  public $notifier;

  // Telegram bot token, used to notify products to the user
  private $telegram_reporter_token =  "_TELEGRAM_BOT_TOKEN_";

  // Geocode key, for getting coordinates for an adress
  private $geocode_key             = "_GOOGLE_GEOCODE_KEY_";

  function __construct() {
    $status = 'error';
    $notifier = null;
  }

  /**
   * @return mixed
   */
  function mock()
  {
    return [];
  }

  /**
   * Test input, will be called if $this->input is not set *(when its a development)
   * @return mixed
   */
  private function getInput()
  {
    return [
      "search" => "Maschine",
      "items" => 4,
      "price" => [
        "min" => 0,
        "max" => 500
      ],
      "address" => "Madrid, España",
      "categories" => ["12545"],
      "published_date" => 2,
      "telegram_chat_id" => "211514466"
    ];
  }

  /**
   * @return mixed
   */
  function run()
  {
    $input = $this->input;
    $logger = $this->logger;

    if (!$input) {
      $input = $this->getInput();
    }

    try {
      $this->notifier = new Notifier('telegram');
      $logger->debug("START");

      // Main query to search
      $main_search_term = $input["search"];
      $address = $input['address'];
      $location = $this->getCoordinatesFromAdress($address);
      $logger->debug($location);

      $categories = $input["categories"];
      $min_price = $input["price"]["min"];
      $max_price = $input["price"]["max"];

      if($main_search_term != "") {
        $logger->debug("Search Started...");
        $this->doSearch($main_search_term, $location, $categories[0], $min_price, $max_price, $input);
      }
      else $this->$status = "ERROR";

      $logger->debug("END", [
        "status" => "SUCCESS",
        "input" =>  $input
      ]);

      return [];
    } catch (Exception $e) {
      $this->notifier->sendMessage(TELEGRAM_ERROR_USER, "$e", $this->telegram_reporter_token);
    }
  }

  /**
   * @return string
   */
  private function doSearch($query, $location, $category, $min_price, $max_price, $input)
  {
    /* Here it should search for a query, and return elements */
    $formatedUrl = $this->formatURL($query, $location, $category);
    $productCardCssSelector = '.card.card-product:not(.no-shake)';

    $this->logger->debug("Doing search... for " . $query);
    $this->logger->debug("Searching url: " . $formatedUrl);

    $wallpopSite = $this->device->url($formatedUrl);

    $this->twikPrice($min_price, $max_price);
    sleep(4);
    $this->selectPublished($input["published_date"]);
    sleep(4);

    $this->logger->capture($this->device);

    $allElements = $this->device->elements($this->device->using('css selector')->value($productCardCssSelector));
    $elements = array_slice($allElements, 0, 3);
    $this->logger->debug($elements);

    if ($elements) {
      for($i = 0; $i < count($elements); $i++){
        $element = $elements[$i];
        if ($element) {
          $elText = $element->text();
          $this->logger->debug($elText);

          $elLink = $element->byTag('a')->attribute('href');
          $this->logger->debug($elLink);

          $matches = preg_split("/\n/", $elText);

          $price = $matches[0];
          $title = $matches[1];
          $user = $matches[2];

          $formatedMessage = Item::formatMessage($title, $price, $elLink, $user);
          $this->logger->debug("sending telergam for: ".$title);
          $this->notifier->sendMessage($input['telegram_chat_id'], "$formatedMessage", $this->telegram_reporter_token);
        }
        else {
          break;
        }
      }
      return 'success';
    }
    else {
      return 'error: no elements found';
    }
  }

  /**
   * @return string
  */
  private function formatURL($query, $location, $category)
  {
    $formated_url = WALLAPOP_URL . 'search?';

    if ($query) {
      $query = urlencode($query);
      $formated_url = $formated_url . "kws=$query";
    }

    if ($location) {
      $lat = $location->lat;
      $lng = $location->lng;
      $formated_url = $formated_url . "&lat=$lat&lng=$lng";
    }

    if ($category) {
      $formated_url = $formated_url . "&catIds=$category";
    }

    return $formated_url;
  }

  /**
   * @return void
  */
  private function twikPrice($min_price, $max_price)
  {
    /* Didn't find a better way of doing this */
    return $this->device->execute(array(
      'script' => "$('#price-slider')[0].noUiSlider.set([$min_price, $max_price]); $('#price-slider > div > div:nth-child(3) > div').trigger('click')",
      'args' => []
    ));
  }

  /**
   * Selects published in
   * 24h     -> 1,
   * 7d      -> 2,
   * 30d     -> 3,
   * Overall -> 4,
   * @return void
  */
  private function selectPublished($elIndex = 1)
  {
    $publishedXpathSelector = '//*[@id="js-sidebar-filters-form"]/div[5]/ul/li['.$elIndex.']/label';
    return $this->device->byXPath($publishedXpathSelector)->click();
  }

  /**
   * @param $title string
   * @param $items mixed
   * @return void
  */
  private function notifyUser($title, $items)
  {
    return $this->notifier->notify($title, $items);
  }

  /**
   * @param $address mixed
   * @return mixed
  */
  private function getCoordinatesFromAdress($address){
    $this->logger->debug("Coordinates for: {$address}");

    $gocode_url = "https://maps.google.com/maps/api/geocode/json?address=".urlencode($address)."&key={$this->geocode_key}";
    $response = json_decode(file_get_contents($gocode_url));
    $result = $response->results[0];
    $geometry = $result->geometry;
    $formatted_address = $result->formatted_address;

    return $geometry->location;
  }
}
