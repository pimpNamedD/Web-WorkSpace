import { useState } from 'react';
import { api } from '../lib/api.js';
import { useAuth, useMeta, useToast } from '../lib/store.jsx';
import { Alert, Avatar, Tabs, VerifiedBadge } from '../components/ui.jsx';

export default function Settings() {
  const { user, setUser } = useAuth();
  const meta = useMeta();
  const toast = useToast();
  const [tab, setTab] = useState('profile');

  const [profile, setProfile] = useState({
    full_name: user.full_name, phone: user.phone ?? '', bio: user.bio ?? '',
    program: user.program ?? '', year_of_study: user.year_of_study ?? '',
    email_notifications: user.email_notifications !== 0,
  });
  const [verify, setVerify] = useState({
    student_id: user.student_id ?? '', university: user.university ?? 'University of Lusaka',
    program: user.program ?? '', year_of_study: user.year_of_study ?? '',
  });
  const [pw, setPw] = useState({ current_password: '', new_password: '', confirm: '' });
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  async function saveProfile(e) {
    e.preventDefault();
    setBusy(true); setError('');
    try {
      const { user: updated } = await api.patch('/users/me', profile);
      setUser(updated);
      toast.success('Profile updated.');
    } catch (err) { setError(err.message); } finally { setBusy(false); }
  }

  async function submitVerification(e) {
    e.preventDefault();
    setBusy(true); setError('');
    try {
      const { user: updated } = await api.post('/users/me/verification', verify);
      setUser(updated);
      toast.success('Submitted. An administrator will review your details.');
    } catch (err) { setError(err.message); } finally { setBusy(false); }
  }

  async function changePassword(e) {
    e.preventDefault();
    setError('');
    if (pw.new_password !== pw.confirm) return setError('The two new passwords do not match.');
    setBusy(true);
    try {
      await api.post('/auth/change-password', { current_password: pw.current_password, new_password: pw.new_password });
      toast.success('Password changed.');
      setPw({ current_password: '', new_password: '', confirm: '' });
    } catch (err) { setError(err.message); } finally { setBusy(false); }
  }

  async function uploadAvatar(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    const fd = new FormData();
    fd.append('avatar', file);
    try {
      const { avatar_url } = await api.upload('/users/me/avatar', fd);
      setUser({ ...user, avatar_url });
      toast.success('Profile picture updated.');
    } catch (err) { toast.error(err.message); }
  }

  return (
    <div className="container page" style={{ maxWidth: 780 }}>
      <h1>Settings</h1>

      <Tabs
        value={tab}
        onChange={setTab}
        tabs={[
          { key: 'profile', label: 'Profile' },
          { key: 'verification', label: 'Verification' },
          { key: 'security', label: 'Password' },
        ]}
      />

      <Alert tone="error">{error}</Alert>

      {tab === 'profile' && (
        <div className="card"><div className="card-body">
          <div className="row mb" style={{ gap: '1rem' }}>
            <Avatar user={user} size="lg" />
            <div>
              <label htmlFor="av">Profile picture</label>
              <input id="av" type="file" accept="image/*" onChange={uploadAvatar} />
            </div>
          </div>

          <form onSubmit={saveProfile}>
            <div className="field">
              <label htmlFor="fn">Full name</label>
              <input id="fn" value={profile.full_name} onChange={(e) => setProfile({ ...profile, full_name: e.target.value })} />
            </div>
            <div className="field">
              <label htmlFor="em">E-mail (cannot be changed)</label>
              <input id="em" value={user.email} disabled />
            </div>
            <div className="grid-2">
              <div className="field">
                <label htmlFor="ph">Phone</label>
                <input id="ph" value={profile.phone} onChange={(e) => setProfile({ ...profile, phone: e.target.value })} />
              </div>
              <div className="field">
                <label htmlFor="yr">Year of study</label>
                <select id="yr" value={profile.year_of_study} onChange={(e) => setProfile({ ...profile, year_of_study: e.target.value })}>
                  <option value="">Not applicable</option>
                  {['1', '2', '3', '4', '5', 'Postgraduate'].map((y) => <option key={y}>{y}</option>)}
                </select>
              </div>
            </div>
            <div className="field">
              <label htmlFor="pg">Programme</label>
              <input id="pg" value={profile.program} onChange={(e) => setProfile({ ...profile, program: e.target.value })} />
            </div>
            <div className="field">
              <label htmlFor="bio">About you</label>
              <textarea
                id="bio" value={profile.bio}
                onChange={(e) => setProfile({ ...profile, bio: e.target.value })}
                placeholder="A short introduction shown on your public profile."
              />
            </div>
            <div className="field">
              <label className="check">
                <input
                  type="checkbox"
                  checked={profile.email_notifications}
                  onChange={(e) => setProfile({ ...profile, email_notifications: e.target.checked })}
                />
                Also send notifications to my e-mail address
              </label>
            </div>
            <button className="btn primary" disabled={busy}>Save changes</button>
          </form>
        </div></div>
      )}

      {tab === 'verification' && (
        <div className="card"><div className="card-body">
          <div className="spread mb">
            <h2 style={{ margin: 0 }}>Student verification</h2>
            <VerifiedBadge status={user.verification_status} />
          </div>

          {user.verification_status === 'verified' && (
            <Alert tone="ok">
              Your student status is verified{user.verification_note ? ` — ${user.verification_note}` : ''}. You can post
              across all six services.
            </Alert>
          )}
          {user.verification_status === 'pending' && (
            <Alert tone="warn">Your details are with an administrator. You will be notified once reviewed.</Alert>
          )}
          {user.verification_status === 'rejected' && (
            <Alert tone="error">
              Verification was not approved{user.verification_note ? `: ${user.verification_note}` : ''}. Correct your
              details below and submit again.
            </Alert>
          )}
          {user.verification_status === 'unverified' && (
            <Alert tone="info">
              Submit your student number and university so an administrator can confirm you are a genuine student.
            </Alert>
          )}

          {user.verification_status !== 'verified' && (
            <form onSubmit={submitVerification}>
              <div className="grid-2">
                <div className="field">
                  <label htmlFor="sid">Student number</label>
                  <input id="sid" required value={verify.student_id} onChange={(e) => setVerify({ ...verify, student_id: e.target.value })} />
                </div>
                <div className="field">
                  <label htmlFor="uni">University</label>
                  <select id="uni" value={verify.university} onChange={(e) => setVerify({ ...verify, university: e.target.value })}>
                    {(meta?.universities ?? ['University of Lusaka']).map((u) => <option key={u}>{u}</option>)}
                  </select>
                </div>
              </div>
              <div className="grid-2">
                <div className="field">
                  <label htmlFor="vpg">Programme</label>
                  <input id="vpg" value={verify.program} onChange={(e) => setVerify({ ...verify, program: e.target.value })} />
                </div>
                <div className="field">
                  <label htmlFor="vyr">Year of study</label>
                  <select id="vyr" value={verify.year_of_study} onChange={(e) => setVerify({ ...verify, year_of_study: e.target.value })}>
                    <option value="">Select</option>
                    {['1', '2', '3', '4', '5', 'Postgraduate'].map((y) => <option key={y}>{y}</option>)}
                  </select>
                </div>
              </div>
              <button className="btn primary" disabled={busy}>Submit for verification</button>
            </form>
          )}
        </div></div>
      )}

      {tab === 'security' && (
        <div className="card"><div className="card-body">
          <h2>Change password</h2>
          <form onSubmit={changePassword}>
            <div className="field">
              <label htmlFor="cp">Current password</label>
              <input id="cp" type="password" required value={pw.current_password} onChange={(e) => setPw({ ...pw, current_password: e.target.value })} />
            </div>
            <div className="grid-2">
              <div className="field">
                <label htmlFor="np">New password</label>
                <input id="np" type="password" required minLength={6} value={pw.new_password} onChange={(e) => setPw({ ...pw, new_password: e.target.value })} />
              </div>
              <div className="field">
                <label htmlFor="cf">Confirm new password</label>
                <input id="cf" type="password" required value={pw.confirm} onChange={(e) => setPw({ ...pw, confirm: e.target.value })} />
              </div>
            </div>
            <button className="btn primary" disabled={busy}>Change password</button>
          </form>
        </div></div>
      )}
    </div>
  );
}
