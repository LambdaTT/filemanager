<?php

namespace Filemanager\Routes;

use SplitPHP\WebService;
use SplitPHP\Request;
use SplitPHP\Exceptions\Unauthorized;

class Routes extends WebService
{
  public function init()
  {
    /////////////////
    // IMPORT ENTITY:
    /////////////////
    $this->addEndpoint('GET', '/v1/import/on-context/?context?', function (Request $req, $context = null) {
      $this->auth();

      $params = $req->getBody();
      if (!empty($context))
        $params['ds_context'] = $context;

      $imports = $this->getService('filemanager/import')->list($params);

      return $this->response
        ->withStatus(200)
        ->withData($imports);
    });

    $this->addEndpoint('GET', '/v1/import/?key?', function ($key) {
      $this->auth();

      $params = [
        'ds_key' => $key
      ];

      $import = $this->getService('filemanager/import')->get($params);
      if (empty($import)) return $this->response->withStatus(404);

      return $this->response
        ->withStatus(200)
        ->withData($import);
    });

    $this->addEndpoint('GET', '/v1/import', function (Request $req) {
      $this->auth();

      $params = $req->getBody();

      $imports = $this->getService('filemanager/import')->list($params);

      return $this->response
        ->withStatus(200)
        ->withData($imports);
    });

    $this->addEndpoint('POST', '/v1/import', function (Request $req) {
      $this->auth();

      $data = $req->getBody();

      $newImport = $this->getService('filemanager/import')->create($data);

      return $this->response
        ->withStatus(201)
        ->withData($newImport);
    });

    $this->addEndpoint('PUT', '/v1/import/?key?', function (Request $req, $key) {
      $this->auth();

      $params = [
        'ds_key' => $key
      ];
      $data = $req->getBody();

      $numRows = $this->getService('filemanager/import')->upd($params, $data);
      if ($numRows < 1) return $this->response->withStatus(404);

      return $this->response
        ->withStatus(204);
    });

    $this->addEndpoint('DELETE', '/v1/import/?key?', function ($key) {
      $this->auth();

      $params = [
        'ds_key' => $key
      ];

      $numRows = $this->getService('filemanager/import')->remove($params);
      if ($numRows < 1) return $this->response->withStatus(404);


      return $this->response
        ->withStatus(204);
    });

    ///////////////
    // IMPORT TYPE:
    ///////////////
    $this->addEndpoint('GET', '/v1/import-type/?context?', function (Request $req, $context = null) {
      $this->auth();

      $params = $req->getBody();
      if (!empty($context))
        $params['ds_context'] = $context;

      $importTypes = $this->getService('filemanager/import')->getTypes($params);

      return $this->response
        ->withStatus(200)
        ->withData($importTypes);
    });
  }

  private function auth(array $permissions = [])
  {
    if (!$this->getService('modcontrol/control')->moduleExists('iam')) return;

    // Auth user login:
    if (!$this->getService('iam/session')->authenticate())
      throw new Unauthorized("Não autorizado.");

    // Validate user permissions:
    if (!empty($permissions))
      $this->getService('iam/permission')
        ->validatePermissions($permissions);
  }
}
