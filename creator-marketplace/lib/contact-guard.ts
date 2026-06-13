// Middle-man protection: detect and strip contact info from creator resumes,
// portfolio descriptions, and chat messages so creators can't bypass the
// platform by sharing direct contacts. The platform sits in the middle.
//
// We block:
//   - phone numbers (Georgian +995, also generic international + 7–15 digit forms)
//   - email addresses
//   - Telegram / WhatsApp / Viber handles
//   - direct social media handles (when prefixed with explicit "contact me" verbs)
//   - URLs that look like personal payment pages (revolut.me, paypal.me, etc.)
//
// This is intentionally aggressive on the registration/resume side; chat uses
// the same rules but only flags / replaces, doesn't reject.

const PHONE_PATTERNS: RegExp[] = [
  // Georgia: +995 5XX XX XX XX (with optional spaces, dashes, parens)
  /\+?\s*9\s*9\s*5[\s().-]*\d(?:[\s().-]*\d){6,9}/g,
  // Generic international: + then 7–15 digits with separators
  /\+\s*\d(?:[\s().-]*\d){6,14}/g,
  // Local Georgian mobile pattern (5XX XXX XXX), 9 digits starting with 5
  /\b5\d{2}[\s().-]?\d{2}[\s().-]?\d{2}[\s().-]?\d{2}\b/g,
  // Long unbroken digit run that looks phone-shaped
  /\b\d{9,15}\b/g,
];

const EMAIL_PATTERN = /[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/g;

const HANDLE_PATTERNS: RegExp[] = [
  /\b(?:telegram|tg|t\.me)[\s:/@]+[\w.-]{3,}/gi,
  /\b(?:whatsapp|wa\.me|whats\s*app)[\s:/@]+[+\d\w.-]{3,}/gi,
  /\b(?:viber|signal)[\s:/@]+[+\d\w.-]{3,}/gi,
  /\bt\.me\/[\w.-]{3,}/gi,
  /\bwa\.me\/[+\d]{6,}/gi,
];

const OFFPLATFORM_PAYMENT_PATTERNS: RegExp[] = [
  /\b(?:revolut|paypal|venmo|cash\s*app|wise|monobank|qiwi)\.(?:me|com)\b/gi,
  /\bIBAN[:\s]*[A-Z]{2}\d{2}[\s\d]{8,}/gi,
  /\bGE\d{2}\s?(?:[A-Z0-9]\s?){16}/g, // Georgian IBAN: GE + 2 digits + 16 alphanum
];

const SOLICITATION_PATTERNS: RegExp[] = [
  // "write to me on…", "reach me at…", "contact me…"
  /\b(?:contact|reach|message|call|whatsapp|telegram|email|დამიკავშირდი|დამირეკე|მომწერე)\s+(?:me|us|me\s+on)?\s*[:@]?\s*[\w+@./-]{3,}/gi,
];

export interface ContactScanResult {
  clean: string;
  redactions: number;
  detected: {
    phones: string[];
    emails: string[];
    handles: string[];
    payments: string[];
    solicitations: string[];
  };
  hasViolations: boolean;
}

const REDACTED = '[დაფარულია — გამოიყენე პლატფორმის მესენჯერი]';

export function scanContactInfo(text: string): ContactScanResult {
  const detected = {
    phones: [] as string[],
    emails: [] as string[],
    handles: [] as string[],
    payments: [] as string[],
    solicitations: [] as string[],
  };
  let clean = text;

  for (const re of PHONE_PATTERNS) {
    const matches = text.match(re);
    if (matches) detected.phones.push(...matches);
    clean = clean.replace(re, REDACTED);
  }
  const emails = text.match(EMAIL_PATTERN);
  if (emails) detected.emails.push(...emails);
  clean = clean.replace(EMAIL_PATTERN, REDACTED);

  for (const re of HANDLE_PATTERNS) {
    const m = text.match(re);
    if (m) detected.handles.push(...m);
    clean = clean.replace(re, REDACTED);
  }
  for (const re of OFFPLATFORM_PAYMENT_PATTERNS) {
    const m = text.match(re);
    if (m) detected.payments.push(...m);
    clean = clean.replace(re, REDACTED);
  }
  for (const re of SOLICITATION_PATTERNS) {
    const m = text.match(re);
    if (m) detected.solicitations.push(...m);
  }

  // dedupe
  for (const k of Object.keys(detected) as (keyof typeof detected)[]) {
    detected[k] = Array.from(new Set(detected[k]));
  }

  const redactions =
    detected.phones.length +
    detected.emails.length +
    detected.handles.length +
    detected.payments.length;

  return {
    clean,
    redactions,
    detected,
    hasViolations: redactions > 0 || detected.solicitations.length > 0,
  };
}

// For resumes: hard-reject any phone number. The platform is the middle-man;
// creators cannot publish their direct number on a public resume.
export function validateResume(text: string): { ok: boolean; reason?: string; scan: ContactScanResult } {
  const scan = scanContactInfo(text);
  if (scan.detected.phones.length > 0) {
    return {
      ok: false,
      reason: 'რეზიუმეში დაფიქსირდა ტელეფონის ნომერი. წაშალე და თავიდან ატვირთე — პლატფორმა შუამავალია და პირადი ნომერი არ უნდა იყოს საჯაროდ.',
      scan,
    };
  }
  if (scan.detected.emails.length > 0) {
    return {
      ok: false,
      reason: 'რეზიუმეში დაფიქსირდა პირადი ელ-ფოსტა. ბრენდები დაგიკავშირდებიან მხოლოდ პლატფორმის შეტყობინებებით.',
      scan,
    };
  }
  if (scan.detected.handles.length > 0) {
    return {
      ok: false,
      reason: 'რეზიუმეში დაფიქსირდა Telegram/WhatsApp/Viber ბმული. გადააფასე და ატვირთე ისე, რომ პირადი მესენჯერის ბმული არ შეიცავდეს.',
      scan,
    };
  }
  if (scan.detected.payments.length > 0) {
    return {
      ok: false,
      reason: 'რეზიუმეში დაფიქსირდა გადახდის რეკვიზიტი (IBAN / Revolut / PayPal). გადახდები ხდება მხოლოდ პლატფორმის გავლით.',
      scan,
    };
  }
  return { ok: true, scan };
}
