<?php
namespace WallapopSearcher;
use WallapopSearcher\Item;

class Notifier {

  /**
  * @param string $notifyBy [MAIL, TELEGRAM]
  * The notification method, if its by mail or by telegram
  */
  public $notifyBy;

  function __construct($notifyBy){
    $this->$notifyBy = $notifyBy;
  }

  public function sendMessage($chatID, $messaggio, $telegram_token)
  {
    echo "sending message to " . $chatID . "\n";
    $url = "https://api.telegram.org/bot{$telegram_token}/sendMessage?chat_id=$chatID&text=$messaggio&parse_mode=Markdown";
    $opts = [
        'http' => [
            'method' => "GET",
            'header' => "Accept: application/json\r\n" .
                "Content-Type: application/json\r\n",
            'ignore_errors' => true
        ]
    ];
    echo "url: ".$url;
    $ctx = stream_context_create($opts);
    return file_get_contents($url, false, $ctx);
  }
}
