<?php
// A seamless wallet callback endpoint for the NexusGGR casino API: answers user_balance and transaction on POST /gold_api.
// Rules: https://my.nexusggr.com/app/docs/integration/seamless-api
//
// Balances and applied transaction IDs live in a JSON file, locked for each request, so the example runs with nothing
// installed. A real wallet keeps them in its database, applies each transaction in a database transaction and stores
// txn_id under a unique key.

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) !== '/gold_api') {
    http_response_code(404);
    exit;
}

$agentCode = getenv('AGENT_CODE');
$agentSecret = getenv('AGENT_SECRET');
// without both, every callback would pass the check below: refuse to run
if (!is_string($agentCode) || $agentCode === '' || !is_string($agentSecret) || $agentSecret === '') {
    http_response_code(500);
    echo json_encode(['status' => 0, 'msg' => 'AGENT_CODE and AGENT_SECRET are not set']);
    exit;
}
$storeFile = getenv('WALLET_FILE') ?: sys_get_temp_dir() . '/nexusggr-wallet-example.json';

function answer(array $body): void
{
    // always HTTP 200 with JSON: the platform reads status, and must get an answer within 5 seconds
    echo json_encode($body);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    answer(['status' => 0, 'msg' => 'INVALID_JSON']);
}
$sentCode = $body['agent_code'] ?? null;
$sentSecret = $body['agent_secret'] ?? null;
// hash_equals compares in constant time, so the secret cannot be guessed from response times
if (!is_string($sentCode) || !is_string($sentSecret) || !hash_equals($agentCode, $sentCode) || !hash_equals($agentSecret, $sentSecret)) {
    answer(['status' => 0, 'msg' => 'INVALID_AGENT']);
}
$user = (string)($body['user_code'] ?? '');
if ($user === '') {
    answer(['status' => 0, 'msg' => 'INVALID_USER']);
}

$fp = fopen($storeFile, 'c+');
flock($fp, LOCK_EX);
$store = json_decode(stream_get_contents($fp), true) ?: ['balances' => ['player_1001' => 100000], 'applied' => []];
$balance = $store['balances'][$user] ?? 0;

function save($fp, array $store): void
{
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($store));
    fflush($fp);
}

$method = $body['method'] ?? '';
if ($method === 'user_balance') {
    answer(['status' => 1, 'user_balance' => $balance]); // even when it is 0
}

if ($method === 'transaction') {
    // the game details are in the object named by game_type: slot, live, SB, MN or FH
    $game = $body[$body['game_type'] ?? ''] ?? null;
    $txnId = is_array($game) ? (string)($game['txn_id'] ?? '') : '';
    if ($txnId === '') {
        answer(['status' => 0, 'msg' => 'INVALID_REQUEST']);
    }
    // sent again after a timeout or a network error: already applied, so answer with the current balance
    if (isset($store['applied'][$txnId])) {
        answer(['status' => 1, 'user_balance' => $balance]);
    }
    $bet = (float)($game['bet_money'] ?? 0);
    $win = (float)($game['win_money'] ?? 0);
    // refusing a bet makes the platform roll it back; a transaction without a bet is never refused
    if ($bet > 0 && $balance < $bet) {
        answer(['status' => 0, 'msg' => 'INSUFFICIENT_USER_FUNDS']);
    }
    $balance = round($balance - $bet + $win, 2);
    $store['balances'][$user] = $balance;
    $store['applied'][$txnId] = true;
    save($fp, $store);
    answer(['status' => 1, 'user_balance' => $balance]);
}

answer(['status' => 0, 'msg' => 'UNKNOWN_METHOD']);
