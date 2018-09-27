<?php

namespace Anexia\Neos\Monitoring\Controller;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\Mvc\View\JsonView;

abstract class BaseController extends ActionController
{
    /**
     * @Flow\InjectConfiguration(path="queryParameter")
     * @var string
     */
    protected $queryParameter;

    /**
     * @Flow\InjectConfiguration(path="accessToken")
     * @var string
     */
    protected $accessToken;

    /**
     * @var array
     */
    protected $supportedMediaTypes = ['application/json'];

    /**
     * @var array
     */
    protected $viewFormatToObjectNameMap = ['json' => JsonView::class];

    /**
     * @return void
     */
    protected function setDefaultHeaders()
    {
        $this->response->setHeader('Access-Control-Allow-Origin', '*');
        $this->response->setHeader('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $this->response->setHeader('Access-Control-Allow-Credentials', 'true');
        $this->response->setHeader('Content-Type', 'application/json');
    }

    /**
     * @return void
     * @throws \Neos\Flow\Mvc\Exception\NoSuchArgumentException
     * @throws \Neos\Flow\Mvc\Exception\StopActionException
     * @throws \Neos\Flow\Mvc\Exception\UnsupportedRequestTypeException
     */
    protected function validateRequest()
    {
        if ($this->request->getHttpRequest()->getMethod() === 'OPTIONS') {
            die();
        }

        if (empty($this->accessToken) || !\is_string($this->accessToken) || \strlen(trim($this->accessToken)) <= 0) {
            $this->throwStatus(503, null, json_encode(
                [
                    'code'    => 'ServiceUnavailable',
                    'message' => 'Plugin isn\'t correctly configured.'
                ]
            ));
        }

        if ($this->request->getHttpRequest()->getMethod() !== 'GET') {
            $this->throwStatus(405, null, json_encode(
                [
                    'code'    => 'MethodNotAllowed',
                    'message' => 'Unsupported method'
                ]
            ));
        }

        if (!$this->request->hasArgument($this->queryParameter)) {
            $this->throwStatus(403, null, json_encode(
                [
                    'code'    => 'BadRequest',
                    'message' => 'Missing Parameter'
                ]
            ));
        }

        $accessToken = $this->request->getArgument($this->queryParameter);
        if ($accessToken !== $this->accessToken) {
            $this->throwStatus(401, null, json_encode(
                [
                    'code'    => 'Unauthorized',
                    'message' => 'You are not authorized to do this'
                ]
            ));
        }
    }
}
