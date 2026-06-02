export type Locale = "ka" | "en" | "ru";

const messages = {
  ka: {
    loading: "იტვირთება",
    error: "შეცდომა",
  },
  en: {
    loading: "Loading",
    error: "Error",
  },
  ru: {
    loading: "Загрузка",
    error: "Ошибка",
  },
} satisfies Record<Locale, Record<string, string>>;

export function t(locale: Locale, key: keyof typeof messages.en): string {
  return messages[locale]?.[key] ?? messages.en[key];
}
