import { Link } from 'react-router-dom';
import { useFetch } from '../../lib/store.jsx';
import { Empty, Spinner } from '../../components/ui.jsx';
import { dateOnly, timeAgo } from '../../lib/format.js';

const TONE = { open: 'warn', reviewing: 'info', resolved: 'ok', dismissed: '' };

export default function MyReports() {
  const { data, loading } = useFetch('/reports/mine');
  const reports = data?.reports ?? [];

  return (
    <div className="stack">
      <h2 style={{ margin: 0 }}>My reports</h2>
      <p className="small muted" style={{ margin: 0 }}>
        Reports you have submitted, and what the administrators decided.
      </p>

      {loading && <Spinner />}

      {!loading && reports.length === 0 && (
        <Empty icon="🚩" title="You have not reported anything">
          Use the report button on a listing or profile if something looks like a scam or breaks the rules.
        </Empty>
      )}

      {reports.map((r) => (
        <div className="card" key={r.id}><div className="card-body">
          <div className="spread">
            <div className="grow">
              <div className="row-wrap mb">
                <span className={`badge ${TONE[r.status]}`}>{r.status}</span>
                <span className="badge">{r.target_type}</span>
                <span className="small muted">reported {timeAgo(r.created_at)}</span>
              </div>
              <div className="strong">{r.reason}</div>
              <div className="small muted">
                Target: {r.target_type === 'listing'
                  ? <Link to={`/listing/${r.target_id}`}>{r.target_label ?? `listing #${r.target_id}`}</Link>
                  : <Link to={`/profile/${r.target_id}`}>{r.target_label ?? `user #${r.target_id}`}</Link>}
              </div>
              {r.details && <p className="small mt" style={{ marginBottom: 0 }}>“{r.details}”</p>}
              {r.admin_note && (
                <div className="alert info small mt" style={{ marginBottom: 0 }}>
                  <strong>Administrator response:</strong> {r.admin_note}
                  {r.handled_at && <span className="muted"> ({dateOnly(r.handled_at)})</span>}
                </div>
              )}
            </div>
          </div>
        </div></div>
      ))}
    </div>
  );
}
