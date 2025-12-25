<?php
date_default_timezone_set('Europe/Prague');

// --- 1. Handle the SSE Handshake (Discovery) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    echo "event: endpoint\n";
    echo "data: http://".$_SERVER['HTTP_HOST']."/\n\n";
    flush();
    sleep(5); 
    exit;
}

// --- 2. Handle Tool Requests (JSON-RPC) ---
$input = file_get_contents('php://input');
$request = json_decode($input, true);

if (!$request) exit;

$method = $request['method'] ?? '';
$id = $request['id'] ?? null;
$response = ['jsonrpc' => '2.0', 'id' => $id];

switch ($method) {
    case 'initialize':
        $response['result'] = [
            'protocolVersion' => '2024-11-05',
            'capabilities' => ['tools' => (object)[]],
            'serverInfo' => ['name' => 'php-mcp-server', 'version' => '1.0']
        ];
        break;

    case 'tools/list':
        $response['result'] = [
            'tools' => [
/////////
[
                'name' => 'get_time',
                'description' => 'Returns the full current date and time (Year, Month, Day, Time).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'timezone' => [
                            'type' => 'string',
                            'description' => "The timezone identifier (e.g., 'Europe/Prague')."
                        ]
                    ]
                ]
            ],
/////////
[
                'name' => 'get_minidlna',
                'description' => 'Returns video clip from database.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'movie' => [
                            'type' => 'string',
                            'description' => "Video name"
                        ]
                    ]
                ]
            ],
/////////
]
        ];
        break;

    case 'tools/call':
/////////////////
        if (($request['params']['name'] ?? '') === 'get_time') {
            $tz = $request['params']['arguments']['timezone'] ?? 'Europe/Prague';
            if (in_array($tz, timezone_identifiers_list())) {
                date_default_timezone_set($tz);
            } else {
                date_default_timezone_set('Europe/Prague');
            }

            $fullTime = date('l, F j, Y H:i:s');

            $response['result'] = [
                'content' => [[
                    'type' => 'text',
                    'text' => "The current full date and time in $tz is: $fullTime"
                ]]
            ];
        }
/////////////////
    if (($request['params']['name'] ?? '') === 'get_minidlna') {
    $name = $request['params']['arguments']['movie'];
    $name = str_replace(' ','%',$name);
    $movie='';
    $text='';
    $db = new SQLite3('/var/cache/minidlna/files.db', SQLITE3_OPEN_READONLY);
    $rs = $db->query("SELECT * FROM DETAILS WHERE TITLE LIKE '%$name%';");
    while($row = $rs->fetchArray()) {
        if ($movie == '') $movie .= $row[4].' ('.$row[17].')'; else $movie .= ' and '.$row[4].' ('.$row[17].')';
    }

    if ($movie == '') $text = 'Cannot find video in database';
    else $text = 'There is a video in database with name '.$movie;

            $response['result'] = [
                'content' => [[
                    'type' => 'text',
                    'text' => $text
                ]]
            ];
    }
////////////////


    break;
    default:
        $response['result'] = (object)[];
}

header('Content-Type: application/json');
echo json_encode($response);
