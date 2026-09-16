import { useState } from 'react';
import { useAuth, useMeta, useToast } from '../lib/store.jsx';
import { api } from '../lib/api.js';
import { Modal } from './ui.jsx';

/**
 * Advanced search filters (1.4.1). The panel adapts to whichever module is
 * being browsed, and any filter set can be stored as a saved search.
 */
export default function Filters({ type, values, onChange, onReset }) {
  const meta = useMeta();
  const { user } = useAuth();
  const toast = useToast();
  const [saving, setSaving] = useState(false);
  const [name, setName] = useState('');

  const def = meta?.listing_types?.[type];
  const fields = def?.fields ?? [];
  const set = (key) => (e) => onChange({ ...values, [key]: e.target.value, page: 1 });

  const activeCount = Object.entries(values)
    .filter(([k, v]) => !['page', 'sort', 'type', 'limit'].includes(k) && v !== '' && v !== undefined).length;

  async function saveSearch(e) {
    e.preventDefault();
    try {
      await api.post('/saved-searches', { name: name.trim(), type, query: { ...values, type } });
      toast.success('Saved. You will be notified when new listings match.');
      setSaving(false);
      setName('');
    } catch (err) {
      toast.error(err.message);
    }
  }

  return (
    <aside className="panel filters">
      <div className="spread mb">
        <h3 style={{ margin: 0 }}>Filters</h3>
        {activeCount > 0 && <button className="btn ghost sm" onClick={onReset}>Clear ({activeCount})</button>}
      </div>

      <div className="field">
        <label htmlFor="f-q">Keyword</label>
        <input id="f-q" value={values.q ?? ''} onChange={set('q')} placeholder="Search this section" />
      </div>

      {def?.categories?.length > 0 && (
        <div className="field">
          <label htmlFor="f-cat">{type === 'lostfound' ? 'Lost or found' : 'Category'}</label>
          <select id="f-cat" value={values.category ?? ''} onChange={set('category')}>
            <option value="">All</option>
            {def.categories.map((c) => <option key={c} value={c}>{c}</option>)}
          </select>
        </div>
      )}

      <div className="field">
        <label htmlFor="f-loc">Location</label>
        <input id="f-loc" list="known-locations" value={values.location ?? ''} onChange={set('location')} placeholder="Any area" />
        <datalist id="known-locations">
          {(meta?.locations ?? []).map((l) => <option key={l} value={l} />)}
        </datalist>
      </div>

      {(fields.includes('price') || type === 'roommate') && (
        <div className="field">
          <label>{type === 'roommate' ? 'Budget range (ZMW)' : `${def?.priceLabel ?? 'Price'} range`}</label>
          <div className="grid-2">
            <input type="number" min="0" placeholder="Min" value={values.min_price ?? ''} onChange={set('min_price')} />
            <input type="number" min="0" placeholder="Max" value={values.max_price ?? ''} onChange={set('max_price')} />
          </div>
        </div>
      )}

      {fields.includes('item_condition') && (
        <div className="field">
          <label htmlFor="f-cond">Condition</label>
          <select id="f-cond" value={values.condition ?? ''} onChange={set('condition')}>
            <option value="">Any</option>
            {(meta?.conditions ?? []).map((c) => <option key={c} value={c}>{c}</option>)}
          </select>
        </div>
      )}

      {fields.includes('bedrooms') && (
        <>
          <div className="field">
            <label htmlFor="f-bed">Minimum bedrooms</label>
            <select id="f-bed" value={values.bedrooms ?? ''} onChange={set('bedrooms')}>
              <option value="">Any</option>
              {[1, 2, 3, 4].map((n) => <option key={n} value={n}>{n}+</option>)}
            </select>
          </div>
          <div className="field">
            <label className="check">
              <input
                type="checkbox"
                checked={values.furnished === '1'}
                onChange={(e) => onChange({ ...values, furnished: e.target.checked ? '1' : '', page: 1 })}
              />
              Furnished only
            </label>
          </div>
        </>
      )}

      {fields.includes('level') && (
        <div className="field">
          <label htmlFor="f-lvl">Level</label>
          <select id="f-lvl" value={values.level ?? ''} onChange={set('level')}>
            <option value="">Any</option>
            {(meta?.levels ?? []).map((l) => <option key={l} value={l}>{l}</option>)}
          </select>
        </div>
      )}

      {fields.includes('job_type') && (
        <div className="field">
          <label htmlFor="f-jt">Opportunity type</label>
          <select id="f-jt" value={values.job_type ?? ''} onChange={set('job_type')}>
            <option value="">Any</option>
            {(meta?.job_types ?? []).map((j) => <option key={j} value={j}>{j}</option>)}
          </select>
        </div>
      )}

      {fields.includes('gender_pref') && (
        <div className="field">
          <label htmlFor="f-gp">Gender preference</label>
          <select id="f-gp" value={values.gender_pref ?? ''} onChange={set('gender_pref')}>
            <option value="">Any</option>
            {(meta?.gender_prefs ?? []).map((g) => <option key={g} value={g}>{g}</option>)}
          </select>
        </div>
      )}

      <div className="field">
        <label htmlFor="f-rate">Minimum seller rating</label>
        <select id="f-rate" value={values.min_rating ?? ''} onChange={set('min_rating')}>
          <option value="">Any rating</option>
          <option value="4.5">4.5 and above</option>
          <option value="4">4 and above</option>
          <option value="3">3 and above</option>
        </select>
      </div>

      <div className="field">
        <label htmlFor="f-age">Posted within</label>
        <select id="f-age" value={values.posted_within ?? ''} onChange={set('posted_within')}>
          <option value="">Any time</option>
          <option value="1">Last 24 hours</option>
          <option value="7">Last 7 days</option>
          <option value="30">Last 30 days</option>
        </select>
      </div>

      <div className="field">
        <label className="check">
          <input
            type="checkbox"
            checked={values.verified_only === '1'}
            onChange={(e) => onChange({ ...values, verified_only: e.target.checked ? '1' : '', page: 1 })}
          />
          Verified students only
        </label>
      </div>

      {user && (
        <button className="btn block" onClick={() => setSaving(true)}>🔔 Save this search</button>
      )}

      {saving && (
        <Modal
          title="Save this search"
          onClose={() => setSaving(false)}
          footer={<>
            <button className="btn" onClick={() => setSaving(false)}>Cancel</button>
            <button className="btn primary" onClick={saveSearch} disabled={!name.trim()}>Save search</button>
          </>}
        >
          <p className="small muted">
            Campus Connect will notify you whenever a new listing matches these filters.
          </p>
          <form onSubmit={saveSearch}>
            <div className="field">
              <label htmlFor="ss-name">Name this search</label>
              <input
                id="ss-name"
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="e.g. Rooms under K2000 in Chalala"
                autoFocus
              />
            </div>
          </form>
        </Modal>
      )}
    </aside>
  );
}
