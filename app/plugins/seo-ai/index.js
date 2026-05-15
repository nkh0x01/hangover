'use strict';

/*
 * SEO AI plugin — Soro-like helper that generates SEO-optimized blog posts
 * with Anthropic Claude and (optionally) publishes them to WordPress.
 *
 * Usage:
 *   const seoAi = require('./app/plugins/seo-ai');
 *   const post  = await seoAi.generatePost({ topic: 'best running shoes 2026' });
 *   await seoAi.publishPost(post, { status: 'draft' });
 */

const { generatePost } = require('./generator');
const { publishPost }  = require('./wordpress');

async function generateAndPublish(input, opts = {}) {
  const post = await generatePost(input, opts);
  const published = await publishPost(post, opts);
  return { post, published };
}

module.exports = { generatePost, publishPost, generateAndPublish };
