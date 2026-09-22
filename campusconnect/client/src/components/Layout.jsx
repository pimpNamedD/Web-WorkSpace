import { useEffect, useRef, useState } from 'react';
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import { api } from '../lib/api.js';
import { useAuth } from '../lib/store.jsx';
import { TYPE_ICONS, TYPE_LABELS, timeAgo } from '../lib/format.js';
import { Avatar, VerifiedBadge } from './ui.jsx';
import ccLogo from '../assets/cc-logo.svg';

const MODULES = ['marketplace', 'accommodation', 'tutor', 'job', 'roommate', 'lostfound'];

function useOutsideClose(ref, onClose) {
  useEffect(() => {
    const handler = (e) => { if (ref.current && !ref.current.contains(e.target)) onClose(); };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [ref, onClose]);
}

function NotificationsMenu() {
  const { counts, refreshCounts } = useAuth();
  const [open, setOpen] = useState(false);
  const [items, setItems] = useState([]);
  const ref = useRef(null);
  useOutsideClose(ref, () => setOpen(false));

  async function toggle() {
    const next = !open;
    setOpen(next);
    if (next) {
      try { setItems((await api.get('/notifications?limit=12')).notifications); } catch { /* ignore */ }
    }
  }

  async function markAll() {
    await api.post('/notifications/read-all');
    setItems((list) => list.map((n) => ({ ...n, is_read: 1 })));
    refreshCounts();
  }

  async function openItem(n) {
    setOpen(false);
    if (!n.is_read) { await api.patch(`/notifications/${n.id}/read`); refreshCounts(); }
  }

  return (
    <div className="menu-wrap" ref={ref}>
      <button className="icon-btn" onClick={toggle} aria-label="Notifications" title="Notifications">
        🔔
        {counts.notifications > 0 && <span className="badge-dot">{counts.notifications > 9 ? '9+' : counts.notifications}</span>}
      </button>
      {open && (
        <div className="dropdown wide">
          <div className="head spread">
            <strong className="small">Notifications</strong>
            <button className="btn ghost sm" onClick={markAll}>Mark all read</button>
          </div>
          {items.length === 0 && <div className="small muted" style={{ padding: '1rem .6rem' }}>Nothing yet.</div>}
          {items.map((n) => (
            <Link
              key={n.id}
              to={n.link || '/dashboard/notifications'}
              onClick={() => openItem(n)}
              style={{ background: n.is_read ? undefined : '#f2f9f8' }}
            >
              <div className="small strong">{n.title}</div>
              {n.body && <div className="small muted" style={{ lineHeight: 1.35 }}>{n.body.slice(0, 90)}</div>}
              <div className="small muted">{timeAgo(n.created_at)}</div>
            </Link>
          ))}
          <div className="sep" />
          <Link to="/dashboard/notifications" onClick={() => setOpen(false)}>View all notifications</Link>
        </div>
      )}
    </div>
  );
}

function AccountMenu() {
  const { user, logout, isAdmin } = useAuth();
  const [open, setOpen] = useState(false);
  const ref = useRef(null);
  const navigate = useNavigate();
  useOutsideClose(ref, () => setOpen(false));

  return (
    <div className="menu-wrap" ref={ref}>
      <button className="avatar-btn" onClick={() => setOpen(!open)} aria-label="Account menu" title={user.full_name}>
        <Avatar user={user} />
      </button>
      {open && (
        <div className="dropdown" onClick={() => setOpen(false)}>
          <div className="head">
            <div className="small strong">{user.full_name}</div>
            <div className="small muted">{user.email}</div>
            <div style={{ marginTop: '.35rem' }}><VerifiedBadge status={user.verification_status} /></div>
          </div>
          <Link to="/dashboard">My dashboard</Link>
          <Link to="/dashboard/listings">My listings</Link>
          <Link to="/dashboard/favorites">Favourites</Link>
          <Link to="/dashboard/saved-searches">Saved searches</Link>
          <Link to="/dashboard/messages">Messages</Link>
          <Link to="/dashboard/applications">Applications</Link>
          <Link to={`/profile/${user.id}`}>Public profile</Link>
          <Link to="/settings">Settings</Link>
          {isAdmin && <><div className="sep" /><Link to="/admin">Administrator console</Link></>}
          <div className="sep" />
          <button onClick={() => { logout(); navigate('/'); }}>Sign out</button>
        </div>
      )}
    </div>
  );
}

export default function Layout() {
  const { user, counts } = useAuth();
  const [navOpen, setNavOpen] = useState(false);
  const [term, setTerm] = useState('');
  const navigate = useNavigate();
  const location = useLocation();

  useEffect(() => { setNavOpen(false); }, [location.pathname]);

  function submitSearch(e) {
    e.preventDefault();
    if (term.trim()) navigate(`/search?q=${encodeURIComponent(term.trim())}`);
  }

  return (
    <>
      <header className="site-header">
        <div className="container bar">
          <Link to="/" className="brand" aria-label="Campus Connect">
            <img src={ccLogo} alt="Campus Connect" className="brand-logo" />
            <span className="brand-name">Campus Connect</span>
          </Link>

          <nav className={`nav-links ${navOpen ? 'open' : ''}`}>
            {MODULES.map((m) => (
              <NavLink key={m} to={`/browse/${m}`} className={({ isActive }) => (isActive ? 'active' : '')}>
                {TYPE_ICONS[m]} {TYPE_LABELS[m]}
              </NavLink>
            ))}
          </nav>

          <form onSubmit={submitSearch} className="header-search">
            <input
              value={term}
              onChange={(e) => setTerm(e.target.value)}
              placeholder="Search everything…"
              aria-label="Search all listings"
            />
          </form>

          <div className="header-actions">
            {user ? (
              <>
                <Link to="/post" className="btn primary sm post-btn">+ Post</Link>
                <Link to="/dashboard/messages" className="icon-btn" title="Messages" aria-label="Messages">
                  ✉️
                  {counts.messages > 0 && <span className="badge-dot">{counts.messages > 9 ? '9+' : counts.messages}</span>}
                </Link>
                <NotificationsMenu />
                <AccountMenu />
              </>
            ) : (
              <>
                <Link to="/login" className="btn sm">Sign in</Link>
                <Link to="/register" className="btn primary sm">Register</Link>
              </>
            )}

            <button
              className="icon-btn mobile-toggle"
              onClick={() => setNavOpen(!navOpen)}
              aria-label="Toggle navigation"
            >☰</button>
          </div>
        </div>
      </header>

      <main><Outlet /></main>

      <footer className="site-footer">
        <div className="container">
          <div className="spread">
            <div className="footer-brand-wrap">
              <img src={ccLogo} alt="Campus Connect" className="footer-logo" />
              <div>
                <strong style={{ color: 'var(--ink)' }}>Campus Connect</strong>
                <div>University Student Marketplace and Services Management System</div>
                <div className="small">University of Lusaka · Bachelor of Information Technology final-year project</div>
              </div>
            </div>
            <div className="row-wrap small">
              {MODULES.map((m) => <Link key={m} to={`/browse/${m}`}>{TYPE_LABELS[m]}</Link>)}
            </div>
          </div>
          <div className="small mt" style={{ borderTop: '1px solid var(--line)', paddingTop: '.8rem' }}>
            Campus Connect does not process payments or arrange delivery. Always meet in a public place on campus and
            verify goods before paying.
          </div>
        </div>
      </footer>
    </>
  );
}
