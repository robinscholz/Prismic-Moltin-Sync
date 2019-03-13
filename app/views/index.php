<?php

$requestType = $_SERVER['REQUEST_METHOD'];
$body = file_get_contents('php://input');
$decoded = json_decode($body);
$prismicSecret = $WPGLOBAL['app']->getContainer()->get('settings')['prismic.secret'];

use Prismic\Api;
use Prismic\Predicates;

//Request type check
if($requestType == 'POST' && $decoded->secret == $prismicSecret) {

  $prismicToken = $WPGLOBAL['app']->getContainer()->get('settings')['prismic.token'];
  $prismicUrl = $WPGLOBAL['app']->getContainer()->get('settings')['prismic.url'];
  $moltinID = $WPGLOBAL['app']->getContainer()->get('settings')['moltin.id'];
  $moltinSecret = $WPGLOBAL['app']->getContainer()->get('settings')['moltin.secret'];

  // Initiate Prismic
  $url = $prismicUrl;
  $token = $prismicToken;
  $api = Api::get($url, $token); 

  // Initiate Moltin
  $moltin = new Moltin\Client([
    'client_id'     => $moltinID,
    'client_secret' => $moltinSecret
  ]);

  // Prismic: Variables
  $inventory = new StdClass();
  $n = 0;

  // Prismic: Fetch data
  function prismicCall($api, $currentPage, $pageSize, $inventory, $n) {
    $response = $api->query(
      Predicates::at('document.type', 'article'),
      [ 'pageSize' => $pageSize, 'page' => $currentPage, 'orderings' => '[article.date desc]' ]
    );

    // Prismic add response to object
    $n++;
    $inventory->{$n} = $response->results;

    // Loop
    if($response->page < $response->total_pages) {
      prismiccall($api, ($response->page + 1), 20, $inventory, $n);
    }
  };

  // Prismic: Helper
  function flattenOneDimArray($array) {
    $new = array();
    foreach($array as $values) {
      foreach($values as $value) {
        $new[] = $value;
      }
    }
    return $new;
  }

  // Prismic: Call function
  prismicCall($api, 1, 20, $inventory, $n);

  // Prismic: Clean up
  $inventory = flattenOneDimArray($inventory);

  // Lil helper
  // echo "<pre>"; 

  // Prismic: Create Moltin Products
  foreach($inventory as $article) {

    // Moltin: Does article exist?
    try {
      $filterMoltin = $moltin->products->filter(['eq' => ['sku' => $article->id]])->all();
      $products = $filterMoltin->data();
    } catch(Exception $e) {
        echo 'An exception occurred filtering the moltin API:';
        var_dump($e);
        exit;
    }

    //Moltin: If not, create product
    if (empty($products)) {

      // Moltin: Create Product
      try {
          $price = isset($article->data->price) ? $article->data->price * 100 : 0;
          $productCreateResponse = $moltin->products->create([
              'type' => 'product',
              'name' => $article->data->title,
              'slug' => $article->uid,
              'sku' =>  $article->id,
              'description' => 'Created with MoltinPrismicSync',
              'manage_stock' => true,
              'status' => 'live',
              'commodity_type' => 'physical',
              'price' => [
                [
                  'amount' => $price,
                  'currency' => 'GBP',
                  'includes_tax' => true
                ]
              ],
              'stock' => $article->data->stock
          ]);
          if ($productCreateResponse->getStatusCode() === 201) {
            $product = $productCreateResponse->data();
            echo "Product created (" . $productCreateResponse->getExecutionTime() . " secs)\n";

            // delete the product (for testing)
            // $deleteProductResponse = $moltin->products->delete($product->id);
          }
        } catch(Exception $e) {
          echo 'An exception occurred calling the moltin API:';
          var_dump($e);
          exit;
      }

    //Moltin: Product exists already
    } else if(!empty($products)) {

      // Moltin: Check if product has been updated in Prismic
      $mDataset = [];
      $pDataset = [];
      $product = $products[0];

      $mDataset['title'] = $product->name;
      $mDataset['price'] = $product->price[0]->amount;
      $mDataset['sku'] = $product->sku;
      $mDataset['slug'] = $product->slug;

      $pDataset['title'] = $article->data->title;
      $pDataset['price'] = $article->data->price * 100;
      $pDataset['sku'] = $article->id;
      $pDataset['slug'] = $article->uid;

      $compare = array_diff($mDataset, $pDataset);

      // Moltin: Update product if arrays differ
      if(!empty($compare)) {
        try {
          $price = isset($article->data->price) ? $article->data->price * 100 : 0;
          $productUpdateResponse = $moltin->products->update($product->id, 
            ['data' => 
              [
                'type' => 'product', // Required
                'id' => $product->id, // Required
                'name' => $article->data->title,
                'slug' => $article->uid,
                'sku' =>  $article->id,
                'description' => 'Updated with MoltinPrismicSync',
                'price' => [
                  [
                    'amount' => $price,
                    'currency' => 'GBP',
                    'includes_tax' => true
                  ]
                ]
              ]
            ]
          );
          if ($productUpdateResponse->getStatusCode() === 200) {
            $product = $productUpdateResponse->data();
            echo "Product updated (" . $productUpdateResponse->getExecutionTime() . " secs)\n";
          }
        } catch(Exception $e) {
          echo 'An exception occurred calling the moltin API:';
          var_dump($e);
          exit;
        }

      // Moltin: Product exists and does not need to be updated
      } else {
        echo "Product already exists\n";
      }
    }
  }

// Request type check
} else {
  echo 'Request not authorized';
}
