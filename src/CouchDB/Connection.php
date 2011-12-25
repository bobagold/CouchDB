<?php
namespace CouchDB;

use CouchDB\Http\ClientInterface;
use CouchDB\Events\EventArgs;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 */
class Connection
{
    /**
     * @var \CouchDB\Http\ClientInterface
     */
    private $client;

    /**
     * @var \Symfony\Component\EventDispatcher\EventDispatcher
     */
    private $dispatcher;

    /**
     * @var \CouchDB\Configuration
     */
    private $configuration;

    public function __construct(ClientInterface $client, Configuration $config = null, EventDispatcher $dispatcher = null)
    {
        $this->client = $client;
        $this->dispatcher = $dispatcher ?: new EventDispatcher();
        $this->configuration = $config ?: new Configuration();
    }

    /**
     * Return the HTTP Client
     *
     * @return Http\ClientInterface
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Set the client
     *
     * @param Http\ClientInterface $client
     */
    public function setClient(ClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * Get the configuration
     *
     * @return Configuration
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    /**
     * Initialized the client
     */
    public function initialize()
    {
        if ($this->client->isConnected()) {
            return;
        }

        if ($this->dispatcher->hasListeners(Events::preConnect)) {
            $this->dispatcher->dispatch(Events::preConnect, new EventArgs($this));
        }

        $this->client->connect();

        if ($this->dispatcher->hasListeners(Events::postConnect)) {
            $this->dispatcher->dispatch(Events::postConnect, new EventArgs($this));
        }
    }

    /**
     * Check if the client is connected to the couchdb server
     *
     * @return bool
     */
    public function isConnected()
    {
        return $this->client->isConnected();
    }

    /**
     * Get the couchdb version
     *
     * @return string
     */
    public function version()
    {
        $this->initialize();
        $json  = $this->client->request('/')->getContent();
        $value = $this->configuration->getEncoder()->decode($json);

        return $value['version'];
    }

    /**
     * Show all databases
     *
     * @return array
     */
    public function listDatabases()
    {
        $this->initialize();
        $json      = $this->client->request('/_all_dbs')->getContent();
        $databases = $this->configuration->getEncoder()->decode($json);
        return $databases;
    }

    /**
     * Drop a database
     *
     * @param string $name
     * @return bool
     */
    public function dropDatabase($name)
    {
        $this->initialize();
        if ($this->dispatcher->hasListeners(Events::preDropDatabase)) {
            $this->dispatcher->dispatch(Events::preDropDatabase, new EventArgs($this, $name));
        }

        $json = $this->client->request("/{$name}", ClientInterface::METHOD_DELETE)->getContent();
        $status = $this->configuration->getEncoder()->decode($json);

        if ($this->dispatcher->hasListeners(Events::postDropDatabase)) {
            $this->dispatcher->dispatch(Events::postDropDatabase, new EventArgs($this, $name));
        }

        return isset($status['ok']) && $status['ok'] === true;
    }

    /**
     * Select a database
     *
     * @param string $name
     * @return Database
     */
    public function selectDatabase($name)
    {
        $this->initialize();
        return $this->wrapDatabase($name);
    }

    /**
     * Create a new database
     *
     * @param string $name
     * @return Database
     * @throws \RuntimeException If the database could not be created
     */
    public function createDatabase($name)
    {
        if (!preg_match('@[a-z0-9_$\(\)+\-]@', $name)) {
            throw new \RuntimeException(sprintf('The database name % is invalid. The database name must match the following pattern (a-z0-9_$()+-', $name));
        }

        $this->initialize();

        if ($this->dispatcher->hasListeners(Events::preCreateDatabase)) {
            $this->dispatcher->dispatch(Events::preCreateDatabase, new EventArgs($this, $name));
        }

        $json  = $this->client->request("/{$name}/", ClientInterface::METHOD_PUT);
        $value = $this->configuration->getEncoder()->decode($json);

        if (!isset($value['ok']) || (isset($value['ok']) && $value['ok'] !== true)) {
            throw new \RuntimeException(sprintf('Failed to create database %s', $name));
        }

        $database = $this->wrapDatabase($name);

        if ($this->dispatcher->hasListeners(Events::postCreateDatabase)) {
            $this->dispatcher->dispatch(Events::postCreateDatabase, new EventArgs($database));
        }

        return $database;
    }

    /**
     * Gets the database
     *
     * @param string $name
     * @return Database
     */
    public function __get($name)
    {
        return $this->selectDatabase($name);
    }

    /**
     * Drop a database
     *
     * @param string $name
     * @return bool
     */
    public function __unset($name)
    {
        return $this->dropDatabase($name);
    }

    /**
     * Wraps the database to a object
     *
     * @param string $name
     * @return Database
     */
    protected function wrapDatabase($name)
    {
        return new Database($name, $this->client, $this->dispatcher);
    }
}
