<?php
namespace CouchDB\Http;

/**
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 */
class LoggingClient implements ClientInterface
{
    protected $stack;

    /**
     * @var ClientInterface
     */
    protected $client;

    /**
     * @var integer
     */
    protected $totalDuration;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
        $this->totalDuration = 0;
        $this->stack = new \SplStack();
    }

    /**
     * {@inheritDoc}
     */
    public function connect()
    {
        return $this->client->connect();
    }

    /**
     * {@inheritDoc}
     */
    public function isConnected()
    {
        return $this->client->isConnected();
    }

    /**
     * {@inheritDoc}
     */
    public function request($path, $method = ClientInterface::METHOD_GET, $data = '', array $headers = array())
    {
        $start = microtime(true);
        $response = $this->client->request($path, $method, $data, $headers);
        $duration = microtime(true) - $start;

        $this->stack->push(array(
            'duration'         => $duration,

            'request_method'   => $method,
            'request_path'     => $path,
            'request_data'     => $data,
            'request_headers'  => $headers,

            'response_headers' => $response->getHeaders(),
            'response_status'  => $response->getStatusCode(),
            'response_body'    => $response->getContent()
        ));

        $this->totalDuration += $duration;

        return $response;
    }

    /**
     * Gets the total duration
     *
     * @return integer
     */
    public function getTotalDuration()
    {
        return $this->totalDuration;
    }

    /**
     * Get the logging stack
     *
     * @return \SplStack
     */
    public function getStack()
    {
        return $this->stack;
    }
}
