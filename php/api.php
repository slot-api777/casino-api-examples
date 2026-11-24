<?php
// A minimal client for the NexusGGR casino API: one endpoint, JSON over HTTPS, a "method" field per call.
// Docs: https://my.nexusggr.com/app/docs

function call_api(string $method, array $fields = []): array
{
    $endpoint = getenv('API_ENDPOINT');
    $agentCode = getenv('AGENT_CODE');
    $agentToken = getenv('AGENT_TOKEN');
    if (!$endpoint || !$agentCode || !$agentToken) {
        throw new RuntimeException('Set API_ENDPOINT, AGENT_CODE and AGENT_TOKEN (API Settings page of the back office).');
    }
    $body = json_encode(array_merge(['method' => $method, 'agent_code' => $agentCode, 'agent_token' => $agentToken], $fields));

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
    ]);
    $raw = curl_exec($ch);
    $error = curl_error($ch);
    if ($raw === false) {
        throw new RuntimeException("$method: $error");
    }
    $result = json_decode($raw, true);
    // decide on status or code, never on the message text: status 1 / code 7777777 is success, a 7770xxx code a failure
    if (!is_array($result) || ($result['status'] ?? 0) !== 1) {
        throw new RuntimeException("$method failed: " . ($result['code'] ?? '?') . ' ' . ($result['msg'] ?? $raw));
    }
    return $result;
}
