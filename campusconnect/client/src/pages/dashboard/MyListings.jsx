import { useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../../lib/api.js';
import { useFetch, useMeta, useToast } from '../../lib/store.jsx';
import { Empty, Modal, Spinner, Tabs } from '../../components/ui.jsx';
import { STATUS_LABELS, TYPE_ICONS, TYPE_LABELS, dateOnly, listingPrice, timeAgo } from '../../lib/format.js';

export default function MyListings() {
  const meta = useMeta();
  const toast = useToast();
  const { data, loading, reload } = useFetch('/listings/mine?limit=60');
  const [tab, setTab] = useState('all');
  const [confirm, setConfirm] = useState(null);

  const all = data?.listings ?? [];
  const filtered = tab === 'all' ? all
    : tab === 'active' ? all.filter((l) => l.status === 'active')
      : tab === 'archived' ? all.filter((l) => ['archived', 'removed'].includes(l.status))
        : all.filter((l) => ['sold', 'closed', 'filled', 'resolved'].includes(l.status));

  async function act(fn, message) {
    try { await fn(); toast.success(message); reload(); }
    catch (err) { toast.error(err.message); }
  }

  return (
    <div className="stack">
      <div className="spread">
        <h2 style={{ margin: 0 }}>My listings</h2>
        <Link to="/post" className="btn primary sm">+ New listing</Link>
      </div>

      <Tabs
        value={tab}
        onChange={setTab}
        tabs={[
          { key: 'all', label: 'All', count: all.length },
          { key: 'active', label: 'Active', count: all.filter((l) => l.status === 'active').length },
          { key: 'closed', label: 'Completed', count: all.filter((l) => ['sold', 'closed', 'filled', 'resolved'].includes(l.status)).length },
          { key: 'archived', label: 'Expired / removed', count: all.filter((l) => ['archived', 'removed'].includes(l.status)).length },
        ]}
      />

      {loading && <Spinner />}

      {!loading && filtered.length === 0 && (
        <Empty icon="📋" title="Nothing here yet" action={<Link to="/post" className="btn primary">Post a listing</Link>}>
          Listings you publish appear here, where you can renew, close or delete them.
        </Empty>
      )}

      {filtered.map((l) => {
        const def = meta?.listing_types?.[l.type];
        const price = listingPrice(l);
        return (
          <div className="card" key={l.id}>
            <div className="card-body">
              <div className="spread">
                <div className="grow" style={{ minWidth: 240 }}>
                  <div className="row-wrap small muted mb">
                    <span className="badge">{TYPE_ICONS[l.type]} {TYPE_LABELS[l.type]}</span>
                    <span className={`badge ${l.status === 'active' ? 'ok' : l.status === 'removed' ? 'danger' : l.status === 'archived' ? 'warn' : 'info'}`}>
                      {STATUS_LABELS[l.status]}
                    </span>
                    <span>👁 {l.views} views · ♥ {l.favorite_count} saved</span>
                  </div>
                  <Link to={`/listing/${l.id}`} className="strong">{l.title}</Link>
                  <div className="small muted">
                    {price ? `${price} · ` : ''}Posted {timeAgo(l.created_at)}
                    {l.expires_at && l.status === 'active' ? ` · expires ${dateOnly(l.expires_at)}` : ''}
                  </div>
                </div>

                <div className="row-wrap">
                  <Link to={`/post?edit=${l.id}`} className="btn sm">Edit</Link>

                  {l.status === 'active' && def && (
                    <button
                      className="btn sm"
                      onClick={() => act(
                        () => api.patch(`/listings/${l.id}`, { status: def.closeStatus }),
                        def.closeLabel + '.',
                      )}
                    >{def.closeLabel}</button>
                  )}

                  {['archived', 'sold', 'closed', 'filled', 'resolved'].includes(l.status) && (
                    <button
                      className="btn sm"
                      onClick={() => act(() => api.post(`/listings/${l.id}/renew`, { ttl_days: 60 }), 'Listing is live again.')}
                    >Renew</button>
                  )}

                  <button className="btn danger sm" onClick={() => setConfirm(l)}>Delete</button>
                </div>
              </div>
            </div>
          </div>
        );
      })}

      {confirm && (
        <Modal
          title="Delete this listing?"
          onClose={() => setConfirm(null)}
          footer={<>
            <button className="btn" onClick={() => setConfirm(null)}>Cancel</button>
            <button
              className="btn danger"
              onClick={async () => {
                await act(() => api.del(`/listings/${confirm.id}`), 'Listing deleted.');
                setConfirm(null);
              }}
            >Delete permanently</button>
          </>}
        >
          <p>“{confirm.title}” will be removed for good, along with its photos and enquiries. This cannot be undone.</p>
          <p className="small muted" style={{ marginBottom: 0 }}>
            If you only want to hide it, mark it as closed instead — you can renew it later.
          </p>
        </Modal>
      )}
    </div>
  );
}
