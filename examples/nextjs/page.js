/**
 * Next.js App Router page — fetches Strava activities via GraphQL.
 *
 * Place this in your app/ directory as app/strava/page.js
 * Set NEXT_PUBLIC_GRAPHQL_ENDPOINT in .env.local
 */

const ENDPOINT = process.env.NEXT_PUBLIC_GRAPHQL_ENDPOINT || 'http://localhost/graphql';

const QUERY = `{
  stravaActivities(first: 10) {
    title
    distance
    duration
    date
    type
    unit
    speedUnit
    svgMap
    stravaUrl
    photoUrl
    elevationGain
    averageSpeed
    maxSpeed
    kudosCount
    city
    poweredByStrava
  }
}`;

async function getActivities() {
  const res = await fetch(ENDPOINT, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ query: QUERY }),
    next: { revalidate: 3600 }, // ISR: revalidate every hour
  });
  const { data } = await res.json();
  return data?.stravaActivities ?? [];
}

export default async function StravaPage() {
  const activities = await getActivities();

  return (
    <main style={{ maxWidth: 800, margin: '0 auto', padding: '2rem', fontFamily: 'sans-serif' }}>
      <h1>Strava Activities</h1>

      {activities.map((activity, i) => (
        <article key={i} style={{
          border: '1px solid #e5e7eb',
          borderRadius: '8px',
          padding: '16px',
          marginBottom: '16px',
        }}>
          {activity.svgMap && (
            <div
              style={{ marginBottom: '12px' }}
              dangerouslySetInnerHTML={{ __html: activity.svgMap }}
            />
          )}

          <h2 style={{ margin: '0 0 4px', fontSize: '18px' }}>{activity.title}</h2>

          <p style={{ color: '#6b7280', fontSize: '13px', margin: '0 0 8px' }}>
            {new Date(activity.date).toLocaleDateString('en-US', {
              month: 'long', day: 'numeric', year: 'numeric',
            })}
            {activity.city && ` · ${activity.city}`}
          </p>

          <div style={{ display: 'flex', gap: '16px', fontSize: '14px' }}>
            <span><strong>{activity.distance}</strong> {activity.unit}</span>
            <span><strong>{activity.duration}</strong></span>
            <span><strong>{activity.averageSpeed}</strong> {activity.speedUnit}</span>
            <span>{activity.type}</span>
          </div>

          {activity.stravaUrl && (
            <a
              href={activity.stravaUrl}
              target="_blank"
              rel="noopener noreferrer"
              style={{ color: '#FC5200', fontWeight: 'bold', textDecoration: 'none', fontSize: '13px', marginTop: '8px', display: 'inline-block' }}
            >
              View on Strava →
            </a>
          )}
        </article>
      ))}

      {activities[0]?.poweredByStrava && (
        <p style={{ fontSize: '12px', color: '#9ca3af', textAlign: 'center' }}>
          {activities[0].poweredByStrava}
        </p>
      )}
    </main>
  );
}
