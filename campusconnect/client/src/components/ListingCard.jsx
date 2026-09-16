import { Link, useNavigate } from 'react-router-dom';
import { api } from '../lib/api.js';
import { useAuth, useToast } from '../lib/store.jsx';
import {
  TYPE_ICONS, TYPE_LABELS, STATUS_LABELS, listingPrice, listingSubtitle, timeAgo,
} from '../lib/format.js';
import { useState } from 'react';

export default function ListingCard({ listing, onChange, showStatus = false }) {
  const { user } = useAuth();
  const toast = useToast();
  const navigate = useNavigate();
  const [fav, setFav] = useState(Boolean(listing.is_favorite));
  const [busy, setBusy] = useState(false);

  const price = listingPrice(listing);
  const subtitle = listingSubtitle(listing);

  async function toggleFavorite(e) {
    e.preventDefault();
    e.stopPropagation();
    if (!user) { navigate('/login'); return; }
    setBusy(true);
    try {
      const res = await api.post(`/favorites/${listing.id}/toggle`);
      setFav(res.is_favorite);
      toast.success(res.is_favorite ? 'Saved to your favourites.' : 'Removed from favourites.');
      onChange?.();
    } catch (err) {
      toast.error(err.message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <article className="listing-card">
      <Link to={`/listing/${listing.id}`} className="thumb">
        {listing.image
          ? <img src={listing.image} alt={listing.title} loading="lazy" />
          : <span>{TYPE_ICONS[listing.type]}</span>}
        <span className="type-tag">{TYPE_LABELS[listing.type]}</span>
      </Link>

      <button
        className={`fav ${fav ? 'on' : ''}`}
        onClick={toggleFavorite}
        disabled={busy}
        title={fav ? 'Remove from favourites' : 'Save to favourites'}
        aria-label={fav ? 'Remove from favourites' : 'Save to favourites'}
      >{fav ? '♥' : '♡'}</button>

      {showStatus && listing.status !== 'active' && (
        <div className={`status-strip ${listing.status}`}>{STATUS_LABELS[listing.status]}</div>
      )}

      <div className="body">
        <Link to={`/listing/${listing.id}`} className="title">{listing.title}</Link>
        {price && <div className="price">{price}</div>}
        {subtitle && <div className="meta">{subtitle}</div>}
        <div className="meta">
          {listing.location && <span>📍 {listing.location}</span>}
          <span>· {timeAgo(listing.created_at)}</span>
        </div>
        <div className="foot">
          {listing.seller_verification === 'verified' && <span className="badge ok" style={{ fontSize: '.64rem' }}>✓</span>}
          <span className="grow" style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
            {listing.seller_name}
          </span>
          {Number(listing.seller_rating) > 0 && (
            <span className="stars" style={{ fontSize: '.75rem' }}>★ {Number(listing.seller_rating).toFixed(1)}</span>
          )}
        </div>
      </div>
    </article>
  );
}

export function ListingGrid({ listings, onChange, showStatus }) {
  return (
    <div className="listing-grid">
      {listings.map((l) => (
        <ListingCard key={l.id} listing={l} onChange={onChange} showStatus={showStatus} />
      ))}
    </div>
  );
}
