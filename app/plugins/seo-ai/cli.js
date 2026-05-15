#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');
const seoAi = require('./index');

function parseArgs(argv) {
  const args = { keywords: [], publish: false, status: 'draft' };
  for (let i = 2; i < argv.length; i++) {
    const a = argv[i];
    const next = () => argv[++i];
    switch (a) {
      case '-t': case '--topic':       args.topic = next(); break;
      case '-k': case '--keywords':    args.keywords = next().split(',').map(s => s.trim()); break;
      case '-l': case '--language':    args.language = next(); break;
      case '-a': case '--audience':    args.audience = next(); break;
      case '--tone':                   args.tone = next(); break;
      case '-w': case '--words':       args.wordCount = parseInt(next(), 10); break;
      case '--cta':                    args.cta = next(); break;
      case '-o': case '--out':         args.out = next(); break;
      case '--publish':                args.publish = true; break;
      case '--status':                 args.status = next(); break;
      case '-h': case '--help':        args.help = true; break;
      default:
        if (!args.topic) args.topic = a;
    }
  }
  return args;
}

function usage() {
  console.log(`
seo-ai — generate SEO-optimized blog posts with Claude

Usage:
  node app/plugins/seo-ai/cli.js --topic "best running shoes 2026" \\
       --keywords "running shoes, marathon, cushioning" \\
       --language English --words 1000 \\
       --out post.json [--publish --status draft]

Environment:
  ANTHROPIC_API_KEY   required for generation
  SEO_AI_MODEL        optional, default claude-sonnet-4-6
  WP_SITE_URL         e.g. https://example.com
  WP_USERNAME         WordPress username
  WP_APP_PASSWORD     WordPress application password (Users -> Profile)
`);
}

async function main() {
  const args = parseArgs(process.argv);
  if (args.help || !args.topic) {
    usage();
    process.exit(args.help ? 0 : 1);
  }

  console.error(`[seo-ai] generating post for: ${args.topic}`);
  const post = await seoAi.generatePost({
    topic:     args.topic,
    keywords:  args.keywords,
    language:  args.language,
    audience:  args.audience,
    tone:      args.tone,
    wordCount: args.wordCount,
    cta:       args.cta
  });

  const json = JSON.stringify(post, null, 2);
  if (args.out) {
    fs.writeFileSync(path.resolve(args.out), json);
    console.error(`[seo-ai] wrote ${args.out}`);
  } else {
    process.stdout.write(json + '\n');
  }

  if (args.publish) {
    console.error(`[seo-ai] publishing to WordPress as ${args.status}...`);
    const res = await seoAi.publishPost(post, { status: args.status });
    console.error(`[seo-ai] published id=${res.id} link=${res.link}`);
  }
}

main().catch(err => {
  console.error('[seo-ai] error:', err.message);
  process.exit(1);
});
