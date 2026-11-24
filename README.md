# NexusGGR casino API examples

Working examples for the [NexusGGR casino API](https://nexusggr.com/casino-api): one integration for live casino, slots, Aviator, mini games, fish games, lottery and the Nexustrike sportsbook.

- **Overview:** [what the casino API includes](https://nexusggr.com/casino-api)
- **Step by step:** [integration guide](https://nexusggr.com/casino-api-integration)
- **Wallet models:** [seamless wallet or transfer wallet](https://nexusggr.com/seamless-wallet-api)
- **Reference:** [API documentation](https://my.nexusggr.com/app/docs)
- **Try the games:** [casino API demo](https://nexusggr.games/)

## What is here

| Folder | What it shows |
|---|---|
| `node/` | Calling the API (`provider_list`, `game_list`, `game_launch`), and a seamless wallet server that answers `user_balance` and `transaction` |
| `php/` | The same two things in PHP with cURL |
| `postman/` | A Postman collection of the main API methods |

Every request goes to one endpoint as JSON over HTTPS, with a `method` field that names the call. Your agent code, token, secret and API endpoint are on the API Settings page of the back office. Register your server's IP address in the IP whitelist there before the first call: requests from other addresses get no answer.

## Node.js (18 or newer, no dependencies)

```bash
cd node
export API_ENDPOINT="https://your-api-endpoint"   # from the API Settings page
export AGENT_CODE="your_agent_code"
export AGENT_TOKEN="your_agent_token"

node api-example.mjs providers                      # provider_list
node api-example.mjs games PRAGMATIC                # game_list for one provider
node api-example.mjs launch player_1001 PRAGMATIC vs20olympgate en
```

Seamless wallet: run the callback server. It speaks plain HTTP, so put it behind HTTPS (a reverse proxy such as nginx or Caddy), then enter that https address as the Site Endpoint on the API Settings page, without `/gold_api` (the platform adds it).

```bash
export AGENT_CODE="your_agent_code"
export AGENT_SECRET="your_agent_secret"
PORT=8080 node seamless-wallet-server.mjs
```

## PHP (7.4 or newer, with cURL)

```bash
cd php
API_ENDPOINT=... AGENT_CODE=... AGENT_TOKEN=... php api-example.php launch player_1001 PRAGMATIC vs20olympgate en
AGENT_CODE=... AGENT_SECRET=... php -S 127.0.0.1:8080 seamless-wallet.php
```

`php -S` is PHP's built-in server, for local testing only. In production, serve `seamless-wallet.php` from your web server behind HTTPS.

## Rules the wallet examples follow

These come from the [transaction callback documentation](https://my.nexusggr.com/app/docs/integration/seamless-api/transaction-site-endpoint):

- **Answer within 5 seconds** with `status: 1` and the player's new balance. No answer, a network error or a non-2xx status leaves the result unknown, and the transaction may be sent again.
- **Deduplicate on `txn_id`.** A transaction you have already applied is answered with the current balance and not applied again. `round_id` is shared by every transaction of one bet, so it cannot be used for this.
- **Refuse a bet you cannot cover** with `status: 0` and a `msg` such as `INSUFFICIENT_USER_FUNDS`. The platform rolls the bet back. A transaction without a bet is not refused by `status: 0`.
- **Check `agent_code` and `agent_secret`** on every callback. Both servers refuse to start without them.

The examples keep balances in memory (Node.js) or in a file (PHP) so they run with nothing installed. A real wallet keeps them in your database, applies each transaction in a database transaction, and stores `txn_id` under a unique key.

## Keep your credentials secret

Never commit your agent token or secret. Read them from the environment, as these examples do.

## Support

support@nexusggr.com
