# SEO AI plugin

A Soro-like SEO content helper: generates SEO-optimized blog posts with
Anthropic Claude and (optionally) publishes them to WordPress via the
REST API.

## Layout

```
app/plugins/seo-ai/
  index.js        public API: generatePost, publishPost, generateAndPublish
  generator.js    Claude API call + JSON parsing + validation
  wordpress.js    WordPress REST publisher (Basic auth with app password)
  prompts.js      system + user prompt templates
  cli.js          standalone CLI entry point
app/controllers/seo-ai.js   Express-style HTTP controller
```

## Environment

| Variable             | Required for | Notes                                            |
| -------------------- | ------------ | ------------------------------------------------ |
| `ANTHROPIC_API_KEY`  | generation   | https://console.anthropic.com/                   |
| `SEO_AI_MODEL`       | optional     | defaults to `claude-sonnet-4-6`                  |
| `WP_SITE_URL`        | publishing   | e.g. `https://example.com`                       |
| `WP_USERNAME`        | publishing   | WordPress user                                   |
| `WP_APP_PASSWORD`    | publishing   | WordPress *Application Password* (Users -> Edit) |

## Install

```
npm install
```

## CLI

```
node app/plugins/seo-ai/cli.js \
  --topic "საუკეთესო სარბენი ფეხსაცმელი 2026" \
  --keywords "სარბენი ფეხსაცმელი, მარათონი, ამორტიზაცია" \
  --language Georgian \
  --words 900 \
  --out post.json \
  --publish --status draft
```

Without `--publish` the post JSON is written to stdout or `--out`.

## Programmatic

```js
const seoAi = require('./app/plugins/seo-ai');

const post = await seoAi.generatePost({
  topic: 'best running shoes 2026',
  keywords: ['running shoes', 'marathon'],
  language: 'English',
  wordCount: 900
});

await seoAi.publishPost(post, { status: 'draft' });
```

## Express integration

```js
const seoAi = require('./app/controllers/seo-ai');
app.post('/api/seo/generate', seoAi.generate);
app.post('/api/seo/publish',  seoAi.publish);
app.post('/api/seo/post',     seoAi.generateAndPublish);
```

## Output shape

`generatePost` returns:

```json
{
  "title": "...", "meta_title": "...", "meta_description": "...",
  "slug": "...", "tags": ["..."], "excerpt": "...",
  "body_html": "<h2>...</h2><p>...</p>",
  "focus_keyword": "...",
  "schema_faq": [{ "q": "...", "a": "..." }]
}
```
