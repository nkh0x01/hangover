import http from "node:http";

const target = process.env.RIDE360_PROXY_TARGET ?? "https://ride.365sakartvelo.com";
const port = Number(process.env.RIDE360_PROXY_PORT ?? 8083);

function corsHeaders() {
  return {
    "Access-Control-Allow-Origin": "*",
    "Access-Control-Allow-Methods": "GET,POST,PUT,PATCH,DELETE,OPTIONS",
    "Access-Control-Allow-Headers":
      "authorization,content-type,x-app-version,x-platform,accept-language,x-device-id",
    "Access-Control-Max-Age": "86400",
  };
}

const server = http.createServer(async (req, res) => {
  if (req.method === "OPTIONS") {
    res.writeHead(204, corsHeaders());
    res.end();
    return;
  }

  if (!req.url?.startsWith("/api/")) {
    res.writeHead(404, { ...corsHeaders(), "Content-Type": "application/json" });
    res.end(JSON.stringify({ error: { code: "proxy.not_found" } }));
    return;
  }

  const chunks = [];
  for await (const chunk of req) chunks.push(chunk);
  const body = chunks.length > 0 ? Buffer.concat(chunks) : undefined;

  try {
    const upstream = await fetch(`${target}${req.url}`, {
      method: req.method,
      headers: {
        accept: req.headers.accept ?? "application/json",
        authorization: req.headers.authorization ?? "",
        "content-type": req.headers["content-type"] ?? "application/json",
        "x-app-version": req.headers["x-app-version"] ?? "0.1.0",
        "x-platform": req.headers["x-platform"] ?? "web",
        "accept-language": req.headers["accept-language"] ?? "ka",
        "x-device-id": req.headers["x-device-id"] ?? "",
      },
      body,
    });
    const responseBody = Buffer.from(await upstream.arrayBuffer());
    res.writeHead(upstream.status, {
      ...corsHeaders(),
      "Content-Type": upstream.headers.get("content-type") ?? "application/json",
      "X-Ride360-Proxy-Target": target,
    });
    res.end(responseBody);
  } catch (error) {
    res.writeHead(502, { ...corsHeaders(), "Content-Type": "application/json" });
    res.end(
      JSON.stringify({
        error: {
          code: "proxy.upstream_failed",
          message: error instanceof Error ? error.message : "Proxy failed",
        },
      }),
    );
  }
});

server.listen(port, () => {
  console.log(`Ride 360 dev API proxy listening on http://localhost:${port}`);
  console.log(`Proxy target: ${target}`);
});
