'use strict';

/*
 * Express-style controller for the SEO AI plugin. Wire it up in your router:
 *
 *   var seoAi = require('./controllers/seo-ai');
 *   app.post('/api/seo/generate', seoAi.generate);
 *   app.post('/api/seo/publish',  seoAi.publish);
 */

const plugin = require('../plugins/seo-ai');

function readBody(req) {
  if (req.body && typeof req.body === 'object') return Promise.resolve(req.body);
  return new Promise((resolve, reject) => {
    const chunks = [];
    req.on('data', c => chunks.push(c));
    req.on('end', () => {
      const raw = Buffer.concat(chunks).toString('utf8');
      try { resolve(raw ? JSON.parse(raw) : {}); }
      catch (e) { reject(new Error('Invalid JSON body')); }
    });
    req.on('error', reject);
  });
}

function send(res, status, payload) {
  res.statusCode = status;
  res.setHeader('Content-Type', 'application/json; charset=utf-8');
  res.end(JSON.stringify(payload));
}

exports.generate = async function (req, res) {
  try {
    const body = await readBody(req);
    if (!body.topic) return send(res, 400, { error: 'topic is required' });
    const post = await plugin.generatePost(body);
    send(res, 200, { post });
  } catch (err) {
    send(res, 500, { error: err.message });
  }
};

exports.publish = async function (req, res) {
  try {
    const body = await readBody(req);
    if (!body.post || !body.post.title) {
      return send(res, 400, { error: 'post object is required' });
    }
    const published = await plugin.publishPost(body.post, {
      status: body.status || 'draft'
    });
    send(res, 200, { published });
  } catch (err) {
    send(res, 500, { error: err.message });
  }
};

exports.generateAndPublish = async function (req, res) {
  try {
    const body = await readBody(req);
    if (!body.topic) return send(res, 400, { error: 'topic is required' });
    const result = await plugin.generateAndPublish(body, { status: body.status || 'draft' });
    send(res, 200, result);
  } catch (err) {
    send(res, 500, { error: err.message });
  }
};
