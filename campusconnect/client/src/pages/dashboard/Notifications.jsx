import { Link } from 'react-router-dom';
import { api } from '../../lib/api.js';
import { useAuth, useFetch, useToast } from '../../lib/store.jsx';
import { Empty, Spinner } from '../../components/ui.jsx';
import { timeAgo } from '../../lib/format.js';

export default function Notifications() {
  const { refreshCounts } = useAuth();
  const toast = useToast();
  const { data, loading, reload } = useFetch('/notifications?limit=100');
  const items = data?.notifications ?? [];

  async function markAll() {
    await api.post('/notifications/read-all');
    toast.success('All notifications marked as read.');
    refreshCounts();
    reload();
  }

  async function dismiss(n) {
    await api.del(`/notifications/${n.id}`);
    refreshCounts();
    reload();
  }

  async function open(n) {
    if (!n.is_read) { await api.patch(`/notifications/${n.id}/read`); refreshCounts(); reload(); }
  }

  return (
    <div className="stack">
      <div className="spread">
        <h2 style={{ margin: 0 }}>Notifications</h2>
        {data?.unread_count > 0 && <button className="btn sm" onClick={markAll}>Mark all read ({data.unread_count})</button>}
      </div>

      {loading && <Spinner />}

      {!loading && items.length === 0 && (
        <Empty icon="🔔" title="Nothing to show">
          You will be notified about messages, saved-search matches, application updates, reviews and report outcomes.
        </Empty>
      )}

      {items.length > 0 && (
        <div className="card">
          {items.map((n) => (
            <div key={n.id} className={`notif ${n.is_read ? '' : 'unread'}`}>
              {!n.is_read && <span className="dot" />}
              <div className="grow" style={{ marginLeft: n.is_read ? '1.1rem' : 0 }}>
                <div className="small strong">{n.title}</div>
                {n.body && <div className="small muted">{n.body}</div>}
                <div className="small muted">{timeAgo(n.created_at)}</div>
              </div>
              <div className="row">
                {n.link && <Link to={n.link} className="btn ghost sm" onClick={() => open(n)}>Open</Link>}
                <button className="btn ghost sm" onClick={() => dismiss(n)} aria-label="Dismiss">✕</button>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
