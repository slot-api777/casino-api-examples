<?php
// php api-example.php providers | games <PROVIDER_CODE> | launch <user_code> <PROVIDER_CODE> <game_code> [lang]
require __DIR__ . '/api.php';

$args = array_slice($argv, 1);
$command = $args[0] ?? '';

try {
    if ($command === 'providers') {
        foreach (call_api('provider_list')['providers'] as $p) {
            printf("%-16s %-6s %-11s %s\n", $p['code'], $p['type'], $p['status'] === 1 ? 'open' : 'maintenance', $p['name']);
        }
    } elseif ($command === 'games' && isset($args[1])) {
        foreach (call_api('game_list', ['provider_code' => $args[1]])['games'] as $g) {
            printf("%-28s %s\n", $g['game_code'], $g['game_name']);
        }
    } elseif ($command === 'launch' && count($args) >= 4) {
        $result = call_api('game_launch', [
            'user_code' => $args[1],
            'provider_code' => $args[2],
            'game_code' => $args[3],
            'lang' => $args[4] ?? 'en',
            'lobby_url' => 'https://your-site.com/lobby',
        ]);
        echo $result['launch_url'], "\n";
    } else {
        fwrite(STDERR, "usage: php api-example.php providers | games <PROVIDER_CODE> | launch <user_code> <PROVIDER_CODE> <game_code> [lang]\n");
        exit(2);
    }
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
