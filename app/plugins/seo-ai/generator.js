'use strict';

const Anthropic = require('@anthropic-ai/sdk');
const { SYSTEM_PROMPT, buildUserPrompt } = require('./prompts');

const DEFAULT_MODEL = process.env.SEO_AI_MODEL || 'claude-sonnet-4-6';

function extractText(message) {
  return message.content
    .filter(b => b.type === 'text')
    .map(b => b.text)
    .join('')
    .trim();
}

function parseJson(text) {
  // Tolerate occasional ```json fences even though the prompt forbids them.
  const cleaned = text
    .replace(/^```(?:json)?\s*/i, '')
    .replace(/\s*```$/i, '')
    .trim();
  try {
    return JSON.parse(cleaned);
  } catch (err) {
    const first = cleaned.indexOf('{');
    const last = cleaned.lastIndexOf('}');
    if (first !== -1 && last > first) {
      return JSON.parse(cleaned.slice(first, last + 1));
    }
    throw new Error('SEO AI returned non-JSON output: ' + err.message);
  }
}

function validate(post) {
  const required = ['title', 'meta_title', 'meta_description', 'slug', 'body_html'];
  for (const key of required) {
    if (!post[key] || typeof post[key] !== 'string') {
      throw new Error(`SEO AI output missing required field: ${key}`);
    }
  }
  return post;
}

async function generatePost(input, opts = {}) {
  if (!input || !input.topic) {
    throw new Error('generatePost requires { topic }');
  }
  const apiKey = opts.apiKey || process.env.ANTHROPIC_API_KEY;
  if (!apiKey) {
    throw new Error('ANTHROPIC_API_KEY is not set');
  }

  const client = new Anthropic({ apiKey });
  const message = await client.messages.create({
    model: opts.model || DEFAULT_MODEL,
    max_tokens: opts.maxTokens || 4096,
    system: SYSTEM_PROMPT,
    messages: [{ role: 'user', content: buildUserPrompt(input) }]
  });

  const post = validate(parseJson(extractText(message)));
  post._model = message.model;
  post._usage = message.usage;
  return post;
}

module.exports = { generatePost };
