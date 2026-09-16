import { Link } from 'react-router-dom';
import { api } from '../../lib/api.js';
import { useFetch, useToast } from '../../lib/store.jsx';
import { BarChart, Spinner } from '../../components/ui.jsx';
import { TYPE_ICONS, TYPE_LABELS } from '../../lib/format.js';

export default function AdminOverview() {
  const toast = useToast();
  const { data, loading, reload } = useFetch('/admin/stats');

  if (loading || !data) return <Spinner />;

  const { users, listings, by_type: byType, reports, engagement, trends } = data;
  const n = (v) => Number(v ?? 0);

  async function runMaintenance() {
    try {
      const res = await api.post('/admin/maintenance/run');
      toast.success(`Maintenance complete — ${res.archived} expired listing(s) archived.`);
      reload();
    } catch (err) { toast.error(err.message); }
  }

  return (
    <div className="stack">
      <div className="tiles">
        <div className="tile brand">
          <div className="n">{n(users.total)}</div>
          <div className="l">Registered users</div>
          <div className="sub">{n(users.verified)} verified · {n(users.new_this_week)} new this week</div>
        </div>
        <div className="tile warn">
          <div className="n">{n(users.pending)}</div>
          <div className="l">Awaiting verification</div>
          <div className="sub"><Link to="/admin/verifications">Review queue →</Link></div>
        </div>
        <div className="tile danger">
          <div className="n">{n(reports.open) + n(reports.reviewing)}</div>
          <div className="l">Open reports</div>
          <div className="sub"><Link to="/admin/reports">Moderate →</Link></div>
        </div>
        <div className="tile">
          <div className="n">{n(listings.active)}</div>
          <div className="l">Active listings</div>
          <div className="sub">{n(listings.total)} total · {n(listings.archived)} archived</div>
        </div>
      </div>

      <div className="grid-2" style={{ gap: '1rem' }}>
        <div className="card"><div className="card-body">
          <h3>New registrations (14 days)</h3>
          <BarChart data={trends.signups} label={`${trends.signups.reduce((s, d) => s + Number(d.count), 0)} in the last fortnight`} />
        </div></div>
        <div className="card"><div className="card-body">
          <h3>New listings (14 days)</h3>
          <BarChart data={trends.listings} label={`${trends.listings.reduce((s, d) => s + Number(d.count), 0)} in the last fortnight`} />
        </div></div>
      </div>

      <div className="card">
        <div className="card-head"><h3 style={{ margin: 0 }}>Listings by module</h3></div>
        <div className="card-body">
          <div className="tiles">
            {byType.map((t) => (
              <Link key={t.type} to={`/admin/listings?type=${t.type}`} className="tile" style={{ color: 'inherit' }}>
                <div className="n" style={{ fontSize: '1.3rem' }}>{TYPE_ICONS[t.type]} {n(t.active)}</div>
                <div className="l">{TYPE_LABELS[t.type]}</div>
                <div className="sub">{n(t.total)} posted in total</div>
              </Link>
            ))}
          </div>
        </div>
      </div>

      <div className="grid-2" style={{ gap: '1rem' }}>
        <div className="card"><div className="card-body">
          <h3>Community engagement</h3>
          <div className="spec-list">
            <div><div className="k">Messages sent</div><div className="v">{n(engagement.messages)}</div></div>
            <div><div className="k">Reviews left</div><div className="v">{n(engagement.reviews)}</div></div>
            <div><div className="k">Average rating</div><div className="v">{engagement.average_rating ?? '—'}</div></div>
            <div><div className="k">Job applications</div><div className="v">{n(engagement.applications)}</div></div>
          </div>
        </div></div>

        <div className="card"><div className="card-body">
          <h3>Platform health</h3>
          <div className="spec-list">
            <div><div className="k">Suspended accounts</div><div className="v">{n(users.suspended)}</div></div>
            <div><div className="k">Removed listings</div><div className="v">{n(listings.removed)}</div></div>
            <div><div className="k">Reports resolved</div><div className="v">{n(reports.resolved)} of {n(reports.total)}</div></div>
          </div>
          <button className="btn mt" onClick={runMaintenance}>Run listing-expiry maintenance now</button>
          <p className="small muted mt" style={{ marginBottom: 0 }}>
            Expired listings are archived automatically in the background; this runs the same job on demand.
          </p>
        </div></div>
      </div>
    </div>
  );
}
