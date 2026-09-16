/**
 * Category-specific field definitions (1.4.1 "Category-Specific Fields").
 * The server uses `fields` to decide which columns a module may write; the
 * client uses the same definitions to render its dynamic listing forms, so the
 * two can never drift apart.
 */

export const LISTING_TYPES = {
  marketplace: {
    key: 'marketplace',
    label: 'Marketplace',
    singular: 'Item',
    icon: 'cart',
    blurb: 'Buy and sell textbooks, laptops, phones and furniture.',
    priceLabel: 'Price (ZMW)',
    defaultPriceUnit: 'total',
    closeStatus: 'sold',
    closeLabel: 'Mark as sold',
    categories: ['Textbooks', 'Electronics', 'Phones', 'Laptops', 'Furniture', 'Clothing', 'Stationery', 'Sports', 'Other'],
    fields: ['price', 'price_unit', 'category', 'location', 'item_condition', 'contact_info'],
  },
  accommodation: {
    key: 'accommodation',
    label: 'Accommodation',
    singular: 'Property',
    icon: 'home',
    blurb: 'Find or advertise housing near campus.',
    priceLabel: 'Rent (ZMW)',
    defaultPriceUnit: 'per month',
    closeStatus: 'closed',
    closeLabel: 'Mark as taken',
    categories: ['Single room', 'Self-contained', 'Shared flat', 'Boarding house', 'Full house', 'Hostel'],
    fields: ['price', 'price_unit', 'category', 'location', 'bedrooms', 'bathrooms', 'furnished', 'amenities', 'contact_info'],
  },
  tutor: {
    key: 'tutor',
    label: 'Tutors',
    singular: 'Tutor offer',
    icon: 'book',
    blurb: 'Advertise tutoring or find help with a subject.',
    priceLabel: 'Rate (ZMW)',
    defaultPriceUnit: 'per hour',
    closeStatus: 'closed',
    closeLabel: 'Mark as unavailable',
    categories: ['Mathematics', 'Statistics', 'Programming', 'Accounting', 'Economics', 'Physics', 'Chemistry', 'Biology', 'English', 'Law', 'Other'],
    fields: ['price', 'price_unit', 'category', 'location', 'subject', 'level', 'qualifications', 'availability', 'contact_info'],
  },
  job: {
    key: 'job',
    label: 'Jobs & Internships',
    singular: 'Opportunity',
    icon: 'briefcase',
    blurb: 'Part-time work and internships for students.',
    priceLabel: 'Pay (ZMW)',
    defaultPriceUnit: 'per month',
    closeStatus: 'filled',
    closeLabel: 'Mark as filled',
    categories: ['Internship', 'Part-time', 'Casual', 'Freelance', 'Attachment', 'Volunteer'],
    fields: ['price', 'price_unit', 'category', 'location', 'company', 'job_type', 'requirements', 'apply_instructions', 'deadline', 'contact_info'],
  },
  roommate: {
    key: 'roommate',
    label: 'Roommates',
    singular: 'Roommate request',
    icon: 'users',
    blurb: 'Find someone to share accommodation with.',
    priceLabel: 'Budget (ZMW)',
    defaultPriceUnit: 'per month',
    closeStatus: 'filled',
    closeLabel: 'Mark as filled',
    categories: ['Looking for a roommate', 'Looking for a room to share'],
    fields: ['category', 'location', 'gender_pref', 'budget_min', 'budget_max', 'move_in_date', 'lifestyle', 'contact_info'],
  },
  lostfound: {
    key: 'lostfound',
    label: 'Lost & Found',
    singular: 'Item report',
    icon: 'search',
    blurb: 'Report lost items or help return found ones.',
    priceLabel: null,
    defaultPriceUnit: null,
    closeStatus: 'resolved',
    closeLabel: 'Mark as resolved',
    categories: ['Lost', 'Found'],
    fields: ['category', 'location', 'item_date', 'contact_info'],
  },
};

export const TYPE_KEYS = Object.keys(LISTING_TYPES);

export const CONDITIONS = ['New', 'Like new', 'Good', 'Fair', 'For parts'];
export const LEVELS = ['Secondary', 'Certificate/Diploma', 'Undergraduate', 'Postgraduate'];
export const JOB_TYPES = ['Part-time', 'Internship', 'Casual', 'Freelance', 'Attachment', 'Volunteer'];
export const GENDER_PREFS = ['Any', 'Male', 'Female'];
export const PRICE_UNITS = ['total', 'per month', 'per week', 'per day', 'per hour', 'negotiable'];

export const REPORT_REASONS = [
  'Scam or fraud',
  'Fake or misleading listing',
  'Item already sold or unavailable',
  'Offensive or inappropriate content',
  'Harassment or abuse',
  'Spam or duplicate posting',
  'Suspected non-student account',
  'Other',
];

export const UNIVERSITIES = [
  'University of Lusaka',
  'University of Zambia',
  'Copperbelt University',
  'Mulungushi University',
  'Cavendish University Zambia',
  'ZCAS University',
  'Levy Mwanawasa Medical University',
  'Other',
];

/** Every status a listing can hold, grouped for the UI. */
export const STATUSES = ['active', 'sold', 'closed', 'filled', 'resolved', 'archived', 'removed'];

/** Statuses that still count as "available" when browsing. */
export const OPEN_STATUSES = ['active'];

export function isValidType(type) {
  return Object.hasOwn(LISTING_TYPES, type);
}
