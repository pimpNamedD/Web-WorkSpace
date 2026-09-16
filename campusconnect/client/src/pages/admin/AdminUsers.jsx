import { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { api, qs } from '../../lib/api.js';
import { useFetch, useToast } from '../../lib/store.jsx';
import { Avatar, Empty, Modal, Pagination, Spinner, VerifiedBadge } from '../../components/ui.jsx';
import { dateOnly } from '../../lib/format.js';

export default function AdminUsers() {
  const toast = useToast();
  const [params, setParams] = useSearchParams();
  const values = Object.fromEntries(params.entries());
  const { data, loading, reload } = useFetch(`/admin/users${qs({ ...values, limit: 25 })}`);
  const [suspend, setSuspend] = useState(null);
  const [reason, setReason] = useState('');
  const [creating, setCreating] = useState(false);
  const [newUser, setNewUser] = useState({ full_name: '', email: '', password: '', role: 'admin' });

  const users = data?.users ?? [];
  const update = (next) => setParams(Object.fromEntries(Object.entries(next).filter(([, v]) => v !== '')), { replace: true });

  async function act(fn, message) {
    try { await fn(); toast.success(message); reload(); }
    catch (err) { toast.error(err.message); }
  }

  return (
    <div className="stack">
      <div className="spread">
        <h2 style={{ margin: 0 }}>Users</h2>
        <button className="btn primary sm" onClick={() => setCreating(true)}>+ Create account</button>
      </div>

      <div className="panel">
        <div className="grid-3">
          <div className="field">
            <label htmlFor="q">Search</label>
            <input
              id="q" defaultValue={values.q ?? ''} placeholder="Name, e-mail or student number"
              onKeyDown={(e) => e.key === 'Enter' && update({ ...values, q: e.target.value, page: 1 })}
            />
          </div>
          <div className="field">
            <label htmlFor="st">Verification</label>
            <select id="st" value={values.status ?? ''} onChange={(e) => update({ ...values, status: e.target.value, page: 1 })}>
              <option value="">Any</option>
              {['verified', 'pending', 'unverified', 'rejected'].map((s) => <option key={s}>{s}</option>)}
            </select>
          </div>
          <div className="field">
            <label htmlFor="rl">Role</label>
            <select id="rl" value={values.role ?? ''} onChange={(e) => update({ ...values, role: e.target.value, page: 1 })}>
              <option value="">Any</option>
              {['student', 'employer', 'admin'].map((r) => <option key={r}>{r}</option>)}
            </select>
          </div>
        </div>
      </div>

      {loading && <Spinner />}
      {!loading && users.length === 0 && <Empty icon="👥" title="No users match those filters" />}

      {users.length > 0 && (
        <div className="card table-wrap">
          <table className="data">
            <thead>
              <tr>
                <th>Member</th><th>Role</th><th>Verification</th><th>Listings</th>
                <th>Rating</th><th>Joined</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {users.map((u) => (
                <tr key={u.id}>
                  <td>
                    <div className="row">
                      <Avatar user={u} size="sm" />
                      <div>
                        <Link to={`/profile/${u.id}`} className="strong">{u.full_name}</Link>
                        <div className="small muted">{u.email}</div>
                        {u.student_id && <div className="small muted">{u.student_id}</div>}
                      </div>
                    </div>
                  </td>
                  <td>
                    <select
                      value={u.role}
                      disabled={u.role === 'admin' && users.filter((x) => x.role === 'admin').length === 1}
                      onChange={(e) => act(
                        () => api.post(`/admin/users/${u.id}/role`, { role: e.target.value }),
                        `${u.full_name} is now ${e.target.value}.`,
                      )}
                      style={{ fontSize: '.8rem', padding: '.2rem .35rem' }}
                    >
                      {['student', 'employer', 'admin'].map((r) => <option key={r} value={r}>{r}</option>)}
                    </select>
                  </td>
                  <td>
                    <VerifiedBadge status={u.verification_status} />
                    {u.is_suspended === 1 && <div><span className="badge danger">suspended</span></div>}
                  </td>
                  <td>{u.listing_count}</td>
                  <td>{u.average_rating ? `★ ${u.average_rating}` : '—'}</td>
                  <td className="small muted nowrap">{dateOnly(u.created_at)}</td>
                  <td>
                    <div className="row-wrap">
                      {u.verification_status === 'pending' && (
                        <button
                          className="btn sm"
                          onClick={() => act(
                            () => api.post(`/admin/users/${u.id}/verify`, { decision: 'verified', note: 'Approved from the user list.' }),
                            `${u.full_name} verified.`,
                          )}
                        >Verify</button>
                      )}
                      {u.role !== 'admin' && (
                        u.is_suspended === 1
                          ? <button
                              className="btn sm"
                              onClick={() => act(() => api.post(`/admin/users/${u.id}/suspend`, { suspend: false }), 'Account restored.')}
                            >Restore</button>
                          : <button className="btn danger sm" onClick={() => { setSuspend(u); setReason(''); }}>Suspend</button>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <Pagination pagination={data?.pagination} onPage={(p) => update({ ...values, page: p })} />

      {suspend && (
        <Modal
          title={`Suspend ${suspend.full_name}`}
          onClose={() => setSuspend(null)}
          footer={<>
            <button className="btn" onClick={() => setSuspend(null)}>Cancel</button>
            <button
              className="btn danger"
              onClick={async () => {
                await act(() => api.post(`/admin/users/${suspend.id}/suspend`, { suspend: true, reason }), 'Account suspended.');
                setSuspend(null);
              }}
            >Suspend account</button>
          </>}
        >
          <p className="small muted">
            They will be signed out, their active listings archived, and the reason shown when they try to sign in.
          </p>
          <div className="field">
            <label htmlFor="sr">Reason</label>
            <textarea id="sr" value={reason} onChange={(e) => setReason(e.target.value)} autoFocus />
          </div>
        </Modal>
      )}

      {creating && (
        <Modal
          title="Create an account"
          onClose={() => setCreating(false)}
          footer={<>
            <button className="btn" onClick={() => setCreating(false)}>Cancel</button>
            <button
              className="btn primary"
              onClick={async () => {
                await act(() => api.post('/admin/users', newUser), 'Account created.');
                setCreating(false);
                setNewUser({ full_name: '', email: '', password: '', role: 'admin' });
              }}
            >Create</button>
          </>}
        >
          <div className="field">
            <label htmlFor="nn">Full name</label>
            <input id="nn" value={newUser.full_name} onChange={(e) => setNewUser({ ...newUser, full_name: e.target.value })} />
          </div>
          <div className="field">
            <label htmlFor="ne">E-mail</label>
            <input id="ne" type="email" value={newUser.email} onChange={(e) => setNewUser({ ...newUser, email: e.target.value })} />
          </div>
          <div className="grid-2">
            <div className="field">
              <label htmlFor="np2">Password</label>
              <input id="np2" type="password" value={newUser.password} onChange={(e) => setNewUser({ ...newUser, password: e.target.value })} />
            </div>
            <div className="field">
              <label htmlFor="nr">Role</label>
              <select id="nr" value={newUser.role} onChange={(e) => setNewUser({ ...newUser, role: e.target.value })}>
                {['admin', 'employer', 'student'].map((r) => <option key={r}>{r}</option>)}
              </select>
            </div>
          </div>
          <p className="small muted" style={{ marginBottom: 0 }}>Accounts created here are verified automatically.</p>
        </Modal>
      )}
    </div>
  );
}
