const ORIGIN = 'https://spawner-eight-delta.vercel.app';

function plainResponse(message, status) {
  return new Response(message, {
    status,
    headers: {
      'content-type': 'text/plain; charset=utf-8',
      'cache-control': 'no-store',
      'x-content-type-options': 'nosniff',
    },
  });
}

export default {
  async fetch(request, env) {
    if (!env.PROXY_SHARED_SECRET) {
      return plainResponse('Fallback proxy is not configured', 503);
    }

    const incomingUrl = new URL(request.url);
    const originUrl = new URL(incomingUrl.pathname + incomingUrl.search, ORIGIN);
    const headers = new Headers(request.headers);

    // Never forward client-supplied identity headers. Only this Worker may add
    // them, authenticated by a secret shared with the PHP application.
    headers.delete('host');
    headers.delete('x-beacon-proxy-secret');
    headers.delete('x-beacon-client-ip');
    headers.delete('x-beacon-client-country');
    headers.set('x-beacon-proxy-secret', env.PROXY_SHARED_SECRET);

    const clientIp = request.headers.get('cf-connecting-ip');
    if (clientIp) headers.set('x-beacon-client-ip', clientIp);

    const country = request.cf?.country;
    if (typeof country === 'string' && /^[A-Z]{2}$/.test(country)) {
      headers.set('x-beacon-client-country', country);
    }

    const init = {
      method: request.method,
      headers,
      redirect: 'manual',
      body: request.method === 'GET' || request.method === 'HEAD' ? undefined : request.body,
    };

    // Avoid stale sessions, balances, approvals, and audit information. Static
    // assets may still use the cache policy returned by Vercel.
    if (!incomingUrl.pathname.startsWith('/assets/')) init.cache = 'no-store';

    let originResponse;
    try {
      originResponse = await fetch(originUrl, init);
    } catch {
      return plainResponse('The primary service is temporarily unreachable', 502);
    }

    const responseHeaders = new Headers(originResponse.headers);
    const location = responseHeaders.get('location');
    if (location) {
      const absoluteLocation = new URL(location, ORIGIN);
      if (absoluteLocation.origin === ORIGIN) {
        absoluteLocation.protocol = incomingUrl.protocol;
        absoluteLocation.host = incomingUrl.host;
        responseHeaders.set('location', absoluteLocation.toString());
      }
    }
    responseHeaders.set('x-robots-tag', 'noindex, nofollow');

    return new Response(originResponse.body, {
      status: originResponse.status,
      statusText: originResponse.statusText,
      headers: responseHeaders,
    });
  },
};
