import { useFetch } from '../../lib/store.jsx';
import { Empty, Spinner } from '../../components/ui.jsx';
import { dateTime } from '../../lib/format.js';

export default function AdminAudit() {
  const { data, loading } = useFetch('/admin/audit?limit=120');
  const actions = data?.actions ?? [];

  return (
    <div className="stack">
      <h2 style={{ margin: 0 }}>Audit trail</h2>
      <p className="small muted" style={{ margin: 0 }}>
        Every moderation decision is recorded here, so administrator oversight itself stays accountable.
      </p>

      {loading && <Spinner />}
      {!loading && actions.length === 0 && <Empty icon="📜" title="No administrator actions recorded yet" />}

      {actions.length > 0 && (
        <div className="card table-wrap">
          <table className="data">
            <thead>
              <tr><th>When</th><th>Administrator</th><th>Action</th><th>Target</th><th>Note</th></tr>
            </thead>
            <tbody>
              {actions.map((a) => (
                <tr key={a.id}>
                  <td className="small muted nowrap">{dateTime(a.created_at)}</td>
                  <td className="small">{a.admin_name}</td>
                  <td><span className="badge">{a.action}</span></td>
                  <td className="small muted">{a.target_type} #{a.target_id}</td>
                  <td className="small">{a.note ?? '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
