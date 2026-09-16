import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { api } from '../../lib/api.js';
import { useAuth, useFetch, useToast } from '../../lib/store.jsx';
import { Avatar, Empty, Spinner, VerifiedBadge } from '../../components/ui.jsx';
import { dateTime, timeAgo } from '../../lib/format.js';

export default function Messages() {
  const { threadKey } = useParams();
  const { user, refreshCounts } = useAuth();
  const toast = useToast();
  const navigate = useNavigate();
  const threads = useFetch('/messages');
  const thread = useFetch(threadKey ? `/messages/thread/${threadKey}` : null, [threadKey], { skip: !threadKey });
  const [body, setBody] = useState('');
  const [busy, setBusy] = useState(false);
  const bottom = useRef(null);

  useEffect(() => {
    if (thread.data) { bottom.current?.scrollIntoView({ behavior: 'smooth' }); refreshCounts(); }
  }, [thread.data, refreshCounts]);

  async function send(e) {
    e.preventDefault();
    if (!body.trim()) return;
    const other = thread.data?.counterpart;
    setBusy(true);
    try {
      await api.post('/messages', {
        recipient_id: other.id,
        listing_id: thread.data.listing?.id ?? null,
        body,
      });
      setBody('');
      thread.reload();
      threads.reload();
    } catch (err) { toast.error(err.message); } finally { setBusy(false); }
  }

  const list = threads.data?.threads ?? [];

  return (
    <div className="stack">
      <h2 style={{ margin: 0 }}>Messages</h2>
      <p className="small muted" style={{ margin: 0 }}>
        Enquiries stay inside Campus Connect, so you never have to publish your phone number.
      </p>

      <div className="with-side" style={{ gridTemplateColumns: '300px 1fr' }}>
        <div className="card" style={{ overflow: 'hidden' }}>
          <div className="card-head"><strong className="small">Conversations</strong></div>
          <div className="thread-list">
            {threads.loading && <Spinner />}
            {!threads.loading && list.length === 0 && (
              <div className="small muted" style={{ padding: '1rem' }}>No conversations yet.</div>
            )}
            {list.map((t) => (
              <Link
                key={t.thread_key}
                to={`/dashboard/messages/${t.thread_key}`}
                className={`thread-item ${t.thread_key === threadKey ? 'on' : ''}`}
              >
                <Avatar user={{ full_name: t.counterpart.full_name, avatar_url: t.counterpart.avatar_url }} size="sm" />
                <div className="grow" style={{ minWidth: 0 }}>
                  <div className="spread" style={{ gap: '.35rem' }}>
                    <span className="t">{t.counterpart.full_name}</span>
                    {t.unread > 0 && <span className="badge danger">{t.unread}</span>}
                  </div>
                  {t.listing_title && <div className="p" style={{ color: 'var(--brand)' }}>{t.listing_title}</div>}
                  <div className="p">{t.preview}</div>
                  <div className="p">{timeAgo(t.last_at)}</div>
                </div>
              </Link>
            ))}
          </div>
        </div>

        <div className="card" style={{ overflow: 'hidden' }}>
          {!threadKey && (
            <Empty icon="✉️" title="Select a conversation">
              Pick a conversation on the left, or message someone from a listing page.
            </Empty>
          )}

          {threadKey && thread.loading && <Spinner />}
          {threadKey && thread.error && (
            <Empty icon="🚫" title={thread.error} action={<button className="btn" onClick={() => navigate('/dashboard/messages')}>Back to inbox</button>} />
          )}

          {threadKey && thread.data && (
            <>
              <div className="card-head">
                <div className="row">
                  <Avatar user={thread.data.counterpart} size="sm" />
                  <div>
                    <Link to={`/profile/${thread.data.counterpart.id}`} className="strong small">
                      {thread.data.counterpart.full_name}
                    </Link>
                    <div><VerifiedBadge status={thread.data.counterpart.verification_status} /></div>
                  </div>
                </div>
                {thread.data.listing && (
                  <Link to={`/listing/${thread.data.listing.id}`} className="small">
                    About: {thread.data.listing.title} →
                  </Link>
                )}
              </div>

              <div className="bubbles">
                {thread.data.messages.map((m) => (
                  <div key={m.id} className={`bubble ${m.sender_id === user.id ? 'me' : ''}`}>
                    <div>{m.body}</div>
                    <div className="when">{dateTime(m.created_at)}</div>
                  </div>
                ))}
                <div ref={bottom} />
              </div>

              <form onSubmit={send} className="row" style={{ padding: '.75rem', borderTop: '1px solid var(--line)' }}>
                <input
                  value={body}
                  onChange={(e) => setBody(e.target.value)}
                  placeholder="Write a reply…"
                  aria-label="Reply"
                />
                <button className="btn primary" disabled={busy || !body.trim()}>Send</button>
              </form>
            </>
          )}
        </div>
      </div>
    </div>
  );
}
