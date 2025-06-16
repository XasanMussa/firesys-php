<?php
require 'vendor/autoload.php';
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory as LoopFactory;
use React\Socket\Server as ReactServer;

class NotificationServer implements MessageComponentInterface {
    protected $clients;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        // Broadcast to all clients except sender
        echo "Received message from client: $msg\n";
        foreach ($this->clients as $client) {
            if ($from !== $client) {
                $client->send($msg);
            }
        }
    }

    public function broadcast($msg) {
        echo "Broadcasting message to clients: $msg\n";
        foreach ($this->clients as $client) {
            $client->send($msg);
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
}

$loop = LoopFactory::create();
$notificationServer = new NotificationServer();

// WebSocket server on 8080
$socket = new ReactServer('0.0.0.0:8080', $loop);
$webSocket = new IoServer(
    new HttpServer(
        new WsServer(
            $notificationServer
        )
    ),
    $socket,
    $loop
);

// Direct socket for PHP push on 8090
$reactServer = new ReactServer('0.0.0.0:8090', $loop);
$reactServer->on('connection', function ($conn) use ($notificationServer) {
    $buffer = '';
    $conn->on('data', function ($data) use (&$buffer, $conn, $notificationServer) {
        $buffer .= $data;
        if (strpos($buffer, "\n") !== false) {
            $messages = explode("\n", $buffer);
            foreach ($messages as $msg) {
                if (trim($msg) !== '') {
                    echo "Received message from PHP push: $msg\n";
                    $notificationServer->broadcast($msg);
                }
            }
            $buffer = '';
        }
    });
});

$loop->run();
?>