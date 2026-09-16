import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { api } from '../lib/api.js';
import { useAuth, useFetch, useMeta, useToast } from '../lib/store.jsx';
import { ListingGrid } from '../components/ListingCard.jsx';
import {
  Alert, Avatar, Empty, Modal, Spinner, Stars, StarPicker, Tabs, VerifiedBadge,
} from '../components/ui.jsx';
import { dateOnly, timeAgo } from '../lib/format.js';

/** Public profile: verification status, rating and review history (1.4.2). */
export default function Profile() {
  const { id } = useParams();
  const { user } = useAuth();
  const meta = useMeta();
  const toast = useToast();
  const { data, loading, error, reload } = useFetch(`/users/${id}`, [id]);
  const [tab, setTab] = useState('listings');
  const [dialog, setDialog] = useState(null);
  const [review, setReview] = useState({ rating: 5, comment: '' });
  const [report, setReport] = useState({ reason: '', details: '' });
  const [message, setMessage] = useState('');
  const [busy, setBusy] = useState(false);

  if (loading || (!data && !error)) return <div className="container page"><Spinner /></div>;
  if (error) return <div className="container page"><Empty icon="🚫" title={error} /></div>;

  const p = data.user;
  const stats = data.stats;
  const mine = user?.id === p.id;

  async function act(fn) {
    setBusy(true);
    try { await fn(); setDialog(null); reload(); }
    catch (err) { toast.error(err.message); }
    finally { setBusy(false); }
  }

  return (
    <div className="container page">
      <div className="card mb">
        <div className="card-body">
          <div className="spread">
            <div className="row" style={{ gap: '1rem' }}>
              <Avatar user={p} size="lg" />
              <div>
                <h1 style={{ marginBottom: '.2rem' }}>{p.full_name}</h1>
                <div className="row-wrap">
                  <VerifiedBadge status={p.verification_status} />
                  <span className="badge">{p.role === 'employer' ? 'Employer / landlord' : p.role === 'admin' ? 'Administrator' : 'Student'}</span>
                  {p.is_suspended === 1 && <span className="badge danger">Suspended</span>}
                </div>
                <div className="small muted mt">
                  {[p.university, p.program, p.year_of_study && `Year ${p.year_of_study}`].filter(Boolean).join(' · ')}
                </div>
                <div className="small muted">Member since {dateOnly(p.created_at)}</div>
              </div>
            </div>

            <div className="stack-sm" style={{ minWidth: 180 }}>
              <div className="row">
                {stats.average_rating
                  ? <Stars value={stats.average_rating} count={stats.review_count} big />
                  : <span className="small muted">No reviews yet</span>}
              </div>
              <div className="small muted">{stats.active_listing_count} active listing(s) · {stats.listing_count} total</div>
              {!mine && user && (
                <div className="row-wrap">
                  <button className="btn sm" onClick={() => setDialog('message')}>✉️ Message</button>
                  <button className="btn sm" onClick={() => setDialog('review')}>★ Review</button>
                  <button className="btn ghost sm" onClick={() => setDialog('report')}>🚩 Report</button>
                </div>
              )}
              {mine && <Link to="/settings" className="btn sm">Edit my profile</Link>}
            </div>
          </div>

          {p.bio && <p className="mt" style={{ marginBottom: 0 }}>{p.bio}</p>}
        </div>
      </div>

      <Tabs
        value={tab}
        onChange={setTab}
        tabs={[
          { key: 'listings', label: 'Listings', count: data.listings.length },
          { key: 'reviews', label: 'Reviews', count: stats.review_count },
        ]}
      />

      {tab === 'listings' && (
        data.listings.length === 0
          ? <Empty icon="📭" title="No listings yet" />
          : <ListingGrid listings={data.listings} showStatus />
      )}

      {tab === 'reviews' && (
        <div className="card"><div className="card-body">
          {data.reviews.length === 0 && <Empty icon="★" title="No reviews yet">
            Reviews appear here once other members rate their dealings with {p.full_name.split(' ')[0]}.
          </Empty>}
          {data.reviews.map((r) => (
            <div key={r.id} className="review">
              <div className="spread">
                <Link to={`/profile/${r.reviewer_id}`} className="row" style={{ color: 'inherit' }}>
                  <Avatar user={{ full_name: r.reviewer_name, avatar_url: r.reviewer_avatar }} size="sm" />
                  <span className="strong small">{r.reviewer_name}</span>
                  {r.reviewer_verification === 'verified' && <span className="badge ok" style={{ fontSize: '.62rem' }}>✓</span>}
                </Link>
                <span className="small muted">{timeAgo(r.created_at)}</span>
              </div>
              <div className="row mt" style={{ gap: '.5rem' }}>
                <Stars value={r.rating} />
                {r.listing_title && <span className="small muted">on “{r.listing_title}”</span>}
              </div>
              {r.comment && <p className="small mt" style={{ marginBottom: 0 }}>{r.comment}</p>}
            </div>
          ))}
        </div></div>
      )}

      {dialog === 'message' && (
        <Modal
          title={`Message ${p.full_name}`}
          onClose={() => setDialog(null)}
          footer={<>
            <button className="btn" onClick={() => setDialog(null)}>Cancel</button>
            <button className="btn primary" disabled={busy || !message.trim()} onClick={() => act(async () => {
              await api.post('/messages', { recipient_id: p.id, body: message });
              toast.success('Message sent.');
              setMessage('');
            })}>Send</button>
          </>}
        >
          <textarea value={message} onChange={(e) => setMessage(e.target.value)} autoFocus placeholder="Write your message…" />
        </Modal>
      )}

      {dialog === 'review' && (
        <Modal
          title={`Review ${p.full_name}`}
          onClose={() => setDialog(null)}
          footer={<>
            <button className="btn" onClick={() => setDialog(null)}>Cancel</button>
            <button className="btn primary" disabled={busy} onClick={() => act(async () => {
              await api.post('/reviews', { reviewee_id: p.id, rating: review.rating, comment: review.comment });
              toast.success('Review published.');
              setReview({ rating: 5, comment: '' });
            })}>Publish review</button>
          </>}
        >
          <Alert tone="info">Only review members you have actually dealt with. Reviews are public.</Alert>
          <div className="field">
            <label>Rating</label>
            <StarPicker value={review.rating} onChange={(rating) => setReview({ ...review, rating })} />
          </div>
          <div className="field">
            <label htmlFor="pc">Comment</label>
            <textarea id="pc" value={review.comment} onChange={(e) => setReview({ ...review, comment: e.target.value })} />
          </div>
        </Modal>
      )}

      {dialog === 'report' && (
        <Modal
          title={`Report ${p.full_name}`}
          onClose={() => setDialog(null)}
          footer={<>
            <button className="btn" onClick={() => setDialog(null)}>Cancel</button>
            <button className="btn danger" disabled={busy || !report.reason} onClick={() => act(async () => {
              await api.post('/reports', { target_type: 'user', target_id: p.id, ...report });
              toast.success('Report submitted for administrator review.');
              setReport({ reason: '', details: '' });
            })}>Submit report</button>
          </>}
        >
          <div className="field">
            <label htmlFor="prr">Reason</label>
            <select id="prr" value={report.reason} onChange={(e) => setReport({ ...report, reason: e.target.value })}>
              <option value="">Choose a reason…</option>
              {(meta?.report_reasons ?? []).map((r) => <option key={r}>{r}</option>)}
            </select>
          </div>
          <div className="field">
            <label htmlFor="prd">Details</label>
            <textarea id="prd" value={report.details} onChange={(e) => setReport({ ...report, details: e.target.value })} />
          </div>
        </Modal>
      )}
    </div>
  );
}
