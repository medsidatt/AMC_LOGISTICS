import { useEffect, useMemo, type ReactNode } from 'react';
import { MapContainer, TileLayer, Marker, Popup, Polyline, Circle, useMap } from 'react-leaflet';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Fix the default marker icon path so it works with Vite bundler
// @ts-ignore — leaflet's default _getIconUrl lookup breaks with bundlers
delete (L.Icon.Default.prototype as any)._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
    iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
    shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
});

export type MapMarker = {
    id: string | number;
    latitude: number;
    longitude: number;
    /** Hex/CSS colour for a circular coloured marker. Falls back to Leaflet default when omitted. */
    color?: string;
    /** Popup content. Keep it small — a string or a tiny JSX tree. */
    popup?: ReactNode;
    /** Optional label shown under the marker on wide zooms. */
    label?: string;
};

export type MapCircle = {
    id: string | number;
    latitude: number;
    longitude: number;
    radiusMeters: number;
    color?: string;
    fillOpacity?: number;
    popup?: ReactNode;
};

interface LeafletMapProps {
    markers?: MapMarker[];
    polyline?: Array<[number, number]>;
    polylineColor?: string;
    circles?: MapCircle[];
    /** Centre (lat, lng). Default: Senegal/Mauritania midpoint. */
    center?: [number, number];
    zoom?: number;
    /** Auto-fit the map bounds to all markers + circles. Default: true. */
    fitBounds?: boolean;
    className?: string;
    height?: number | string;
}

/** Build a circular coloured icon without external assets. */
function coloredIcon(color: string): L.DivIcon {
    return L.divIcon({
        className: 'amc-leaflet-marker',
        html: `<span style="display:inline-block;width:16px;height:16px;border-radius:50%;background:${color};border:2px solid #fff;box-shadow:0 0 0 1px rgba(0,0,0,0.3);"></span>`,
        iconSize: [16, 16],
        iconAnchor: [8, 8],
    });
}

function FitBoundsControl({
    markers,
    polyline,
    circles,
}: {
    markers: MapMarker[];
    polyline?: Array<[number, number]>;
    circles?: MapCircle[];
}) {
    const map = useMap();

    useEffect(() => {
        const points: L.LatLngExpression[] = [];
        markers.forEach((m) => points.push([m.latitude, m.longitude]));
        (polyline ?? []).forEach((p) => points.push(p));
        (circles ?? []).forEach((c) => points.push([c.latitude, c.longitude]));

        if (points.length === 0) return;
        if (points.length === 1) {
            map.setView(points[0] as L.LatLngExpression, Math.max(map.getZoom(), 13));
            return;
        }

        const bounds = L.latLngBounds(points);
        map.fitBounds(bounds, { padding: [30, 30], maxZoom: 15 });
    }, [map, markers, polyline, circles]);

    return null;
}

const DEFAULT_CENTER: [number, number] = [16.0, -16.5]; // Senegal/Mauritania region

export default function LeafletMap({
    markers = [],
    polyline,
    polylineColor = '#ef4444',
    circles = [],
    center = DEFAULT_CENTER,
    zoom = 7,
    fitBounds = true,
    className,
    height = 480,
}: LeafletMapProps) {
    const iconCache = useMemo(() => {
        const cache = new Map<string, L.DivIcon>();
        markers.forEach((m) => {
            if (m.color && !cache.has(m.color)) {
                cache.set(m.color, coloredIcon(m.color));
            }
        });
        return cache;
    }, [markers]);

    return (
        <div
            className={className}
            style={{ height: typeof height === 'number' ? `${height}px` : height, width: '100%' }}
        >
            <MapContainer
                center={center}
                zoom={zoom}
                scrollWheelZoom
                style={{ height: '100%', width: '100%', borderRadius: '0.75rem' }}
            >
                <TileLayer
                    attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                    url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                />

                {markers.map((m) => {
                    const icon = m.color ? iconCache.get(m.color) : undefined;
                    return (
                        <Marker
                            key={m.id}
                            position={[m.latitude, m.longitude]}
                            icon={icon}
                        >
                            {m.popup && <Popup>{m.popup}</Popup>}
                        </Marker>
                    );
                })}

                {polyline && polyline.length > 1 && (
                    <Polyline positions={polyline} pathOptions={{ color: polylineColor, weight: 4 }} />
                )}

                {circles.map((c) => (
                    <Circle
                        key={c.id}
                        center={[c.latitude, c.longitude]}
                        radius={c.radiusMeters}
                        pathOptions={{
                            color: c.color ?? '#3b82f6',
                            fillColor: c.color ?? '#3b82f6',
                            fillOpacity: c.fillOpacity ?? 0.15,
                            weight: 2,
                        }}
                    >
                        {c.popup && <Popup>{c.popup}</Popup>}
                    </Circle>
                ))}

                {fitBounds && (
                    <FitBoundsControl markers={markers} polyline={polyline} circles={circles} />
                )}
            </MapContainer>
        </div>
    );
}
