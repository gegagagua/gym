import { useEffect, useMemo, useRef, useState } from 'react';
import { View, FlatList, Platform } from 'react-native';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { useQuery } from '@tanstack/react-query';
import MapView, { Marker, PROVIDER_GOOGLE, type Region } from 'react-native-maps';
import * as Location from 'expo-location';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { Text, Card, Chip, Tap, LivePulse, Reveal, Pulse } from '@/components';
import { SpotMarker } from '@/features/map/SpotMarker';
import { darkMapStyle } from '@/features/map/mapStyle';
import { colors, gutter, radius, space, border } from '@/theme';
import { spots as spotsApi } from '@/api/endpoints';
import type { NearbySpot } from '@/api/types';

const TBILISI: Region = {
  latitude: 41.7151,
  longitude: 44.8271,
  latitudeDelta: 0.09,
  longitudeDelta: 0.09,
};

const EQUIPMENT_FILTERS = ['pull_up_bar', 'parallel_bars', 'rings', 'monkey_bars', 'wall_bars'];

export default function MapScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const mapRef = useRef<MapView>(null);

  const [region, setRegion] = useState<Region>(TBILISI);
  const [equipment, setEquipment] = useState<string[]>([]);
  const [permission, setPermission] = useState<'granted' | 'denied' | 'pending'>('pending');

  useEffect(() => {
    (async () => {
      // GPS მხოლოდ მაშინ, როცა რუკა გახსნილია — ფონური თრექინგი არ ხდება (სპეც. 17)
      const { status } = await Location.requestForegroundPermissionsAsync();

      if (status !== 'granted') {
        setPermission('denied');
        return;
      }

      setPermission('granted');
      const position = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced });

      const next = {
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
        latitudeDelta: 0.03,
        longitudeDelta: 0.03,
      };

      setRegion(next);
      mapRef.current?.animateToRegion(next, 600);
    })();
  }, []);

  const { data, isFetching } = useQuery({
    queryKey: ['spots', region.latitude.toFixed(3), region.longitude.toFixed(3), equipment.join(',')],
    queryFn: () =>
      spotsApi.nearby(
        region.latitude,
        region.longitude,
        // ხილული არეალის მიხედვით: მასშტაბის შეცვლა რადიუსსაც ცვლის
        Math.min(50_000, Math.round(region.latitudeDelta * 111_000)),
        equipment,
      ),
  });

  const list = useMemo(() => data?.data ?? [], [data]);

  const toggle = (tag: string) =>
    setEquipment((current) => (current.includes(tag) ? current.filter((v) => v !== tag) : [...current, tag]));

  return (
    <View style={{ flex: 1, backgroundColor: colors.bg }}>
      <MapView
        ref={mapRef}
        style={{ flex: 1 }}
        provider={Platform.OS === 'android' ? PROVIDER_GOOGLE : undefined}
        customMapStyle={darkMapStyle}
        initialRegion={TBILISI}
        onRegionChangeComplete={setRegion}
        showsUserLocation={permission === 'granted'}
        showsMyLocationButton={false}
        showsCompass={false}
        toolbarEnabled={false}
      >
        {list.map((spot) => (
          <Marker
            key={spot.id}
            coordinate={{ latitude: spot.lat, longitude: spot.lng }}
            onPress={() => router.push(`/spot/${spot.id}`)}
            tracksViewChanges={false}
            anchor={{ x: 0.5, y: 1 }}
          >
            <SpotMarker activeNow={spot.active_now} equipmentCount={spot.equipment.length} />
          </Marker>
        ))}
      </MapView>

      {/* ---- ფილტრები ---- */}
      <Reveal from="top" distance={12} style={{ position: 'absolute', top: insets.top + space.sm, left: 0, right: 0 }}>
        <FlatList
          horizontal
          showsHorizontalScrollIndicator={false}
          data={EQUIPMENT_FILTERS}
          keyExtractor={(item) => item}
          contentContainerStyle={{ paddingHorizontal: gutter, gap: space.sm }}
          renderItem={({ item }) => (
            <Chip
              label={t(`map.eq_${item}`)}
              selected={equipment.includes(item)}
              onPress={() => toggle(item)}
              compact
            />
          )}
        />
      </Reveal>

      {/* ---- ახალი მოედნის დამატება (UGC, სპეც. 9.3) ---- */}
      <Reveal index={1} zoom style={{ position: 'absolute', right: gutter, top: insets.top + 62 }}>
      <Tap
        onPress={() => router.push('/spot/new')}
        hitSlop={10}
        haptic="medium"
      >
        <View
          style={{
            width: 46,
            height: 46,
            borderRadius: 23,
            alignItems: 'center',
            justifyContent: 'center',
            backgroundColor: colors.overlay,
            borderWidth: border.thin,
            borderColor: colors.borderStrong,
          }}
        >
          <Text variant="title" style={{ color: colors.accent, marginTop: -2 }}>
            +
          </Text>
        </View>
      </Tap>
      </Reveal>

      {/* ---- ახლომდებარე მოედნების ლენტი ---- */}
      <Reveal index={2} from="bottom" distance={20} style={{ position: 'absolute', bottom: 0, left: 0, right: 0, paddingBottom: space.md }}>
        <View style={{ paddingHorizontal: gutter, marginBottom: space.sm }}>
          <View
            style={{
              alignSelf: 'flex-start',
              paddingHorizontal: space.md,
              paddingVertical: space.xs + 1,
              borderRadius: radius.pill,
              backgroundColor: colors.overlay,
              borderWidth: border.hair,
              borderColor: colors.border,
            }}
          >
            <Text variant="caption" tone="muted">
              {t('map.nearby')} · {isFetching ? '…' : list.length}
            </Text>
          </View>
        </View>

        <FlatList
          horizontal
          showsHorizontalScrollIndicator={false}
          data={list}
          keyExtractor={(item) => String(item.id)}
          contentContainerStyle={{ paddingHorizontal: gutter, gap: space.sm }}
          snapToInterval={268}
          decelerationRate="fast"
          ListEmptyComponent={
            !isFetching ? (
              <Card style={{ width: 260 }}>
                <Text variant="bodySm" tone="muted">
                  {t('map.empty')}
                </Text>
              </Card>
            ) : null
          }
          renderItem={({ item, index }) => (
            <Reveal index={index < 5 ? 3 + index : 0} from="right" distance={16}>
              <SpotCard spot={item} onPress={() => router.push(`/spot/${item.id}`)} />
            </Reveal>
          )}
        />
      </Reveal>
    </View>
  );
}

function SpotCard({ spot, onPress }: { spot: NearbySpot; onPress: () => void }) {
  const { t } = useTranslation();

  return (
    <Tap onPress={onPress} scaleTo={0.97} style={{ width: 260 }}>
      <Pulse active={spot.active_now > 0} to={1.012} period={3000}>
      <Card accent={spot.active_now > 0 ? colors.accent : undefined}>
        <View style={{ gap: space.xs }}>
          <Text variant="subheading" numberOfLines={1}>
            {spot.name}
          </Text>

          <View style={{ flexDirection: 'row', alignItems: 'center', gap: space.md }}>
            <Text variant="caption" tone="muted">
              {t('map.distance', { m: spot.distance_m })}
            </Text>
            <Text variant="caption" tone="muted">
              ★ {spot.condition_rating}
            </Text>
            {spot.has_lighting ? (
              <Text variant="caption" tone="muted">
                ☾
              </Text>
            ) : null}
          </View>

          <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: space.xs, marginTop: space.xs }}>
            {spot.equipment.slice(0, 3).map((tag) => (
              <Chip key={tag} label={t(`map.eq_${tag}`, { defaultValue: tag })} compact />
            ))}
          </View>

          <View style={{ marginTop: space.xs }}>
            <LivePulse count={spot.active_now} label={t('map.activeNow', { count: spot.active_now })} />
          </View>
        </View>
      </Card>
      </Pulse>
    </Tap>
  );
}
