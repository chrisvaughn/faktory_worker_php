<?php

namespace FaktoryQueue;

class FaktoryWorker
{
    private FaktoryClient $client;
    private array $queues;
    private array $jobTypes = [];
    private $id = null;
    private $sendHeartbeatEvery = 15; # seconds
    private $lastHeartbeat;
    private $currentState = "";
    private $labels;

    public function __construct($client, $processId = null, $labels = array())
    {
        $this->client = $client;
        $this->queues = array('default');
        $this->id = $processId ?: substr(sha1(rand()), 0, 8);
        $this->client->setWorker($this);
        $this->lastHeartbeat = time();
        $this->labels = $labels;
    }

    public function getID()
    {
        return $this->id;
    }

    public function getLabels()
    {
        return $this->labels;
    }

    public function setQueues($queues)
    {
        $this->queues = $queues;
    }

    public function register($jobType, $callable)
    {
        $this->jobTypes[$jobType] = $callable;
    }

    public function run()
    {
        do {
            if ($this->shouldSendHeartbeat()) {
                $response = $this->client->heartbeat($this->currentState);
                if ($response !== false) {
                    $this->lastHeartbeat = time();
                }

                if (is_array($response) && array_key_exists("state", $response)) {
                    $this->currentState = $response["state"];
                }
            }

            if (!$this->shouldFetch()) {
                continue;
            }

            $job = $this->client->fetch($this->queues);

            if ($job) {
                $callable = $this->jobTypes[$job['jobtype']];

                try {
                    call_user_func($callable, $job);
                    $this->client->ack($job['jid']);
                } catch (\Throwable $e) {
                    $this->client->fail($job['jid']);
                }
            }

            usleep(250);
        } while ($this->currentState !== "terminate");

        $this->client->end();
        $this->client->close();
    }

    private function shouldFetch(): bool
    {
        return $this->currentState == "";
    }

    private function shouldSendHeartbeat(): bool
    {
        return time() > $this->lastHeartbeat + $this->sendHeartbeatEvery;
    }
}
