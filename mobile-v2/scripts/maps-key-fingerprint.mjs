import { createHash } from "node:crypto";

const key =
  process.argv[2] ??
  process.env.IOS_MAPS_API_KEY ??
  process.env.GOOGLE_MAPS_IOS_API_KEY ??
  process.env.GOOGLE_MAPS_API_KEY ??
  process.env.MAPS_API_KEY;

if (!key) {
  console.error(
    "Usage: node scripts/maps-key-fingerprint.mjs API_KEY_VALUE\n" +
      "Or set IOS_MAPS_API_KEY in the environment.",
  );
  process.exit(1);
}

const trimmed = key.trim();

console.log(
  JSON.stringify(
    {
      length: trimmed.length,
      sha256Prefix: createHash("sha256").update(trimmed).digest("hex").slice(0, 12),
    },
    null,
    2,
  ),
);
