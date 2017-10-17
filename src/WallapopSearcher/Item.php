<?php
namespace WallapopSearcher;

/**
*  Item [
*     "title" => "title",
*     "description" => "description",
*     "value" => value,
*     "location" => [
*       "lat" => lat,
*       "lng" => lng
*     ],
*     "url": "url"
*   ];
*/
class Item {
  /**
   * @param mixed $result
   * @return string
  */
  public static function formatMessage($title, $price, $url, $seller_name)
  {
    $str = "*{$title}*\n".
           "Publisher: *{$seller_name}*\n".
           "Price: `{$price}`\n".
           "{$url} ";
    return urlencode($str);
  }
}
