// node api-example.mjs providers | games <PROVIDER_CODE> | launch <user_code> <PROVIDER_CODE> <game_code> [lang]
import { callApi } from "./api.mjs";

const [command, ...args] = process.argv.slice(2);

try {
    if (command === "providers") {
        const { providers } = await callApi("provider_list");
        for (const p of providers) console.log(`${p.code.padEnd(16)} ${p.type.padEnd(6)} ${p.status === 1 ? "open" : "maintenance"}  ${p.name}`);
    } else if (command === "games" && args[0]) {
        const { games } = await callApi("game_list", { provider_code: args[0] });
        for (const g of games) console.log(`${String(g.game_code).padEnd(28)} ${g.game_name}`);
    } else if (command === "launch" && args.length >= 3) {
        const [user_code, provider_code, game_code, lang = "en"] = args;
        const { launch_url } = await callApi("game_launch", { user_code, provider_code, game_code, lang, lobby_url: "https://your-site.com/lobby" });
        console.log(launch_url);
    } else {
        console.log("usage: node api-example.mjs providers | games <PROVIDER_CODE> | launch <user_code> <PROVIDER_CODE> <game_code> [lang]");
        process.exit(2);
    }
} catch (error) {
    console.error(error.message);
    process.exit(1);
}
