import { conversations, getMessages } from '@/lib/data/messages';
import { getCreatorById } from '@/lib/data/creators';
import { IconChat, IconShield } from '@/components/Icons';
import { MessageComposer } from '@/components/MessageComposer';
import Link from 'next/link';

export default function MessagesPage({ searchParams }: { searchParams: { conv?: string } }) {
  const activeId = searchParams.conv ?? conversations[0]?.id;
  const active = conversations.find((c) => c.id === activeId) ?? conversations[0];
  const messages = active ? getMessages(active.id) : [];
  const creator = active ? getCreatorById(active.creatorId) : null;

  return (
    <section className="container-page py-8">
      <div className="grid grid-cols-1 md:grid-cols-[320px_1fr] gap-4 card overflow-hidden h-[75vh]">
        {/* Conversation list */}
        <aside className="border-r border-ink-100 overflow-y-auto">
          <div className="p-4 border-b border-ink-100">
            <h2 className="font-bold text-ink-900 flex items-center gap-2">
              <IconChat /> საუბრები
            </h2>
            <input
              className="input mt-3 text-sm"
              placeholder="ძებნა..."
            />
          </div>
          <ul>
            {conversations.map((conv) => {
              const c = getCreatorById(conv.creatorId);
              const isActive = conv.id === active?.id;
              return (
                <li key={conv.id}>
                  <Link
                    href={`/messages?conv=${conv.id}`}
                    className={`flex items-center gap-3 p-4 border-b border-ink-100 hover:bg-ink-50 transition ${
                      isActive ? 'bg-brand-50' : ''
                    }`}
                  >
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img src={c?.avatar} alt="" className="h-11 w-11 rounded-full" />
                    <div className="flex-1 min-w-0">
                      <div className="flex justify-between items-center">
                        <p className="font-semibold text-sm text-ink-900 truncate">
                          {c?.nameKa}
                        </p>
                        <span className="text-[10px] muted">
                          {new Date(conv.lastMessageAt).toLocaleDateString('ka-GE', {
                            day: '2-digit',
                            month: '2-digit',
                          })}
                        </span>
                      </div>
                      <p className="text-xs muted truncate mt-0.5">{conv.lastMessage}</p>
                    </div>
                    {conv.unread > 0 && (
                      <span className="h-5 min-w-5 px-1.5 rounded-full bg-brand-600 text-white text-[10px] font-bold flex items-center justify-center">
                        {conv.unread}
                      </span>
                    )}
                  </Link>
                </li>
              );
            })}
          </ul>
        </aside>

        {/* Thread */}
        <div className="flex flex-col">
          {active && creator ? (
            <>
              <header className="flex items-center gap-3 p-4 border-b border-ink-100">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img src={creator.avatar} alt="" className="h-10 w-10 rounded-full" />
                <div className="flex-1">
                  <p className="font-semibold text-ink-900">{creator.nameKa}</p>
                  <p className="text-xs muted">
                    {active.orderId ? `შეკვეთა #${active.orderId.replace('o-', '')}` : 'საუბარი'}
                  </p>
                </div>
                <Link href={`/creator/${creator.slug}`} className="btn-ghost text-sm">პროფილი</Link>
              </header>

              <div className="flex-1 overflow-y-auto p-4 sm:p-6 space-y-4 bg-ink-50/60">
                {messages.map((m) => {
                  const isCreator = m.from === 'creator';
                  return (
                    <div
                      key={m.id}
                      className={`flex items-end gap-2 ${isCreator ? 'justify-start' : 'justify-end'}`}
                    >
                      {isCreator && (
                        // eslint-disable-next-line @next/next/no-img-element
                        <img src={m.authorAvatar} alt="" className="h-7 w-7 rounded-full" />
                      )}
                      <div className="max-w-[70%]">
                        <div
                          className={`px-4 py-2.5 rounded-2xl text-sm leading-relaxed ${
                            isCreator
                              ? 'bg-white border border-ink-200 rounded-bl-md'
                              : 'bg-brand-600 text-white rounded-br-md'
                          }`}
                        >
                          {m.text}
                          {m.attachments?.map((a) => (
                            <div
                              key={a}
                              className={`mt-2 rounded-lg p-2 text-xs flex items-center gap-2 ${
                                isCreator ? 'bg-ink-50 text-ink-700' : 'bg-white/15'
                              }`}
                            >
                              📎 {a}
                            </div>
                          ))}
                        </div>
                        <p className="text-[10px] muted mt-1 px-1">
                          {new Date(m.createdAt).toLocaleString('ka-GE', {
                            day: '2-digit',
                            month: 'short',
                            hour: '2-digit',
                            minute: '2-digit',
                          })}
                        </p>
                      </div>
                    </div>
                  );
                })}
              </div>

              <footer className="border-t border-ink-100 p-3 sm:p-4">
                <div className="mb-2 flex items-center gap-1.5 text-[11px] muted">
                  <IconShield className="text-brand-600 h-3 w-3" />
                  გადახდები და საკონტაქტო ინფორმაცია — მხოლოდ პლატფორმაზე. პირადი ნომერი/ელ-ფოსტა
                  ავტომატურად იბლოკება.
                </div>
                <MessageComposer />
              </footer>
            </>
          ) : (
            <div className="flex-1 flex items-center justify-center muted">
              ჯერ შეტყობინებები არ გაქვს.
            </div>
          )}
        </div>
      </div>
    </section>
  );
}
