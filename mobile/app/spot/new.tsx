import { useEffect, useState } from 'react';
import { View, TextInput, Alert, Switch } from 'react-native';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import * as Location from 'expo-location';
import { Screen, Text, Card, Button, Chip, Tap, SectionHeader, Reveal, Pulse } from '@/components';
import { colors, radius, space, border } from '@/theme';
import { spots as spotsApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';

const TYPES = ['yard', 'park', 'school', 'stadium', 'commercial'] as const;

const EQUIPMENT = [
  'pull_up_bar',
  'parallel_bars',
  'low_bar',
  'wall_bars',
  'rings',
  'monkey_bars',
  'horizontal_ladder',
  'bench',
  'rope',
  'ab_bench',
  'outdoor_gym_machines',
];

/**
 * ახალი მოედნის დამატება (სპეც. 9.3).
 *
 * კოორდინატი მოწყობილობიდან მოდის და არა რუკიდან — მოედანი მხოლოდ
 * იქიდან ემატება, სადაც ავტორი ფიზიკურად დგას. სერვერი 50 მ რადიუსში
 * დუბლიკატს აგდებს და ჩანაწერს `pending` სტატუსით ინახავს.
 */
export default function NewSpotScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const queryClient = useQueryClient();

  const [name, setName] = useState('');
  const [type, setType] = useState<(typeof TYPES)[number]>('yard');
  const [equipment, setEquipment] = useState<string[]>([]);
  const [lighting, setLighting] = useState(false);
  const [coords, setCoords] = useState<{ lat: number; lng: number } | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    (async () => {
      const { status } = await Location.requestForegroundPermissionsAsync();
      if (status !== 'granted') return;

      const position = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.High });
      setCoords({ lat: position.coords.latitude, lng: position.coords.longitude });
    })().catch(() => undefined);
  }, []);

  const toggle = (tag: string) =>
    setEquipment((current) => (current.includes(tag) ? current.filter((v) => v !== tag) : [...current, tag]));

  const submit = async () => {
    if (!coords) {
      Alert.alert(t('map.addSpot'), t('map.needLocation'));
      return;
    }

    setBusy(true);

    const form = new FormData();
    form.append('name', name.trim());
    form.append('lat', String(coords.lat));
    form.append('lng', String(coords.lng));
    form.append('type', type);
    form.append('has_lighting', lighting ? '1' : '0');
    equipment.forEach((tag) => form.append('equipment[]', tag));

    try {
      await spotsApi.create(form);
      await queryClient.invalidateQueries({ queryKey: ['spots'] });
      Alert.alert(t('map.addSpot'), t('map.spotSubmitted'));
      router.back();
    } catch (error) {
      const duplicate = error instanceof ApiError && error.payload?.error === 'duplicate';
      Alert.alert(t('map.addSpot'), duplicate ? t('map.duplicateSpot') : t('common.error'));
    } finally {
      setBusy(false);
    }
  };

  const valid = name.trim().length >= 3 && equipment.length > 0 && !!coords;

  return (
    <Screen
      ambient={colors.accent}
      footer={<Button title={t('map.submit')} loading={busy} disabled={!valid} onPress={submit} />}
    >
      <Reveal from="top" distance={8} style={{ alignSelf: 'flex-start', marginBottom: space.lg }}>
        <Tap onPress={() => router.back()}>
          <Text variant="label" tone="muted">
            ← {t('common.back')}
          </Text>
        </Tap>
      </Reveal>

      <Reveal index={1} style={{ gap: space.xs, marginBottom: space.lg }}>
        <Text variant="title">{t('map.addSpot')}</Text>

        {/* GPS-ს ველოდებით — ტექსტი პულსირებს, სანამ კოორდინატი არ არის */}
        <Pulse active={!coords} to={1.01} period={1800}>
          <Text variant="bodySm" style={{ color: coords ? colors.textMuted : colors.warning }}>
            {coords ? t('map.addSpotReward') : t('map.needLocation')}
          </Text>
        </Pulse>
      </Reveal>

      <Reveal index={2}>
      <Card>
        <Text variant="overline" tone="muted" style={{ marginBottom: space.sm }}>
          {t('map.spotName')}
        </Text>

        <TextInput
          value={name}
          onChangeText={setName}
          maxLength={120}
          placeholderTextColor={colors.textDisabled}
          style={{
            height: 48,
            borderRadius: radius.md,
            paddingHorizontal: space.base,
            backgroundColor: colors.surfaceHi,
            borderWidth: border.hair,
            borderColor: colors.borderStrong,
            color: colors.text,
            fontFamily: 'NotoSansGeorgian_500Medium',
            fontSize: 16,
          }}
        />
      </Card>
      </Reveal>

      <Reveal index={3}>
        <SectionHeader title={t('map.spotType')} />
      </Reveal>

      <Reveal index={4} style={{ flexDirection: 'row', flexWrap: 'wrap', gap: space.sm }}>
        {TYPES.map((key) => (
          <Chip
            key={key}
            label={t(`map.type_${key}`)}
            selected={type === key}
            onPress={() => setType(key)}
            compact
          />
        ))}
      </Reveal>

      <Reveal index={5}>
        <SectionHeader title={t('map.pickEquipment')} />
      </Reveal>

      <Reveal index={6} style={{ flexDirection: 'row', flexWrap: 'wrap', gap: space.sm }}>
        {EQUIPMENT.map((tag) => (
          <Chip
            key={tag}
            label={t(`map.eq_${tag}`, { defaultValue: tag })}
            selected={equipment.includes(tag)}
            onPress={() => toggle(tag)}
            compact
          />
        ))}
      </Reveal>

      <Reveal index={7}>
      <Card style={{ marginTop: space.lg }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
          <Text variant="body">{t('map.lighting')}</Text>
          <Switch
            value={lighting}
            onValueChange={setLighting}
            trackColor={{ false: colors.surfaceHi, true: colors.accentDim }}
            thumbColor={lighting ? colors.accent : colors.textMuted}
          />
        </View>
      </Card>
      </Reveal>

      <Reveal index={8}>
        <Text variant="caption" tone="muted" style={{ marginTop: space.md }}>
          {t('map.pending')}
        </Text>
      </Reveal>
    </Screen>
  );
}
