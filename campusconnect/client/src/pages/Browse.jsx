import { useMemo } from 'react';
import { Link, useParams, useSearchParams } from 'react-router-dom';
import { qs } from '../lib/api.js';
import { useFetch, useMeta, useAuth } from '../lib/store.jsx';
import Filters from '../components/Filters.jsx';
import { ListingGrid } from '../components/ListingCard.jsx';
import { Empty, Pagination, Spinner, Alert } from '../components/ui.jsx';
import { TYPE_ICONS, TYPE_LABELS } from '../lib/format.js';

const SORTS = [
  ['newest', 'Newest first'],
  ['oldest', 'Oldest first'],
  ['price_asc', 'Price: low to high'],
  ['price_desc', 'Price: high to low'],
  ['rating', 'Best rated sellers'],
  ['popular', 'Most viewed'],
];

export default function Browse() {
  const { type } = useParams();
  const meta = useMeta();
  const { user } = useAuth();
  const [params, setParams] = useSearchParams();

  const values = useMemo(() => Object.fromEntries(params.entries()), [params]);
  const def = meta?.listing_types?.[type];

  const path = `/listings${qs({ ...values, type, limit: 12 })}`;
  const { data, loading, error, reload } = useFetch(path, [type]);

  const update = (next) => {
    const clean = Object.fromEntries(Object.entries(next).filter(([, v]) => v !== '' && v !== undefined && v !== null));
    setParams(clean, { replace: true });
  };

  if (!def && meta) {
    return (
      <div className="container page">
        <Empty icon="🤔" title="Unknown section" action={<Link to="/" className="btn primary">Back to home</Link>} />
      </div>
    );
  }

  const listings = data?.listings ?? [];

  return (
    <div className="container page">
      <div className="spread mb">
        <div>
          <h1 style={{ marginBottom: '.15rem' }}>{TYPE_ICONS[type]} {TYPE_LABELS[type]}</h1>
          <p className="muted small" style={{ marginBottom: 0 }}>{def?.blurb}</p>
        </div>
        {user && <Link to={`/post?type=${type}`} className="btn primary">+ Post {def?.singular?.toLowerCase()}</Link>}
      </div>

      <div className="with-side">
        <Filters
          type={type}
          values={values}
          onChange={update}
          onReset={() => setParams({}, { replace: true })}
        />

        <div>
          <div className="spread mb">
            <span className="small muted">
              {loading ? 'Searching…' : `${data?.pagination?.total ?? 0} listing${data?.pagination?.total === 1 ? '' : 's'} found`}
            </span>
            <div className="row">
              <label htmlFor="sort" className="small muted" style={{ margin: 0 }}>Sort</label>
              <select
                id="sort"
                value={values.sort ?? 'newest'}
                onChange={(e) => update({ ...values, sort: e.target.value, page: 1 })}
                style={{ width: 'auto', fontSize: '.85rem', padding: '.35rem .5rem' }}
              >
                {SORTS.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
              </select>
            </div>
          </div>

          <Alert tone="error">{error}</Alert>

          {loading && <Spinner />}

          {!loading && listings.length === 0 && (
            <Empty
              icon={TYPE_ICONS[type]}
              title="No listings match your filters"
              action={<button className="btn" onClick={() => setParams({}, { replace: true })}>Clear filters</button>}
            >
              Try widening the price range, clearing the location, or checking back later.
            </Empty>
          )}

          {listings.length > 0 && <ListingGrid listings={listings} onChange={reload} />}

          <Pagination
            pagination={data?.pagination}
            onPage={(p) => { update({ ...values, page: p }); window.scrollTo({ top: 0, behavior: 'smooth' }); }}
          />
        </div>
      </div>
    </div>
  );
}
