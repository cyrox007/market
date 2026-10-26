import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';

/** Activate through the session API, then remove the secret from the address bar. */
export default function PrivateOfferRedirect() {
  const { token } = useParams();
  const [failed, setFailed] = useState(false);
  useEffect(() => {
    let active = true;
    const base = (import.meta.env.VITE_API_BASE_URL || '/api/v1').replace(/\/$/, '');
    if (!token || !/^[a-f0-9]{64}$/.test(token)) { setFailed(true); return; }
    fetch(`${base}/private-offers/activate`, {
      method: 'POST', credentials: 'include', referrerPolicy: 'no-referrer',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ token }),
    }).then(async (response) => {
      if (!response.ok) throw new Error('Offer unavailable');
      const data = await response.json();
      if (active && typeof data.slug === 'string') {
        window.location.replace(`/product/${encodeURIComponent(data.slug)}`);
      }
    }).catch(() => { if (active) setFailed(true); });
    return () => { active = false; };
  }, [token]);
  return failed ? <p role="alert">Ссылка недействительна или срок предложения истёк.</p> : null;
}
