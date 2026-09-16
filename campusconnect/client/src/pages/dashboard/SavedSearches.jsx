import { Link } from 'react-router-dom';
import { api, qs } from '../../lib/api.js';
import { useFetch, useToast } from '../../lib/store.jsx';
import { Empty, Spinner } from '../../components/ui.jsx';
import { TYPE_ICONS, TYPE_LABELS, dateOnly } from '../../lib/format.js';

const LABELS = {
  q: 'Keyword', category: 'Category', location: 'Location', min_price: 'From', max_price: 'Up to',
  condition: 'Condition', bedrooms: 'Min bedrooms', furnished: 'Furnished', subject: 'Subject',
  level: 'Level', job_type: 'Type', gender_pref: 'Gender', min_rating: 'Min rating',
  verified_only: 'Verified only', sort: 'Sorted by',
};

export default function SavedSearches() {
  const toast = useToast();
  const { data, loading, reload } = useFetch('/saved-searches');
  const searches = data?.saved_searches ?? [];

  async function toggle(s) {
    try {
      await api.patch(`/saved-searches/${s.id}`, { notify: !s.notify });
      toast.success(s.notify ? 'Alerts muted.' : 'Alerts switched on.');
      reload();
    } catch (err) { toast.error(err.message); }
  }

  async function remove(s) {
    try { await api.del(`/saved-searches/${s.id}`); toast.success('Saved search deleted.'); reload(); }
    catch (err) { toast.error(err.message); }
  }

  return (
    <div className="stack">
      <h2 style={{ margin: 0 }}>Saved searches</h2>
      <p className="small muted" style={{ margin: 0 }}>
        Campus Connect notifies you whenever a newly posted listing matches one of these filter sets.
      </p>

      {loading && <Spinner />}

      {!loading && searches.length === 0 && (
        <Empty icon="🔔" title="No saved searches yet" action={<Link to="/browse/accommodation" className="btn primary">Browse and save a search</Link>}>
          Set your filters in any section, then press “Save this search”.
        </Empty>
      )}

      {searches.map((s) => {
        const entries = Object.entries(s.query ?? {}).filter(([k]) => k !== 'type' && LABELS[k]);
        return (
          <div className="card" key={s.id}>
            <div className="card-body">
              <div className="spread">
                <div className="grow">
                  <div className="row-wrap mb">
                    <strong>{s.name}</strong>
                    {s.type && <span className="badge">{TYPE_ICONS[s.type]} {TYPE_LABELS[s.type]}</span>}
                    <span className={`badge ${s.notify ? 'ok' : ''}`}>{s.notify ? '🔔 Alerts on' : '🔕 Muted'}</span>
                  </div>
                  <div className="row-wrap small muted">
                    {entries.length === 0 && <span>All listings in this section</span>}
                    {entries.map(([k, v]) => (
                      <span key={k} className="badge">{LABELS[k]}: {String(v)}</span>
                    ))}
                  </div>
                  {s.last_alerted_at && (
                    <div className="small muted mt">Last match notified {dateOnly(s.last_alerted_at)}</div>
                  )}
                </div>
                <div className="row-wrap">
                  <Link
                    className="btn sm"
                    to={s.type ? `/browse/${s.type}${qs(s.query)}` : `/search${qs(s.query)}`}
                  >Run search</Link>
                  <button className="btn sm" onClick={() => toggle(s)}>{s.notify ? 'Mute' : 'Enable alerts'}</button>
                  <button className="btn danger sm" onClick={() => remove(s)}>Delete</button>
                </div>
              </div>
            </div>
          </div>
        );
      })}
    </div>
  );
}
