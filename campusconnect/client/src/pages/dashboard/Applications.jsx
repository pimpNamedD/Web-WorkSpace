import { useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../../lib/api.js';
import { useFetch, useToast } from '../../lib/store.jsx';
import { Avatar, Empty, Spinner, Stars, Tabs, VerifiedBadge } from '../../components/ui.jsx';
import { dateOnly, timeAgo } from '../../lib/format.js';

const STATUS_TONE = {
  submitted: '', reviewed: 'info', shortlisted: 'warn', accepted: 'ok', rejected: 'danger',
};
const NEXT = ['reviewed', 'shortlisted', 'accepted', 'rejected'];

export default function Applications() {
  const toast = useToast();
  const [tab, setTab] = useState('sent');
  const sent = useFetch('/applications/mine');
  const received = useFetch('/applications/received');

  const mine = sent.data?.applications ?? [];
  const theirs = received.data?.applications ?? [];

  async function setStatus(app, status) {
    try {
      await api.patch(`/applications/${app.id}`, { status });
      toast.success(`Marked as ${status}. The applicant has been notified.`);
      received.reload();
    } catch (err) { toast.error(err.message); }
  }

  async function withdraw(app) {
    try { await api.del(`/applications/${app.id}`); toast.success('Application withdrawn.'); sent.reload(); }
    catch (err) { toast.error(err.message); }
  }

  return (
    <div className="stack">
      <h2 style={{ margin: 0 }}>Job applications</h2>

      <Tabs
        value={tab}
        onChange={setTab}
        tabs={[
          { key: 'sent', label: 'Applications I sent', count: mine.length },
          { key: 'received', label: 'Applications received', count: theirs.length },
        ]}
      />

      {tab === 'sent' && (
        <>
          {sent.loading && <Spinner />}
          {!sent.loading && mine.length === 0 && (
            <Empty icon="📄" title="No applications yet" action={<Link to="/browse/job" className="btn primary">Browse opportunities</Link>}>
              Apply for part-time work and internships from the Jobs section.
            </Empty>
          )}
          {mine.map((a) => (
            <div className="card" key={a.id}><div className="card-body">
              <div className="spread">
                <div className="grow">
                  <div className="row-wrap mb">
                    <span className={`badge ${STATUS_TONE[a.status]}`}>{a.status}</span>
                    {a.listing_status !== 'active' && <span className="badge warn">Listing {a.listing_status}</span>}
                  </div>
                  <Link to={`/listing/${a.listing_id}`} className="strong">{a.title}</Link>
                  <div className="small muted">
                    {[a.company, a.location].filter(Boolean).join(' · ')} · applied {timeAgo(a.created_at)}
                    {a.deadline ? ` · closes ${dateOnly(a.deadline)}` : ''}
                  </div>
                  {a.cover_note && <p className="small mt" style={{ marginBottom: 0 }}>“{a.cover_note}”</p>}
                </div>
                <div className="row-wrap">
                  <Link to={`/profile/${a.employer_id}`} className="btn sm">View employer</Link>
                  {a.status === 'submitted' && <button className="btn danger sm" onClick={() => withdraw(a)}>Withdraw</button>}
                </div>
              </div>
            </div></div>
          ))}
        </>
      )}

      {tab === 'received' && (
        <>
          {received.loading && <Spinner />}
          {!received.loading && theirs.length === 0 && (
            <Empty icon="📥" title="No applications received">
              Applications to opportunities you post appear here with each applicant's profile and verification status.
            </Empty>
          )}
          {theirs.map((a) => (
            <div className="card" key={a.id}><div className="card-body">
              <div className="spread">
                <div className="grow">
                  <div className="row mb">
                    <Avatar user={{ full_name: a.applicant_name, avatar_url: a.applicant_avatar }} size="sm" />
                    <Link to={`/profile/${a.applicant_id}`} className="strong">{a.applicant_name}</Link>
                    <VerifiedBadge status={a.applicant_verification} />
                    {Number(a.applicant_rating) > 0 && <Stars value={a.applicant_rating} />}
                  </div>
                  <div className="small muted">
                    Applied for <Link to={`/listing/${a.listing_id}`}>{a.title}</Link> · {timeAgo(a.created_at)}
                  </div>
                  <div className="small muted">
                    {[a.program, a.year_of_study && `Year ${a.year_of_study}`, a.university].filter(Boolean).join(' · ')}
                  </div>
                  <div className="small muted">{a.applicant_email}{a.applicant_phone ? ` · ${a.applicant_phone}` : ''}</div>
                  {a.cover_note && <p className="small mt" style={{ marginBottom: 0 }}>“{a.cover_note}”</p>}
                </div>
                <div className="stack-sm" style={{ minWidth: 160 }}>
                  <span className={`badge ${STATUS_TONE[a.status]}`}>{a.status}</span>
                  <select
                    value={a.status}
                    onChange={(e) => setStatus(a, e.target.value)}
                    aria-label="Update application status"
                  >
                    <option value="submitted">submitted</option>
                    {NEXT.map((s) => <option key={s} value={s}>{s}</option>)}
                  </select>
                </div>
              </div>
            </div></div>
          ))}
        </>
      )}
    </div>
  );
}
