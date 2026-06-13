'use client';

import { useState } from 'react';
import { scanContactInfo, validateResume } from '@/lib/contact-guard';
import { IconCheck, IconShield } from './Icons';

export function ResumeUpload() {
  const [text, setText] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);
  const [redactedPreview, setRedactedPreview] = useState<string | null>(null);

  const scan = scanContactInfo(text);

  function onPasteOrChange(v: string) {
    setText(v);
    setError(null);
    setSuccess(false);
    setRedactedPreview(null);
  }

  function onUpload() {
    setError(null);
    setSuccess(false);
    const result = validateResume(text);
    if (!result.ok) {
      setError(result.reason ?? 'რეზიუმე ვერ აიტვირთა');
      setRedactedPreview(result.scan.clean);
      return;
    }
    setSuccess(true);
  }

  return (
    <div className="card p-6">
      <div className="flex items-start gap-3 mb-4">
        <span className="h-10 w-10 rounded-xl bg-brand-100 text-brand-700 flex items-center justify-center shrink-0">
          <IconShield />
        </span>
        <div>
          <h3 className="font-bold text-ink-900">რეზიუმე / CV ატვირთვა</h3>
          <p className="text-xs muted mt-0.5">
            აუცილებლად <strong>გარეშე ტელეფონის ნომრის</strong>. ჩვენ ვართ შუამავალი — ბრენდები
            დაგიკავშირდებიან მხოლოდ პლატფორმის შეტყობინებებით.
          </p>
        </div>
      </div>

      <div className="rounded-xl border-2 border-dashed border-ink-300 bg-ink-50 p-4 mb-3 text-center">
        <p className="text-sm muted">
          გადმოიტანე PDF / DOCX ფაილი ან{' '}
          <label className="link cursor-pointer">
            აირჩიე
            <input type="file" accept=".pdf,.doc,.docx" className="hidden" />
          </label>
        </p>
        <p className="text-xs muted mt-1">ან ჩასვი ტექსტი ქვემოთ</p>
      </div>

      <label className="label">რეზიუმეს ტექსტი (ჩასვი)</label>
      <textarea
        className="input min-h-[160px] font-mono text-xs"
        placeholder="ჩასვი რეზიუმე აქ — სახელი, გამოცდილება, ნამუშევრები, კატეგორია, ენები..."
        value={text}
        onChange={(e) => onPasteOrChange(e.target.value)}
      />

      {/* Live warning */}
      {(scan.detected.phones.length > 0 ||
        scan.detected.emails.length > 0 ||
        scan.detected.handles.length > 0 ||
        scan.detected.payments.length > 0) && (
        <div className="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
          <p className="font-semibold mb-1">⚠ აღმოჩენილია აკრძალული შინაარსი:</p>
          <ul className="text-xs space-y-0.5">
            {scan.detected.phones.length > 0 && (
              <li>• ტელეფონის ნომერი: {scan.detected.phones.length} ცალი</li>
            )}
            {scan.detected.emails.length > 0 && (
              <li>• ელ-ფოსტა: {scan.detected.emails.length} ცალი</li>
            )}
            {scan.detected.handles.length > 0 && (
              <li>• პირადი მესენჯერი: {scan.detected.handles.length} ცალი</li>
            )}
            {scan.detected.payments.length > 0 && (
              <li>• გადახდის რეკვიზიტი: {scan.detected.payments.length} ცალი</li>
            )}
          </ul>
          <p className="text-xs mt-2 muted">წაშალე ეს ინფორმაცია სანამ ატვირთავ.</p>
        </div>
      )}

      {error && (
        <div className="mt-3 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-900">
          ❌ {error}
        </div>
      )}

      {success && (
        <div className="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900 flex items-start gap-2">
          <span className="h-5 w-5 mt-0.5 rounded-full bg-emerald-600 text-white inline-flex items-center justify-center shrink-0">
            <IconCheck />
          </span>
          <span>რეზიუმე წარმატებით აიტვირთა და მზადაა ადმინისტრაციის შემოწმებისთვის.</span>
        </div>
      )}

      {redactedPreview && (
        <details className="mt-3 text-xs">
          <summary className="cursor-pointer link">↓ ნახე როგორ გამოიყურება ფარული ვერსია</summary>
          <pre className="mt-2 whitespace-pre-wrap bg-ink-50 p-3 rounded-lg border border-ink-200">
            {redactedPreview}
          </pre>
        </details>
      )}

      <div className="flex gap-2 mt-4">
        <button onClick={onUpload} type="button" className="btn-primary flex-1">
          ატვირთვა
        </button>
        <button
          type="button"
          onClick={() => onPasteOrChange('')}
          className="btn-secondary"
        >
          გასუფთავება
        </button>
      </div>

      <p className="text-[11px] muted mt-3 leading-relaxed">
        💡 რეზიუმე ხელმისაწვდომი იქნება მხოლოდ ვერიფიცირებული ბრენდებისთვის. ყველა
        პირადი საკონტაქტო ინფორმაცია ავტომატურად ფარდება. გადახდები ხდება მხოლოდ
        პლატფორმის გავლით — საკომისიო 12%.
      </p>
    </div>
  );
}
