import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useFetch, useMeta, useAuth } from '../lib/store.jsx';
import { ListingGrid } from '../components/ListingCard.jsx';
import { Spinner } from '../components/ui.jsx';
import { TYPE_ICONS, TYPE_LABELS } from '../lib/format.js';

const ORDER = ['marketplace', 'accommodation', 'tutor', 'job', 'roommate', 'lostfound'];

export default function Home() {
  const meta = useMeta();
  const { user } = useAuth();
  const navigate = useNavigate();
  const [term, setTerm] = useState('');
  const stats = useFetch('/stats');
  const featured = useFetch('/listings/featured?per_type=4');
  const trending = useFetch('/trending?limit=4');

  const counts = stats.data?.by_type ?? {};
  const totals = stats.data?.totals ?? {};

  return (
    <>
      <section className="hero">
        <div className="container">
          <h1>Everything you need on campus, in one verified place.</h1>
          <p className="lead">
            Buy and sell, find accommodation and tutors, apply for part-time work, meet roommates and recover lost
            items — all through a single verified student account, instead of scattered WhatsApp and Facebook groups.
          </p>

          <form
            className="hero-search"
            onSubmit={(e) => { e.preventDefault(); navigate(`/search?q=${encodeURIComponent(term.trim())}`); }}
          >
            <input
              value={term}
              onChange={(e) => setTerm(e.target.value)}
              placeholder="Search textbooks, rooms, tutors, jobs…"
              aria-label="Search all listings"
            />
            <button className="btn" type="submit" style={{ padding: '.75rem 1.4rem', fontWeight: 700 }}>Search</button>
          </form>

          <div className="hero-stats">
            <div>
              <div className="n">{totals.active_listings ?? '—'}</div>
              <div className="l">Active listings</div>
            </div>
            <div>
              <div className="n">{totals.verified_students ?? '—'}</div>
              <div className="l">Verified members</div>
            </div>
            <div>
              <div className="n">{totals.reviews ?? '—'}</div>
              <div className="l">Reviews left</div>
            </div>
            <div>
              <div className="n">6</div>
              <div className="l">Services in one account</div>
            </div>
          </div>
        </div>
      </section>

      <div className="container page">
        <div className="module-grid">
          {ORDER.map((key) => {
            const def = meta?.listing_types?.[key];
            return (
              <Link key={key} to={`/browse/${key}`} className="module-card">
                <span className="ic">{TYPE_ICONS[key]}</span>
                <span className="nm">{TYPE_LABELS[key]}</span>
                <span className="ds">{def?.blurb ?? ''}</span>
                <span className="ct">{counts[key] ?? 0} active →</span>
              </Link>
            );
          })}
        </div>

        {!user && (
          <div className="card mt-2">
            <div className="card-body spread">
              <div>
                <h2 style={{ marginBottom: '.25rem' }}>Why verification matters</h2>
                <p className="muted small" style={{ maxWidth: '70ch', marginBottom: 0 }}>
                  Every member is checked against their university details before they can post. Combined with public
                  ratings and a reporting channel straight to the administrators, that makes it far harder for
                  scammers to operate than in an open chat group.
                </p>
              </div>
              <Link to="/register" className="btn primary">Create a student account</Link>
            </div>
          </div>
        )}

        {trending.data?.listings?.length > 0 && (
          <section className="mt-2">
            <div className="spread mb">
              <h2 style={{ margin: 0 }}>🔥 Trending this month</h2>
              <Link to="/search?sort=popular" className="small">See more</Link>
            </div>
            <ListingGrid listings={trending.data.listings} onChange={trending.reload} />
          </section>
        )}

        {featured.loading && <Spinner />}

        {ORDER.map((key) => {
          const items = featured.data?.featured?.[key] ?? [];
          if (!items.length) return null;
          return (
            <section key={key} className="mt-2">
              <div className="spread mb">
                <h2 style={{ margin: 0 }}>{TYPE_ICONS[key]} Latest in {TYPE_LABELS[key]}</h2>
                <Link to={`/browse/${key}`} className="small">Browse all {counts[key] ?? 0} →</Link>
              </div>
              <ListingGrid listings={items} onChange={featured.reload} />
            </section>
          );
        })}
      </div>
    </>
  );
}
