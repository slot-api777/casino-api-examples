// A minimal client for the NexusGGR casino API: one endpoint, JSON over HTTPS, a "method" field per call.
// Docs: https://my.nexusggr.com/app/docs

const { API_ENDPOINT, AGENT_CODE, AGENT_TOKEN } = process.env;

export async function callApi(method, fields = {}) {
    if (!API_ENDPOINT || !AGENT_CODE || !AGENT_TOKEN) throw new Error("Set API_ENDPOINT, AGENT_CODE and AGENT_TOKEN (API Settings page of the back office).");
    const response = await fetch(API_ENDPOINT, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ method, agent_code: AGENT_CODE, agent_token: AGENT_TOKEN, ...fields }),
        signal: AbortSignal.timeout(10000),
    });
    // every JSON answer comes with HTTP 200; a body that is not valid JSON is refused with 400
    const result = await response.json();
    // decide on status or code, never on the message text: status 1 / code 7777777 is success, a 7770xxx code a failure
    if (result.status !== 1) {
        const error = new Error(`${method} failed: ${result.code} ${result.msg}`);
        error.code = result.code;
        throw error;
    }
    return result;
}
