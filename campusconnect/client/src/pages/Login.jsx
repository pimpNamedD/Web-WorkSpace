import { useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useAuth, useToast } from '../lib/store.jsx';
import { Alert } from '../components/ui.jsx';

const DEMO = [
  ['Student (verified)', 'dalitso.mwansa@student.unilus.ac.zm'],
  ['Student (pending verification)', 'natasha.banda@gmail.com'],
  ['Employer', 'careers@zamtel-demo.co.zm'],
  ['Administrator', 'admin@unilus.ac.zm'],
];

export default function Login() {
  const { login } = useAuth();
  const toast = useToast();
  const navigate = useNavigate();
  const location = useLocation();
  const [form, setForm] = useState({ email: '', password: '' });
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  async function submit(e) {
    e.preventDefault();
    setBusy(true);
    setError('');
    try {
      const user = await login(form.email.trim(), form.password);
      toast.success(`Welcome back, ${user.full_name.split(' ')[0]}.`);
      navigate(location.state?.from ?? (user.role === 'admin' ? '/admin' : '/dashboard'), { replace: true });
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
            <h1>Sign in</h1>
            <p className="muted small">Use your Campus Connect account to reach all six services.</p>

            <Alert tone="error">{error}</Alert>

            <form onSubmit={submit}>
              <div className="field">
                <label htmlFor="email">E-mail address</label>
                <input
                  id="email" type="email" required autoComplete="email" autoFocus
                  value={form.email}
                  onChange={(e) => setForm({ ...form, email: e.target.value })}
                />
              </div>
              <div className="field">
                <label htmlFor="password">Password</label>
                <input
                  id="password" type="password" required autoComplete="current-password"
                  value={form.password}
                  onChange={(e) => setForm({ ...form, password: e.target.value })}
                />
              </div>
              <button className="btn primary block" disabled={busy}>
                {busy ? 'Signing in…' : 'Sign in'}
              </button>
            </form>

            <p className="small mt center muted">
              No account yet? <Link to="/register">Register as a student</Link>
            </p>
          </div>
        </div>

        <div className="panel">
          <h3>Demonstration accounts</h3>
          <p className="small muted">
            Every demonstration account uses the password <code>password123</code>. Click one to fill the form.
          </p>
          <div className="stack-sm">
            {DEMO.map(([label, email]) => (
              <button
                key={email}
                className="btn sm"
                style={{ justifyContent: 'flex-start', textAlign: 'left' }}
                onClick={() => setForm({ email, password: 'password123' })}
              >
                <span className="grow">
                  <span className="strong">{label}</span><br />
                  <span className="small muted">{email}</span>
                </span>
              </button>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
