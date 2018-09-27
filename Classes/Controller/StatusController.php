<?php

namespace Anexia\Neos\Monitoring\Controller;

use Anexia\Neos\Monitoring\Check\CheckInterface;
use http\Exception\RuntimeException;
use Neos\Flow\Annotations as Flow;

class StatusController extends BaseController
{
    /**
     * @Flow\InjectConfiguration(path="status.checks")
     * @var array
     */
    protected $checks;

    /**
     * @return string
     * @throws \Neos\Flow\Mvc\Exception\NoSuchArgumentException
     * @throws \Neos\Flow\Mvc\Exception\StopActionException
     * @throws \Neos\Flow\Mvc\Exception\UnsupportedRequestTypeException
     */
    public function indexAction(): string
    {
        $this->validateRequest();

        $errors = $this->runChecks();
        $this->response->setHeader('Content-Type', 'text/plain');
        if (empty($errors)) {
            return 'OK';
        }

        $this->response->setHeader('Content-Type', 'application/json');
        $this->response->setStatus(500);
        return implode('\r\n', $errors);
    }

    /**
     * @return array
     * @throws RuntimeException
     */
    private function runChecks(): array
    {
        if (empty($this->checks)) {
            return [];
        }

        $checks = $this->checks;
        if (\is_string($this->checks)) {
            $checks = [$this->checks];
        }

        $errors = [];
        foreach ($checks as $class) {
            if (!\class_exists($class)) {
                throw new RuntimeException('The class "' . $class . '" does not exist.');
            }
            $interfaces = class_implements($class);
            if (!($interfaces && \in_array(CheckInterface::class, $interfaces, true))) {
                throw new RuntimeException('The class "' . $class . '" does not implement "' . CheckInterface::class . '".');
            }

            $fallbackMessage = $class . 'didn\'t pass the check.';
            try {
                /* @var $instance CheckInterface */
                $instance = new $class();
                if (!$instance->run()) {
                    $errors[] = $fallbackMessage;
                }
            } catch (\Exception $ex) {
                $errors[] = !empty($ex->getMessage())
                    ? $ex->getMessage()
                    : $fallbackMessage;
            }
        }

        return $errors;
    }
}
