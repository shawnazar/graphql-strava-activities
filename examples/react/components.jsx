/**
 * React components for GraphQL Strava Activities.
 *
 * Copy these into your project or use as reference.
 * Requires: React 18+, a GraphQL endpoint with the plugin installed.
 */

import React, { useState, useEffect } from 'react';

const STRAVA_QUERY = `
  query StravaActivities($first: Int, $offset: Int, $type: String) {
    stravaActivities(first: $first, offset: $offset, type: $type) {
      title
      distance
      duration
      date
      type
      unit
      speedUnit
      svgMap
      elevationProfileSvg
      stravaUrl
      photoUrl
      elevationGain
      averageSpeed
      maxSpeed
      averageHeartrate
      maxHeartrate
      calories
      kudosCount
      city
      country
      poweredByStrava
    }
  }
`;

/**
 * Fetch activities from the GraphQL endpoint.
 */
async function fetchActivities(endpoint, { count = 10, offset = 0, type = '' } = {}) {
  const res = await fetch(endpoint, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      query: STRAVA_QUERY,
      variables: { first: count, offset, type: type || undefined },
    }),
  });
  const { data } = await res.json();
  return data?.stravaActivities ?? [];
}

/**
 * Render an inline SVG map safely.
 */
export function StravaMap({ svgMarkup, className = '' }) {
  if (!svgMarkup) return null;
  return (
    <div
      className={`strava-map ${className}`}
      dangerouslySetInnerHTML={{ __html: svgMarkup }}
    />
  );
}

/**
 * Single activity card.
 */
export function StravaActivityCard({ activity }) {
  const date = activity.date
    ? new Date(activity.date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
      })
    : '';

  return (
    <div className="strava-card" style={{
      border: '1px solid #e5e7eb',
      borderRadius: '8px',
      padding: '16px',
      marginBottom: '16px',
      fontFamily: 'sans-serif',
    }}>
      {activity.svgMap && (
        <StravaMap svgMarkup={activity.svgMap} />
      )}
      <h3 style={{ margin: '8px 0 4px' }}>{activity.title}</h3>
      {date && <p style={{ color: '#6b7280', fontSize: '13px', margin: 0 }}>{date}</p>}
      <div style={{ display: 'flex', gap: '16px', marginTop: '8px', fontSize: '14px' }}>
        <span><strong>{activity.distance}</strong> {activity.unit}</span>
        <span><strong>{activity.duration}</strong></span>
        <span>{activity.type}</span>
      </div>
      {activity.averageSpeed > 0 && (
        <div style={{ fontSize: '13px', color: '#6b7280', marginTop: '4px' }}>
          Avg: {activity.averageSpeed} {activity.speedUnit} · Max: {activity.maxSpeed} {activity.speedUnit}
        </div>
      )}
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
    </div>
  );
}

/**
 * Activity list with data fetching.
 */
export function StravaActivities({ endpoint, count = 10, type = '' }) {
  const [activities, setActivities] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchActivities(endpoint, { count, type })
      .then(setActivities)
      .finally(() => setLoading(false));
  }, [endpoint, count, type]);

  if (loading) return <p>Loading activities...</p>;
  if (!activities.length) return <p>No activities found.</p>;

  return (
    <div className="strava-activities">
      {activities.map((activity, i) => (
        <StravaActivityCard key={i} activity={activity} />
      ))}
      <p style={{ fontSize: '12px', color: '#9ca3af' }}>
        {activities[0]?.poweredByStrava}
      </p>
    </div>
  );
}

/**
 * Aggregate stats display.
 */
export function StravaStats({ activities }) {
  if (!activities?.length) return null;

  const totalDistance = activities.reduce((sum, a) => sum + (a.distance || 0), 0);
  const unit = activities[0]?.unit || 'mi';
  const types = {};
  activities.forEach(a => {
    const t = a.type || 'Other';
    types[t] = (types[t] || 0) + 1;
  });

  return (
    <div className="strava-stats" style={{
      border: '1px solid #e5e7eb',
      borderRadius: '8px',
      padding: '16px',
      fontFamily: 'sans-serif',
    }}>
      <div style={{ display: 'flex', gap: '24px', flexWrap: 'wrap' }}>
        <div>
          <div style={{ fontSize: '12px', color: '#9ca3af', textTransform: 'uppercase' }}>Activities</div>
          <div style={{ fontSize: '24px', fontWeight: 700 }}>{activities.length}</div>
        </div>
        <div>
          <div style={{ fontSize: '12px', color: '#9ca3af', textTransform: 'uppercase' }}>Total Distance</div>
          <div style={{ fontSize: '24px', fontWeight: 700 }}>{totalDistance.toFixed(1)} {unit}</div>
        </div>
      </div>
      <div style={{ fontSize: '13px', color: '#6b7280', marginTop: '12px' }}>
        {Object.entries(types).sort((a, b) => b[1] - a[1]).map(([t, c]) => `${t}: ${c}`).join(' · ')}
      </div>
    </div>
  );
}
