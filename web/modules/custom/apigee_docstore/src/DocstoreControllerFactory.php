<?php
/**
 * Created by IntelliJ IDEA.
 * User: gkoli
 * Date: 2018-12-14
 * Time: 17:40
 */
namespace Drupal\apigee_docstore;
use Apigee\Edge\Api\Docstore\Controller\DocstoreController;
use Drupal\apigee_edge\SDKConnectorInterface;

class DocstoreControllerFactory{

  private $controller;
  public function __construct(SDKConnectorInterface $connector)
  {
    $this->controller = new DocstoreController($connector->getOrganization(), $connector->getClient());
  }
  public function getController(){
    return $this->controller;
  }
}
