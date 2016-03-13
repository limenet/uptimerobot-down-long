<?php

require_once 'vendor/autoload.php';

use UptimeRobot\API as UptimeRobot;

$climate = new League\CLImate\CLImate();

$dotenv = new Dotenv\Dotenv(__DIR__);

try {
    $dotenv->load();

    $dotenv->required(['UPTIMEROBOT_APIKEY', 'PUSHOVER_APIKEY', 'PUSHOVER_USERKEY', 'MONITOR_UPTIME_THRESHOLD'])->notEmpty();
    $dotenv->required('MONITOR_UPTIME_THRESHOLD')->isInteger();

    $uptimeRobot = new UptimeRobot([
        'apiKey' => getenv('UPTIMEROBOT_APIKEY'),
        'url'    => 'http://api.uptimerobot.com',
    ]);

    $results = $uptimeRobot->request('/getMonitors', [
        'statuses'          => 9,
        'customUptimeRatio' => 1,
    ]);

    $monitors = [];

    if (is_array($results['monitors']['monitor'])) {
        foreach ($results['monitors']['monitor'] as $monitor) {
            $id = $monitor['id'];
            $data = [
                'name'        => $monitor['friendlyname'],
                'url'         => $monitor['url'],
                'status'      => (int) $monitor['status'],
                'uptimeRatio' => (int) $monitor['customuptimeratio'],
            ];

            if ($data['status'] === 9 && $data['uptimeRatio'] < getenv('MONITOR_UPTIME_THRESHOLD')) {
                $monitors[$id] = $data;
            }
        }
    }

    $alertedMonitorsFilename = 'monitors-alerted.txt';
    if (!file_exists($alertedMonitorsFilename)) {
        touch($alertedMonitorsFilename);
    }
    $alertedMonitors = file($alertedMonitorsFilename);

    $pushy = new \Pushy\Client(getenv('PUSHOVER_APIKEY'));
    $user = new \Pushy\User(getenv('PUSHOVER_USERKEY'));

    foreach ($monitors as $id => $monitor) {
        if (!in_array($id, $alertedMonitors, true)) {
            $message = (new \Pushy\Message())
                ->setTitle('STILL DOWN: '.$monitor['name'])
                ->setMessage('Uptime ratio is '.$monitor['uptimeRatio'].'%')
                ->setUser($user)
                ->setUrl($monitor['url']);
            $pushy->sendMessage($message);
        }
    }

    file_put_contents($alertedMonitorsFilename, implode("\n", array_keys($monitors)));
} catch (Exception $e) {
    $climate->error($e->getMessage());
}
