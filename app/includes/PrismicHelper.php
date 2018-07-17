<?php

use Prismic\Api;

/**
 * This class contains helpers for the Prismic API. 
 */
class PrismicHelper
{
  private $api = null;
  private $app;

  public function __construct($app)
  {
    $this->app = $app;
  }

  public function get_api()
  {
    $container = $this->app->getContainer();
    $url = $container->get('settings')['prismic.url'];
    $token = $container->get('settings')['prismic.token'];
    
    if ($this->api == null) {
      $this->api = Api::get($url, $token);
    }

    return $this->api;
  }
}
