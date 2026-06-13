export default function ContactPage() {
  return (
    <section className="container-page py-16 max-w-5xl">
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div>
          <h1 className="h-display">დაგვიკავშირდი</h1>
          <p className="muted text-lg mt-4">
            გვაქვს კითხვა ან გვინდა გავხდეთ პარტნიორი? შეავსე ფორმა და დაგიკავშირდებით 24 საათში.
          </p>

          <div className="space-y-4 mt-8 text-sm">
            <ContactRow label="ელ-ფოსტა" value="hello@kreatorebi.ge" />
            <ContactRow label="ტელეფონი" value="+995 32 2 12 34 56" />
            <ContactRow label="მისამართი" value="ი. ჭავჭავაძის გამზ. 50, თბილისი 0179, საქართველო" />
            <ContactRow label="სამუშაო საათები" value="ორშ–პარ, 10:00 – 19:00" />
          </div>
        </div>

        <form className="card p-6 sm:p-8 space-y-4">
          <div>
            <label className="label">სახელი</label>
            <input className="input" placeholder="გვარი სახელი" />
          </div>
          <div>
            <label className="label">ელ-ფოსტა</label>
            <input className="input" type="email" placeholder="you@example.com" />
          </div>
          <div>
            <label className="label">თემა</label>
            <select className="input">
              <option>ზოგადი კითხვა</option>
              <option>პრესა / პარტნიორობა</option>
              <option>ბრენდის შეთავაზება</option>
              <option>კრეატორების ვერიფიკაცია</option>
              <option>ტექნიკური პრობლემა</option>
            </select>
          </div>
          <div>
            <label className="label">შეტყობინება</label>
            <textarea className="input min-h-[140px]" placeholder="დაწერე შენი შეტყობინება..." />
          </div>
          <button type="button" className="btn-primary w-full py-3">გაგზავნა</button>
        </form>
      </div>
    </section>
  );
}

function ContactRow({ label, value }: { label: string; value: string }) {
  return (
    <div className="card p-4">
      <p className="text-xs muted">{label}</p>
      <p className="font-semibold text-ink-900 mt-1">{value}</p>
    </div>
  );
}
