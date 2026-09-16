import { NavLink, Outlet } from 'react-router-dom';
import { useAuth } from '../../lib/store.jsx';
import { VerifiedBadge } from '../../components/ui.jsx';

const LINKS = [
  ['/dashboard', 'Overview', '📊', true],
  ['/dashboard/listings', 'My listings', '📋'],
  ['/dashboard/favorites', 'Favourites', '♥'],
  ['/dashboard/saved-searches', 'Saved searches', '🔔'],
  ['/dashboard/messages', 'Messages', '✉️'],
  ['/dashboard/applications', 'Job applications', '📄'],
  ['/dashboard/notifications', 'Notifications', '🔔'],
  ['/dashboard/reports', 'My reports', '🚩'],
];

export default function DashboardLayout() {
  const { user, counts } = useAuth();

  return (
    <div className="container page">
      <div className="spread mb">
        <div>
          <h1 style={{ marginBottom: '.2rem' }}>My dashboard</h1>
          <div className="row-wrap small muted">
            <span>{user.full_name}</span>
            <VerifiedBadge status={user.verification_status} />
          </div>
        </div>
      </div>

      <div className="with-side">
        <nav className="panel side-nav">
          {LINKS.map(([to, label, icon, end]) => (
            <NavLink key={to} to={to} end={end} className={({ isActive }) => (isActive ? 'active' : '')}>
              <span>{icon} {label}</span>
              {to.endsWith('messages') && counts.messages > 0 && <span className="badge danger">{counts.messages}</span>}
              {to.endsWith('notifications') && counts.notifications > 0 && <span className="badge danger">{counts.notifications}</span>}
            </NavLink>
          ))}
        </nav>
        <div><Outlet /></div>
      </div>
    </div>
  );
}
