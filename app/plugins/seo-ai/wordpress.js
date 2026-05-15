'use strict';

const https = require('https');
const http = require('http');
const { URL } = require('url');

function request(method, urlStr, { auth, body } = {}) {
  return new Promise((resolve, reject) => {
    const url = new URL(urlStr);
    const lib = url.protocol === 'https:' ? https : http;
    const payload = body ? Buffer.from(JSON.stringify(body)) : null;

    const headers = { 'Accept': 'application/json' };
    if (payload) {
      headers['Content-Type'] = 'application/json';
      headers['Content-Length'] = payload.length;
    }
    if (auth) {
      headers['Authorization'] = 'Basic ' + Buffer.from(`${auth.user}:${auth.pass}`).toString('base64');
    }

    const req = lib.request({
      method,
      hostname: url.hostname,
      port: url.port || (url.protocol === 'https:' ? 443 : 80),
      path: url.pathname + url.search,
      headers
    }, res => {
      const chunks = [];
      res.on('data', c => chunks.push(c));
      res.on('end', () => {
        const text = Buffer.concat(chunks).toString('utf8');
        const parsed = text ? safeJson(text) : null;
        if (res.statusCode >= 200 && res.statusCode < 300) {
          resolve(parsed);
        } else {
          const msg = (parsed && parsed.message) || text || res.statusMessage;
          reject(new Error(`WordPress ${method} ${url.pathname} -> ${res.statusCode}: ${msg}`));
        }
      });
    });
    req.on('error', reject);
    if (payload) req.write(payload);
    req.end();
  });
}

function safeJson(text) {
  try { return JSON.parse(text); } catch (_) { return text; }
}

function buildClient(opts = {}) {
  const siteUrl = (opts.siteUrl || process.env.WP_SITE_URL || '').replace(/\/+$/, '');
  const user = opts.user || process.env.WP_USERNAME;
  const pass = opts.appPassword || process.env.WP_APP_PASSWORD;
  if (!siteUrl) throw new Error('WP_SITE_URL is not set');
  if (!user || !pass) throw new Error('WP_USERNAME / WP_APP_PASSWORD are not set');
  return { siteUrl, auth: { user, pass } };
}

async function ensureTags(client, names = []) {
  const ids = [];
  for (const name of names) {
    const search = await request('GET',
      `${client.siteUrl}/wp-json/wp/v2/tags?search=${encodeURIComponent(name)}&per_page=10`,
      { auth: client.auth });
    const hit = Array.isArray(search) && search.find(t => t.name.toLowerCase() === name.toLowerCase());
    if (hit) {
      ids.push(hit.id);
    } else {
      const created = await request('POST', `${client.siteUrl}/wp-json/wp/v2/tags`,
        { auth: client.auth, body: { name } });
      if (created && created.id) ids.push(created.id);
    }
  }
  return ids;
}

async function publishPost(post, opts = {}) {
  const client = buildClient(opts);
  const status = opts.status || 'draft';
  const tagIds = post.tags ? await ensureTags(client, post.tags) : [];

  const body = {
    title: post.title,
    slug: post.slug,
    status,
    excerpt: post.excerpt || '',
    content: post.body_html,
    tags: tagIds,
    meta: {
      _yoast_wpseo_title: post.meta_title,
      _yoast_wpseo_metadesc: post.meta_description,
      _yoast_wpseo_focuskw: post.focus_keyword || ''
    }
  };

  return request('POST', `${client.siteUrl}/wp-json/wp/v2/posts`,
    { auth: client.auth, body });
}

module.exports = { publishPost };
