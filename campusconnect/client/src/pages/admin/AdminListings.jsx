import { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { api, qs } from '../../lib/api.js';
import { useFetch, useToast } from '../../lib/store.jsx';
import { Empty, Modal, Pagination, Spinner } from '../../components/ui.jsx';
import { STATUS_LABELS, TYPE_ICONS, TYPE_LABELS, listingPrice, timeAgo } from '../../lib/format.js';

const STATUS_TONE = {
  active: 'ok', archived: 'warn', removed: 'danger',
  sold: 'info', closed: 'info', filled: 'info', resolved: 'info',
};

export default function AdminListings() {
  const toast = useToast();
  const [params, setParams] = useSearchParams();
  const values = Object.fromEntries(params.entries());
  const { data, loading, reload } = useFetch(`/admin/listings${qs({ ...values, limit: 25 })}`);
  const [action, setAction] = useState(null);
  const [note, setNote] = useState('');

  const listings = data?.listings ?? [];
  const update = (next) => setParams(Object.fromEntries(Object.entries(next).filter(([, v]) => v !== '')), { replace: true });

  async function moderate(listing, kind, adminNote) {
    try {
      await api.post(`/admin/listings/${listing.id}/moderate`, { action: kind, note: adminNote });
      toast.success(`Listing ${kind}d. The owner has been notified.`);
      reload();
    } catch (err) { toast.error(err.message); }
  }

  return (
    <div className="stack">
      <h2 style={{ margin: 0 }}>Listings</h2>

      <div className="panel">
        <div className="grid-3">
          <div className="field">
            <label htmlFor="lq">Search</label>
            <input
              id="lq" defaultValue={values.q ?? ''} placeholder="Title, description, location"
              onKeyDown={(e) => e.key === 'Enter' && update({ ...values, q: e.target.value, page: 1 })}
            />
          </div>
          <div className="field">
            <label htmlFor="lt">Module</label>
            <select id="lt" value={values.type ?? ''} onChange={(e) => update({ ...values, type: e.target.value, page: 1 })}>
              <option value="">All modules</option>
              {Object.entries(TYPE_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
            </select>
          </div>
          <div className="field">
            <label htmlFor="ls">Status</label>
            <select id="ls" value={values.status ?? ''} onChange={(e) => update({ ...values, status: e.target.value, page: 1 })}>
              <option value="">Any status</option>
              {Object.entries(STATUS_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
            </select>
          </div>
        </div>
      </div>

      {loading && <Spinner />}
      {!loading && listings.length === 0 && <Empty icon="📋" title="No listings match those filters" />}

      {listings.length > 0 && (
        <div className="card table-wrap">
          <table className="data">
            <thead>
              <tr><th>Listing</th><th>Module</th><th>Owner</th><th>Status</th><th>Views</th><th>Posted</th><th>Actions</th></tr>
            </thead>
            <tbody>
              {listings.map((l) => (
                <tr key={l.id}>
                  <td style={{ maxWidth: 280 }}>
                    <Link to={`/listing/${l.id}`} className="strong">{l.title}</Link>
                    <div className="small muted">{[listingPrice(l), l.location].filter(Boolean).join(' · ')}</div>
                  </td>
                  <td className="nowrap small">{TYPE_ICONS[l.type]} {TYPE_LABELS[l.type]}</td>
                  <td>
                    <Link to={`/profile/${l.user_id}`} className="small">{l.seller_name}</Link>
                    {l.seller_verification === 'verified' && <span className="badge ok" style={{ fontSize: '.62rem', marginLeft: '.3rem' }}>✓</span>}
                  </td>
                  <td><span className={`badge ${STATUS_TONE[l.status] ?? ''}`}>{l.status}</span></td>
                  <td>{l.views}</td>
                  <td className="small muted nowrap">{timeAgo(l.created_at)}</td>
                  <td>
                    <div className="row-wrap">
                      {l.status === 'removed'
                        ? <button className="btn sm" onClick={() => moderate(l, 'restore', 'Restored after review.')}>Restore</button>
                        : <>
                            {l.status === 'active' && (
                              <button className="btn sm" onClick={() => moderate(l, 'archive', 'Archived by an administrator.')}>Archive</button>
                            )}
                            <button className="btn danger sm" onClick={() => { setAction(l); setNote(''); }}>Remove</button>
                          </>}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <Pagination pagination={data?.pagination} onPage={(p) => update({ ...values, page: p })} />

      {action && (
        <Modal
          title="Remove this listing"
          onClose={() => setAction(null)}
          footer={<>
            <button className="btn" onClick={() => setAction(null)}>Cancel</button>
            <button
              className="btn danger"
              onClick={async () => { await moderate(action, 'remove', note); setAction(null); }}
            >Remove listing</button>
          </>}
        >
          <p className="small muted">
            “{action.title}” will be hidden from every section and its owner notified with the reason below. The action
            is recorded in the audit trail and can be reversed.
          </p>
          <div className="field">
            <label htmlFor="mn">Reason</label>
            <textarea id="mn" value={note} onChange={(e) => setNote(e.target.value)} autoFocus />
          </div>
        </Modal>
      )}
    </div>
  );
}
