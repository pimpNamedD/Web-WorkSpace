import { NavLink, Outlet } from 'react-router-dom';
import { useFetch } from '../../lib/store.jsx';

const LINKS = [
  ['/admin', 'Overview', '📊', true],
  ['/admin/verifications', 'Verification queue', '✅'],
  ['/admin/reports', 'Reports', '🚩'],
  ['/admin/users', 'Users', '👥'],
  ['/admin/listings', 'Listings', '📋'],
  ['/admin/audit', 'Audit trail', '📜'],
];

export default function AdminLayout() {
  const { data } = useFetch('/admin/stats');
  const pending = Number(data?.users?.pending ?? 0);
  const openReports = Number(data?.reports?.open ?? 0);

  return (
    <div className="container page">
      <div className="spread mb">
        <div>
          <h1 style={{ marginBottom: '.2rem' }}>Administrator console</h1>
          <p className="muted small" style={{ marginBottom: 0 }}>
            Manage members, moderate listings and resolve reports to keep the platform safe.
          </p>
        </div>
      </div>

      <div className="with-side">
        <nav className="panel side-nav">
          {LINKS.map(([to, label, icon, end]) => (
            <NavLink key={to} to={to} end={end} className={({ isActive }) => (isActive ? 'active' : '')}>
              <span>{icon} {label}</span>
              {to.endsWith('verifications') && pending > 0 && <span className="badge warn">{pending}</span>}
              {to.endsWith('reports') && openReports > 0 && <span className="badge danger">{openReports}</span>}
            </NavLink>
          ))}
        </nav>
        <div><Outlet /></div>
      </div>
    </div>
  );
}
