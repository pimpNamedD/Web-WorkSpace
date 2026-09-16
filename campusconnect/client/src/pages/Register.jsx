import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth, useMeta, useToast } from '../lib/store.jsx';
import { Alert } from '../components/ui.jsx';

export default function Register() {
  const { register } = useAuth();
  const meta = useMeta();
  const toast = useToast();
  const navigate = useNavigate();
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [form, setForm] = useState({
    role: 'student',
    full_name: '',
    email: '',
    password: '',
    confirm: '',
    student_id: '',
    university: 'University of Lusaka',
    program: '',
    year_of_study: '',
    phone: '',
  });

  const set = (k) => (e) => setForm({ ...form, [k]: e.target.value });
  const willAutoVerify = form.role === 'student' && /@(student\.)?unilus\.ac\.zm$/i.test(form.email.trim());

  async function submit(e) {
    e.preventDefault();
    setError('');
    if (form.password !== form.confirm) return setError('The two passwords do not match.');
    if (form.password.length < 6) return setError('Choose a password of at least 6 characters.');

    setBusy(true);
    try {
      const { confirm, ...payload } = form;
      if (payload.role !== 'student') { delete payload.student_id; delete payload.program; delete payload.year_of_study; }
      const user = await register(payload);
      toast.success(
        user.verification_status === 'verified'
          ? 'Account created and verified. You can start posting right away.'
          : 'Account created. Submit your student details for verification.',
      );
      navigate(user.verification_status === 'verified' ? '/dashboard' : '/settings');
    } catch (err) {
      setError(err.message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="container page" style={{ maxWidth: 880 }}>
      <div className="grid-2" style={{ gap: '1.5rem', alignItems: 'start' }}>
        <div className="card">
          <div className="card-body">
            <h1>Create your account</h1>
            <p className="muted small">One account gives you access to all six Campus Connect services.</p>

            <Alert tone="error">{error}</Alert>

            <form onSubmit={submit}>
              <div className="field">
                <label>I am registering as</label>
                <div className="row">
                  {[['student', '🎓 A student'], ['employer', '🏢 An employer or landlord']].map(([v, l]) => (
                    <button
                      key={v}
                      type="button"
                      className={`btn ${form.role === v ? 'active' : ''}`}
                      onClick={() => setForm({ ...form, role: v })}
                    >{l}</button>
                  ))}
                </div>
              </div>

              <div className="field">
                <label htmlFor="full_name">Full name</label>
                <input id="full_name" required value={form.full_name} onChange={set('full_name')} />
              </div>

              <div className="field">
                <label htmlFor="email">E-mail address</label>
                <input id="email" type="email" required value={form.email} onChange={set('email')} />
                {form.role === 'student' && (
                  <div className="hint">
                    {willAutoVerify
                      ? '✓ A university e-mail address means your student status is verified immediately.'
                      : 'Using your university e-mail verifies you instantly. Otherwise an administrator will review your details.'}
                  </div>
                )}
              </div>

              {form.role === 'student' && (
                <>
                  <div className="grid-2">
                    <div className="field">
                      <label htmlFor="student_id">Student number</label>
                      <input id="student_id" required value={form.student_id} onChange={set('student_id')} placeholder="e.g. BIT23221244" />
                    </div>
                    <div className="field">
                      <label htmlFor="university">University</label>
                      <select id="university" value={form.university} onChange={set('university')}>
                        {(meta?.universities ?? ['University of Lusaka']).map((u) => <option key={u}>{u}</option>)}
                      </select>
                    </div>
                  </div>
                  <div className="grid-2">
                    <div className="field">
                      <label htmlFor="program">Programme of study</label>
                      <input id="program" value={form.program} onChange={set('program')} placeholder="e.g. BSc Information Technology" />
                    </div>
                    <div className="field">
                      <label htmlFor="year">Year of study</label>
                      <select id="year" value={form.year_of_study} onChange={set('year_of_study')}>
                        <option value="">Select</option>
                        {['1', '2', '3', '4', '5', 'Postgraduate'].map((y) => <option key={y}>{y}</option>)}
                      </select>
                    </div>
                  </div>
                </>
              )}

              <div className="field">
                <label htmlFor="phone">Phone number (optional)</label>
                <input id="phone" value={form.phone} onChange={set('phone')} placeholder="+260 …" />
              </div>

              <div className="grid-2">
                <div className="field">
                  <label htmlFor="password">Password</label>
                  <input id="password" type="password" required minLength={6} value={form.password} onChange={set('password')} />
                </div>
                <div className="field">
                  <label htmlFor="confirm">Confirm password</label>
                  <input id="confirm" type="password" required value={form.confirm} onChange={set('confirm')} />
                </div>
              </div>

              <button className="btn primary block" disabled={busy}>
                {busy ? 'Creating account…' : 'Create account'}
              </button>
            </form>

            <p className="small mt center muted">
              Already registered? <Link to="/login">Sign in</Link>
            </p>
          </div>
        </div>

        <div className="panel">
          <h3>How verification works</h3>
          <ol className="small muted" style={{ paddingLeft: '1.1rem', lineHeight: 1.7 }}>
            <li>Register with your student number and university.</li>
            <li>A university e-mail address (<code>@unilus.ac.zm</code>) verifies you automatically.</li>
            <li>Otherwise your details join the administrator queue and you are notified once reviewed.</li>
            <li>Only verified accounts can publish listings, which is what keeps scammers out.</li>
          </ol>
          <div className="alert info small" style={{ marginTop: '1rem' }}>
            You can browse everything without an account. Verification is only required to post, message and review.
          </div>
        </div>
      </div>
    </div>
  );
}
