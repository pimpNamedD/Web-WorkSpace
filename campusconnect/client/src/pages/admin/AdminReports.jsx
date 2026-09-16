import { useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../../lib/api.js';
import { useFetch, useToast } from '../../lib/store.jsx';
import { Empty, Modal, Spinner, Tabs } from '../../components/ui.jsx';
import { dateTime, timeAgo } from '../../lib/format.js';

const TONE = { open: 'danger', reviewing: 'warn', resolved: 'ok', dismissed: '' };

/** Moderation queue — reports flow straight here from the report buttons. */
export default function AdminReports() {
  const toast = useToast();
  const [status, setStatus] = useState('open');
  const { data, loading, reload } = useFetch(`/admin/reports${status ? `?status=${status}` : ''}`, [status]);
  const [action, setAction] = useState(null);   // { report, kind }
  const [note, setNote] = useState('');

  const reports = data?.reports ?? [];

  async function update(report, newStatus, adminNote) {
    try {
      await api.patch(`/admin/reports/${report.id}`, { status: newStatus, admin_note: adminNote });
      toast.success(`Report marked as ${newStatus}.`);
      reload();
    } catch (err) { toast.error(err.message); }
  }

  async function moderate(report, kind, adminNote) {
    try {
      if (report.target_type === 'listing') {
        await api.post(`/admin/listings/${report.target_id}/moderate`, { action: kind, note: adminNote });
      } else {
        await api.post(`/admin/users/${report.target_id}/suspend`, { suspend: kind === 'remove', reason: adminNote });
      }
      await api.patch(`/admin/reports/${report.id}`, { status: 'resolved', admin_note: adminNote });
      toast.success('Action taken and both parties notified.');
      reload();
    } catch (err) { toast.error(err.message); }
  }

  return (
    <div className="stack">
      <h2 style={{ margin: 0 }}>Reports</h2>
      <p className="small muted" style={{ margin: 0 }}>
        Every report a student files lands here. Resolving one notifies the person who reported it.
      </p>

      <Tabs
        value={status}
        onChange={setStatus}
        tabs={[
          { key: 'open', label: 'Open' },
          { key: 'reviewing', label: 'Under review' },
          { key: 'resolved', label: 'Resolved' },
          { key: 'dismissed', label: 'Dismissed' },
          { key: '', label: 'All' },
        ]}
      />

      {loading && <Spinner />}
      {!loading && reports.length === 0 && <Empty icon="🚩" title="No reports in this queue" />}

      {reports.map((r) => (
        <div className="card" key={r.id}><div className="card-body">
          <div className="spread">
            <div className="grow">
              <div className="row-wrap mb">
                <span className={`badge ${TONE[r.status]}`}>{r.status}</span>
                <span className="badge">{r.target_type}</span>
                <strong>{r.reason}</strong>
                <span className="small muted">· {timeAgo(r.created_at)}</span>
              </div>

              <div className="small">
                Target:{' '}
                {r.target_type === 'listing'
                  ? <Link to={`/listing/${r.target_id}`}>{r.target_label ?? `listing #${r.target_id}`}</Link>
                  : <Link to={`/profile/${r.target_id}`}>{r.target_label ?? `user #${r.target_id}`}</Link>}
                {r.target_status && <span className="badge" style={{ marginLeft: '.4rem' }}>{r.target_status}</span>}
              </div>
              <div className="small muted">
                Reported by <Link to={`/profile/${r.reporter_id}`}>{r.reporter_name}</Link> ({r.reporter_email})
              </div>
              {r.details && <p className="small mt" style={{ marginBottom: 0 }}>“{r.details}”</p>}
              {r.admin_note && (
                <div className="alert info small mt" style={{ marginBottom: 0 }}>
                  {r.handled_by_name ? `${r.handled_by_name}: ` : ''}{r.admin_note}
                  {r.handled_at && <span className="muted"> · {dateTime(r.handled_at)}</span>}
                </div>
              )}
            </div>

            <div className="stack-sm" style={{ minWidth: 175 }}>
              {r.status === 'open' && (
                <button className="btn sm" onClick={() => update(r, 'reviewing')}>Start review</button>
              )}
              {['open', 'reviewing'].includes(r.status) && (
                <>
                  <button className="btn danger sm" onClick={() => { setAction({ report: r, kind: 'remove' }); setNote(''); }}>
                    {r.target_type === 'listing' ? 'Remove listing' : 'Suspend account'}
                  </button>
                  <button className="btn sm" onClick={() => { setAction({ report: r, kind: 'resolve' }); setNote(''); }}>
                    Resolve with note
                  </button>
                  <button className="btn ghost sm" onClick={() => update(r, 'dismissed', 'No breach of the platform rules was found.')}>
                    Dismiss
                  </button>
                </>
              )}
              {r.status === 'resolved' && r.target_type === 'listing' && (
                <button className="btn sm" onClick={() => moderate(r, 'restore', 'Listing restored after review.')}>
                  Restore listing
                </button>
              )}
            </div>
          </div>
        </div></div>
      ))}

      {action && (
        <Modal
          title={action.kind === 'remove'
            ? (action.report.target_type === 'listing' ? 'Remove this listing' : 'Suspend this account')
            : 'Resolve this report'}
          onClose={() => setAction(null)}
          footer={<>
            <button className="btn" onClick={() => setAction(null)}>Cancel</button>
            <button
              className={action.kind === 'remove' ? 'btn danger' : 'btn primary'}
              onClick={async () => {
                if (action.kind === 'remove') await moderate(action.report, 'remove', note);
                else await update(action.report, 'resolved', note);
                setAction(null);
              }}
            >Confirm</button>
          </>}
        >
          <p className="small muted">
            {action.kind === 'remove'
              ? 'The owner is notified, the report is resolved, and the action is written to the audit trail.'
              : 'Your note is shown to the student who filed the report.'}
          </p>
          <div className="field">
            <label htmlFor="an">Note</label>
            <textarea
              id="an" value={note} onChange={(e) => setNote(e.target.value)} autoFocus
              placeholder="Explain the decision in one or two sentences."
            />
          </div>
        </Modal>
      )}
    </div>
  );
}
