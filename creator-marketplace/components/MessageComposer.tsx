'use client';

import { useState } from 'react';
import { scanContactInfo } from '@/lib/contact-guard';

export function MessageComposer() {
  const [value, setValue] = useState('');
  const scan = scanContactInfo(value);

  function onSend(e: React.FormEvent) {
    e.preventDefault();
    if (!value.trim()) return;
    if (scan.hasViolations) {
      // In production we'd still send the redacted version + flag the user.
      alert(
        'შენი შეტყობინება შეიცავს პირად საკონტაქტო ინფორმაციას (ნომერი, ელ-ფოსტა, Telegram).\nგამოაგზავნე მხოლოდ პლატფორმაზე — გვერდის ავლა აკრძალულია ხელშეკრულებით.',
      );
      return;
    }
    setValue('');
  }

  return (
    <form onSubmit={onSend} className="flex items-center gap-2">
      <button type="button" className="btn-ghost shrink-0" aria-label="attach">
        📎
      </button>
      <div className="flex-1">
        <input
          className={`input ${scan.hasViolations ? 'border-amber-400 focus:ring-amber-100 focus:border-amber-500' : ''}`}
          placeholder="დაწერე შეტყობინება..."
          value={value}
          onChange={(e) => setValue(e.target.value)}
        />
        {scan.hasViolations && (
          <p className="text-[11px] text-amber-700 mt-1">
            ⚠ აღმოჩენილია პირადი საკონტაქტო ინფორმაცია — ვერ გაიგზავნება. გადახდები მხოლოდ პლატფორმაზე.
          </p>
        )}
      </div>
      <button
        type="submit"
        className="btn-primary shrink-0 disabled:opacity-50"
        disabled={scan.hasViolations || !value.trim()}
      >
        გაგზავნა
      </button>
    </form>
  );
}
