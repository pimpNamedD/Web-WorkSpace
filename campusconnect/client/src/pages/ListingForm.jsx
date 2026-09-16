import { useEffect, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { api } from '../lib/api.js';
import { useAuth, useMeta, useToast } from '../lib/store.jsx';
import { Alert, Spinner } from '../components/ui.jsx';
import { TYPE_ICONS, TYPE_LABELS } from '../lib/format.js';

const BLANK = {
  title: '', description: '', price: '', price_unit: '', category: '', location: '', contact_info: '',
  item_condition: '', bedrooms: '', bathrooms: '', furnished: '', amenities: '',
  subject: '', level: '', qualifications: '', availability: '',
  company: '', job_type: '', requirements: '', apply_instructions: '', deadline: '',
  gender_pref: '', budget_min: '', budget_max: '', move_in_date: '', lifestyle: '',
  item_date: '', ttl_days: '60',
};

/**
 * One form for all six modules. The fields rendered come from the module
 * definition served by /api/meta, so the client can never offer a field the
 * server would reject (1.4.1 "Category-Specific Fields").
 */
export default function ListingForm() {
  const meta = useMeta();
  const { user, isVerified } = useAuth();
  const toast = useToast();
  const navigate = useNavigate();
  const [params] = useSearchParams();

  const editId = params.get('edit');
  const [type, setType] = useState(params.get('type') || 'marketplace');
  const [form, setForm] = useState(BLANK);
  const [files, setFiles] = useState([]);
  const [existing, setExisting] = useState([]);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [loading, setLoading] = useState(Boolean(editId));

  useEffect(() => {
    if (!editId) return;
    api.get(`/listings/${editId}`)
      .then(({ listing }) => {
        setType(listing.type);
        setExisting(listing.images ?? []);
        setForm({
          ...BLANK,
          ...Object.fromEntries(Object.keys(BLANK).map((k) => [k, listing[k] ?? ''])),
          furnished: listing.furnished === null ? '' : String(listing.furnished),
          ttl_days: '60',
        });
      })
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, [editId]);

  const def = meta?.listing_types?.[type];
  const fields = def?.fields ?? [];
  const has = (f) => fields.includes(f);
  const set = (k) => (e) => setForm({ ...form, [k]: e.target.value });

  if (!user) {
    return (
      <div className="container page">
        <Alert tone="info">Sign in to post a listing.</Alert>
        <Link to="/login" className="btn primary">Sign in</Link>
      </div>
    );
  }

  if (!isVerified && user.role !== 'admin') {
    return (
      <div className="container page" style={{ maxWidth: 640 }}>
        <div className="card"><div className="card-body">
          <h1>Verification required</h1>
          <p className="muted">
            Only verified members can publish listings — that restriction is what keeps unverifiable accounts and
            scams off Campus Connect.
          </p>
          <Alert tone={user.verification_status === 'pending' ? 'warn' : 'info'}>
            {user.verification_status === 'pending'
              ? 'Your details have been submitted and an administrator is reviewing them. You will be notified once a decision is made.'
              : 'Submit your student number and university under Settings to request verification.'}
          </Alert>
          <Link to="/settings" className="btn primary">Go to verification</Link>
        </div></div>
      </div>
    );
  }

  if (loading || !meta) return <div className="container page"><Spinner /></div>;

  async function submit(e) {
    e.preventDefault();
    setError('');
    setBusy(true);
    try {
      const payload = { type, title: form.title, description: form.description, ttl_days: form.ttl_days };
      for (const f of fields) if (form[f] !== '' && form[f] !== undefined) payload[f] = form[f];

      if (editId) {
        await api.patch(`/listings/${editId}`, payload);
        if (files.length) {
          const fd = new FormData();
          files.forEach((f) => fd.append('images', f));
          await api.upload(`/listings/${editId}/images`, fd);
        }
        toast.success('Listing updated.');
        navigate(`/listing/${editId}`);
      } else {
        const fd = new FormData();
        Object.entries(payload).forEach(([k, v]) => fd.append(k, v));
        files.forEach((f) => fd.append('images', f));
        const { listing } = await api.upload('/listings', fd);
        toast.success('Your listing is live.');
        navigate(`/listing/${listing.id}`);
      }
    } catch (err) {
      setError(err.message);
    } finally {
      setBusy(false);
    }
  }

  async function removeImage(imageId) {
    await api.del(`/listings/${editId}/images/${imageId}`);
    setExisting((imgs) => imgs.filter((i) => i.id !== imageId));
  }

  return (
    <div className="container page" style={{ maxWidth: 760 }}>
      <h1>{editId ? 'Edit listing' : 'Post a listing'}</h1>
      <p className="muted small">
        {editId ? 'Update the details below.' : 'Choose the service, then fill in the details for that section.'}
      </p>

      <Alert tone="error">{error}</Alert>

      {!editId && (
        <div className="field">
          <label>Which service is this for?</label>
          <div className="row-wrap">
            {Object.keys(TYPE_LABELS).map((t) => (
              <button
                key={t}
                type="button"
                className={`chip ${type === t ? 'on' : ''}`}
                onClick={() => setType(t)}
              >{TYPE_ICONS[t]} {TYPE_LABELS[t]}</button>
            ))}
          </div>
        </div>
      )}

      <form onSubmit={submit} className="card">
        <div className="card-body">
          <div className="field">
            <label htmlFor="title">Title</label>
            <input
              id="title" required minLength={4} value={form.title} onChange={set('title')}
              placeholder={{
                marketplace: 'e.g. Database Systems textbook, 7th edition',
                accommodation: 'e.g. Self-contained room in Chalala',
                tutor: 'e.g. Mathematics tutoring for first years',
                job: 'e.g. IT support intern, three-month attachment',
                roommate: 'e.g. Looking for one roommate in Chalala',
                lostfound: 'e.g. Lost: black backpack near the library',
              }[type]}
            />
          </div>

          <div className="field">
            <label htmlFor="description">Description</label>
            <textarea
              id="description" required value={form.description} onChange={set('description')}
              placeholder="Give enough detail that someone can decide without messaging you first."
            />
          </div>

          <div className="grid-2">
            {def?.categories?.length > 0 && (
              <div className="field">
                <label htmlFor="category">{type === 'lostfound' ? 'Is the item lost or found?' : 'Category'}</label>
                <select id="category" required value={form.category} onChange={set('category')}>
                  <option value="">Select…</option>
                  {def.categories.map((c) => <option key={c}>{c}</option>)}
                </select>
              </div>
            )}

            {has('location') && (
              <div className="field">
                <label htmlFor="location">Location / area</label>
                <input id="location" list="loc-options" value={form.location} onChange={set('location')} placeholder="e.g. Chalala" />
                <datalist id="loc-options">
                  {(meta.locations ?? []).map((x) => <option key={x} value={x} />)}
                </datalist>
              </div>
            )}
          </div>

          {has('price') && (
            <div className="grid-2">
              <div className="field">
                <label htmlFor="price">{def.priceLabel}</label>
                <input id="price" type="number" min="0" step="0.01" value={form.price} onChange={set('price')} />
              </div>
              <div className="field">
                <label htmlFor="price_unit">Charged</label>
                <select id="price_unit" value={form.price_unit || def.defaultPriceUnit} onChange={set('price_unit')}>
                  {(meta.price_units ?? []).map((u) => <option key={u} value={u}>{u}</option>)}
                </select>
              </div>
            </div>
          )}

          {has('item_condition') && (
            <div className="field">
              <label htmlFor="cond">Condition</label>
              <select id="cond" value={form.item_condition} onChange={set('item_condition')}>
                <option value="">Select…</option>
                {(meta.conditions ?? []).map((c) => <option key={c}>{c}</option>)}
              </select>
            </div>
          )}

          {has('bedrooms') && (
            <>
              <div className="grid-3">
                <div className="field">
                  <label htmlFor="bed">Bedrooms</label>
                  <input id="bed" type="number" min="0" max="20" value={form.bedrooms} onChange={set('bedrooms')} />
                </div>
                <div className="field">
                  <label htmlFor="bath">Bathrooms</label>
                  <input id="bath" type="number" min="0" max="20" value={form.bathrooms} onChange={set('bathrooms')} />
                </div>
                <div className="field">
                  <label htmlFor="furn">Furnished</label>
                  <select id="furn" value={form.furnished} onChange={set('furnished')}>
                    <option value="">Not specified</option>
                    <option value="1">Yes</option>
                    <option value="0">No</option>
                  </select>
                </div>
              </div>
              <div className="field">
                <label htmlFor="amen">Amenities</label>
                <input id="amen" value={form.amenities} onChange={set('amenities')} placeholder="Wi-Fi, prepaid electricity, borehole water, security" />
              </div>
            </>
          )}

          {has('subject') && (
            <>
              <div className="grid-2">
                <div className="field">
                  <label htmlFor="subj">Subject(s) taught</label>
                  <input id="subj" value={form.subject} onChange={set('subject')} placeholder="e.g. Mathematics, Statistics" />
                </div>
                <div className="field">
                  <label htmlFor="lvl">Level</label>
                  <select id="lvl" value={form.level} onChange={set('level')}>
                    <option value="">Select…</option>
                    {(meta.levels ?? []).map((x) => <option key={x}>{x}</option>)}
                  </select>
                </div>
              </div>
              <div className="field">
                <label htmlFor="qual">Qualifications</label>
                <input id="qual" value={form.qualifications} onChange={set('qualifications')} placeholder="e.g. Final-year BIT student, distinction in MAT110" />
              </div>
              <div className="field">
                <label htmlFor="avail">Availability</label>
                <input id="avail" value={form.availability} onChange={set('availability')} placeholder="e.g. Weekday evenings, Saturday mornings" />
              </div>
            </>
          )}

          {has('company') && (
            <>
              <div className="grid-2">
                <div className="field">
                  <label htmlFor="co">Employer / organisation</label>
                  <input id="co" value={form.company} onChange={set('company')} />
                </div>
                <div className="field">
                  <label htmlFor="jt">Opportunity type</label>
                  <select id="jt" value={form.job_type} onChange={set('job_type')}>
                    <option value="">Select…</option>
                    {(meta.job_types ?? []).map((x) => <option key={x}>{x}</option>)}
                  </select>
                </div>
              </div>
              <div className="field">
                <label htmlFor="req">Requirements</label>
                <textarea id="req" value={form.requirements} onChange={set('requirements')} style={{ minHeight: 80 }} />
              </div>
              <div className="grid-2">
                <div className="field">
                  <label htmlFor="appin">How to apply</label>
                  <input id="appin" value={form.apply_instructions} onChange={set('apply_instructions')} placeholder="Apply through Campus Connect" />
                </div>
                <div className="field">
                  <label htmlFor="dl">Application deadline</label>
                  <input id="dl" type="date" value={form.deadline ? String(form.deadline).slice(0, 10) : ''} onChange={set('deadline')} />
                </div>
              </div>
            </>
          )}

          {has('gender_pref') && (
            <>
              <div className="grid-3">
                <div className="field">
                  <label htmlFor="gp">Gender preference</label>
                  <select id="gp" value={form.gender_pref} onChange={set('gender_pref')}>
                    <option value="">Any</option>
                    {(meta.gender_prefs ?? []).map((g) => <option key={g}>{g}</option>)}
                  </select>
                </div>
                <div className="field">
                  <label htmlFor="bmin">Budget from (ZMW)</label>
                  <input id="bmin" type="number" min="0" value={form.budget_min} onChange={set('budget_min')} />
                </div>
                <div className="field">
                  <label htmlFor="bmax">Budget to (ZMW)</label>
                  <input id="bmax" type="number" min="0" value={form.budget_max} onChange={set('budget_max')} />
                </div>
              </div>
              <div className="grid-2">
                <div className="field">
                  <label htmlFor="mid">Preferred move-in date</label>
                  <input id="mid" type="date" value={form.move_in_date ? String(form.move_in_date).slice(0, 10) : ''} onChange={set('move_in_date')} />
                </div>
                <div className="field">
                  <label htmlFor="life">Lifestyle notes</label>
                  <input id="life" value={form.lifestyle} onChange={set('lifestyle')} placeholder="e.g. Non-smoker, quiet, tidy" />
                </div>
              </div>
            </>
          )}

          {has('item_date') && (
            <div className="field">
              <label htmlFor="idate">Date lost or found</label>
              <input id="idate" type="date" value={form.item_date ? String(form.item_date).slice(0, 10) : ''} onChange={set('item_date')} />
            </div>
          )}

          {has('contact_info') && (
            <div className="field">
              <label htmlFor="ci">Contact details (optional)</label>
              <input id="ci" value={form.contact_info} onChange={set('contact_info')} placeholder="Leave blank to be contacted through Campus Connect only" />
              <div className="hint">Anything you type here is public. Messaging keeps your number private.</div>
            </div>
          )}

          <div className="field">
            <label htmlFor="imgs">Photos (up to 6, max 4 MB each)</label>
            <input id="imgs" type="file" accept="image/*" multiple onChange={(e) => setFiles([...e.target.files].slice(0, 6))} />
            {files.length > 0 && <div className="hint">{files.length} file(s) selected</div>}
            {existing.length > 0 && (
              <div className="row-wrap mt">
                {existing.map((img) => (
                  <div key={img.id} style={{ position: 'relative' }}>
                    <img src={img.url} alt="" style={{ width: 84, height: 64, objectFit: 'cover', borderRadius: 6 }} />
                    <button
                      type="button" className="btn danger sm"
                      style={{ position: 'absolute', top: -6, right: -6, padding: '.1rem .35rem', borderRadius: '50%' }}
                      onClick={() => removeImage(img.id)}
                      aria-label="Remove image"
                    >✕</button>
                  </div>
                ))}
              </div>
            )}
          </div>

          <div className="field">
            <label htmlFor="ttl">Keep this listing live for</label>
            <select id="ttl" value={form.ttl_days} onChange={set('ttl_days')} style={{ maxWidth: 260 }}>
              {[7, 14, 30, 60, 90].map((d) => <option key={d} value={d}>{d} days</option>)}
            </select>
            <div className="hint">Listings archive automatically when they expire; you can renew them at any time.</div>
          </div>

          <div className="row mt">
            <button className="btn primary" disabled={busy}>
              {busy ? 'Saving…' : editId ? 'Save changes' : 'Publish listing'}
            </button>
            <button type="button" className="btn" onClick={() => navigate(-1)}>Cancel</button>
          </div>
        </div>
      </form>
    </div>
  );
}
