export default async function handler(req, res) {
  // CORS Headers
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Telegram-Bot-Api-Secret-Token');

  if (req.method === 'OPTIONS') {
    return res.status(200).end();
  }

  // Construct target URL to Telegram API
  // Incoming req.url will be like: /bot123456:ABC/getMe
  const path = req.url;
  const targetUrl = `https://api.telegram.org${path}`;

  try {
    const fetchOptions = {
      method: req.method,
      headers: {
        'Content-Type': req.headers['content-type'] || 'application/json',
      },
    };

    if (req.method !== 'GET' && req.method !== 'HEAD') {
      fetchOptions.body = typeof req.body === 'object' ? JSON.stringify(req.body) : req.body;
    }

    const tgRes = await fetch(targetUrl, fetchOptions);
    const data = await tgRes.text();

    res.status(tgRes.status);
    res.setHeader('Content-Type', tgRes.headers.get('content-type') || 'application/json');
    return res.send(data);
  } catch (err) {
    return res.status(502).json({
      ok: false,
      error_code: 502,
      description: 'Telegram Proxy Error: ' + (err.message || 'Unknown network error'),
    });
  }
}
