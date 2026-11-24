// A seamless wallet callback server for the NexusGGR casino API: answers user_balance and transaction on POST /gold_api.
// Rules: https://my.nexusggr.com/app/docs/integration/seamless-api
//
// Balances and applied transaction IDs live in memory so the example runs with nothing installed. A real wallet keeps them
// in its database, applies each transaction in a database transaction and stores txn_id under a unique key.
import http from "node:http";
import { timingSafeEqual } from "node:crypto";

const { AGENT_CODE, AGENT_SECRET, PORT = "8080" } = process.env;
if (!AGENT_CODE || !AGENT_SECRET) throw new Error("Set AGENT_CODE and AGENT_SECRET (API Settings page of the back office).");

const balances = new Map([["player_1001", 100000]]); // user_code -> balance (demo data)
const applied = new Set(); // txn_id of every transaction already applied

const balanceOf = (user) => balances.get(user) ?? 0;
const MAX_BODY = 64 * 1024; // a callback is a few hundred bytes

// compares in constant time, so the secret cannot be guessed from response times
function same(sent, expected) {
    if (typeof sent !== "string") return false;
    const a = Buffer.from(sent);
    const b = Buffer.from(expected);
    return a.length === b.length && timingSafeEqual(a, b);
}

function handle(body) {
    if (!same(body.agent_code, AGENT_CODE) || !same(body.agent_secret, AGENT_SECRET)) return { status: 0, msg: "INVALID_AGENT" };
    const user = String(body.user_code ?? "");
    if (!user) return { status: 0, msg: "INVALID_USER" };

    if (body.method === "user_balance") return { status: 1, user_balance: balanceOf(user) }; // even when it is 0

    if (body.method === "transaction") {
        // the game details are in the object named by game_type: slot, live, SB, MN or FH
        const game = body[body.game_type];
        if (!game || !game.txn_id) return { status: 0, msg: "INVALID_REQUEST" };
        // sent again after a timeout or a network error: already applied, so answer with the current balance
        if (applied.has(game.txn_id)) return { status: 1, user_balance: balanceOf(user) };
        const bet = Number(game.bet_money) || 0;
        const win = Number(game.win_money) || 0;
        // refusing a bet makes the platform roll it back; a transaction without a bet is never refused
        if (bet > 0 && balanceOf(user) < bet) return { status: 0, msg: "INSUFFICIENT_USER_FUNDS" };
        const next = Math.round((balanceOf(user) - bet + win) * 100) / 100;
        balances.set(user, next);
        applied.add(game.txn_id);
        return { status: 1, user_balance: next };
    }

    return { status: 0, msg: "UNKNOWN_METHOD" };
}

http
    .createServer((req, res) => {
        if (req.method !== "POST" || req.url !== "/gold_api") {
            res.writeHead(404).end();
            return;
        }
        let raw = "";
        let tooLarge = false;
        req.on("data", (chunk) => {
            if (tooLarge) return;
            raw += chunk;
            if (raw.length > MAX_BODY) {
                tooLarge = true;
                res.writeHead(413, { Connection: "close" }).end();
                req.destroy();
            }
        });
        req.on("end", () => {
            if (tooLarge) return;
            let answer;
            try {
                answer = handle(JSON.parse(raw));
            } catch {
                answer = { status: 0, msg: "INVALID_JSON" };
            }
            // always HTTP 200 with JSON: the platform reads status, and must get an answer within 5 seconds
            res.writeHead(200, { "Content-Type": "application/json" }).end(JSON.stringify(answer));
        });
    })
    .listen(Number(PORT), () => console.log(`seamless wallet callbacks on http://localhost:${PORT}/gold_api`));
