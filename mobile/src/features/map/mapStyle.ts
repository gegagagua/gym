import { palette } from '@/theme';

/**
 * Google Maps-ის ბნელი სტილი, აწყობილი ჩვენს პალიტრაზე.
 *
 * ნაგულისხმევი „dark" სტილი ლურჯ-ნაცრისფერია და აპის ბეტონისფერ
 * ფონს ეხურება — რუკა ცალკე აპლიკაციად გამოიყურება. აქ ფონები
 * ჩვენი surface-ებია, გზები ოდნავ ღიაა, პარკები კი ლაიმისკენ
 * იხრება, რომ მოედნების კონტექსტი თვალით იკითხებოდეს.
 */
export const darkMapStyle = [
  { elementType: 'geometry', stylers: [{ color: palette.asphalt }] },
  { elementType: 'labels.text.fill', stylers: [{ color: palette.smoke }] },
  { elementType: 'labels.text.stroke', stylers: [{ color: palette.void }] },
  { featureType: 'administrative', elementType: 'geometry', stylers: [{ color: palette.steel }] },
  { featureType: 'poi', elementType: 'labels', stylers: [{ visibility: 'off' }] },
  {
    featureType: 'poi.park',
    elementType: 'geometry',
    stylers: [{ color: '#14200F' }],
  },
  {
    featureType: 'poi.sports_complex',
    elementType: 'geometry',
    stylers: [{ color: '#1B2410' }],
  },
  { featureType: 'road', elementType: 'geometry', stylers: [{ color: palette.concrete }] },
  { featureType: 'road', elementType: 'labels.text.fill', stylers: [{ color: palette.ghost }] },
  { featureType: 'road.arterial', elementType: 'geometry', stylers: [{ color: palette.concreteHi }] },
  { featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: palette.steel }] },
  { featureType: 'transit', stylers: [{ visibility: 'off' }] },
  { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#08131A' }] },
  { featureType: 'water', elementType: 'labels.text.fill', stylers: [{ color: '#2A4653' }] },
];
