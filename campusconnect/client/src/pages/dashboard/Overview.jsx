import { Link } from 'react-router-dom';
import { useAuth, useFetch } from '../../lib/store.jsx';
import { Alert, Spinner } from '../../components/ui.jsx';
import { ListingGrid } from '../../components/ListingCard.jsx';
import { timeAgo } from '../../lib/format.js';

export default function Overview() {
  const { user, counts, isVerified } = useAuth();
  const mine = useFetch('/listings/mine?limit=6');
  const notifications = useFetch('/notifications?limit=6');
  const applications = useFetch('/applications/mine');

  const listings = mine.data?.listings ?? [];
  const active = listings.filter((l) => l.status === 'active').length;
  const views = listings.reduce((sum, l) => sum + Number(l.views ?? 0), 0);

  return (
    <div className="stack">
      {!isVerified && user.role !== 'admin' && (
        <Alert tone={user.verification_status === 'pending' ? 'warn' : 'info'}>
          {user.verification_status === 'pending'
            ? 'Your verification request is with an administrator. You will be able to post once it is approved.'
            : <>Your account is not verified yet, so you cannot post listings. <Link to="/settings">Submit your student details →</Link></>}
        </Alert>
      )}

      <div className="tiles">
        <div className="tile brand">
          <div className="n">{mine.data?.pagination?.total ?? 0}</div>
          <div className="l">My listings</div>
          <div className="sub">{active} currently active</div>
        </div>
        <div className="tile">
          <div className="n">{views}</div>
          <div className="l">Views on my listings</div>
        </div>
        <div className="tile warn">
          <div className="n">{counts.messages}</div>
          <div className="l">Unread messages</div>
          <div className="sub"><Link to="/dashboard/messages">Open inbox →</Link></div>
        </div>
        <div className="tile">
          <div className="n">{applications.data?.applications?.length ?? 0}</div>
          <div className="l">Job applications sent</div>
          <div className="sub"><Link to="/dashboard/applications">Track them →</Link></div>
        </div>
      </div>

      <div className="card">
        <div className="card-head">
          <h2 style={{ margin: 0 }}>Recent activity</h2>
          <Link to="/dashboard/notifications" className="small">View all</Link>
        </div>
        {notifications.loading && <Spinner />}
        {(notifications.data?.notifications ?? []).slice(0, 6).map((n) => (
          <div key={n.id} className={`notif ${n.is_read ? '' : 'unread'}`}>
            {!n.is_read && <span className="dot" />}
            <div className="grow">
              <div className="small strong">{n.title}</div>
              {n.body && <div className="small muted">{n.body}</div>}
              <div className="small muted">{timeAgo(n.created_at)}</div>
            </div>
            {n.link && <Link to={n.link} className="btn ghost sm">Open</Link>}
          </div>
        ))}
        {notifications.data?.notifications?.length === 0 && (
          <div className="small muted" style={{ padding: '1rem' }}>No activity yet.</div>
        )}
      </div>

      <div className="card">
        <div className="card-head">
          <h2 style={{ margin: 0 }}>My latest listings</h2>
          <Link to="/dashboard/listings" className="small">Manage all</Link>
        </div>
        <div className="card-body">
          {mine.loading && <Spinner />}
          {listings.length === 0 && !mine.loading && (
            <p className="small muted" style={{ marginBottom: 0 }}>
              You have not posted anything yet. <Link to="/post">Create your first listing →</Link>
            </p>
          )}
          {listings.length > 0 && <ListingGrid listings={listings} showStatus onChange={mine.reload} />}
        </div>
      </div>
    </div>
  );
}
