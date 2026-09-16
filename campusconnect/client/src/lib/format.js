export const TYPE_ICONS = {
  marketplace: '🛒',
  accommodation: '🏠',
  tutor: '📘',
  job: '💼',
  roommate: '👥',
  lostfound: '🔎',
};

export const TYPE_LABELS = {
  marketplace: 'Marketplace',
  accommodation: 'Accommodation',
  tutor: 'Tutors',
  job: 'Jobs',
  roommate: 'Roommates',
  lostfound: 'Lost & Found',
};

/** Zambian kwacha, no decimals when whole. */
export function money(amount, unit) {
  if (amount === null || amount === undefined || amount === '') return null;
  const n = Number(amount);
  if (Number.isNaN(n)) return null;
  const formatted = `K${n.toLocaleString('en-ZM', { minimumFractionDigits: 0, maximumFractionDigits: n % 1 === 0 ? 0 : 2 })}`;
  return unit && unit !== 'total' ? `${formatted} ${unit}` : formatted;
}

export function timeAgo(value) {
  if (!value) return '';
  const then = new Date(value).getTime();
  const seconds = Math.floor((Date.now() - then) / 1000);
  if (seconds < 60) return 'just now';
  const steps = [
    [60, 'minute'], [24, 'hour'], [7, 'day'], [4.35, 'week'], [12, 'month'],
  ];
  let value_ = seconds / 60;
  let unit = 'minute';
  for (const [divisor, name] of steps) {
    if (Math.floor(value_) < divisor || name === 'month') { unit = name; break; }
    value_ /= divisor;
    unit = name;
  }
  const rounded = Math.floor(value_);
  if (unit === 'month' && rounded >= 12) {
    const years = Math.floor(rounded / 12);
    return `${years} year${years === 1 ? '' : 's'} ago`;
  }
  return `${rounded} ${unit}${rounded === 1 ? '' : 's'} ago`;
}

export function dateOnly(value) {
  if (!value) return '';
  return new Date(value).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}

export function dateTime(value) {
  if (!value) return '';
  return new Date(value).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
}

export const initials = (name = '') =>
  name.split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase() || '?';

export const STATUS_LABELS = {
  active: 'Active',
  sold: 'Sold',
  closed: 'Closed',
  filled: 'Filled',
  resolved: 'Resolved',
  archived: 'Archived (expired)',
  removed: 'Removed by admin',
};

/** Short one-line summary shown under a listing title in card view. */
export function listingSubtitle(l) {
  switch (l.type) {
    case 'accommodation':
      return [l.category, l.bedrooms ? `${l.bedrooms} bed` : null, l.furnished ? 'Furnished' : null]
        .filter(Boolean).join(' · ');
    case 'tutor':
      return [l.subject || l.category, l.level].filter(Boolean).join(' · ');
    case 'job':
      return [l.company, l.job_type || l.category].filter(Boolean).join(' · ');
    case 'roommate':
      return [l.category, l.gender_pref && l.gender_pref !== 'Any' ? `${l.gender_pref} preferred` : null]
        .filter(Boolean).join(' · ');
    case 'lostfound':
      return [l.category, l.item_date ? dateOnly(l.item_date) : null].filter(Boolean).join(' · ');
    default:
      return [l.category, l.item_condition].filter(Boolean).join(' · ');
  }
}

/** The headline figure a card shows (price, rent, rate, pay or budget range). */
export function listingPrice(l) {
  if (l.type === 'roommate') {
    if (l.budget_min || l.budget_max) {
      const lo = money(l.budget_min);
      const hi = money(l.budget_max);
      return lo && hi ? `${lo} – ${hi} per month` : `${lo ?? hi} per month`;
    }
    return null;
  }
  if (l.type === 'lostfound') return null;
  return money(l.price, l.price_unit);
}
