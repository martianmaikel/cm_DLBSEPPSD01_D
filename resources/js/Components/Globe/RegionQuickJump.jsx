import { useTranslation } from 'react-i18next';

const REGIONS = [
    { key: '1', code: 'EEUR', label: 'E.EUR', lat: 48.5, lng: 35, zoom: 4.5 },
    { key: '2', code: 'MENA', label: 'MENA', lat: 30, lng: 42, zoom: 4 },
    { key: '3', code: 'SAHEL', label: 'SAHEL', lat: 14, lng: 2, zoom: 4 },
    { key: '4', code: 'HORN', label: 'HORN', lat: 5, lng: 42, zoom: 4.5 },
    { key: '5', code: 'SASIA', label: 'S.ASIA', lat: 28, lng: 72, zoom: 4 },
    { key: '6', code: 'SEASIA', label: 'SE.ASIA', lat: 12, lng: 107, zoom: 4 },
];

export { REGIONS };

export default function RegionQuickJump({ onFlyTo }) {
    return (
        <div className="region-quickjump">
            <span className="region-quickjump-title">REGIONS</span>
            {REGIONS.map((r) => (
                <button
                    key={r.code}
                    className="region-quickjump-btn"
                    onClick={(e) => { e.stopPropagation(); onFlyTo(r.lat, r.lng, r.zoom); }}
                    title={`${r.label} [${r.key}]`}
                >
                    <span className="region-quickjump-key">{r.key}</span>
                    <span className="region-quickjump-label">{r.label}</span>
                </button>
            ))}
        </div>
    );
}
