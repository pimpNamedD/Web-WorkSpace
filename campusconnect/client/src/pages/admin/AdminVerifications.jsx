import { useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../../lib/api.js';
import { useFetch, useToast } from '../../lib/store.jsx';
import { Avatar, Empty, Modal, Spinner, Tabs } from '../../components/ui.jsx';
import { dateOnly, timeAgo } from '../../lib/format.js';

/** The student verification queue — objective 1 of the proposal. */
export default function AdminVerifications() {
  const toast = useToast();
  const [status, setStatus] = useState('pending');
  const { data, loading, reload } = useFetch(`/admin/verifications?status=${status}`, [status]);
  const [reject, setReject] = useState(null);
  const [note, setNote] = useState('');

  const users = data?.users ?? [];

  async function decide(user, decision, reason) {
    try {
      await api.post(`/admin/users/${user.id}/verify`, { decision, note: reason });
      toast.success(decision === 'verified' ? `${user.full_name} is now verified.` : 'Request rejected and the student notified.');
      reload();
    } catch (err) { toast.error(err.message); }
  }

  return (
    <div className="stack">
      <h2 style={{ margin: 0 }}>Verification queue</h2>
      <p className="small muted" style={{ margin: 0 }}>
        Confirm that each student number and university match a genuine student before allowing them to post.
      </p>

      <Tabs
        value={status}
        onChange={setStatus}
        tabs={[
          { key: 'pending', label: 'Pending' },
          { key: 'unverified', label: 'Not submitted' },
          { key: 'verified', label: 'Verified' },
          { key: 'rejected', label: 'Rejected' },
        ]}
      />

      {loading && <Spinner />}

      {!loading && users.length === 0 && (
        <Empty icon="✅" title="Nothing in this queue">
          {status === 'pending' ? 'Every verification request has been dealt with.' : 'No accounts with this status.'}
        </Empty>
      )}

      {users.map((u) => (
        <div className="card" key={u.id}><div className="card-body">
          <div className="spread">
            <div className="row grow" style={{ gap: '.85rem', alignItems: 'flex-start' }}>
              <Avatar user={u} />
              <div>
                <Link to={`/profile/${u.id}`} className="strong">{u.full_name}</Link>
                <div className="small muted">{u.email}</div>
                <div className="spec-list mt" style={{ gap: '.6rem' }}>
                  <div><div className="k">Student number</div><div className="v">{u.student_id ?? '—'}</div></div>
                  <div><div className="k">University</div><div className="v">{u.university ?? '—'}</div></div>
                  <div><div className="k">Programme</div><div className="v">{u.program ?? '—'}</div></div>
                  <div><div className="k">Year</div><div className="v">{u.year_of_study ?? '—'}</div></div>
                </div>
                <div className="small muted mt">
                  Registered {dateOnly(u.created_at)} · submitted {timeAgo(u.updated_at)}
                </div>
                {u.verification_note && <div className="small muted">Note: {u.verification_note}</div>}
              </div>
            </div>

            {status !== 'verified' && (
              <div className="stack-sm" style={{ minWidth: 150 }}>
                <button className="btn primary sm" onClick={() => decide(u, 'verified', 'Student details confirmed by an administrator.')}>
                  ✓ Approve
                </button>
                <button className="btn danger sm" onClick={() => { setReject(u); setNote(''); }}>✕ Reject</button>
              </div>
            )}
          </div>
        </div></div>
      ))}

      {reject && (
        <Modal
          title={`Reject ${reject.full_name}`}
          onClose={() => setReject(null)}
          footer={<>
            <button className="btn" onClick={() => setReject(null)}>Cancel</button>
            <button
              className="btn danger"
              onClick={async () => { await decide(reject, 'rejected', note); setReject(null); }}
            >Reject request</button>
          </>}
        >
          <div className="field">
            <label htmlFor="rn">Reason (shown to the student)</label>
            <textarea
              id="rn" value={note} onChange={(e) => setNote(e.target.value)} autoFocus
              placeholder="e.g. The student number does not match our records. Please check and resubmit."
            />
          </div>
        </Modal>
      )}
    </div>
  );
}
