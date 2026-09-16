import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { initials } from '../lib/format.js';

export function Spinner() {
  return <div className="spinner" role="status" aria-label="Loading" />;
}

export function Empty({ icon = '📭', title, children, action }) {
  return (
    <div className="empty">
      <div className="ic">{icon}</div>
      <h3>{title}</h3>
      {children && <p className="small">{children}</p>}
      {action && <div className="mt">{action}</div>}
    </div>
  );
}

export function Alert({ tone = 'info', children }) {
  if (!children) return null;
  return <div className={`alert ${tone}`}>{children}</div>;
}

/** Verification badge - the trust signal that runs through the whole system. */
export function VerifiedBadge({ status, size }) {
  if (status === 'verified') return <span className="badge ok" title="Student status verified">✓ Verified</span>;
  if (status === 'pending') return <span className="badge warn" title="Awaiting administrator approval">⏳ Pending</span>;
  if (status === 'rejected') return <span className="badge danger">✕ Not verified</span>;
  return <span className="badge" style={size === 'sm' ? { fontSize: '.68rem' } : undefined}>Unverified</span>;
}

export function Avatar({ user, size = '' }) {
  const name = user?.full_name ?? user?.name ?? '';
  const url = user?.avatar_url ?? user?.avatar;
  if (url) return <img className={`avatar ${size}`} src={url} alt={name} />;
  return <span className={`avatar ${size}`}>{initials(name)}</span>;
}

export function Stars({ value, count, big }) {
  const rating = Number(value) || 0;
  const full = Math.round(rating);
  return (
    <span className="row" style={{ gap: '.35rem' }}>
      <span className={`stars ${big ? 'big' : ''}`} aria-label={`${rating} out of 5`}>
        {'★'.repeat(full)}{'☆'.repeat(5 - full)}
      </span>
      {rating > 0 && <span className="small strong">{rating.toFixed(1)}</span>}
      {count !== undefined && <span className="small muted">({count})</span>}
    </span>
  );
}

export function StarPicker({ value, onChange }) {
  const [hover, setHover] = useState(0);
  return (
    <div onMouseLeave={() => setHover(0)}>
      {[1, 2, 3, 4, 5].map((n) => (
        <button
          key={n}
          type="button"
          className={`star-btn ${n <= (hover || value) ? 'on' : ''}`}
          onMouseEnter={() => setHover(n)}
          onClick={() => onChange(n)}
          aria-label={`${n} star${n > 1 ? 's' : ''}`}
        >★</button>
      ))}
    </div>
  );
}

export function Modal({ title, onClose, children, footer, wide }) {
  useEffect(() => {
    const onKey = (e) => e.key === 'Escape' && onClose();
    window.addEventListener('keydown', onKey);
    document.body.style.overflow = 'hidden';
    return () => { window.removeEventListener('keydown', onKey); document.body.style.overflow = ''; };
  }, [onClose]);

  return (
    <div className="modal-back" onMouseDown={(e) => e.target === e.currentTarget && onClose()}>
      <div className="modal" style={wide ? { width: 'min(760px, 100%)' } : undefined} role="dialog" aria-modal="true">
        <div className="head">
          <h3 style={{ margin: 0 }}>{title}</h3>
          <button className="btn ghost sm" onClick={onClose} aria-label="Close">✕</button>
        </div>
        <div className="body">{children}</div>
        {footer && <div className="foot">{footer}</div>}
      </div>
    </div>
  );
}

export function Pagination({ pagination, onPage }) {
  if (!pagination || pagination.pages <= 1) return null;
  const { page, pages } = pagination;
  const window_ = [];
  for (let i = Math.max(1, page - 2); i <= Math.min(pages, page + 2); i += 1) window_.push(i);

  return (
    <div className="pagination">
      <button className="btn sm" disabled={page <= 1} onClick={() => onPage(page - 1)}>← Prev</button>
      {window_[0] > 1 && <button className="btn sm" onClick={() => onPage(1)}>1</button>}
      {window_[0] > 2 && <span className="muted small">…</span>}
      {window_.map((n) => (
        <button key={n} className={`btn sm ${n === page ? 'active' : ''}`} onClick={() => onPage(n)}>{n}</button>
      ))}
      {window_.at(-1) < pages - 1 && <span className="muted small">…</span>}
      {window_.at(-1) < pages && <button className="btn sm" onClick={() => onPage(pages)}>{pages}</button>}
      <button className="btn sm" disabled={page >= pages} onClick={() => onPage(page + 1)}>Next →</button>
    </div>
  );
}

/** Compact user line used on cards, reviews and tables. */
export function UserChip({ id, name, avatar, verification, rating, reviewCount }) {
  return (
    <Link to={`/profile/${id}`} className="row" style={{ gap: '.4rem', color: 'inherit' }}>
      <Avatar user={{ full_name: name, avatar_url: avatar }} size="sm" />
      <span className="small strong">{name}</span>
      {verification === 'verified' && <span className="badge ok" style={{ fontSize: '.64rem' }}>✓</span>}
      {rating ? <Stars value={rating} count={reviewCount} /> : null}
    </Link>
  );
}

export function Tabs({ tabs, value, onChange }) {
  return (
    <div className="tabs">
      {tabs.map((t) => (
        <button key={t.key} className={value === t.key ? 'on' : ''} onClick={() => onChange(t.key)}>
          {t.label}{t.count !== undefined ? ` (${t.count})` : ''}
        </button>
      ))}
    </div>
  );
}

/** Simple 14-day bar chart used on the admin dashboard. */
export function BarChart({ data, label }) {
  const max = Math.max(1, ...data.map((d) => Number(d.count)));
  return (
    <div>
      <div className="small muted">{label}</div>
      <div className="bars">
        {data.length === 0 && <div className="small muted">No activity yet</div>}
        {data.map((d) => (
          <div key={d.day} className="b" style={{ height: `${(Number(d.count) / max) * 100}%` }}>
            <span>{new Date(d.day).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}: {d.count}</span>
          </div>
        ))}
      </div>
    </div>
  );
}
