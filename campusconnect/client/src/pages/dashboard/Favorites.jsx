import { Link } from 'react-router-dom';
import { useFetch } from '../../lib/store.jsx';
import { ListingGrid } from '../../components/ListingCard.jsx';
import { Empty, Spinner } from '../../components/ui.jsx';

export default function Favorites() {
  const { data, loading, reload } = useFetch('/favorites');
  const listings = data?.listings ?? [];

  return (
    <div className="stack">
      <h2 style={{ margin: 0 }}>Favourites</h2>
      <p className="small muted" style={{ margin: 0 }}>
        Listings you have saved with the ♥ button, so you can come back to them without searching again.
      </p>

      {loading && <Spinner />}

      {!loading && listings.length === 0 && (
        <Empty icon="♡" title="No favourites yet" action={<Link to="/" className="btn primary">Browse listings</Link>}>
          Tap the heart on any listing to save it here.
        </Empty>
      )}

      {listings.length > 0 && <ListingGrid listings={listings} showStatus onChange={reload} />}
    </div>
  );
}
