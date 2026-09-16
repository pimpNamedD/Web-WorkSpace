import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { api } from '../lib/api.js';
import { useAuth, useFetch, useMeta, useToast } from '../lib/store.jsx';
import {
  Alert, Avatar, Empty, Modal, Spinner, Stars, StarPicker, VerifiedBadge,
} from '../components/ui.jsx';
import {
  TYPE_ICONS, TYPE_LABELS, STATUS_LABELS, dateOnly, listingPrice, money, timeAgo,
} from '../lib/format.js';

/** Module-specific specification rows (1.4.1 "Category-Specific Fields"). */
function specs(l) {
  const rows = [];
  const add = (k, v) => { if (v !== null && v !== undefined && v !== '') rows.push([k, v]); };

  add('Category', l.category);
  add('Location', l.location);

  switch (l.type) {
    case 'marketplace':
      add('Condition', l.item_condition);
      break;
    case 'accommodation':
      add('Bedrooms', l.bedrooms);
      add('Bathrooms', l.bathrooms);
      add('Furnished', l.furnished === null ? null : l.furnished ? 'Yes' : 'No');
      add('Amenities', l.amenities);
      break;
    case 'tutor':
      add('Subject(s)', l.subject);
      add('Level', l.level);
      add('Qualifications', l.qualifications);
      add('Availability', l.availability);
      break;
    case 'job':
      add('Employer', l.company);
      add('Type', l.job_type);
      add('Application deadline', l.deadline ? dateOnly(l.deadline) : null);
      add('Requirements', l.requirements);
      add('How to apply', l.apply_instructions);
      break;
    case 'roommate':
      add('Budget', l.budget_min || l.budget_max
        ? `${money(l.budget_min) ?? '—'} – ${money(l.budget_max) ?? '—'} per month` : null);
      add('Gender preference', l.gender_pref);
      add('Preferred move-in', l.move_in_date ? dateOnly(l.move_in_date) : null);
      add('Lifestyle', l.lifestyle);
      break;
    case 'lostfound':
      add('Date lost / found', l.item_date ? dateOnly(l.item_date) : null);
      break;
    default:
      break;
  }
  add('Contact', l.contact_info);
  return rows;
}

export default function ListingDetail() {
  const { id } = useParams();
  const { user } = useAuth();
  const meta = useMeta();
  const toast = useToast();
  const navigate = useNavigate();
  const { data, loading, error, reload } = useFetch(`/listings/${id}`, [id]);

  const [active, setActive] = useState(0);
  const [dialog, setDialog] = useState(null);   // 'message' | 'report' | 'review' | 'apply'
  const [message, setMessage] = useState('');
  const [report, setReport] = useState({ reason: '', details: '' });
  const [review, setReview] = useState({ rating: 5, comment: '' });
  const [cover, setCover] = useState('');
  const [busy, setBusy] = useState(false);

  if (loading || (!data && !error)) return <div className="container page"><Spinner /></div>;
  if (error) {
    return (
      <div className="container page">
        <Empty icon="🚫" title={error} action={<Link to="/" className="btn primary">Back to home</Link>} />
      </div>
    );
  }

  const l = data.listing;
  const mine = user?.id === l.user_id;
  const def = meta?.listing_types?.[l.type];
  const price = listingPrice(l);
  const images = l.images ?? [];

  const guard = () => {
    if (!user) { navigate('/login', { state: { from: `/listing/${id}` } }); return false; }
    return true;
  };

  async function toggleFav() {
    if (!guard()) return;
    try {
      const res = await api.post(`/favorites/${l.id}/toggle`);
      toast.success(res.is_favorite ? 'Saved to favourites.' : 'Removed from favourites.');
      reload();
    } catch (err) { toast.error(err.message); }
  }

  async function send(e, action) {
    e.preventDefault();
    setBusy(true);
    try {
      await action();
      setDialog(null);
      reload();
    } catch (err) {
      toast.error(err.message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="container page">
      <div className="small muted mb">
        <Link to={`/browse/${l.type}`}>{TYPE_ICONS[l.type]} {TYPE_LABELS[l.type]}</Link>
        {l.category && <> · {l.category}</>}
      </div>

      {l.status !== 'active' && (
        <Alert tone={l.status === 'removed' ? 'error' : 'warn'}>
          This listing is marked as <strong>{STATUS_LABELS[l.status]}</strong>.
          {mine && l.status === 'archived' && ' Renew it from your dashboard to make it visible again.'}
        </Alert>
      )}

      <div className="detail-grid">
        {/* ---------------- main column ---------------- */}
        <div className="stack">
          <div className="gallery">
            <div className="main">
              {images.length > 0
                ? <img src={images[active]?.url} alt={l.title} />
                : <span>{TYPE_ICONS[l.type]}</span>}
            </div>
            {images.length > 1 && (
              <div className="thumbs">
                {images.map((img, i) => (
                  <img
                    key={img.id}
                    src={img.url}
                    alt=""
                    className={i === active ? 'on' : ''}
                    onClick={() => setActive(i)}
                  />
                ))}
              </div>
            )}
          </div>

          <div className="card">
            <div className="card-body">
              <div className="spread">
                <h1 style={{ marginBottom: '.3rem' }}>{l.title}</h1>
                <button className="btn sm" onClick={toggleFav}>
                  {l.is_favorite ? '♥ Saved' : '♡ Save'}
                </button>
              </div>
              {price && <div style={{ fontSize: '1.5rem', fontWeight: 750, color: 'var(--brand-dark)' }}>{price}</div>}
              <div className="row-wrap small muted mt">
                <span>📍 {l.location || 'Location not given'}</span>
                <span>· Posted {timeAgo(l.created_at)}</span>
                <span>· 👁 {l.views} views</span>
                <span>· ♥ {l.favorite_count} saved</span>
                {l.type === 'job' && <span>· 📄 {l.application_count} applicants</span>}
              </div>

              <h3 className="mt-2">Description</h3>
              <p style={{ whiteSpace: 'pre-wrap' }}>{l.description}</p>

              <h3 className="mt-2">Details</h3>
              <div className="spec-list">
                {specs(l).map(([k, v]) => (
                  <div key={k}>
                    <div className="k">{k}</div>
                    <div className="v">{String(v)}</div>
                  </div>
                ))}
              </div>

              {l.expires_at && l.status === 'active' && (
                <p className="small muted mt">This listing expires on {dateOnly(l.expires_at)}.</p>
              )}
            </div>
          </div>

          <div className="alert warn small" style={{ marginBottom: 0 }}>
            <strong>Stay safe.</strong> Campus Connect does not handle payments or delivery. Meet in a public place on
            campus, inspect the item before paying, and report anything suspicious using the button on the right.
          </div>
        </div>

        {/* ---------------- sidebar ---------------- */}
        <div className="stack">
          <div className="card">
            <div className="card-body">
              <h3>Posted by</h3>
              <Link to={`/profile/${l.user_id}`} className="row mt" style={{ color: 'inherit' }}>
                <Avatar user={{ full_name: l.seller_name, avatar_url: l.seller_avatar }} />
                <div>
                  <div className="strong">{l.seller_name}</div>
                  <div className="small muted">{l.seller_university || 'Campus Connect member'}</div>
                </div>
              </Link>
              <div className="row-wrap mt">
                <VerifiedBadge status={l.seller_verification} />
                {Number(l.seller_rating) > 0
                  ? <Stars value={l.seller_rating} count={l.seller_review_count} />
                  : <span className="small muted">No reviews yet</span>}
              </div>
              <div className="small muted mt">Member since {dateOnly(l.seller_joined)}</div>

              {!mine && (
                <div className="stack-sm mt">
                  <button className="btn primary block" onClick={() => guard() && setDialog('message')}>
                    ✉️ Message {l.seller_name.split(' ')[0]}
                  </button>

                  {l.type === 'job' && (
                    l.my_application
                      ? <div className="alert ok small" style={{ marginBottom: 0 }}>
                          You applied {timeAgo(l.my_application.created_at)} — status: <strong>{l.my_application.status}</strong>.
                        </div>
                      : <button
                          className="btn block"
                          disabled={l.status !== 'active'}
                          onClick={() => guard() && setDialog('apply')}
                        >📄 Apply for this opportunity</button>
                  )}

                  <button className="btn block" onClick={() => guard() && setDialog('review')}>
                    ★ Leave a review
                  </button>
                  <button className="btn ghost block small" onClick={() => guard() && setDialog('report')}>
                    🚩 Report this listing
                  </button>
                </div>
              )}

              {mine && (
                <div className="stack-sm mt">
                  <Link to={`/post?edit=${l.id}`} className="btn block">✏️ Edit listing</Link>
                  <Link to="/dashboard/listings" className="btn ghost block">Manage my listings</Link>
                </div>
              )}
            </div>
          </div>

          {l.contact_info && (
            <div className="panel">
              <h3>Direct contact</h3>
              <p className="small" style={{ marginBottom: 0 }}>{l.contact_info}</p>
            </div>
          )}
        </div>
      </div>

      {/* ---------------- dialogs ---------------- */}
      {dialog === 'message' && (
        <Modal
          title={`Message ${l.seller_name}`}
          onClose={() => setDialog(null)}
          footer={<>
            <button className="btn" onClick={() => setDialog(null)}>Cancel</button>
            <button
              className="btn primary"
              disabled={busy || !message.trim()}
              onClick={(e) => send(e, async () => {
                const res = await api.post('/messages', { recipient_id: l.user_id, listing_id: l.id, body: message });
                toast.success('Message sent.');
                setMessage('');
                navigate(`/dashboard/messages/${res.thread_key}`);
              })}
            >Send message</button>
          </>}
        >
          <p className="small muted">About: <strong>{l.title}</strong></p>
          <textarea
            value={message}
            onChange={(e) => setMessage(e.target.value)}
            placeholder="Hello, is this still available?"
            autoFocus
          />
          <p className="small muted" style={{ marginBottom: 0 }}>
            Messages stay inside Campus Connect, so you do not need to share your phone number publicly.
          </p>
        </Modal>
      )}

      {dialog === 'apply' && (
        <Modal
          title="Apply for this opportunity"
          onClose={() => setDialog(null)}
          footer={<>
            <button className="btn" onClick={() => setDialog(null)}>Cancel</button>
            <button
              className="btn primary"
              disabled={busy}
              onClick={(e) => send(e, async () => {
                await api.post('/applications', { listing_id: l.id, cover_note: cover });
                toast.success('Application submitted.');
                setCover('');
              })}
            >Submit application</button>
          </>}
        >
          <p className="small muted"><strong>{l.title}</strong>{l.company ? ` · ${l.company}` : ''}</p>
          <div className="field">
            <label htmlFor="cover">Short note to the employer</label>
            <textarea
              id="cover"
              value={cover}
              onChange={(e) => setCover(e.target.value)}
              placeholder="Explain briefly why you suit the role and when you are available."
              autoFocus
            />
          </div>
          <p className="small muted" style={{ marginBottom: 0 }}>
            Your name, programme, year of study and verification status are shared with the employer.
          </p>
        </Modal>
      )}

      {dialog === 'review' && (
        <Modal
          title={`Review ${l.seller_name}`}
          onClose={() => setDialog(null)}
          footer={<>
            <button className="btn" onClick={() => setDialog(null)}>Cancel</button>
            <button
              className="btn primary"
              disabled={busy}
              onClick={(e) => send(e, async () => {
                await api.post('/reviews', {
                  reviewee_id: l.user_id, listing_id: l.id, rating: review.rating, comment: review.comment,
                });
                toast.success('Thank you — your review is public on their profile.');
                setReview({ rating: 5, comment: '' });
              })}
            >Publish review</button>
          </>}
        >
          <p className="small muted">
            Review only after you have actually dealt with this person. Ratings are public and help other students
            decide who to trust.
          </p>
          <div className="field">
            <label>Your rating</label>
            <StarPicker value={review.rating} onChange={(rating) => setReview({ ...review, rating })} />
          </div>
          <div className="field">
            <label htmlFor="rc">Comment (optional)</label>
            <textarea
              id="rc"
              value={review.comment}
              onChange={(e) => setReview({ ...review, comment: e.target.value })}
              placeholder="Was the listing accurate? Did they turn up on time?"
            />
          </div>
        </Modal>
      )}

      {dialog === 'report' && (
        <Modal
          title="Report this listing"
          onClose={() => setDialog(null)}
          footer={<>
            <button className="btn" onClick={() => setDialog(null)}>Cancel</button>
            <button
              className="btn danger"
              disabled={busy || !report.reason}
              onClick={(e) => send(e, async () => {
                await api.post('/reports', {
                  target_type: 'listing', target_id: l.id, reason: report.reason, details: report.details,
                });
                toast.success('Report submitted. An administrator will review it.');
                setReport({ reason: '', details: '' });
              })}
            >Submit report</button>
          </>}
        >
          <div className="field">
            <label htmlFor="rr">Reason</label>
            <select id="rr" value={report.reason} onChange={(e) => setReport({ ...report, reason: e.target.value })}>
              <option value="">Choose a reason…</option>
              {(meta?.report_reasons ?? []).map((r) => <option key={r}>{r}</option>)}
            </select>
          </div>
          <div className="field">
            <label htmlFor="rd">What happened? (optional)</label>
            <textarea
              id="rd"
              value={report.details}
              onChange={(e) => setReport({ ...report, details: e.target.value })}
              placeholder="Give the administrator enough detail to act on."
            />
          </div>
        </Modal>
      )}
    </div>
  );
}
