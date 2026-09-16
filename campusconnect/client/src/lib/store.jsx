import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { api, getToken, setToken } from './api.js';

/* ===========================================================================
   Toasts
   =========================================================================== */
const ToastCtx = createContext(null);
export const useToast = () => useContext(ToastCtx);

export function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([]);
  const nextId = useRef(1);

  const push = useCallback((message, tone = 'ok') => {
    const id = nextId.current++;
    setToasts((t) => [...t, { id, message, tone }]);
    setTimeout(() => setToasts((t) => t.filter((x) => x.id !== id)), 4200);
  }, []);

  const value = useMemo(() => ({
    success: (m) => push(m, 'ok'),
    error: (m) => push(m, 'error'),
    info: (m) => push(m, ''),
  }), [push]);

  return (
    <ToastCtx.Provider value={value}>
      {children}
      <div className="toast-wrap">
        {toasts.map((t) => <div key={t.id} className={`toast ${t.tone}`}>{t.message}</div>)}
      </div>
    </ToastCtx.Provider>
  );
}

/* ===========================================================================
   Authentication + live counters
   =========================================================================== */
const AuthCtx = createContext(null);
export const useAuth = () => useContext(AuthCtx);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(Boolean(getToken()));
  const [counts, setCounts] = useState({ notifications: 0, messages: 0 });

  const refreshCounts = useCallback(async () => {
    if (!getToken()) return setCounts({ notifications: 0, messages: 0 });
    try {
      const [n, m] = await Promise.all([
        api.get('/notifications/unread-count'),
        api.get('/messages/unread-count'),
      ]);
      setCounts({ notifications: n.count, messages: m.count });
    } catch { /* ignore transient errors */ }
  }, []);

  useEffect(() => {
    if (!getToken()) return setLoading(false);
    api.get('/auth/me')
      .then((d) => { setUser(d.user); refreshCounts(); })
      .catch(() => setToken(null))
      .finally(() => setLoading(false));
  }, [refreshCounts]);

  // Poll for new notifications/messages while signed in.
  useEffect(() => {
    if (!user) return undefined;
    const id = setInterval(refreshCounts, 45000);
    return () => clearInterval(id);
  }, [user, refreshCounts]);

  const login = useCallback(async (email, password) => {
    const data = await api.post('/auth/login', { email, password });
    setToken(data.token);
    setUser(data.user);
    refreshCounts();
    return data.user;
  }, [refreshCounts]);

  const register = useCallback(async (payload) => {
    const data = await api.post('/auth/register', payload);
    setToken(data.token);
    setUser(data.user);
    refreshCounts();
    return data.user;
  }, [refreshCounts]);

  const logout = useCallback(() => {
    setToken(null);
    setUser(null);
    setCounts({ notifications: 0, messages: 0 });
  }, []);

  const value = useMemo(() => ({
    user, loading, counts, login, register, logout, setUser, refreshCounts,
    isAdmin: user?.role === 'admin',
    isVerified: user?.verification_status === 'verified',
  }), [user, loading, counts, login, register, logout, refreshCounts]);

  return <AuthCtx.Provider value={value}>{children}</AuthCtx.Provider>;
}

/* ===========================================================================
   Reference data (listing types, categories, option lists)
   =========================================================================== */
const MetaCtx = createContext(null);
export const useMeta = () => useContext(MetaCtx);

export function MetaProvider({ children }) {
  const [meta, setMeta] = useState(null);
  useEffect(() => { api.get('/meta').then(setMeta).catch(() => setMeta(null)); }, []);
  return <MetaCtx.Provider value={meta}>{children}</MetaCtx.Provider>;
}

/* ===========================================================================
   Small data-fetching hook with loading / error state
   =========================================================================== */
export function useFetch(path, deps = [], { skip = false } = {}) {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(!skip);
  const [error, setError] = useState(null);
  const [nonce, setNonce] = useState(0);

  useEffect(() => {
    if (skip || !path) { setLoading(false); return undefined; }
    const controller = new AbortController();
    let cancelled = false;
    setLoading(true);
    setError(null);
    api.get(path, { signal: controller.signal })
      .then((result) => { if (!cancelled) { setData(result); setLoading(false); } })
      .catch((err) => {
        // An aborted request is superseded by a newer one, so leave the
        // loading flag set and let that request resolve the state.
        if (cancelled || err.name === 'AbortError') return;
        setError(err.message);
        setLoading(false);
      });
    return () => { cancelled = true; controller.abort(); };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [path, skip, nonce, ...deps]);

  return { data, loading, error, reload: () => setNonce((n) => n + 1), setData };
}
