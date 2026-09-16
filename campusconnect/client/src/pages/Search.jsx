import { useMemo } from 'react';
import { useSearchParams } from 'react-router-dom';
import { qs } from '../lib/api.js';
import { useFetch } from '../lib/store.jsx';
import { ListingGrid } from '../components/ListingCard.jsx';
import { Empty, Pagination, Spinner } from '../components/ui.jsx';
import { TYPE_ICONS, TYPE_LABELS } from '../lib/format.js';

/** Global search across every module at once (1.5 "Search and Filtering"). */
export default function Search() {
  const [params, setParams] = useSearchParams();
  const values = useMemo(() => Object.fromEntries(params.entries()), [params]);
  const { data, loading, reload } = useFetch(`/search${qs({ ...values, limit: 16 })}`);

  const counts = Object.fromEntries((data?.counts_by_type ?? []).map((c) => [c.type, Number(c.count)]));
  const update = (next) => setParams(
    Object.fromEntries(Object.entries(next).filter(([, v]) => v !== '' && v !== undefined)),
    { replace: true },
  );

  return (
    <div className="container page">
      <h1>Search results</h1>
      <form
        className="row mb"
        onSubmit={(e) => { e.preventDefault(); update({ ...values, q: e.target.q.value, page: 1 }); }}
      >
        <input name="q" defaultValue={values.q ?? ''} placeholder="Search all listings" aria-label="Search" />
        <button className="btn primary">Search</button>
      </form>

      <div className="row-wrap mb">
        <button className={`chip ${!values.type ? 'on' : ''}`} onClick={() => update({ ...values, type: '', page: 1 })}>
          All results {data?.pagination ? `(${data.pagination.total})` : ''}
        </button>
        {Object.keys(TYPE_LABELS).map((t) => (
          <button
            key={t}
            className={`chip ${values.type === t ? 'on' : ''}`}
            onClick={() => update({ ...values, type: t, page: 1 })}
          >
            {TYPE_ICONS[t]} {TYPE_LABELS[t]} {counts[t] ? `(${counts[t]})` : ''}
          </button>
        ))}
      </div>

      {loading && <Spinner />}

      {!loading && (data?.listings?.length ?? 0) === 0 && (
        <Empty icon="🔍" title={`Nothing found for "${values.q ?? ''}"`}>
          Check the spelling, use fewer words, or browse a section from the menu above.
        </Empty>
      )}

      {data?.listings?.length > 0 && <ListingGrid listings={data.listings} onChange={reload} />}

      <Pagination
        pagination={data?.pagination}
        onPage={(p) => { update({ ...values, page: p }); window.scrollTo({ top: 0, behavior: 'smooth' }); }}
      />
    </div>
  );
}
