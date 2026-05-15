'use strict';

const SYSTEM_PROMPT = `You are an expert SEO copywriter. You produce blog posts that
rank well on Google and convert readers into customers.

Hard rules:
- Write in the language requested by the user. If the user writes Georgian,
  reply in Georgian; if English, reply in English.
- Output strictly valid JSON matching the schema the user provides. No markdown
  fences, no commentary outside the JSON.
- Body must be valid HTML using <h2>, <h3>, <p>, <ul>, <li>, <strong>, <a>.
  Never include <html>, <head> or <body> wrappers.
- Use the primary keyword in the title, first paragraph, at least one H2 and
  the meta description. Keep keyword density natural (~1-2%).
- Meta title <= 60 chars. Meta description 140-160 chars. Slug is lowercase,
  hyphen-separated, ASCII where possible.
- Structure: hook intro, 3-6 H2 sections (each with 1-3 paragraphs), a short
  FAQ with 3 questions as <h3>, then a clear CTA paragraph.
- Be specific, avoid filler, no hallucinated statistics.`;

function buildUserPrompt({ topic, keywords, language, audience, tone, wordCount, cta }) {
  const kw = (keywords && keywords.length) ? keywords.join(', ') : topic;
  return `Write an SEO-optimized blog post.

Topic: ${topic}
Primary keyword: ${kw.split(',')[0].trim()}
Secondary keywords: ${kw}
Language: ${language || 'English'}
Target audience: ${audience || 'general readers / potential customers'}
Tone: ${tone || 'professional, friendly, persuasive'}
Approx. word count: ${wordCount || 900}
Call to action: ${cta || 'invite the reader to contact us / try the service'}

Return JSON with exactly these keys:
{
  "title":            string,   // <h1> headline, includes primary keyword
  "meta_title":       string,   // <= 60 chars
  "meta_description": string,   // 140-160 chars
  "slug":             string,
  "tags":             string[], // 4-8 tags
  "excerpt":          string,   // 1-2 sentences
  "body_html":        string,   // full HTML body
  "focus_keyword":    string,
  "schema_faq":       [ { "q": string, "a": string } ]  // 3 items
}`;
}

module.exports = { SYSTEM_PROMPT, buildUserPrompt };
