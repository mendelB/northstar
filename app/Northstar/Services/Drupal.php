<?php namespace Northstar\Services\Drupal;

use GuzzleHttp\Client;
use Config;

class DrupalAPI {

  protected $client;

  public function __construct()
  {
    $base_url = Config::get('services.drupal.url');
    $version = Config::get('services.drupal.version');

    $this->client = new Client([
      'base_url' => [$base_url . '/api/{version}/', ['version' => $version]],
      'defaults' => [
        'headers' => [
          'Content-Type' => 'application/json',
          'Accept' => 'application/json'
        ]
      ],
    ]);
  }

  public function campaigns($id = NULL)
  {
    // Get all campaigns if there's no id set.
    if (!$id) {
      $response = $this->client->get('campaigns.json');
    }
    else {
      $response = $this->client->get('content/' . $id . '.json');
    }
    return $response->json();
  }

  /**
   * Forward registration to drupal.
   * @param $user - User to be registered on Drupal site
   * @return int - Created Drupal user UID
   */
  public function register($user)
  {
    // Format user object for consumption by Drupal API.
    $user->birthdate = date('Y-m-d', strtotime($user->birthdate));
    $user->user_registration_source = $user->source;

    $response = $this->client->post('users', [
      'body' => json_encode($user),
    ]);

    return $response->uid;
  }

  /**
   * Create a new campaign signup on the Drupal site.
   * @param $drupal_id    String - UID of user on the Drupal site
   * @param $campaign_id  String - NID of campaign on the Drupal site
   * @param $source       String - Sign up source (e.g. web, iPhone, etc.)
   *
   * @return String - Signup ID
   */
  public function campaignSignup($drupal_id, $campaign_id, $source)
  {
    $payload = [
      'uid' => $drupal_id,
      'source' => $source
    ];

    // @TODO: This request must be authenticated as the relevant user.
    $response = $this->client->post('campaigns/' . $campaign_id . '/signup', [
      'body' => json_encode($payload)
    ]);

    return $response->sid;
  }
}