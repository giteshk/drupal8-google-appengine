<?php

/**
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 2 as published by the
 * Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public
 * License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

namespace Drupal\apigee_docstore;

use Apigee\Edge\Api\Management\Controller\OrganizationController;
use Apigee\Edge\Client;
use Apigee\Edge\ClientInterface;
use Apigee\Edge\HttpClient\Utility\Builder;
use Drupal\apigee_edge\CredentialsInterface;
use Drupal\apigee_edge\Exception\AuthenticationKeyException;
use Drupal\apigee_edge\Exception\AuthenticationKeyNotFoundException;
use Drupal\apigee_edge\OauthCredentials;
use Drupal\apigee_edge\Plugin\EdgeKeyTypeInterface;
use Drupal\apigee_edge\Plugin\KeyType\ApigeeAuthKeyType;
use Drupal\apigee_edge\SDKConnector;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\InfoParserInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\key\KeyInterface;
use Drupal\key\KeyRepositoryInterface;
use Http\Adapter\Guzzle6\Client as GuzzleClientAdapter;
use Http\Message\Authentication;

/**
 * Provides an Apigee Edge SDK connector.
 */
class DocstoreConnector extends SDKConnector {

  /**
   * The client object.
   *
   * @var null|\Http\Client\HttpClient
   */
  private static $client = NULL;

  /**
   * The currently used credentials object.
   *
   * @var null|\Drupal\apigee_edge\CredentialsInterface
   */
  private static $credentials = NULL;

  /**
   * {@inheritdoc}
   */
  public function getClient(?Authentication $authentication = NULL, ?string $endpoint = NULL): ClientInterface {
      if (self::$client === NULL) {
        $credentials = $this->getCredentials();
        /** @var \Drupal\apigee_edge\Plugin\EdgeKeyTypeInterface $key_type */
        self::$client = $this->buildClient($credentials->getAuthentication(), "https://apigee.com");
      }
      return self::$client;
  }

  /**
   * Returns the credentials object used by the API client.
   *
   * @return \Drupal\apigee_edge\CredentialsInterface
   *   The key entity.
   */
  private function getCredentials(): CredentialsInterface {
    if (self::$credentials === NULL) {
      $active_key = $this->configFactory->get('apigee_edge.auth')->get('active_key');
      if (empty($active_key)) {
        throw new AuthenticationKeyException('Apigee Edge API authentication key is not set.');
      }
      if (!($key = $this->keyRepository->getKey($active_key))) {
        throw new AuthenticationKeyNotFoundException($active_key, 'Apigee Edge API authentication key not found with "@id" id.');
      }
      self::$credentials = $this->buildCredentials($key);
    }

    return self::$credentials;
  }

  /**
   * Changes credentials used by the API client.
   *
   * @param \Drupal\apigee_edge\CredentialsInterface $credentials
   *   The new credentials object.
   */
  private function setCredentials(CredentialsInterface $credentials) {
    self::$credentials = $credentials;
    // Ensure that client will be rebuilt with the new key.
    self::$client = NULL;
  }

  /**
   * Builds credentials, which depends on the KeyType of the key entity.
   *
   * @param \Drupal\key\KeyInterface $key
   *   The key entity which stores the API credentials.
   *
   * @return \Drupal\apigee_edge\CredentialsInterface
   *   The credentials.
   */
  private function buildCredentials(KeyInterface $key): CredentialsInterface {
    /** @var \Drupal\apigee_edge\Plugin\EdgeKeyTypeInterface $key */
    if ($key->getKeyType() instanceof EdgeKeyTypeInterface) {
      if( $key->getKeyType()->getAuthenticationType($key) == EdgeKeyTypeInterface::EDGE_AUTH_TYPE_BASIC) {
        $key_config = $key->toArray();
        $key_config['auth_type'] = EdgeKeyTypeInterface::EDGE_AUTH_TYPE_OAUTH;
        unset($key_config['endpoint'], $key_config['client_id'] , $key_config['client_secret'], $key_config['authorization_server']);
        $key = new ApigeeAuthKeyType($key_config, $key->getKeyType()->getPluginId(), $key->getKeyType()->getPluginDefinition());

      }
      return new OauthCredentials($key);
    }
    else {
      throw new AuthenticationKeyException("Type of {$key->id()} key does not implement EdgeKeyTypeInterface.");
    }
  }

}
