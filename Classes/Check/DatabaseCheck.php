<?php

namespace Anexia\Neos\Monitoring\Check;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\Doctrine\Exception\DatabaseConnectionException;
use Neos\Flow\Persistence\PersistenceManagerInterface;

class DatabaseCheck implements CheckInterface
{
    /**
     * @Flow\Inject
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * @return bool
     * @throws DatabaseConnectionException
     */
    public function run(): bool
    {
        if ($this->persistenceManager->isConnected()) {
            return true;
        }
        throw new DatabaseConnectionException('Could not connect to the database.');
    }
}
